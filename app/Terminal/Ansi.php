<?php

declare(strict_types=1);

namespace App\Terminal;

/**
 * SGR styling with truecolor output that degrades to the xterm 256 palette,
 * the 16 basic colors, or nothing at all.
 *
 * Styles are palette names (see PALETTE), `#rrggbb` hex colors, or one of
 * bold, dim, italic, underline, strike.
 */
final class Ansi
{
    public const RESET = "\e[0m";

    /** The site's editor theme. */
    public const PALETTE = [
        'primary' => '#43D9AD',
        'orange' => '#FEA55F',
        'blue' => '#4D5BCE',
        'purple' => '#C98BDF',
        'pink' => '#E99287',
        'slate' => '#607B96',
        'white' => '#E5E9F0',
        'line' => '#3A4F66',
    ];

    private const ATTRIBUTES = [
        'bold' => '1',
        'dim' => '2',
        'italic' => '3',
        'underline' => '4',
        'strike' => '9',
    ];

    /** Basic 16 colors: SGR foreground code => rgb. */
    private const BASIC = [
        30 => [0, 0, 0], 31 => [205, 49, 49], 32 => [13, 188, 121], 33 => [229, 229, 16],
        34 => [36, 114, 200], 35 => [188, 63, 188], 36 => [17, 168, 205], 37 => [229, 229, 229],
        90 => [102, 102, 102], 91 => [241, 76, 76], 92 => [35, 209, 139], 93 => [245, 245, 67],
        94 => [59, 142, 234], 95 => [214, 112, 214], 96 => [41, 184, 219], 97 => [255, 255, 255],
    ];

    public function __construct(public readonly ColorMode $mode = ColorMode::TrueColor) {}

    public function enabled(): bool
    {
        return $this->mode !== ColorMode::None;
    }

    public function style(string $text, string ...$styles): string
    {
        if (! $this->enabled() || $text === '' || $styles === []) {
            return $text;
        }

        $open = $this->open(...$styles);

        if ($open === '') {
            return $text;
        }

        // Re-open our style after any reset inside nested styled text.
        $text = str_replace(self::RESET, self::RESET.$open, $text);

        return $open.$text.self::RESET;
    }

    /**
     * Horizontal gradient across a plain (unstyled) string. `$span` lets
     * several lines of different lengths share the same gradient columns.
     */
    public function gradient(string $text, string $from, string $to, ?int $span = null, string ...$extra): string
    {
        if (! $this->enabled()) {
            return $text;
        }

        $chars = mb_str_split($text);
        $span = max(1, ($span ?? count($chars)) - 1);
        [$r1, $g1, $b1] = self::rgb(self::PALETTE[$from] ?? $from);
        [$r2, $g2, $b2] = self::rgb(self::PALETTE[$to] ?? $to);

        $out = '';
        $last = null;
        foreach ($chars as $i => $char) {
            if ($char === ' ') {
                $out .= $char;

                continue;
            }
            $t = min(1, $i / $span);
            $hex = sprintf('#%02x%02x%02x', (int) round($r1 + ($r2 - $r1) * $t), (int) round($g1 + ($g2 - $g1) * $t), (int) round($b1 + ($b2 - $b1) * $t));
            $open = $this->open($hex, ...$extra);
            $out .= ($open === $last ? '' : $open).$char;
            $last = $open;
        }

        return $out.self::RESET;
    }

    public function primary(string $t): string
    {
        return $this->style($t, 'primary');
    }

    public function orange(string $t): string
    {
        return $this->style($t, 'orange');
    }

    public function blue(string $t): string
    {
        return $this->style($t, 'blue');
    }

    public function purple(string $t): string
    {
        return $this->style($t, 'purple');
    }

    public function pink(string $t): string
    {
        return $this->style($t, 'pink');
    }

    public function slate(string $t): string
    {
        return $this->style($t, 'slate');
    }

    public function white(string $t): string
    {
        return $this->style($t, 'white');
    }

    public function bold(string $t): string
    {
        return $this->style($t, 'bold');
    }

    public function italic(string $t): string
    {
        return $this->style($t, 'italic');
    }

    public function line(string $t): string
    {
        return $this->style($t, 'line');
    }

    /** A `// comment` in the editor theme. */
    public function comment(string $t): string
    {
        return $this->style('// '.$t, 'slate');
    }

    private function open(string ...$styles): string
    {
        $codes = [];
        foreach ($styles as $style) {
            if (isset(self::ATTRIBUTES[$style])) {
                $codes[] = self::ATTRIBUTES[$style];

                continue;
            }

            $hex = self::PALETTE[$style] ?? $style;
            if (preg_match('/^#?[0-9a-f]{6}$/i', $hex)) {
                $codes[] = $this->foreground($hex);
            }
        }

        return $codes === [] ? '' : "\e[".implode(';', $codes).'m';
    }

    private function foreground(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return match ($this->mode) {
            ColorMode::TrueColor => "38;2;{$r};{$g};{$b}",
            ColorMode::Ansi256 => '38;5;'.self::to256($r, $g, $b),
            default => (string) self::toBasic($r, $g, $b),
        };
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    public static function to256(int $r, int $g, int $b): int
    {
        $levels = [0, 95, 135, 175, 215, 255];
        $nearest = function (int $v) use ($levels): int {
            $best = 0;
            foreach ($levels as $i => $level) {
                if (abs($level - $v) < abs($levels[$best] - $v)) {
                    $best = $i;
                }
            }

            return $best;
        };

        [$ri, $gi, $bi] = [$nearest($r), $nearest($g), $nearest($b)];
        $cube = 16 + 36 * $ri + 6 * $gi + $bi;
        $cubeDistance = ($levels[$ri] - $r) ** 2 + ($levels[$gi] - $g) ** 2 + ($levels[$bi] - $b) ** 2;

        $grayIndex = (int) max(0, min(23, round(((($r + $g + $b) / 3) - 8) / 10)));
        $gray = 8 + 10 * $grayIndex;
        $grayDistance = ($gray - $r) ** 2 + ($gray - $g) ** 2 + ($gray - $b) ** 2;

        return $grayDistance < $cubeDistance ? 232 + $grayIndex : $cube;
    }

    public static function toBasic(int $r, int $g, int $b): int
    {
        $best = 37;
        $bestDistance = PHP_INT_MAX;
        foreach (self::BASIC as $code => [$cr, $cg, $cb]) {
            $distance = ($cr - $r) ** 2 + ($cg - $g) ** 2 + ($cb - $b) ** 2;
            if ($distance < $bestDistance) {
                $best = $code;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
