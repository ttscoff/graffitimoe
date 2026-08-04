<?php

declare(strict_types=1);

namespace Graffiti;

use PDO;

final class MessageRepository
{
    public function __construct(private PDO $pdo)
    {
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
        $stmt = $this->pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->rowCount();
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
