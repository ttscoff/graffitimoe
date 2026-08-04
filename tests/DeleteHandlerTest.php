<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\Handlers\DeleteHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use Graffiti\OwnedMessages;
use PHPUnit\Framework\TestCase;

final class DeleteHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private ArraySession $session;
    private OwnedMessages $owned;
    private DeleteHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_delete_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->session = new ArraySession();
        $this->owned = new OwnedMessages($this->session);
        $this->handler = new DeleteHandler($this->repo, $this->session, $this->owned);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_owner_can_delete_own_message(): void
    {
        $id = $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'h');
        $this->owned->remember($id);
        $csrf = $this->session->csrfToken();

        $response = $this->handler->handle(new Request(
            'POST',
            '/delete',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['id' => (string) $id, 'csrf_token' => $csrf],
            '1.1.1.1',
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame("Deleted.\n", $response->body);
        $this->assertNull($this->repo->random());
        $this->assertFalse($this->owned->owns($id));
    }

    public function test_cannot_delete_unowned_message(): void
    {
        $id = $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'h');
        $csrf = $this->session->csrfToken();

        $response = $this->handler->handle(new Request(
            'POST',
            '/delete',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['id' => (string) $id, 'csrf_token' => $csrf],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
        $this->assertNotNull($this->repo->random());
    }

    public function test_rejects_invalid_csrf(): void
    {
        $id = $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'h');
        $this->owned->remember($id);

        $response = $this->handler->handle(new Request(
            'POST',
            '/delete',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['id' => (string) $id, 'csrf_token' => 'nope'],
            '1.1.1.1',
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame('/add?error=action', $response->headers['Location']);
        $this->assertNotNull($this->repo->random());
    }
}
