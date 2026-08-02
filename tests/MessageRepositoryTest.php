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
        $recent = $this->repo->recent(10);
        $this->assertCount(1, $recent);
        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->random());
    }
}
