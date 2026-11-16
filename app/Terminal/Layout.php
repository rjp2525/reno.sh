<?php

declare(strict_types=1);

namespace App\Terminal;

/**
 * Rules, boxes, key/value lists and code listings. Every helper returns
 * lines no wider than the width it was given.
 */
final class Layout
{
    public function __construct(private readonly Ansi $ansi) {}

    public function rule(int $width, string $char = '─', string $style = 'line'): string
    {
        return $this->ansi->style(str_repeat($char, max(0, $width)), $style);
    }

    /**
     * Rounded box around already styled content, wrapped to fit.
     */
    public function box(string $content, int $width, ?string $title = null, string $border = 'line'): string
    {
        $inner = max(1, $width - 4);
        // Content is expected pre-wrapped; only hard break what still overflows.
        $lines = explode("\n", Text::breakLines($content, $inner));

        $topWidth = 2;
        if ($title !== null && $title !== '') {
            $label = ' '.Text::truncate($title, max(1, $width - 6)).' ';
            $topLine = $this->ansi->style('╭─', $border).$label;
            $topWidth += Text::width($label);
        } else {
            $topLine = $this->ansi->style('╭─', $border);
        }
        $topLine .= $this->ansi->style(str_repeat('─', max(0, $width - $topWidth - 1)).'╮', $border);

        $out = [$topLine];
        $side = $this->ansi->style('│', $border);
        foreach ($lines as $line) {
            $out[] = $side.' '.Text::pad($line, $inner).' '.$side;
        }
        $out[] = $this->ansi->style('╰'.str_repeat('─', max(0, $width - 2)).'╯', $border);

        return implode("\n", $out);
    }

    /**
     * Aligned `label  value` rows; values wrap with a hanging indent.
     *
     * @param  array<string, string|null>  $rows
     */
    public function keyValue(array $rows, int $width, string $labelStyle = 'slate'): string
    {
        $rows = array_filter($rows, fn ($v) => $v !== null && $v !== '');
        if ($rows === []) {
            return '';
        }

        $labelWidth = min(16, max(array_map(fn ($k) => Text::width((string) $k), array_keys($rows))) + 2);
        if ($width - $labelWidth < 20) {
            // Too narrow for columns: stack label over value.
            $out = [];
            foreach ($rows as $label => $value) {
                $out[] = $this->ansi->style((string) $label, $labelStyle);
                $out[] = Text::wrap((string) $value, $width, '  ', '  ');
            }

            return implode("\n", $out);
        }

        $out = [];
        foreach ($rows as $label => $value) {
            $prefix = $this->ansi->style(Text::pad((string) $label, $labelWidth), $labelStyle);
            $out[] = Text::wrap((string) $value, $width, str_repeat(' ', $labelWidth), $prefix);
        }

        return implode("\n", $out);
    }

    /**
     * A code listing: optional `filename · language` caption and a gutter.
     * Long lines are hard broken rather than wrapped on words.
     */
    public function code(string $code, int $width, ?string $language = null, ?string $filename = null): string
    {
        $code = rtrim(str_replace(["\r\n", "\t"], ["\n", '    '], $code), "\n");
        $caption = implode(' · ', array_filter([$filename, $language]));

        $out = [];
        if ($caption !== '') {
            $out[] = $this->ansi->style(Text::truncate('┌ '.$caption, $width), 'slate');
        }

        $gutter = $this->ansi->style('│', 'line').' ';
        $body = Text::breakLines($this->ansi->style($code, 'white'), max(1, $width - 2));
        $out[] = Text::indent($body, $gutter);

        return implode("\n", $out);
    }

    /** Horizontal `label ───────` section divider. */
    public function section(string $label, int $width): string
    {
        $text = $this->ansi->comment($label);
        $remaining = $width - Text::width($text) - 1;

        return $remaining > 2 ? $text.' '.$this->rule($remaining) : Text::truncate($text, $width);
    }
}
