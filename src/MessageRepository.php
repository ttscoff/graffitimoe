<?php

declare(strict_types=1);

namespace Graffiti;

use PDO;

final class MessageRepository
{
    public const COMMUNITY_FLAG_THRESHOLD = 3;

    public function __construct(private PDO $pdo)
    {
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM messages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param list<array{t:string,c:string}>|null $spans
     */
    public function create(
        string $body,
        string $color,
        bool $bold,
        string $ipHash,
        ?array $spans = null,
        bool $flagged = false,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (body, color, bold, spans, flagged, created_at, ip_hash)
             VALUES (:body, :color, :bold, :spans, :flagged, :created_at, :ip_hash)'
        );
        $stmt->execute([
            ':body' => $body,
            ':color' => $color,
            ':bold' => $bold ? 1 : 0,
            ':spans' => $spans === null ? null : json_encode($spans, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':flagged' => $flagged ? 1 : 0,
            ':created_at' => gmdate('c'),
            ':ip_hash' => $ipHash,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,body:string,color:string,bold:bool,spans:list<array{t:string,c:string}>|null,flagged:bool,created_at:string}|null */
    public function random(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT id, body, color, bold, spans, flagged, created_at FROM messages ORDER BY RANDOM() LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** @return list<array{id:int,body:string,color:string,bold:bool,spans:list<array{t:string,c:string}>|null,flagged:bool,created_at:string}> */
    public function recent(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, body, color, bold, spans, flagged, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /** @return list<array{id:int,body:string,color:string,bold:bool,spans:list<array{t:string,c:string}>|null,flagged:bool,created_at:string}> */
    public function allNewestFirst(bool $flaggedOnly = false): array
    {
        if ($flaggedOnly) {
            $stmt = $this->pdo->query(
                'SELECT id, body, color, bold, spans, flagged, created_at FROM messages
                 WHERE flagged = 1 ORDER BY created_at DESC, id DESC'
            );
        } else {
            $stmt = $this->pdo->query(
                'SELECT id, body, color, bold, spans, flagged, created_at FROM messages
                 ORDER BY created_at DESC, id DESC'
            );
        }
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function setFlagged(int $id, bool $flagged): bool
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET flagged = :flagged WHERE id = :id');
        $stmt->execute([
            ':flagged' => $flagged ? 1 : 0,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /** @param list<int> $ids */
    public function setFlaggedMany(array $ids, bool $flagged): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE messages SET flagged = ? WHERE id IN ($placeholders)"
        );
        $stmt->execute([$flagged ? 1 : 0, ...$ids]);
        return $stmt->rowCount();
    }

    public function delete(int $id): bool
    {
        $this->pdo->prepare('DELETE FROM message_flags WHERE message_id = :id')
            ->execute([':id' => $id]);
        $stmt = $this->pdo->prepare('DELETE FROM messages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** @param list<int> $ids */
    public function deleteMany(array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("DELETE FROM message_flags WHERE message_id IN ($placeholders)")
            ->execute($ids);
        $stmt = $this->pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /** @return 'flagged'|'unflagged'|null */
    public function toggleCommunityFlag(int $messageId, string $ipHash): ?string
    {
        if ($messageId <= 0 || !$this->exists($messageId)) {
            return null;
        }

        // BEGIN IMMEDIATE grabs SQLite's write lock up front instead of the
        // deferred lock beginTransaction() would use, so a second writer
        // fails fast on the BEGIN itself (as a normal PDOException we let
        // propagate) rather than racing us between the read and write below.
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $check = $this->pdo->prepare(
                'SELECT 1 FROM message_flags WHERE message_id = :id AND ip_hash = :ip'
            );
            $check->execute([':id' => $messageId, ':ip' => $ipHash]);
            if ($check->fetchColumn() !== false) {
                $this->pdo->prepare(
                    'DELETE FROM message_flags WHERE message_id = :id AND ip_hash = :ip'
                )->execute([':id' => $messageId, ':ip' => $ipHash]);

                $row = $this->pdo->prepare('SELECT flag_count FROM messages WHERE id = :id');
                $row->execute([':id' => $messageId]);
                $prev = (int) $row->fetchColumn();
                $next = max(0, $prev - 1);
                if ($prev >= self::COMMUNITY_FLAG_THRESHOLD && $next < self::COMMUNITY_FLAG_THRESHOLD) {
                    $this->pdo->prepare(
                        'UPDATE messages SET flag_count = :c, flagged = 0 WHERE id = :id'
                    )->execute([':c' => $next, ':id' => $messageId]);
                } else {
                    $this->pdo->prepare(
                        'UPDATE messages SET flag_count = :c WHERE id = :id'
                    )->execute([':c' => $next, ':id' => $messageId]);
                }
                $this->pdo->commit();
                return 'unflagged';
            }

            try {
                $this->pdo->prepare(
                    'INSERT INTO message_flags (message_id, ip_hash, created_at)
                     VALUES (:id, :ip, :created_at)'
                )->execute([
                    ':id' => $messageId,
                    ':ip' => $ipHash,
                    ':created_at' => gmdate('c'),
                ]);
            } catch (\PDOException $e) {
                if (!self::isUniqueConstraintViolation($e)) {
                    // Not a duplicate-flag race (e.g. disk I/O error, FK
                    // failure, locked database) — surface it instead of
                    // reporting a false "flagged" success.
                    throw $e;
                }
                // This IP already has a flag row (inserted concurrently
                // before we took the write lock, or a stale read). Treat it
                // as an already-flagged no-op rather than an error.
                $this->pdo->rollBack();
                return 'flagged';
            }

            $row = $this->pdo->prepare('SELECT flag_count FROM messages WHERE id = :id');
            $row->execute([':id' => $messageId]);
            $prev = (int) $row->fetchColumn();
            $next = $prev + 1;
            if ($next >= self::COMMUNITY_FLAG_THRESHOLD) {
                $this->pdo->prepare(
                    'UPDATE messages SET flag_count = :c, flagged = 1 WHERE id = :id'
                )->execute([':c' => $next, ':id' => $messageId]);
            } else {
                $this->pdo->prepare(
                    'UPDATE messages SET flag_count = :c WHERE id = :id'
                )->execute([':c' => $next, ':id' => $messageId]);
            }
            $this->pdo->commit();
            return 'flagged';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * True only for a genuine UNIQUE constraint violation (SQLite error code
     * 19, SQLSTATE 23000, message mentions "UNIQUE"). Other PDOExceptions —
     * database locked, disk I/O errors, foreign key failures — also carry
     * code 19/SQLSTATE 23000 under SQLite, so the message text is required
     * to tell them apart.
     */
    private static function isUniqueConstraintViolation(\PDOException $e): bool
    {
        if (stripos($e->getMessage(), 'unique') === false) {
            return false;
        }
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;
        return $sqlState === '23000' || $driverCode === 19;
    }

    /**
     * @param list<int> $messageIds
     * @return list<int>
     */
    public function flaggedMessageIdsForIp(array $messageIds, string $ipHash): array
    {
        $messageIds = $this->normalizeIds($messageIds);
        if ($messageIds === [] || $ipHash === '') {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT message_id FROM message_flags
             WHERE ip_hash = ? AND message_id IN ($placeholders)"
        );
        $stmt->execute([$ipHash, ...$messageIds]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<mixed> $ids @return list<int> */
    private function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }

    public function countRecentByIpHash(string $ipHash, int $windowSeconds): int
    {
        $since = gmdate('c', time() - $windowSeconds);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM messages WHERE ip_hash = :ip_hash AND created_at >= :since'
        );
        $stmt->execute([':ip_hash' => $ipHash, ':since' => $since]);
        return (int) $stmt->fetchColumn();
    }

    public function countFlagged(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM messages WHERE flagged = 1');
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:int,body:string,color:string,bold:bool,spans:list<array{t:string,c:string}>|null,flagged:bool,created_at:string}
     */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'body' => (string) $row['body'],
            'color' => (string) $row['color'],
            'bold' => ((int) $row['bold']) === 1,
            'spans' => $this->decodeSpans($row['spans'] ?? null),
            'flagged' => ((int) ($row['flagged'] ?? 0)) === 1,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @return list<array{t:string,c:string,b?:bool}>|null */
    private function decodeSpans(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_string($raw)) {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded) || $decoded === []) {
            return null;
        }
        $spans = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['t'], $item['c']) || !is_string($item['t']) || !is_string($item['c'])) {
                return null;
            }
            $run = ['t' => $item['t'], 'c' => $item['c']];
            if (!empty($item['b'])) {
                $run['b'] = true;
            }
            $spans[] = $run;
        }
        return $spans === [] ? null : $spans;
    }
}
