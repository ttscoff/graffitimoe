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
        $this->assertSame("a\nb    c............", MessageSanitizer::sanitizeBody("a\r\nb\tc............"));
    }

    public function test_strips_control_chars_and_escapes(): void
    {
        $raw = "hi\x07there\x1b[31mX........";
        $this->assertSame('hithere[31mX........', MessageSanitizer::sanitizeBody($raw));
    }

    public function test_trims_edges_but_keeps_internal_blank_line(): void
    {
        $this->assertSame("a\n\nb................", MessageSanitizer::sanitizeBody("\na\n\nb................\n"));
    }

    public function test_rejects_empty_and_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody("   \n");
    }

    public function test_rejects_under_20_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody(str_repeat('a', 19));
    }

    public function test_accepts_exactly_20_chars(): void
    {
        $body = str_repeat('a', 20);
        $this->assertSame($body, MessageSanitizer::sanitizeBody($body));
    }

    public function test_rejects_over_1000_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody(str_repeat('a', 1001));
    }

    public function test_rejects_padded_over_limit_after_newline_trim(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody("\n " . str_repeat('a', 999) . " \n");
    }

    public function test_color_and_bold_normalization(): void
    {
        $this->assertSame('red', MessageSanitizer::normalizeColor('red'));
        $this->assertSame('default', MessageSanitizer::normalizeColor('neon'));
        $this->assertTrue(MessageSanitizer::normalizeBold('1'));
        $this->assertTrue(MessageSanitizer::normalizeBold('on'));
        $this->assertFalse(MessageSanitizer::normalizeBold(null));
    }

    public function test_normalize_spans_valid_partition(): void
    {
        $spans = MessageSanitizer::normalizeSpans('hello', json_encode([
            ['t' => 'hel', 'c' => 'red'],
            ['t' => 'lo', 'c' => 'cyan'],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame([
            ['t' => 'hel', 'c' => 'red'],
            ['t' => 'lo', 'c' => 'cyan'],
        ], $spans);
    }

    public function test_normalize_spans_merges_adjacent_same_color(): void
    {
        $spans = MessageSanitizer::normalizeSpans('abcd', [
            ['t' => 'ab', 'c' => 'red'],
            ['t' => 'c', 'c' => 'red'],
            ['t' => 'd', 'c' => 'blue'],
        ]);
        $this->assertSame([
            ['t' => 'abc', 'c' => 'red'],
            ['t' => 'd', 'c' => 'blue'],
        ], $spans);
    }

    public function test_normalize_spans_preserves_per_run_bold_and_merges_same_style(): void
    {
        $spans = MessageSanitizer::normalizeSpans('hello!!..............', [
            ['t' => 'hel', 'c' => 'red', 'b' => true],
            ['t' => 'lo', 'c' => 'red', 'b' => true],
            ['t' => '!!..............', 'c' => 'cyan'],
        ]);
        $this->assertSame([
            ['t' => 'hello', 'c' => 'red', 'b' => true],
            ['t' => '!!..............', 'c' => 'cyan'],
        ], $spans);
    }

    public function test_normalize_spans_same_color_different_bold_stays_split(): void
    {
        $spans = MessageSanitizer::normalizeSpans('abcd................', [
            ['t' => 'ab', 'c' => 'red', 'b' => true],
            ['t' => 'cd................', 'c' => 'red'],
        ]);
        $this->assertSame([
            ['t' => 'ab', 'c' => 'red', 'b' => true],
            ['t' => 'cd................', 'c' => 'red'],
        ], $spans);
    }

    public function test_normalize_spans_rejects_mismatch_and_bad_colors(): void
    {
        $this->assertNull(MessageSanitizer::normalizeSpans('hi', [
            ['t' => 'h', 'c' => 'red'],
            ['t' => 'x', 'c' => 'cyan'],
        ]));
        $this->assertNull(MessageSanitizer::normalizeSpans('hi', [
            ['t' => 'h', 'c' => 'neon'],
            ['t' => 'i', 'c' => 'cyan'],
        ]));
    }

    public function test_normalize_spans_empty_or_trivial_returns_null(): void
    {
        $this->assertNull(MessageSanitizer::normalizeSpans('hi', null));
        $this->assertNull(MessageSanitizer::normalizeSpans('hi', ''));
        $this->assertNull(MessageSanitizer::normalizeSpans('hi', [
            ['t' => 'hi', 'c' => 'red'],
        ]));
    }
}
