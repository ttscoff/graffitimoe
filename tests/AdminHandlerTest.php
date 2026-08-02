<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\Handlers\AdminHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/views.php';

final class AdminHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private ArraySession $session;
    private AdminHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_admin_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->session = new ArraySession();
        $this->handler = new AdminHandler(
            $this->repo,
            'secret-password',
            $this->session,
            'render_admin',
            'render_admin_login',
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_wrong_password_renders_login_without_authorizing_session(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['password' => 'wrong'],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
        $this->assertFalse($this->session->isAdmin());
        $this->assertStringContainsString('Invalid password.', $response->body);
    }

    public function test_correct_password_authorizes_session_and_can_delete_message(): void
    {
        $id = $this->repo->create('delete me', 'red', false, 'ip-hash');

        $login = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['password' => 'secret-password'],
            '1.1.1.1',
        ));

        $this->assertSame(302, $login->status);
        $this->assertSame('/admin', $login->headers['Location']);
        $this->assertTrue($this->session->isAdmin());

        $delete = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['id' => (string) $id],
            '1.1.1.1',
        ));

        $this->assertSame(302, $delete->status);
        $this->assertSame('/admin', $delete->headers['Location']);
        $this->assertSame([], $this->repo->allNewestFirst());
    }

    public function test_unauthorized_plain_request_is_forbidden(): void
    {
        $response = $this->handler->handle(new Request(
            'GET',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
        $this->assertSame("Forbidden.\n", $response->body);
    }

    public function test_admin_list_escapes_message_bodies_and_hides_ip_hash(): void
    {
        $this->repo->create('<script>alert(1)</script>', 'red', false, 'secret-ip-hash');
        $this->session->set('admin', 1);

        $response = $this->handler->handle(new Request(
            'GET',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(200, $response->status);
        $this->assertStringNotContainsString('<script>', $response->body);
        $this->assertStringContainsString('&lt;script&gt;', $response->body);
        $this->assertStringNotContainsString('secret-ip-hash', $response->body);
        $this->assertStringContainsString('name="id"', $response->body);
    }
}
