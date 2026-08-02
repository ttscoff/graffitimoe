<?php

declare(strict_types=1);

namespace Graffiti;

use PDO;

final class MessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $body, string $color, bool $bold, string $ipHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (body, color, bold, created_at, ip_hash) VALUES (:body, :color, :bold, :created_at, :ip_hash)'
        );
        $stmt->execute([
            ':body' => $body,
            ':color' => $color,
            ':bold' => $bold ? 1 : 0,
            ':created_at' => gmdate('c'),
            ':ip_hash' => $ipHash,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,body:string,color:string,bold:bool,created_at:string}|null */
    public function random(): ?array
    {
        $stmt = $this->pdo->query('SELECT id, body, color, bold, created_at FROM messages ORDER BY RANDOM() LIMIT 1');
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** @return list<array{id:int,body:string,color:string,bold:bool,created_at:string}> */
    public function recent(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, body, color, bold, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /** @return list<array{id:int,body:string,color:string,bold:bool,created_at:string}> */
    public function allNewestFirst(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, body, color, bold, created_at FROM messages ORDER BY created_at DESC, id DESC'
        );
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM messages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
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

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'body' => (string) $row['body'],
            'color' => (string) $row['color'],
            'bold' => ((int) $row['bold']) === 1,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
