<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\FlaggedMessages;
use Graffiti\Handlers\IdHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use Graffiti\OwnedMessages;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/views.php';

final class IdHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private ArraySession $session;
    private OwnedMessages $owned;
    private FlaggedMessages $flagged;
    private IdHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_id_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->session = new ArraySession();
        $this->owned = new OwnedMessages($this->session);
        $this->flagged = new FlaggedMessages($this->session);
        $this->handler = $this->makeHandler();
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    /** @param callable(array<string, mixed>): string|null $render */
    private function makeHandler(?callable $render = null): IdHandler
    {
        return new IdHandler(
            $this->repo,
            $this->session,
            $this->owned,
            $this->flagged,
            'secret',
            $render ?? static fn (array $vars): string => render_id($vars),
        );
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

    public function test_browser_gets_html_solo_page(): void
    {
        $id = $this->repo->create('solo spray!!', 'cyan', false, 'h');
        $res = $this->handler->handle(new Request(
            'GET',
            '/id/' . $id,
            [],
            ['HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_ACCEPT' => 'text/html'],
            '',
            [],
            '1.2.3.4',
        ), $id);
        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('text/html', (string) ($res->headers['Content-Type'] ?? ''));
        $this->assertStringContainsString('solo spray!!', $res->body);
        $this->assertStringContainsString('back to the wall', $res->body);
        $this->assertStringContainsString('/add', $res->body);
        $this->assertStringContainsString('msg #' . $id, $res->body);
        $this->assertStringContainsString('href="/id/' . $id . '"', $res->body);
    }

    public function test_browser_missing_id_html_404(): void
    {
        $res = $this->handler->handle(new Request(
            'GET',
            '/id/999',
            [],
            ['HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_ACCEPT' => 'text/html'],
            '',
            [],
            '1.2.3.4',
        ), 999);
        $this->assertSame(404, $res->status);
        $this->assertStringContainsString('text/html', (string) ($res->headers['Content-Type'] ?? ''));
        $this->assertStringContainsString('not found', strtolower($res->body));
        $this->assertStringContainsString('/add', $res->body);
        $this->assertStringContainsString('back to the wall', $res->body);
    }
}
