<?php

declare(strict_types=1);

namespace Graffiti;

use InvalidArgumentException;

final class MessageSanitizer
{
    public const MAX_LENGTH = 1000;

    /** @var list<string> */
    public const COLORS = ['default', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan'];

    public static function sanitizeBody(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = str_replace("\t", '    ', $text);
        // Strip ESC and other controls except newline (\n = 0x0A)
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text) ?? '';
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Message is empty');
        }
        if (mb_strlen($trimmed, 'UTF-8') > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Message exceeds 1000 characters');
        }
        return trim($text, "\n");
    }

    public static function normalizeColor(?string $color): string
    {
        $color = strtolower(trim((string) $color));
        return in_array($color, self::COLORS, true) ? $color : 'default';
    }

    public static function normalizeBold(mixed $bold): bool
    {
        if (is_bool($bold)) {
            return $bold;
        }
        if (is_int($bold)) {
            return $bold !== 0;
        }
        $value = strtolower(trim((string) $bold));
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
