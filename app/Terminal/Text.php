<?php

declare(strict_types=1);

namespace App\Terminal;

/**
 * Width-aware text helpers. Everything here ignores ANSI escape sequences
 * when measuring and counts East Asian wide characters as two columns.
 */
final class Text
{
    private const ANSI_PATTERN = '/\e\[[0-9;?]*[A-Za-z]/';

    public static function strip(string $text): string
    {
        return (string) preg_replace(self::ANSI_PATTERN, '', $text);
    }

    public static function width(string $text): int
    {
        return mb_strwidth(self::strip($text), 'UTF-8');
    }

    /** Widest line of a (possibly multi-line) string. */
    public static function maxWidth(string $text): int
    {
        return max(array_map(self::width(...), explode("\n", $text)));
    }

    public static function pad(string $text, int $width, string $align = 'left'): string
    {
        $gap = max(0, $width - self::width($text));

        return match ($align) {
            'right' => str_repeat(' ', $gap).$text,
            'center' => str_repeat(' ', intdiv($gap, 2)).$text.str_repeat(' ', $gap - intdiv($gap, 2)),
            default => $text.str_repeat(' ', $gap),
        };
    }

    /** Prefix every line of a block. */
    public static function indent(string $text, string $prefix, ?string $first = null): string
    {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $p = ($i === 0 && $first !== null) ? $first : $prefix;
            $lines[$i] = $line === '' ? rtrim($p) : $p.$line;
        }

        return implode("\n", $lines);
    }

    public static function truncate(string $text, int $width, string $ellipsis = '…'): string
    {
        if (self::width($text) <= $width) {
            return $text;
        }

        $chunks = self::hardBreak($text, max(1, $width - mb_strwidth($ellipsis)));

        return self::closeOpen($chunks[0]).$ellipsis;
    }

    /**
     * Word wrap to `$width` columns. Existing newlines and runs of spaces
     * within a line are kept (spaces at a break are dropped), words longer
     * than a line are broken, and styles that are open
     * at a line break are closed and re-opened on the next line so indents and
     * borders never inherit colors.
     *
     * `$indent` prefixes continuation lines (a hanging indent); `$firstIndent`
     * prefixes the first line. Both count towards `$width`.
     */
    public static function wrap(string $text, int $width, string $indent = '', string $firstIndent = ''): string
    {
        $out = [];
        $active = '';
        $first = true;

        foreach (explode("\n", $text) as $paragraph) {
            $lineIndent = $first ? $firstIndent : $indent;
            $first = false;
            $available = max(1, $width - self::width($lineIndent));

            $words = self::words($paragraph);
            if ($words === []) {
                $out[] = rtrim($lineIndent);

                continue;
            }

            $line = $active;
            $lineWidth = 0;

            foreach ($words as $index => [$word, $wordWidth, $spaces]) {
                // Break against the narrower of this line and continuation lines.
                $limit = min($available, max(1, $width - self::width($indent)));
                $pieces = $wordWidth > $available ? self::hardBreak($word, $limit) : [$word];

                foreach ($pieces as $p => $piece) {
                    $pieceWidth = self::width($piece);
                    $gap = match (true) {
                        $p > 0 || $pieceWidth === 0 => 0,
                        $lineWidth > 0 => max(1, $spaces),
                        $index === 0 => $spaces, // leading spaces of the paragraph
                        default => 0,
                    };

                    if ($lineWidth > 0 && $lineWidth + $gap + $pieceWidth > $available) {
                        $active = self::activeAfter('', $line);
                        $out[] = $lineIndent.$line.($active !== '' ? Ansi::RESET : '');
                        $lineIndent = $indent;
                        $available = max(1, $width - self::width($lineIndent));
                        $line = $active;
                        $lineWidth = 0;
                        $gap = 0;
                    }

                    $line .= str_repeat(' ', $gap).$piece;
                    $lineWidth += $gap + $pieceWidth;
                }
            }

            $active = self::activeAfter('', $line);
            $out[] = $lineIndent.$line.($active !== '' ? Ansi::RESET : '');
            $lineIndent = $indent;
        }

        return implode("\n", $out);
    }

    /**
     * Break a string (no wrapping on spaces) into chunks of at most `$width`
     * columns, keeping escape sequences attached to the characters they style.
     *
     * @return list<string>
     */
    public static function hardBreak(string $text, int $width): array
    {
        $width = max(1, $width);
        $chunks = [];
        $current = '';
        $currentWidth = 0;

        foreach (self::tokens($text) as $token) {
            if ($token[0] === "\e") {
                $current .= $token;

                continue;
            }

            foreach (mb_str_split($token) as $char) {
                $w = mb_strwidth($char, 'UTF-8');
                if ($currentWidth + $w > $width && $currentWidth > 0) {
                    $chunks[] = $current;
                    $current = '';
                    $currentWidth = 0;
                }
                $current .= $char;
                $currentWidth += $w;
            }
        }

        if ($current !== '' || $chunks === []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Hard break every line of preformatted text (no word wrapping), carrying
     * styles across breaks.
     */
    public static function breakLines(string $text, int $width): string
    {
        $out = [];
        $active = '';

        foreach (explode("\n", $text) as $line) {
            foreach (self::hardBreak($line, $width) as $chunk) {
                $chunk = $active.$chunk;
                $active = self::activeAfter('', $chunk);
                $out[] = $chunk.($active !== '' ? Ansi::RESET : '');
            }
        }

        return implode("\n", $out);
    }

    /**
     * @return list<array{string, int, int}> words with their visible widths and the spaces before them
     */
    private static function words(string $line): array
    {
        $words = [];
        $word = '';
        $spaces = 0;
        $pending = 0;

        foreach (self::tokens($line) as $token) {
            if ($token[0] === "\e") {
                $word .= $token;

                continue;
            }

            foreach (preg_split('/([ \t]+)/u', $token, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
                if ($part === '') {
                    continue;
                }
                if (trim($part, " \t") === '') {
                    if (self::width($word) > 0) {
                        $words[] = [$word, self::width($word), $spaces];
                        $word = '';
                        $spaces = 0;
                    }
                    $pending += strlen(str_replace("\t", '    ', $part));

                    continue;
                }
                if (self::width($word) === 0) {
                    $spaces += $pending;
                }
                $pending = 0;
                $word .= $part;
            }
        }

        if ($word !== '') {
            $words[] = [$word, self::width($word), $spaces];
        }

        // A line of only escape codes still needs to be emitted.
        if ($words !== [] && array_sum(array_column($words, 1)) === 0) {
            return [[implode('', array_column($words, 0)), 0, 0]];
        }

        return $words;
    }

    /** @return list<string> alternating plain text and escape sequences */
    private static function tokens(string $text): array
    {
        $parts = preg_split('/(\e\[[0-9;?]*[A-Za-z])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }

    /** SGR codes still in effect after `$segment`, given those active before it. */
    private static function activeAfter(string $active, string $segment): string
    {
        preg_match_all('/\e\[([0-9;]*)m/', $segment, $matches);
        foreach ($matches[0] as $i => $code) {
            $params = $matches[1][$i];
            $active = ($params === '' || $params === '0') ? '' : $active.$code;
        }

        return $active;
    }

    private static function closeOpen(string $text): string
    {
        return self::activeAfter('', $text) !== '' ? $text.Ansi::RESET : $text;
    }
}
