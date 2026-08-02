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

    public function test_css_class(): void
    {
        $this->assertSame('term-cyan term-bold', Color::cssClass('cyan', true));
        $this->assertSame('term-default', Color::cssClass('default', false));
    }
}
