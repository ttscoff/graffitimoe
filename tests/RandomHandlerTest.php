<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\Handlers\RandomHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class RandomHandlerTest extends TestCase
{
    public function test_empty_pool_fallback(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rand_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $handler = new RandomHandler($repo, 'https://graffiti.moe');
        $res = $handler->handle(new Request('GET', '/random', [], [], '', [], '1.1.1.1'));
        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('The wall is blank', $res->body);
        @unlink($path);
    }

    public function test_returns_body_and_optional_color(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rand_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $repo->create('hello', 'red', false, 'h');
        $handler = new RandomHandler($repo, 'https://graffiti.moe');
        $plain = $handler->handle(new Request('GET', '/random', [], [], '', [], '1.1.1.1'));
        $this->assertSame("hello\n", $plain->body);
        $colored = $handler->handle(new Request('GET', '/random', ['color' => 'always'], [], '', [], '1.1.1.1'));
        $this->assertStringContainsString("\033[31mhello\033[0m", $colored->body);
        @unlink($path);
    }

    public function test_returns_multicolor_ansi_for_painted_spans(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rand_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $repo->create('hiyo', 'red', false, 'h', [
            ['t' => 'hi', 'c' => 'red'],
            ['t' => 'yo', 'c' => 'cyan'],
        ]);
        $handler = new RandomHandler($repo, 'https://graffiti.moe');
        $colored = $handler->handle(new Request('GET', '/random', ['color' => 'always'], [], '', [], '1.1.1.1'));
        $this->assertSame("\033[31mhi\033[0m\033[36myo\033[0m\n", $colored->body);
        @unlink($path);
    }
}
