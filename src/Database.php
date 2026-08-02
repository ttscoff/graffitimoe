<?php

declare(strict_types=1);

namespace Graffiti;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                body TEXT NOT NULL,
                color TEXT NOT NULL DEFAULT \'default\',
                bold INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                ip_hash TEXT NOT NULL
            );'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at DESC);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_ip_created ON messages(ip_hash, created_at);');
        return $pdo;
    }
}
