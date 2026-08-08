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
        $this->assertStringContainsString('/assets/favicon.png', $html);
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
        $this->assertStringContainsString('cli-block', $html);
        $this->assertStringContainsString('No language filter. Posts are anonymous.', $html);

        $composeNoticePos = strpos($html, 'compose-notice');
        $recentSpraysPos = strpos($html, 'recent sprays');
        $this->assertNotFalse($composeNoticePos);
        $this->assertNotFalse($recentSpraysPos);
        $this->assertLessThan($recentSpraysPos, $composeNoticePos);

        $houseRulesPos = strpos($html, 'id="house-rules"');
        $this->assertNotFalse($houseRulesPos);
        $this->assertGreaterThan($recentSpraysPos, $houseRulesPos);

        $this->assertStringContainsString('house rules', $html);
        $this->assertStringContainsString('no automated language filtering', $html);
        $this->assertStringContainsString('takes no responsibility', $html);
        $this->assertStringContainsString('Hate speech and pornographic content', $html);
        $this->assertStringContainsString('from your terminal', $html);
        $this->assertStringContainsString('brew tap ttscoff/thelab', $html);
        $this->assertStringContainsString('brew install graffiti', $html);
        $this->assertStringContainsString("graffiti spraypaint 'your message'", $html);
        $this->assertStringContainsString('graffiti get 42', $html);
        $this->assertStringContainsString('curl graffiti.moe', $html);
        $this->assertStringContainsString('color=always', $html);
        $this->assertStringNotContainsString('Want color?', $html);
    }

    public function test_add_view_has_live_wall_hooks(): void
    {
        $html = render_add([
            'recent' => [['id' => 9, 'body' => 'hi', 'color' => 'red', 'bold' => false, 'created_at' => 'x']],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);
        $this->assertStringContainsString('data-id="9"', $html);
        $this->assertStringContainsString('wall-grid', $html);
        $this->assertStringContainsString('wall-empty', $html);
        $this->assertStringContainsString('/assets/wall.js', $html);
    }

    public function test_add_view_empty_still_has_grid_and_empty(): void
    {
        $html = render_add([
            'recent' => [],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);
        $this->assertStringContainsString('wall-grid', $html);
        $this->assertStringContainsString('wall-empty', $html);
    }

    public function test_add_view_has_char_counter_hooks(): void
    {
        $html = render_add([
            'recent' => [],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('id="char-count"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('/assets/compose.js', $html);
        $this->assertStringNotContainsString('maxlength="1000"', $html);
    }

    public function test_add_view_has_paint_mode_controls(): void
    {
        $html = render_add([
            'recent' => [],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('id="paint-toggle"', $html);
        $this->assertStringContainsString('id="paint-surface"', $html);
        $this->assertStringContainsString('id="brush-palette"', $html);
        $this->assertStringContainsString('id="paint-hint"', $html);
        $this->assertStringContainsString('id="compose-hint"', $html);
        $this->assertStringContainsString('10 character minimum', $html);
        $this->assertStringContainsString('name="spans"', $html);
        $this->assertStringContainsString('name="brush"', $html);
    }

    public function test_add_view_renders_painted_spans_in_wall(): void
    {
        $html = render_add([
            'recent' => [[
                'id' => 3,
                'body' => 'hiyo',
                'color' => 'red',
                'bold' => false,
                'spans' => [
                    ['t' => 'hi', 'c' => 'red'],
                    ['t' => 'yo', 'c' => 'cyan'],
                ],
                'created_at' => 'x',
            ]],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('<span class="term-red">hi</span>', $html);
        $this->assertStringContainsString('<span class="term-cyan">yo</span>', $html);
    }

    public function test_add_view_renders_mixed_bold_spans(): void
    {
        $html = render_add([
            'recent' => [[
                'id' => 3,
                'body' => 'hiyo',
                'color' => 'red',
                'bold' => true,
                'spans' => [
                    ['t' => 'hi', 'c' => 'red', 'b' => true],
                    ['t' => 'yo', 'c' => 'cyan'],
                ],
                'created_at' => 'x',
            ]],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
        ]);

        $this->assertStringContainsString('<span class="term-red term-bold">hi</span>', $html);
        $this->assertStringContainsString('<span class="term-cyan">yo</span>', $html);
        $this->assertStringNotContainsString('term-cyan term-bold', $html);
    }

    public function test_add_view_admin_wall_shows_delete_controls(): void
    {
        $html = render_add([
            'recent' => [['id' => 42, 'body' => 'hello painted world!!', 'color' => 'red', 'bold' => false, 'created_at' => 'x']],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
            'isAdmin' => true,
            'csrfToken' => 'test-csrf-token',
        ]);

        $this->assertStringContainsString('data-admin="1"', $html);
        $this->assertStringContainsString('data-wall-max="50"', $html);
        $this->assertStringContainsString('data-csrf="test-csrf-token"', $html);
        $this->assertStringContainsString('wall-delete', $html);
        $this->assertStringContainsString('name="next" value="/add"', $html);
        $this->assertStringContainsString('recent sprays (admin)', $html);
    }

    public function test_add_view_public_wall_hides_delete_controls(): void
    {
        $html = render_add([
            'recent' => [['id' => 42, 'body' => 'hello painted world!!', 'color' => 'red', 'bold' => false, 'flagged' => true, 'created_at' => 'x']],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
            'isAdmin' => false,
            'csrfToken' => '',
        ]);

        $this->assertStringNotContainsString('data-admin=', $html);
        $this->assertStringContainsString('data-wall-max="10"', $html);
        $this->assertStringNotContainsString('wall-delete', $html);
        $this->assertStringNotContainsString('flag-badge', $html);
        $this->assertStringNotContainsString('wall-approve', $html);
    }

    public function test_add_view_admin_shows_flag_badge_and_approve(): void
    {
        $html = render_add([
            'recent' => [['id' => 7, 'body' => 'Hello world!!!!!!!!!!', 'color' => 'red', 'bold' => false, 'flagged' => true, 'created_at' => 'x']],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
            'isAdmin' => true,
            'csrfToken' => 'csrf',
        ]);

        $this->assertStringContainsString('flag-badge', $html);
        $this->assertStringContainsString('wall-approve', $html);
        $this->assertStringContainsString('name="approve"', $html);
        $this->assertStringContainsString('is-flagged', $html);
    }

    public function test_add_view_owner_sees_delete_for_owned_only(): void
    {
        $html = render_add([
            'recent' => [
                ['id' => 10, 'body' => 'my own spray on the wall!!', 'color' => 'red', 'bold' => false, 'created_at' => 'x'],
                ['id' => 11, 'body' => 'someone else sprayed this!!', 'color' => 'cyan', 'bold' => false, 'created_at' => 'y'],
            ],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
            'isAdmin' => false,
            'ownedIds' => [10],
            'csrfToken' => 'csrf',
        ]);

        $this->assertStringContainsString('data-owned="10"', $html);
        $this->assertStringContainsString('action="/delete"', $html);
        $this->assertSame(1, substr_count($html, 'class="wall-delete"'));

        $deleteFormStart = strpos($html, 'class="wall-delete"');
        $deleteFormEnd = strpos($html, '</form>', $deleteFormStart);
        $deleteForm = substr($html, $deleteFormStart, $deleteFormEnd - $deleteFormStart);
        $this->assertStringContainsString('name="id" value="10"', $deleteForm);
        $this->assertStringNotContainsString('name="id" value="11"', $deleteForm);
    }

    public function test_add_view_has_flag_controls(): void
    {
        $html = render_add([
            'recent' => [[
                'id' => 5,
                'body' => 'hello painted world!!',
                'color' => 'red',
                'bold' => false,
                'created_at' => 'x',
            ]],
            'ok' => false,
            'error' => null,
            'colors' => \Graffiti\MessageSanitizer::COLORS,
            'csrfToken' => 'tok',
            'flaggedIds' => [5],
        ]);
        $this->assertStringContainsString('action="/flag"', $html);
        $this->assertStringContainsString('wall-flag-btn', $html);
        $this->assertStringContainsString('is-flagged-by-me', $html);
        $this->assertStringContainsString('data-csrf="tok"', $html);
        $this->assertStringContainsString('data-flagged-ids="5"', $html);
    }
}
