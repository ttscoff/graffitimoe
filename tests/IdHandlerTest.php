<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\Handlers\IdHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class IdHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private IdHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_id_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->handler = new IdHandler($this->repo);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_missing_id_returns_404(): void
    {
        $res = $this->handler->handle(
            new Request('GET', '/id/999', [], [], '', [], '1.1.1.1'),
            999,
        );
        $this->assertSame(404, $res->status);
        $this->assertSame("Not found.\n", $res->body);
    }

    public function test_returns_body_and_optional_color(): void
    {
        $id = $this->repo->create('hello there', 'red', false, 'h');
        $plain = $this->handler->handle(
            new Request('GET', '/id/' . $id, [], [], '', [], '1.1.1.1'),
            $id,
        );
        $this->assertSame(200, $plain->status);
        $this->assertSame("hello there\n", $plain->body);

        $colored = $this->handler->handle(
            new Request('GET', '/id/' . $id, ['color' => 'always'], [], '', [], '1.1.1.1'),
            $id,
        );
        $this->assertStringContainsString("\033[31mhello there\033[0m", $colored->body);
    }

    public function test_returns_multicolor_ansi_for_painted_spans(): void
    {
        $id = $this->repo->create('hiyo', 'red', false, 'h', [
            ['t' => 'hi', 'c' => 'red'],
            ['t' => 'yo', 'c' => 'cyan'],
        ]);
        $colored = $this->handler->handle(
            new Request('GET', '/id/' . $id, ['color' => 'always'], [], '', [], '1.1.1.1'),
            $id,
        );
        $this->assertSame("\033[31mhi\033[0m\033[36myo\033[0m\n", $colored->body);
    }
}
