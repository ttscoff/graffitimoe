<?php

declare(strict_types=1);

namespace Graffiti;

use InvalidArgumentException;

final class MessageSanitizer
{
    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 1000;

    /** @var list<string> */
    public const COLORS = ['default', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan'];

    public static function sanitizeBody(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = str_replace("\t", '    ', $text);
        // Strip ESC and other controls except newline (\n = 0x0A)
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text) ?? '';
        if (trim($text) === '') {
            throw new InvalidArgumentException('Message is empty');
        }
        $result = trim($text, "\n");
        $length = mb_strlen($result, 'UTF-8');
        if ($length < self::MIN_LENGTH) {
            throw new InvalidArgumentException('Message is shorter than ' . self::MIN_LENGTH . ' characters');
        }
        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Message exceeds ' . self::MAX_LENGTH . ' characters');
        }
        return $result;
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

    /**
     * Validate painted runs. Returns null when absent, invalid, or trivially uniform.
     *
     * @return list<array{t:string,c:string,b?:bool}>|null
     */
    public static function normalizeSpans(string $body, mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $raw = $decoded;
        }

        if (!is_array($raw) || $raw === []) {
            return null;
        }

        $runs = [];
        $concat = '';
        foreach ($raw as $item) {
            if (!is_array($item)) {
                return null;
            }
            $text = $item['t'] ?? null;
            $color = $item['c'] ?? null;
            if (!is_string($text) || $text === '' || !is_string($color)) {
                return null;
            }
            $color = strtolower(trim($color));
            if (!in_array($color, self::COLORS, true)) {
                return null;
            }
            $bold = self::normalizeBold($item['b'] ?? false);

            // Merge adjacent same color+bold runs
            $last = $runs === [] ? null : count($runs) - 1;
            if (
                $last !== null
                && $runs[$last]['c'] === $color
                && (($runs[$last]['b'] ?? false) === $bold)
            ) {
                $runs[$last]['t'] .= $text;
            } else {
                $run = ['t' => $text, 'c' => $color];
                if ($bold) {
                    $run['b'] = true;
                }
                $runs[] = $run;
            }
            $concat .= $text;
        }

        if ($concat !== $body) {
            // sanitizeBody() trims leading/trailing newlines; paint runs often still include them.
            if (trim($concat, "\n") !== $body) {
                return null;
            }
            $runs = self::trimEdgeNewlinesFromRuns($runs);
            $concat = '';
            foreach ($runs as $run) {
                $concat .= $run['t'];
            }
            if ($concat !== $body || $runs === []) {
                return null;
            }
        }

        // Trivial: one uniform run — store as message-level color/bold instead
        if (count($runs) <= 1) {
            return null;
        }

        return $runs;
    }

    /**
     * @param list<array{t:string,c:string,b?:bool}> $runs
     * @return list<array{t:string,c:string,b?:bool}>
     */
    private static function trimEdgeNewlinesFromRuns(array $runs): array
    {
        while ($runs !== [] && str_starts_with($runs[0]['t'], "\n")) {
            $runs[0]['t'] = substr($runs[0]['t'], 1);
            if ($runs[0]['t'] === '') {
                array_shift($runs);
            }
        }

        while ($runs !== []) {
            $last = array_key_last($runs);
            if (!str_ends_with($runs[$last]['t'], "\n")) {
                break;
            }
            $runs[$last]['t'] = substr($runs[$last]['t'], 0, -1);
            if ($runs[$last]['t'] === '') {
                array_pop($runs);
            }
        }

        return array_values($runs);
    }
}
