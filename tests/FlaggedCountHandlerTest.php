<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\Handlers\FlaggedCountHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class FlaggedCountHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private ArraySession $session;
    private FlaggedCountHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_flagged_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->session = new ArraySession();
        $this->handler = new FlaggedCountHandler($this->repo, 'secret-password', $this->session);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_unauthorized_is_forbidden(): void
    {
        $response = $this->handler->handle(new Request(
            'GET',
            '/flagged',
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(403, $response->status);
    }

    public function test_bearer_token_returns_plain_count_for_curl(): void
    {
        $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'h1', null, true);
        $this->repo->create('a thoughtful line about midnight trains', 'cyan', false, 'h2', null, false);
        $this->repo->create('testing testing testing!!', 'red', false, 'h3', null, true);

        $response = $this->handler->handle(new Request(
            'GET',
            '/flagged',
            [],
            [
                'HTTP_ACCEPT' => '*/*',
                'HTTP_USER_AGENT' => 'curl/8.0',
                'HTTP_AUTHORIZATION' => 'Bearer secret-password',
            ],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame("2\n", $response->body);
    }

    public function test_admin_session_returns_json_when_requested(): void
    {
        $this->repo->create('Hello world!!!!!!!!!!', 'red', false, 'h1', null, true);
        $this->session->set('admin', 1);

        $response = $this->handler->handle(new Request(
            'GET',
            '/flagged',
            [],
            ['HTTP_ACCEPT' => 'application/json'],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame(['flagged' => 1], json_decode($response->body, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_x_admin_password_header_works(): void
    {
        $response = $this->handler->handle(new Request(
            'GET',
            '/flagged',
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_ADMIN_PASSWORD' => 'secret-password',
            ],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame(['flagged' => 0], json_decode($response->body, true, 512, JSON_THROW_ON_ERROR));
    }
}
