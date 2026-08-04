<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function test_wrap_disabled_returns_body_unchanged(): void
    {
        $this->assertSame("hi\n", Color::wrapPlain("hi\n", 'red', true, false));
    }

    public function test_wrap_enabled_uses_allowlisted_sgr_and_reset(): void
    {
        $out = Color::wrapPlain('X', 'red', true, true);
        $this->assertSame("\033[1;31mX\033[0m", $out);
    }

    public function test_wrap_message_with_spans_emits_per_run_sgr(): void
    {
        $spans = [
            ['t' => 'hi', 'c' => 'red'],
            ['t' => 'yo', 'c' => 'cyan'],
        ];
        $out = Color::wrapMessage('hiyo', 'default', false, $spans, true);
        $this->assertSame("\033[31mhi\033[0m\033[36myo\033[0m", $out);
    }

    public function test_wrap_message_spans_respect_bold_and_disabled(): void
    {
        $spans = [
            ['t' => 'a', 'c' => 'red'],
            ['t' => 'b', 'c' => 'green'],
        ];
        $this->assertSame('ab', Color::wrapMessage('ab', 'red', true, $spans, false));
        $this->assertSame(
            "\033[1;31ma\033[0m\033[1;32mb\033[0m",
            Color::wrapMessage('ab', 'red', true, $spans, true),
        );
    }

    public function test_wrap_message_mixed_per_run_bold(): void
    {
        $spans = [
            ['t' => 'hi', 'c' => 'red', 'b' => true],
            ['t' => 'yo', 'c' => 'cyan'],
        ];
        // Message-level bold must not paint the non-bold run.
        $this->assertSame(
            "\033[1;31mhi\033[0m\033[36myo\033[0m",
            Color::wrapMessage('hiyo', 'red', true, $spans, true),
        );
        $this->assertSame(
            '<span class="term-red term-bold">hi</span><span class="term-cyan">yo</span>',
            Color::renderHtmlBody('hiyo', 'red', true, $spans),
        );
    }

    public function test_render_html_body_with_spans(): void
    {
        $html = Color::renderHtmlBody('ab', 'default', false, [
            ['t' => 'a', 'c' => 'red'],
            ['t' => 'b', 'c' => 'cyan'],
        ]);
        $this->assertSame(
            '<span class="term-red">a</span><span class="term-cyan">b</span>',
            $html,
        );
        $this->assertSame('', Color::outerCssClass('red', true, [
            ['t' => 'a', 'c' => 'red'],
            ['t' => 'b', 'c' => 'cyan'],
        ]));
        $this->assertSame('term-red term-bold', Color::outerCssClass('red', true, null));
    }

    public function test_css_class(): void
    {
        $this->assertSame('term-cyan term-bold', Color::cssClass('cyan', true));
        $this->assertSame('term-default', Color::cssClass('default', false));
    }
}
