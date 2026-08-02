<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\MessageSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessageSanitizerTest extends TestCase
{
    public function test_preserves_multiline_ascii_art_and_spaces(): void
    {
        $art = "  /\\_/\\\n ( o.o )\n  > ^ <";
        $this->assertSame($art, MessageSanitizer::sanitizeBody($art));
    }

    public function test_normalizes_crlf_and_expands_tabs(): void
    {
        $this->assertSame("a\nb    c", MessageSanitizer::sanitizeBody("a\r\nb\tc"));
    }

    public function test_strips_control_chars_and_escapes(): void
    {
        $raw = "hi\x07there\x1b[31mX";
        $this->assertSame('hithere[31mX', MessageSanitizer::sanitizeBody($raw));
    }

    public function test_trims_edges_but_keeps_internal_blank_line(): void
    {
        $this->assertSame("a\n\nb", MessageSanitizer::sanitizeBody("\na\n\nb\n"));
    }

    public function test_rejects_empty_and_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody("   \n");
    }

    public function test_rejects_over_1000_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody(str_repeat('a', 1001));
    }

    public function test_color_and_bold_normalization(): void
    {
        $this->assertSame('red', MessageSanitizer::normalizeColor('red'));
        $this->assertSame('default', MessageSanitizer::normalizeColor('neon'));
        $this->assertTrue(MessageSanitizer::normalizeBold('1'));
        $this->assertTrue(MessageSanitizer::normalizeBold('on'));
        $this->assertFalse(MessageSanitizer::normalizeBold(null));
    }
}
