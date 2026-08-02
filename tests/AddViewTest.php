<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/views.php';

final class AddViewTest extends TestCase
{
    public function test_add_view_escapes_html(): void
    {
        $html = render_add([
            'recent' => [['id' => 1, 'body' => '<script>alert(1)</script>', 'color' => 'red', 'bold' => false, 'created_at' => 'x']],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_add_view_renders_recent_terminal_frames_and_wall_empty_state(): void
    {
        $html = render_add([
            'recent' => [],
            'ok' => true,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('graffiti', $html);
        $this->assertStringContainsString('wall-empty', $html);
        $this->assertStringContainsString('name="website"', $html);
    }

    public function test_add_view_applies_color_and_bold_css_class(): void
    {
        $html = render_add([
            'recent' => [['id' => 7, 'body' => 'hi', 'color' => 'cyan', 'bold' => true, 'created_at' => 'x']],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('term-cyan term-bold', $html);
    }

    public function test_add_view_includes_disclaimer_and_cli_sections(): void
    {
        $html = render_add([
            'recent' => [],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('compose-notice', $html);
        $this->assertStringContainsString('No language filter. Posts are anonymous.', $html);
        $this->assertStringContainsString('house rules', $html);
        $this->assertStringContainsString('no automated language filtering', $html);
        $this->assertStringContainsString('takes no responsibility', $html);
        $this->assertStringContainsString('Hate speech and pornographic content', $html);
        $this->assertStringContainsString('from your terminal', $html);
        $this->assertStringContainsString('brew tap ttscoff/thelab', $html);
        $this->assertStringContainsString('brew install graffiti', $html);
        $this->assertStringContainsString('graffiti spraypaint', $html);
        $this->assertStringContainsString('curl graffiti.moe', $html);
        $this->assertStringContainsString('color=always', $html);
        $this->assertStringNotContainsString('Want color?', $html);
    }
}
