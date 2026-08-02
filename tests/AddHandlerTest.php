<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\Handlers\AddHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use Graffiti\RateLimiter;
use PHPUnit\Framework\TestCase;

final class AddHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private AddHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_add_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $limiter = new RateLimiter($this->repo, 5, 600);
        $this->handler = new AddHandler(
            $this->repo,
            $limiter,
            'secret',
            fn (array $vars): string => 'html:' . count($vars['recent']),
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_get_renders_recent_messages_and_query_status(): void
    {
        $this->repo->create('recent', 'red', true, 'hash');

        $response = $this->handler->handle(new Request(
            'GET',
            '/add',
            ['ok' => '1', 'error' => 'invalid'],
            [],
            '',
            [],
            '1.1.1.1',
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('html:1', $response->body);
    }

    public function test_plain_post_creates_multiline_message(): void
    {
        $request = new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => "a\nb", 'color' => 'blue'],
            '9.9.9.9',
        );
        $response = $this->handler->handle($request);

        $this->assertSame(201, $response->status);
        $this->assertSame("Sprayed.\n", $response->body);
        $this->assertSame("a\nb", $this->repo->recent(1)[0]['body']);
        $this->assertSame('blue', $this->repo->recent(1)[0]['color']);
    }

    public function test_text_plain_body_is_created_with_query_options(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            ['color' => 'cyan', 'bold' => 'yes'],
            ['HTTP_ACCEPT' => 'text/plain', 'CONTENT_TYPE' => 'text/plain'],
            "a\r\nb",
            [],
            '9.9.9.9',
        ));

        $this->assertSame(201, $response->status);
        $stored = $this->repo->recent(1)[0];
        $this->assertSame("a\nb", $stored['body']);
        $this->assertSame('cyan', $stored['color']);
        $this->assertTrue($stored['bold']);
    }

    public function test_honeypot_skips_persist(): void
    {
        $request = new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => 'hi', 'website' => 'http://spam.example'],
            '9.9.9.9',
        );
        $response = $this->handler->handle($request);

        $this->assertSame(201, $response->status);
        $this->assertSame("Sprayed.\n", $response->body);
        $this->assertNull($this->repo->random());
    }

    public function test_rate_limit_returns_429(): void
    {
        $handler = new AddHandler(
            $this->repo,
            new RateLimiter($this->repo, 1, 600),
            'secret',
            fn (): string => '',
        );
        $request = fn (): Request => new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => 'x'],
            '8.8.8.8',
        );

        $this->assertSame(201, $handler->handle($request())->status);
        $response = $handler->handle($request());

        $this->assertSame(429, $response->status);
        $this->assertStringContainsString('Slow down', $response->body);
    }

    public function test_too_long_is_400(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => str_repeat('a', 1001)],
            '7.7.7.7',
        ));

        $this->assertSame(400, $response->status);
    }

    public function test_browser_post_redirects_on_success_and_failure(): void
    {
        $success = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['body' => 'okay'],
            '6.6.6.6',
        ));
        $failure = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/html'],
            '',
            ['body' => ''],
            '6.6.6.6',
        ));

        $this->assertSame(302, $success->status);
        $this->assertSame('/add?ok=1', $success->headers['Location']);
        $this->assertSame(302, $failure->status);
        $this->assertSame('/add?error=invalid', $failure->headers['Location']);
    }
}
