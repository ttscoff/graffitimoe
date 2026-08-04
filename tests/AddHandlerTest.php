<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\FlaggedMessages;
use Graffiti\Handlers\AddHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use Graffiti\OwnedMessages;
use Graffiti\RateLimiter;
use PHPUnit\Framework\TestCase;

final class AddHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private ArraySession $session;
    private OwnedMessages $owned;
    private FlaggedMessages $flagged;
    private AddHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_add_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $this->session = new ArraySession();
        $this->owned = new OwnedMessages($this->session);
        $this->flagged = new FlaggedMessages($this->session);
        $limiter = new RateLimiter($this->repo, 5, 600);
        $this->handler = new AddHandler(
            $this->repo,
            $limiter,
            'secret',
            $this->session,
            $this->owned,
            $this->flagged,
            fn (array $vars): string => 'html:' . count($vars['recent']) . ':admin=' . ($vars['isAdmin'] ? '1' : '0'),
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
        $this->assertSame('html:1:admin=0', $response->body);
    }

    public function test_admin_get_loads_up_to_50_recent(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->repo->create('message number ' . $i . ' xx', 'red', false, 'h' . $i);
            usleep(1000);
        }
        $this->session->set('admin', 1);

        $public = $this->handler->handle(new Request(
            'GET',
            '/add',
            [],
            [],
            '',
            [],
            '1.1.1.1',
        ));
        // Still admin session
        $this->assertSame('html:12:admin=1', $public->body);
    }

    public function test_plain_post_creates_multiline_message(): void
    {
        $request = new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => "a\nb.................", 'color' => 'blue'],
            '9.9.9.9',
        );
        $response = $this->handler->handle($request);

        $this->assertSame(201, $response->status);
        $this->assertSame("Sprayed.\n", $response->body);
        $this->assertSame("a\nb.................", $this->repo->recent(1)[0]['body']);
        $this->assertSame('blue', $this->repo->recent(1)[0]['color']);
        $this->assertNull($this->repo->recent(1)[0]['spans']);
        $this->assertFalse($this->repo->recent(1)[0]['flagged']);
    }

    public function test_low_effort_message_is_flagged_on_create(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => 'Hello world!!!!!!!!!!', 'color' => 'red'],
            '9.9.9.9',
        ));

        $this->assertSame(201, $response->status);
        $stored = $this->repo->recent(1)[0];
        $this->assertSame('Hello world!!!!!!!!!!', $stored['body']);
        $this->assertTrue($stored['flagged']);
        $this->assertTrue($this->owned->owns($stored['id']));
    }

    public function test_post_with_spans_persists_painted_runs(): void
    {
        $spansJson = json_encode([
            ['t' => 'hello ', 'c' => 'red'],
            ['t' => 'painted world!!', 'c' => 'cyan'],
        ], JSON_THROW_ON_ERROR);
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => 'hello painted world!!', 'color' => 'default', 'spans' => $spansJson],
            '9.9.9.9',
        ));

        $this->assertSame(201, $response->status);
        $stored = $this->repo->recent(1)[0];
        $this->assertSame('hello painted world!!', $stored['body']);
        $this->assertSame('red', $stored['color']);
        $this->assertSame([
            ['t' => 'hello ', 'c' => 'red'],
            ['t' => 'painted world!!', 'c' => 'cyan'],
        ], $stored['spans']);
    }

    public function test_post_with_mixed_bold_spans_persists_per_run_bold(): void
    {
        $spansJson = json_encode([
            ['t' => 'hello ', 'c' => 'red', 'b' => true],
            ['t' => 'painted world!!', 'c' => 'cyan'],
        ], JSON_THROW_ON_ERROR);
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            [
                'body' => 'hello painted world!!',
                'color' => 'default',
                'bold' => '0',
                'spans' => $spansJson,
            ],
            '9.9.9.9',
        ));

        $this->assertSame(201, $response->status);
        $stored = $this->repo->recent(1)[0];
        $this->assertSame('red', $stored['color']);
        $this->assertTrue($stored['bold']);
        $this->assertSame([
            ['t' => 'hello ', 'c' => 'red', 'b' => true],
            ['t' => 'painted world!!', 'c' => 'cyan'],
        ], $stored['spans']);
    }

    public function test_invalid_spans_are_ignored(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            [
                'body' => 'hello painted world!!',
                'color' => 'green',
                'spans' => json_encode([
                    ['t' => 'h', 'c' => 'red'],
                    ['t' => 'x', 'c' => 'cyan'],
                ], JSON_THROW_ON_ERROR),
            ],
            '9.9.9.9',
        ));

        $this->assertSame(201, $response->status);
        $stored = $this->repo->recent(1)[0];
        $this->assertSame('green', $stored['color']);
        $this->assertNull($stored['spans']);
    }

    public function test_text_plain_body_is_created_with_query_options(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            ['color' => 'cyan', 'bold' => 'yes'],
            ['HTTP_ACCEPT' => 'text/plain', 'CONTENT_TYPE' => 'text/plain'],
            "a\r\nb.................",
            [],
            '9.9.9.9',
        ));

        $this->assertSame(201, $response->status);
        $stored = $this->repo->recent(1)[0];
        $this->assertSame("a\nb.................", $stored['body']);
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
            $this->session,
            $this->owned,
            $this->flagged,
            fn (): string => '',
        );
        $request = fn (): Request => new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => str_repeat('x', 20)],
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

    public function test_too_short_is_400(): void
    {
        $response = $this->handler->handle(new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => str_repeat('a', 19)],
            '7.7.7.8',
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
            ['body' => str_repeat('okay ', 4)],
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
