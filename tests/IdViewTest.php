<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/views.php';

final class IdViewTest extends TestCase
{
    public function test_id_view_renders_solo_terminal_and_back_link(): void
    {
        $html = render_id([
            'message' => [
                'id' => 42,
                'body' => 'hello solo!!',
                'color' => 'cyan',
                'bold' => false,
                'created_at' => 'x',
            ],
            'isAdmin' => false,
            'ownedIds' => [],
            'csrfToken' => 'tok',
            'flaggedIds' => [],
        ]);

        $this->assertStringContainsString('hello solo!!', $html);
        $this->assertStringContainsString('msg #42', $html);
        $this->assertStringContainsString('href="/id/42"', $html);
        $this->assertStringContainsString('back to the wall', $html);
        $this->assertStringContainsString('href="/add"', $html);
        $this->assertStringContainsString('graffiti', $html);
        $this->assertStringContainsString('name="next"', $html);
        $this->assertStringContainsString('value="/id/42"', $html);
    }

    public function test_id_view_not_found(): void
    {
        $html = render_id([
            'message' => null,
            'isAdmin' => false,
            'ownedIds' => [],
            'csrfToken' => '',
            'flaggedIds' => [],
        ]);

        $this->assertStringContainsString('not found', strtolower($html));
        $this->assertStringContainsString('back to the wall', $html);
        $this->assertStringContainsString('href="/add"', $html);
    }

    public function test_id_view_escapes_body(): void
    {
        $html = render_id([
            'message' => [
                'id' => 1,
                'body' => '<script>alert(1)</script>',
                'color' => 'red',
                'bold' => false,
                'created_at' => 'x',
            ],
            'csrfToken' => 'tok',
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
