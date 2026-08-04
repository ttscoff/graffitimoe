<?php

declare(strict_types=1);

namespace Graffiti;

/**
 * Rule-based heuristic for low-effort / test sprays.
 * Flags are advisory only — messages still post immediately.
 */
final class MessageQuality
{
    /** @var list<string> */
    public const DENYLIST = [
        'hello world',
        'hello worlds',
        'testing',
        'test test',
        'this is a test',
        'just testing',
        'just a test',
        'test message',
        'asdf',
        'asdfasdf',
        'qwerty',
        'qwertyuiop',
        'lorem ipsum',
        'abc123',
        'foo bar',
        'foo bar baz',
        'aaaa',
        'test',
        'testing 123',
        'hello',
    ];

    private const KEYBOARD_ROWS = [
        'qwertyuiop',
        'asdfghjkl',
        'zxcvbnm',
        '1234567890',
    ];

    public static function shouldFlag(string $body): bool
    {
        $normalized = self::normalize($body);
        if ($normalized === '') {
            return false;
        }

        if (self::matchesDenylist($normalized)) {
            return true;
        }
        if (self::isKeyboardSmash($normalized)) {
            return true;
        }
        if (self::isLowDiversity($body)) {
            return true;
        }
        if (self::hasWeakWordShape($body, $normalized)) {
            return true;
        }

        return false;
    }

    /** Lowercase, collapse whitespace, strip most punctuation (keep letters/digits/spaces). */
    public static function normalize(string $body): string
    {
        $text = mb_strtolower($body, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim($text);
    }

    private static function matchesDenylist(string $normalized): bool
    {
        foreach (self::DENYLIST as $phrase) {
            if ($normalized === $phrase) {
                return true;
            }
        }

        // Whole message is denylist phrase repeated (e.g. "testing testing testing")
        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return false;
        }
        $unique = array_values(array_unique($tokens));
        if (count($unique) === 1 && in_array($unique[0], self::DENYLIST, true)) {
            return true;
        }

        // Compact (no spaces) equals a denylist entry with no spaces, or keyboard-ish stubs
        $compact = str_replace(' ', '', $normalized);
        foreach (self::DENYLIST as $phrase) {
            $phraseCompact = str_replace(' ', '', $phrase);
            if ($phraseCompact !== '' && $compact === $phraseCompact) {
                return true;
            }
        }

        return false;
    }

    private static function isKeyboardSmash(string $normalized): bool
    {
        $compact = str_replace(' ', '', $normalized);
        if (strlen($compact) < 8) {
            return false;
        }

        foreach (self::KEYBOARD_ROWS as $row) {
            $run = 0;
            $len = strlen($compact);
            for ($i = 0; $i < $len; $i++) {
                $ch = $compact[$i];
                if (str_contains($row, $ch)) {
                    $run++;
                    if ($run >= 8) {
                        return true;
                    }
                } else {
                    $run = 0;
                }
            }
        }

        return false;
    }

    private static function isLowDiversity(string $body): bool
    {
        if (preg_match('/([\p{L}\p{N}])\1{7,}/u', $body) === 1) {
            return true;
        }

        $chars = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($body, 'UTF-8')) ?? '';
        $len = mb_strlen($chars, 'UTF-8');
        if ($len < 16) {
            return false;
        }

        /** @var array<string, true> $unique */
        $unique = [];
        for ($i = 0; $i < $len; $i++) {
            $unique[mb_substr($chars, $i, 1, 'UTF-8')] = true;
        }

        return (count($unique) / $len) < 0.22;
    }

    private static function hasWeakWordShape(string $body, string $normalized): bool
    {
        $stripped = preg_replace('/\s+/u', '', $body) ?? '';
        $total = mb_strlen($stripped, 'UTF-8');
        if ($total < 20) {
            return false;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $stripped) ?? '';
        $letterLen = mb_strlen($letters, 'UTF-8');
        if ($letterLen / $total < 0.55) {
            // Mostly symbols/punctuation — likely ascii art, not weak prose
            return false;
        }

        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $realWords = 0;
        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') >= 3 && preg_match('/[aeiouy]/u', $token) === 1) {
                $realWords++;
            }
        }

        return $realWords < 2;
    }
}
