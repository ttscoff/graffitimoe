<?php

declare(strict_types=1);

namespace Graffiti;

final class Color
{
    /** @var array<string, string> */
    private const FG = [
        'default' => '39',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
    ];

    public static function wrapPlain(string $body, string $color, bool $bold, bool $enable): string
    {
        if (!$enable) {
            return $body;
        }
        $color = MessageSanitizer::normalizeColor($color);
        $codes = [];
        if ($bold) {
            $codes[] = '1';
        }
        $codes[] = self::FG[$color];
        return "\033[" . implode(';', $codes) . 'm' . $body . "\033[0m";
    }

    public static function cssClass(string $color, bool $bold): string
    {
        $color = MessageSanitizer::normalizeColor($color);
        $class = 'term-' . $color;
        if ($bold) {
            $class .= ' term-bold';
        }
        return $class;
    }
}
