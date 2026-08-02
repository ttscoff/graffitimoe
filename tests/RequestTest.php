<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function test_curl_is_not_browser_and_wants_plain_text(): void
    {
        $request = new Request('GET', '/', [], [
            'HTTP_USER_AGENT' => 'curl/8.0.0',
            'HTTP_ACCEPT' => '*/*',
        ], '', [], '1.2.3.4');

        $this->assertFalse($request->isBrowser());
        $this->assertTrue($request->wantsPlainText());
    }

    public function test_html_accept_identifies_browser(): void
    {
        $request = new Request('GET', '/', [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ], '', [], '1.2.3.4');

        $this->assertTrue($request->isBrowser());
        $this->assertFalse($request->wantsPlainText());
    }

    public function test_browser_with_empty_or_wildcard_accept_wants_plain_text(): void
    {
        $emptyAccept = new Request('GET', '/', [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ], '', [], '1.2.3.4');

        $wildcardAccept = new Request('GET', '/', [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT' => '*/*',
        ], '', [], '1.2.3.4');

        $this->assertTrue($emptyAccept->isBrowser());
        $this->assertTrue($emptyAccept->wantsPlainText());
        $this->assertTrue($wildcardAccept->isBrowser());
        $this->assertTrue($wildcardAccept->wantsPlainText());
    }

    public function test_color_is_enabled_only_for_always_value(): void
    {
        $enabled = new Request('GET', '/random', ['color' => 'always'], [], '', [], '1.2.3.4');
        $disabled = new Request('GET', '/random', ['color' => 'Always'], [], '', [], '1.2.3.4');

        $this->assertTrue($enabled->colorEnabled());
        $this->assertFalse($disabled->colorEnabled());
    }

    public function test_normalize_path_strips_trailing_slash_except_root(): void
    {
        $add = new Request('GET', '/add/', [], [], '', [], '1.2.3.4');
        $root = new Request('GET', '/', [], [], '', [], '1.2.3.4');
        $rootWithQuery = new Request('GET', '/?foo=bar', [], [], '', [], '1.2.3.4');

        $this->assertSame('/add', $add->path);
        $this->assertSame('/', $root->path);
        $this->assertSame('/', $rootWithQuery->path);
    }

    public function test_constructor_strips_query_from_path_and_exposes_request_data(): void
    {
        $request = new Request('POST', '/messages?color=always', ['color' => 'always'], [
            'HTTP_ACCEPT' => 'text/plain',
        ], 'body', ['message' => 'Hello'], '1.2.3.4');

        $this->assertSame('POST', $request->method);
        $this->assertSame('/messages', $request->path);
        $this->assertSame(['color' => 'always'], $request->query);
        $this->assertSame('text/plain', $request->headers['Accept']);
        $this->assertSame('body', $request->rawBody);
        $this->assertSame(['message' => 'Hello'], $request->post);
        $this->assertSame('1.2.3.4', $request->ip);
        $this->assertTrue($request->wantsPlainText());
    }

    public function test_from_globals_normalizes_path_and_maps_server_headers(): void
    {
        $originalServer = $_SERVER;
        $originalGet = $_GET;
        $originalPost = $_POST;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/messages?color=always',
                'HTTP_USER_AGENT' => 'Wget/1.21',
                'HTTP_ACCEPT' => '*/*',
                'HTTP_X_REQUEST_ID' => 'abc123',
                'REMOTE_ADDR' => '5.6.7.8',
            ];
            $_GET = ['color' => 'always'];
            $_POST = ['message' => 'Hello'];

            $request = Request::fromGlobals();

            $this->assertSame('POST', $request->method);
            $this->assertSame('/messages', $request->path);
            $this->assertSame(['color' => 'always'], $request->query);
            $this->assertSame('abc123', $request->headers['X-Request-Id']);
            $this->assertSame(['message' => 'Hello'], $request->post);
            $this->assertSame('5.6.7.8', $request->ip);
            $this->assertTrue($request->wantsPlainText());
        } finally {
            $_SERVER = $originalServer;
            $_GET = $originalGet;
            $_POST = $originalPost;
        }
    }

    public function test_response_factories_set_expected_headers_body_and_status(): void
    {
        $plain = Response::plain('Hello');
        $html = Response::html('<p>Hello</p>', 201);
        $redirect = Response::redirect('/messages');

        $this->assertSame(200, $plain->status);
        $this->assertSame(['Content-Type' => 'text/plain; charset=utf-8'], $plain->headers);
        $this->assertSame("Hello\n", $plain->body);
        $this->assertSame(201, $html->status);
        $this->assertSame(['Content-Type' => 'text/html; charset=utf-8'], $html->headers);
        $this->assertSame('<p>Hello</p>', $html->body);
        $this->assertSame(302, $redirect->status);
        $this->assertSame(['Location' => '/messages'], $redirect->headers);
        $this->assertSame('', $redirect->body);
    }
}
