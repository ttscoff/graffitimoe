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
        $csrfToken = $this->session->csrfToken();

        $response = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['password' => 'wrong', 'csrf_token' => $csrfToken],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
        $this->assertFalse($this->session->isAdmin());
        $this->assertStringContainsString('Invalid password.', $response->body);
    }

    public function test_login_post_without_valid_csrf_token_is_rejected(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['password' => 'secret-password', 'csrf_token' => 'bogus-token'],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
        $this->assertFalse($this->session->isAdmin());
        $this->assertStringContainsString('Invalid request.', $response->body);
    }

    public function test_correct_password_authorizes_session_and_can_delete_message(): void
    {
        $id = $this->repo->create('delete me', 'red', false, 'ip-hash');
        $csrfToken = $this->session->csrfToken();

        $login = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['password' => 'secret-password', 'csrf_token' => $csrfToken],
            '1.1.1.1',
        ));

        $this->assertSame(302, $login->status);
        $this->assertSame('/admin', $login->headers['Location']);
        $this->assertTrue($this->session->isAdmin());

        $postLoginCsrfToken = $this->session->csrfToken();

        $delete = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['id' => (string) $id, 'csrf_token' => $postLoginCsrfToken],
            '1.1.1.1',
        ));

        $this->assertSame(302, $delete->status);
        $this->assertSame('/admin', $delete->headers['Location']);
        $this->assertSame([], $this->repo->allNewestFirst());
    }

    public function test_delete_can_redirect_back_to_wall(): void
    {
        $id = $this->repo->create('delete me please!!', 'red', false, 'ip-hash');
        $this->session->set('admin', 1);
        $csrfToken = $this->session->csrfToken();

        $delete = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['id' => (string) $id, 'csrf_token' => $csrfToken, 'next' => '/add'],
            '1.1.1.1',
        ));

        $this->assertSame(302, $delete->status);
        $this->assertSame('/add', $delete->headers['Location']);
        $this->assertSame([], $this->repo->allNewestFirst());
    }

    public function test_delete_rejects_open_redirect_next(): void
    {
        $id = $this->repo->create('keep redirect safe!!', 'red', false, 'ip-hash');
        $this->session->set('admin', 1);
        $csrfToken = $this->session->csrfToken();

        $delete = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['id' => (string) $id, 'csrf_token' => $csrfToken, 'next' => '//evil.example'],
            '1.1.1.1',
        ));

        $this->assertSame(302, $delete->status);
        $this->assertSame('/admin', $delete->headers['Location']);
    }

    public function test_delete_post_without_valid_csrf_token_is_rejected(): void
    {
        $id = $this->repo->create('keep me', 'red', false, 'ip-hash');
        $this->session->set('admin', 1);

        $response = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['id' => (string) $id, 'csrf_token' => 'bogus-token'],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
        $this->assertCount(1, $this->repo->allNewestFirst());
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
        $this->assertStringContainsString('/assets/style.css?v=', $response->body);
        $this->assertStringContainsString('admin-panel', $response->body);
        $this->assertStringContainsString('name="logout"', $response->body);
    }

    public function test_logout_clears_admin_session(): void
    {
        $this->session->set('admin', 1);
        $csrfToken = $this->session->csrfToken();

        $response = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['logout' => '1', 'csrf_token' => $csrfToken],
            '1.1.1.1',
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
        $this->assertFalse($this->session->isAdmin());
    }

    public function test_approve_clears_flag_and_flagged_filter(): void
    {
        $flaggedId = $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'ip', null, true);
        $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'ip2', null, false);
        $this->session->set('admin', 1);

        $filtered = $this->handler->handle(new Request(
            'GET',
            '/admin',
            ['flagged' => '1'],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            [],
            '1.1.1.1',
        ));
        $this->assertSame(200, $filtered->status);
        $this->assertStringContainsString('flagged sprays', $filtered->body);
        $this->assertStringContainsString('Hello world!!!!!!!!!!', $filtered->body);
        $this->assertStringNotContainsString('midnight trains', $filtered->body);

        $csrfToken = $this->session->csrfToken();
        $approve = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            [
                'id' => (string) $flaggedId,
                'approve' => '1',
                'csrf_token' => $csrfToken,
                'next' => '/admin?flagged=1',
            ],
            '1.1.1.1',
        ));
        $this->assertSame(302, $approve->status);
        $this->assertSame('/admin?flagged=1', $approve->headers['Location']);
        $this->assertSame([], $this->repo->allNewestFirst(true));
    }

    public function test_batch_approve_and_delete_selected_ids(): void
    {
        $a = $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'ip', null, true);
        $b = $this->repo->create('testing testing testing!!', 'red', false, 'ip2', null, true);
        $c = $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'ip3', null, false);
        $this->session->set('admin', 1);
        $csrfToken = $this->session->csrfToken();

        $approve = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            [
                'ids' => [(string) $a, (string) $b],
                'batch_approve' => '1',
                'csrf_token' => $csrfToken,
                'next' => '/admin?flagged=1',
            ],
            '1.1.1.1',
        ));
        $this->assertSame(302, $approve->status);
        $this->assertSame([], $this->repo->allNewestFirst(true));

        $delete = $this->handler->handle(new Request(
            'POST',
            '/admin',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            [
                'ids' => [(string) $a, (string) $c],
                'batch_delete' => '1',
                'csrf_token' => $this->session->csrfToken(),
                'next' => '/admin',
            ],
            '1.1.1.1',
        ));
        $this->assertSame(302, $delete->status);
        $remaining = $this->repo->allNewestFirst();
        $this->assertCount(1, $remaining);
        $this->assertSame($b, $remaining[0]['id']);
    }

    public function test_admin_list_includes_batch_controls(): void
    {
        $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'ip', null, true);
        $this->session->set('admin', 1);

        $response = $this->handler->handle(new Request(
            'GET',
            '/admin',
            ['flagged' => '1'],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertStringContainsString('id="admin-select-all"', $response->body);
        $this->assertStringContainsString('name="ids[]"', $response->body);
        $this->assertStringContainsString('name="batch_approve"', $response->body);
        $this->assertStringContainsString('name="batch_delete"', $response->body);
        $this->assertStringContainsString('/assets/admin.js', $response->body);
    }
}
