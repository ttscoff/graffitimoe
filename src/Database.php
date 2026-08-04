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
                spans TEXT,
                flagged INTEGER NOT NULL DEFAULT 0,
                flag_count INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                ip_hash TEXT NOT NULL
            );'
        );
        self::ensureSpansColumn($pdo);
        self::ensureFlaggedColumn($pdo);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        self::ensureFlagCountColumn($pdo);
        self::ensureMessageFlagsTable($pdo);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at DESC);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_ip_created ON messages(ip_hash, created_at);');
        return $pdo;
    }

    private static function ensureSpansColumn(PDO $pdo): void
    {
        if (self::hasColumn($pdo, 'spans')) {
            return;
        }
        $pdo->exec('ALTER TABLE messages ADD COLUMN spans TEXT');
    }

    private static function ensureFlaggedColumn(PDO $pdo): void
    {
        if (self::hasColumn($pdo, 'flagged')) {
            return;
        }
        $pdo->exec('ALTER TABLE messages ADD COLUMN flagged INTEGER NOT NULL DEFAULT 0');
    }

    private static function ensureFlagCountColumn(PDO $pdo): void
    {
        if (self::hasColumn($pdo, 'flag_count')) {
            return;
        }
        $pdo->exec('ALTER TABLE messages ADD COLUMN flag_count INTEGER NOT NULL DEFAULT 0');
    }

    private static function ensureMessageFlagsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS message_flags (
                message_id INTEGER NOT NULL,
                ip_hash TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (message_id, ip_hash),
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_message_flags_ip ON message_flags(ip_hash)');
    }

    private static function hasColumn(PDO $pdo, string $name): bool
    {
        $columns = $pdo->query('PRAGMA table_info(messages)')->fetchAll();
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === $name) {
                return true;
            }
        }
        return false;
    }
}
