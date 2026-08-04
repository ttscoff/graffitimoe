<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\MessageQuality;
use PHPUnit\Framework\TestCase;

final class MessageQualityTest extends TestCase
{
    public function test_flags_denylist_phrases(): void
    {
        $this->assertTrue(MessageQuality::shouldFlag('Hello world!!!!!!!!!!'));
        $this->assertTrue(MessageQuality::shouldFlag('testing'));
        $this->assertTrue(MessageQuality::shouldFlag('testing testing testing!!'));
        $this->assertTrue(MessageQuality::shouldFlag('This is a test........'));
        $this->assertTrue(MessageQuality::shouldFlag('Lorem ipsum'));
        $this->assertTrue(MessageQuality::shouldFlag('asdf'));
    }

    public function test_flags_keyboard_smash(): void
    {
        $this->assertTrue(MessageQuality::shouldFlag('asdfghjklasdfghjkl'));
        $this->assertTrue(MessageQuality::shouldFlag('qwertyuiopzxcvbnm'));
    }

    public function test_flags_repetition_and_low_diversity(): void
    {
        $this->assertTrue(MessageQuality::shouldFlag(str_repeat('a', 24)));
        $this->assertTrue(MessageQuality::shouldFlag(str_repeat('ab', 15)));
    }

    public function test_flags_weak_word_shape(): void
    {
        $this->assertTrue(MessageQuality::shouldFlag('bcdfghjklmnpqrstvwxz!!!!'));
    }

    public function test_allows_normal_graffiti_and_ascii_art(): void
    {
        $this->assertFalse(MessageQuality::shouldFlag(
            'the night bus smells like rain and old coffee cups'
        ));
        $art = "  /\\_/\\\n ( o.o )\n  > ^ <  and friends";
        $this->assertFalse(MessageQuality::shouldFlag($art));
        $this->assertFalse(MessageQuality::shouldFlag(
            'spray something true before the wall forgets your name'
        ));
    }

    public function test_normalize_strips_punctuation(): void
    {
        $this->assertSame('hello world', MessageQuality::normalize('Hello, world!!!'));
    }
}
