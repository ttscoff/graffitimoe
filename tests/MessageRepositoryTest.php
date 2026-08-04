<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class MessageRepositoryTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_test_' . uniqid('', true) . '.sqlite';
        $pdo = Database::connect($this->path);
        $this->repo = new MessageRepository($pdo);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_create_recent_random_delete(): void
    {
        $this->assertNull($this->repo->random());
        $id = $this->repo->create("line1\nline2", 'green', true, 'hash1');
        $this->assertGreaterThan(0, $id);
        $row = $this->repo->random();
        $this->assertNotNull($row);
        $this->assertSame("line1\nline2", $row['body']);
        $this->assertSame('green', $row['color']);
        $this->assertTrue($row['bold']);
        $this->assertNull($row['spans']);
        $this->assertFalse($row['flagged']);
        $recent = $this->repo->recent(10);
        $this->assertCount(1, $recent);
        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->random());
    }

    public function test_create_and_hydrate_spans(): void
    {
        $spans = [
            ['t' => 'hi', 'c' => 'red'],
            ['t' => 'yo', 'c' => 'cyan'],
        ];
        $id = $this->repo->create('hiyo', 'red', false, 'hash', $spans);
        $row = $this->repo->recent(1)[0];
        $this->assertSame($id, $row['id']);
        $this->assertSame($spans, $row['spans']);
        $random = $this->repo->random();
        $this->assertNotNull($random);
        $this->assertSame($spans, $random['spans']);
    }

    public function test_flagged_create_filter_and_approve(): void
    {
        $this->repo->create('a normal spray about the night bus', 'cyan', false, 'h1', null, false);
        $flaggedId = $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'h2', null, true);

        $onlyFlagged = $this->repo->allNewestFirst(true);
        $this->assertCount(2, $this->repo->allNewestFirst());
        $this->assertCount(1, $onlyFlagged);
        $this->assertSame($flaggedId, $onlyFlagged[0]['id']);
        $this->assertTrue($onlyFlagged[0]['flagged']);

        $this->assertTrue($this->repo->setFlagged($flaggedId, false));
        $this->assertSame([], $this->repo->allNewestFirst(true));

        $byId = [];
        foreach ($this->repo->allNewestFirst() as $row) {
            $byId[$row['id']] = $row;
        }
        $this->assertFalse($byId[$flaggedId]['flagged']);
    }

    public function test_delete_many_and_set_flagged_many(): void
    {
        $a = $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'h1', null, true);
        $b = $this->repo->create('testing testing testing!!', 'red', false, 'h2', null, true);
        $c = $this->repo->create('keep this thoughtful spray around', 'cyan', false, 'h3', null, false);

        $this->assertSame(2, $this->repo->setFlaggedMany([$a, $b], false));
        $this->assertSame([], $this->repo->allNewestFirst(true));
        $this->assertSame(2, $this->repo->deleteMany([$a, $c]));
        $left = $this->repo->allNewestFirst();
        $this->assertCount(1, $left);
        $this->assertSame($b, $left[0]['id']);
    }

    public function test_schema_has_flag_count_and_message_flags(): void
    {
        $pdo = Database::connect($this->path);
        $cols = $pdo->query('PRAGMA table_info(messages)')->fetchAll();
        $names = array_column($cols, 'name');
        $this->assertContains('flag_count', $names);

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='message_flags'"
        )->fetchAll();
        $this->assertNotSame([], $tables);

        $fk = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $this->assertSame(1, $fk);
    }
}
