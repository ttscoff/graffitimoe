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
        return self::wrapMessage($body, $color, $bold, null, $enable);
    }

    /**
     * @param list<array{t:string,c:string,b?:bool}>|null $spans
     */
    public static function wrapMessage(
        string $body,
        string $color,
        bool $bold,
        ?array $spans,
        bool $enable,
    ): string {
        if (!$enable) {
            return $body;
        }

        if ($spans !== null && $spans !== []) {
            $perRunBold = self::spansHaveBoldFlag($spans);
            $out = '';
            foreach ($spans as $run) {
                $runBold = $perRunBold ? !empty($run['b']) : $bold;
                $out .= self::sgr($run['c'], $runBold) . $run['t'] . "\033[0m";
            }
            return $out;
        }

        return self::sgr($color, $bold) . $body . "\033[0m";
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

    /**
     * Escaped HTML for a terminal body (inner content of <pre>).
     *
     * @param list<array{t:string,c:string,b?:bool}>|null $spans
     */
    public static function renderHtmlBody(string $body, string $color, bool $bold, ?array $spans): string
    {
        if ($spans !== null && $spans !== []) {
            $perRunBold = self::spansHaveBoldFlag($spans);
            $html = '';
            foreach ($spans as $run) {
                $runBold = $perRunBold ? !empty($run['b']) : $bold;
                $class = htmlspecialchars(self::cssClass($run['c'], $runBold), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $text = htmlspecialchars($run['t'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= '<span class="' . $class . '">' . $text . '</span>';
            }
            return $html;
        }

        return htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * CSS class(es) for the outer <pre> when not using inner spans.
     *
     * @param list<array{t:string,c:string,b?:bool}>|null $spans
     */
    public static function outerCssClass(string $color, bool $bold, ?array $spans): string
    {
        if ($spans !== null && $spans !== []) {
            return '';
        }
        return self::cssClass($color, $bold);
    }

    /**
     * @param list<array{t:string,c:string,b?:bool}> $spans
     */
    private static function spansHaveBoldFlag(array $spans): bool
    {
        foreach ($spans as $run) {
            if (array_key_exists('b', $run)) {
                return true;
            }
        }
        return false;
    }

    private static function sgr(string $color, bool $bold): string
    {
        $color = MessageSanitizer::normalizeColor($color);
        $codes = [];
        if ($bold) {
            $codes[] = '1';
        }
        $codes[] = self::FG[$color];
        return "\033[" . implode(';', $codes) . 'm';
    }
}
