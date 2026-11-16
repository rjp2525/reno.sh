<?php

declare(strict_types=1);

namespace App\Terminal;

/**
 * A compact three row box-drawing banner font, in the spirit of figlet's
 * "future". Letters are case-insensitive; unknown characters render as "?".
 */
final class Banner
{
    /** @var array<string, array{string, string, string}> */
    private const GLYPHS = [
        'A' => ['┏━┓', '┣━┫', '╹ ╹'],
        'B' => ['┏┓ ', '┣┻┓', '┗━┛'],
        'C' => ['┏━╸', '┃  ', '┗━╸'],
        'D' => ['╺┳┓', ' ┃┃', '╺┻┛'],
        'E' => ['┏━╸', '┣╸ ', '┗━╸'],
        'F' => ['┏━╸', '┣╸ ', '╹  '],
        'G' => ['┏━╸', '┃╺┓', '┗━┛'],
        'H' => ['╻ ╻', '┣━┫', '╹ ╹'],
        'I' => ['╻', '┃', '╹'],
        'J' => ['  ╻', '  ┃', '┗━┛'],
        'K' => ['╻┏ ', '┣┻┓', '╹ ╹'],
        'L' => ['╻  ', '┃  ', '┗━╸'],
        'M' => ['┏┳┓', '┃┃┃', '╹ ╹'],
        'N' => ['┏┓╻', '┃┗┫', '╹ ╹'],
        'O' => ['┏━┓', '┃ ┃', '┗━┛'],
        'P' => ['┏━┓', '┣━┛', '╹  '],
        'Q' => ['┏━┓', '┃┓┃', '┗┻┛'],
        'R' => ['┏━┓', '┣┳┛', '╹┗╸'],
        'S' => ['┏━┓', '┗━┓', '┗━┛'],
        'T' => ['╺┳╸', ' ┃ ', ' ╹ '],
        'U' => ['╻ ╻', '┃ ┃', '┗━┛'],
        'V' => ['╻ ╻', '┃┏┛', '┗┛ '],
        'W' => ['╻ ╻', '┃╻┃', '┗┻┛'],
        'X' => ['╻ ╻', '┏╋┛', '╹ ╹'],
        'Y' => ['╻ ╻', '┗┳┛', ' ╹ '],
        'Z' => ['╺━┓', '┏━┛', '┗━╸'],
        '0' => ['┏━┓', '┃┃┃', '┗━┛'],
        '1' => ['╺┓ ', ' ┃ ', '╺┻╸'],
        '2' => ['┏━┓', '┏━┛', '┗━╸'],
        '3' => ['┏━┓', '╺━┫', '┗━┛'],
        '4' => ['╻ ╻', '┗━┫', '  ╹'],
        '5' => ['┏━╸', '┗━┓', '┗━┛'],
        '6' => ['┏━┓', '┣━┓', '┗━┛'],
        '7' => ['╺━┓', '  ┃', '  ╹'],
        '8' => ['┏━┓', '┣━┫', '┗━┛'],
        '9' => ['┏━┓', '┗━┫', '┗━┛'],
        ' ' => ['  ', '  ', '  '],
        '.' => [' ', ' ', '╹'],
        '-' => ['   ', '╺━╸', '   '],
        '_' => ['   ', '   ', '╺━╸'],
        '!' => ['╻', '┃', '╹'],
        '?' => ['┏━┓', ' ┏┛', ' ╹ '],
        '/' => ['  ╻', '┏━┛', '╹  '],
    ];

    /**
     * Render text into three lines. Returns null when it cannot fit `$width`
     * even after splitting words onto separate rows.
     *
     * @return list<string>|null
     */
    public static function lines(string $text, int $width): ?array
    {
        $rows = self::render($text);
        if (Text::width($rows[0]) <= $width) {
            return $rows;
        }

        // Too wide: put each word on its own banner row.
        $out = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $i => $word) {
            $wordRows = self::render($word);
            if (Text::width($wordRows[0]) > $width) {
                return null;
            }
            if ($i > 0) {
                $out[] = '';
            }
            array_push($out, ...$wordRows);
        }

        return $out;
    }

    /** @return array{string, string, string} */
    public static function render(string $text): array
    {
        $rows = ['', '', ''];
        $chars = mb_str_split(mb_strtoupper($text));

        foreach ($chars as $i => $char) {
            $glyph = self::GLYPHS[$char] ?? self::GLYPHS['?'];
            $glyphWidth = max(array_map(fn ($r) => mb_strwidth($r, 'UTF-8'), $glyph));
            $spacing = ($i < count($chars) - 1 && $char !== ' ') ? ' ' : '';

            foreach ($glyph as $r => $row) {
                $rows[$r] .= Text::pad($row, $glyphWidth).$spacing;
            }
        }

        $trim = min(array_map(fn ($r) => mb_strwidth($r) - mb_strwidth(rtrim($r)), $rows));

        return array_map(fn ($r) => $trim > 0 ? mb_substr($r, 0, mb_strlen($r) - $trim) : $r, $rows);
    }
}
