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

    public function test_find_returns_hydrated_row_or_null(): void
    {
        $id = $this->repo->create('hello painted world!!', 'cyan', true, 'poster');
        $row = $this->repo->find($id);
        $this->assertNotNull($row);
        $this->assertSame($id, $row['id']);
        $this->assertSame('hello painted world!!', $row['body']);
        $this->assertSame('cyan', $row['color']);
        $this->assertTrue($row['bold']);
        $this->assertNull($this->repo->find($id + 999));
        $this->assertNull($this->repo->find(0));
    }

    public function test_toggle_community_flag_returns_null_for_missing_message(): void
    {
        $this->assertNull($this->repo->toggleCommunityFlag(999999, 'ip-a'));
    }

    /**
     * toggleCommunityFlag() narrows its INSERT-time catch to genuine UNIQUE
     * violations (the only case where "someone already flagged this from
     * this IP" is a safe no-op) so a locked database, disk error, or FK
     * failure isn't mistaken for success. SQLite reports both UNIQUE and
     * FOREIGN KEY violations under SQLSTATE 23000 / driver code 19, so the
     * message text has to be checked too — this exercises the classifier
     * against real error shapes captured from the sqlite PDO driver.
     */
    public function test_unique_constraint_classifier_distinguishes_real_races_from_other_errors(): void
    {
        $classifier = new \ReflectionMethod(MessageRepository::class, 'isUniqueConstraintViolation');

        $unique = new \PDOException(
            'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: message_flags.message_id, message_flags.ip_hash'
        );
        $unique->errorInfo = ['23000', 19, 'UNIQUE constraint failed: message_flags.message_id, message_flags.ip_hash'];
        $this->assertTrue($classifier->invoke(null, $unique));

        $foreignKey = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed');
        $foreignKey->errorInfo = ['23000', 19, 'FOREIGN KEY constraint failed'];
        $this->assertFalse($classifier->invoke(null, $foreignKey));

        $locked = new \PDOException('SQLSTATE[HY000]: General error: 5 database is locked');
        $locked->errorInfo = ['HY000', 5, 'database is locked'];
        $this->assertFalse($classifier->invoke(null, $locked));
    }

    public function test_toggle_community_flag_uses_begin_immediate_write_lock(): void
    {
        // With BEGIN IMMEDIATE, a second connection cannot even start a
        // write transaction on the same database file until the first one
        // finishes — this is what closes the race the narrowed catch used
        // to paper over. We only assert the happy path still commits
        // correctly with the new locking strategy; genuinely reproducing
        // the old cross-process race in-process isn't practical since the
        // fix's entire purpose is to serialize access at the SQLite level.
        $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');

        $this->assertSame('flagged', $this->repo->toggleCommunityFlag($id, 'ip-a'));
        $this->assertFalse($this->pdo()->inTransaction());

        $row = $this->pdo()->query('SELECT flag_count FROM messages WHERE id=' . $id)->fetch();
        $this->assertSame(1, (int) $row['flag_count']);
    }
}
