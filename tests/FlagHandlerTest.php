<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\FlaggedMessages;
use Graffiti\Handlers\FlagHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class FlagHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private ArraySession $session;
    private FlaggedMessages $flagged;
    private FlagHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_flag_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->session = new ArraySession();
        $this->flagged = new FlaggedMessages($this->session);
        $this->handler = new FlagHandler($this->repo, $this->session, $this->flagged, 'secret');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_flags_and_unflags_with_csrf(): void
    {
        $id = $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'h');
        $csrf = $this->session->csrfToken();

        $flag = $this->handler->handle(new Request(
            'POST', '/flag', [], ['HTTP_ACCEPT' => 'text/plain'], '',
            ['id' => (string) $id, 'csrf_token' => $csrf], '1.2.3.4',
        ));
        $this->assertSame(200, $flag->status);
        $this->assertSame("Flagged.\n", $flag->body);
        $this->assertTrue($this->flagged->has($id));

        $unflag = $this->handler->handle(new Request(
            'POST', '/flag', [], ['HTTP_ACCEPT' => 'text/plain'], '',
            ['id' => (string) $id, 'csrf_token' => $csrf], '1.2.3.4',
        ));
        $this->assertSame(200, $unflag->status);
        $this->assertSame("Unflagged.\n", $unflag->body);
        $this->assertFalse($this->flagged->has($id));
    }

    public function test_rejects_invalid_csrf(): void
    {
        $id = $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'h');
        $response = $this->handler->handle(new Request(
            'POST', '/flag', [], ['HTTP_ACCEPT' => 'text/html'], '',
            ['id' => (string) $id, 'csrf_token' => 'nope'], '1.2.3.4',
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/add?error=invalid', $response->headers['Location']);
    }

    public function test_missing_message_is_404(): void
    {
        $csrf = $this->session->csrfToken();
        $response = $this->handler->handle(new Request(
            'POST', '/flag', [], ['HTTP_ACCEPT' => 'text/plain'], '',
            ['id' => '999', 'csrf_token' => $csrf], '1.2.3.4',
        ));
        $this->assertSame(404, $response->status);
    }
}
