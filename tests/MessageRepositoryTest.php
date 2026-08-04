<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\MessageRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class MessageRepositoryTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_test_' . uniqid('', true) . '.sqlite';
        $this->pdo = Database::connect($this->path);
        $this->repo = new MessageRepository($this->pdo);
    }

    private function pdo(): PDO
    {
        return $this->pdo;
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

    public function test_toggle_community_flag_increments_and_dedupes_ip(): void
    {
        $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');
        $this->assertSame('flagged', $this->repo->toggleCommunityFlag($id, 'ip-a'));
        // Same IP toggling again unflags rather than double-counting.
        $this->assertSame('unflagged', $this->repo->toggleCommunityFlag($id, 'ip-a'));
        $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
        $this->assertSame(0, (int) $row['flag_count']);
        $this->assertSame(0, (int) $row['flagged']);
    }

    public function test_third_distinct_ip_sets_admin_flagged(): void
    {
        $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');
        $this->repo->toggleCommunityFlag($id, 'ip-1');
        $this->repo->toggleCommunityFlag($id, 'ip-2');
        $this->repo->toggleCommunityFlag($id, 'ip-3');
        $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
        $this->assertSame(3, (int) $row['flag_count']);
        $this->assertSame(1, (int) $row['flagged']);
    }

    public function test_unflag_clears_admin_flag_only_when_crossing_below_threshold(): void
    {
        $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');
        $this->repo->toggleCommunityFlag($id, 'ip-1');
        $this->repo->toggleCommunityFlag($id, 'ip-2');
        $this->repo->toggleCommunityFlag($id, 'ip-3');
        $this->assertSame('unflagged', $this->repo->toggleCommunityFlag($id, 'ip-2'));
        $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
        $this->assertSame(2, (int) $row['flag_count']);
        $this->assertSame(0, (int) $row['flagged']);
    }

    public function test_auto_flagged_stays_flagged_below_threshold(): void
    {
        $id = $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'poster', null, true);
        $this->repo->toggleCommunityFlag($id, 'ip-1');
        $this->repo->toggleCommunityFlag($id, 'ip-1'); // unflag
        $stored = $this->repo->recent(1)[0];
        $this->assertTrue($stored['flagged']);
        $row = $this->pdo()->query('SELECT flag_count FROM messages WHERE id=' . $id)->fetch();
        $this->assertSame(0, (int) $row['flag_count']);
    }

    public function test_delete_removes_message_flags_rows(): void
    {
        $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');
        $this->repo->toggleCommunityFlag($id, 'ip-1');
        $this->repo->delete($id);
        $count = (int) $this->pdo()->query('SELECT COUNT(*) FROM message_flags')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_flagged_message_ids_for_ip(): void
    {
        $a = $this->repo->create('aaaaaaaaaaaaaaaaaaaa', 'red', false, 'p');
        $b = $this->repo->create('bbbbbbbbbbbbbbbbbbbb', 'cyan', false, 'p');
        $this->repo->toggleCommunityFlag($a, 'ip-z');
        $this->assertSame([$a], $this->repo->flaggedMessageIdsForIp([$a, $b], 'ip-z'));
    }

    public function test_exists(): void
    {
        $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');
        $this->assertTrue($this->repo->exists($id));
        $this->assertFalse($this->repo->exists($id + 999));
    }

    public function test_toggle_community_flag_returns_null_for_missing_message(): void
    {
        $this->assertNull($this->repo->toggleCommunityFlag(999999, 'ip-a'));
    }
}
