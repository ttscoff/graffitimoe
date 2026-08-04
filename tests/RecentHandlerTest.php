<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\ArraySession;
use Graffiti\Database;
use Graffiti\Handlers\RecentHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class RecentHandlerTest extends TestCase
{
    public function test_returns_json_recent_messages_newest_first(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_recent_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $repo->create('first', 'red', false, 'h1');
        usleep(10000);
        $repo->create('second', 'cyan', true, 'h2');

        $handler = new RecentHandler($repo, new ArraySession());
        $res = $handler->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'));

        $this->assertSame(200, $res->status);
        $this->assertSame('application/json; charset=utf-8', $res->headers['Content-Type']);
        $this->assertSame('no-store', $res->headers['Cache-Control']);

        $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame('second', $data[0]['body']);
        $this->assertSame('cyan', $data[0]['color']);
        $this->assertTrue($data[0]['bold']);
        $this->assertNull($data[0]['spans']);
        $this->assertSame('first', $data[1]['body']);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('created_at', $data[0]);

        @unlink($path);
    }

    public function test_recent_includes_painted_spans(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_recent_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $repo->create('hiyo', 'red', false, 'h1', [
            ['t' => 'hi', 'c' => 'red'],
            ['t' => 'yo', 'c' => 'cyan'],
        ]);

        $handler = new RecentHandler($repo, new ArraySession());
        $res = $handler->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'));
        $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['t' => 'hi', 'c' => 'red'],
            ['t' => 'yo', 'c' => 'cyan'],
        ], $data[0]['spans']);

        @unlink($path);
    }

    public function test_empty_wall_returns_empty_array(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_recent_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $handler = new RecentHandler($repo, new ArraySession());
        $res = $handler->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'));
        $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $data);
        @unlink($path);
    }

    public function test_admin_session_can_fetch_more_than_public_limit(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_recent_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        for ($i = 0; $i < 12; $i++) {
            $repo->create('spray number ' . $i . ' here', 'red', false, 'h' . $i);
            usleep(1000);
        }

        $public = new RecentHandler($repo, new ArraySession());
        $publicData = json_decode(
            $public->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'))->body,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertCount(10, $publicData);

        $session = new ArraySession();
        $session->set('admin', 1);
        $admin = new RecentHandler($repo, $session);
        $adminData = json_decode(
            $admin->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'))->body,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertCount(12, $adminData);

        @unlink($path);
    }
}
