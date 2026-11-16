<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Terminal\Ansi;
use App\Terminal\BlockRenderer;
use App\Terminal\Context;
use App\Terminal\HtmlConverter;
use App\Terminal\Layout;
use App\Terminal\Text;
use DateTimeInterface;

/**
 * A full terminal page: menu header, body, link footnotes and the nav footer.
 */
abstract class Page
{
    /** Menu sections: key => [label, path]. */
    public const MENU = [
        'hello' => ['_hello', '/'],
        'about' => ['_about', '/about'],
        'projects' => ['_projects', '/projects'],
        'photography' => ['_photography', '/gallery'],
        'blog' => ['_blog', '/blog'],
        'contact' => ['_contact-me', '/contact'],
    ];

    /** Left/right margin around the body. */
    protected const MARGIN = 2;

    protected readonly Ansi $ansi;

    protected readonly Layout $layout;

    protected readonly HtmlConverter $html;

    protected readonly BlockRenderer $blocks;

    public function __construct(protected readonly Context $ctx)
    {
        $this->ansi = $ctx->ansi;
        $this->layout = new Layout($ctx->ansi);
        $this->html = new HtmlConverter($ctx->ansi);
        $this->blocks = new BlockRenderer($ctx->ansi, $this->html);
    }

    /** Key of the active MENU entry (or null). */
    abstract public function section(): ?string;

    /** Body content, no wider than `$width`. */
    abstract protected function body(int $width): string;

    public function status(): int
    {
        return 200;
    }

    public function render(): string
    {
        $inner = $this->ctx->width - self::MARGIN * 2;
        $margin = str_repeat(' ', self::MARGIN);

        $body = $this->body($inner);

        $footnotes = $this->html->footnotes($inner);
        if ($footnotes !== '') {
            $body .= "\n\n".$this->layout->section('links', $inner)."\n".$footnotes;
        }

        return implode("\n", [
            $this->header(),
            '',
            Text::indent($body, $margin),
            '',
            $this->footer(),
        ])."\n";
    }

    protected function header(): string
    {
        $width = $this->ctx->width;
        $items = [$this->ansi->slate('reno-philibert')];
        foreach (self::MENU as $key => [$label]) {
            $items[] = $key === $this->section()
                ? $this->ansi->style($label, 'primary', 'bold', 'underline')
                : $this->ansi->slate($label);
        }

        // Greedy fill with two-space gaps; items never break.
        $lines = [''];
        foreach ($items as $item) {
            $current = &$lines[count($lines) - 1];
            if ($current !== '' && Text::width($current) + 2 + Text::width($item) > $width - self::MARGIN) {
                $lines[] = $item;
            } else {
                $current .= ($current === '' ? '' : '  ').$item;
            }
            unset($current);
        }
        $menu = implode("\n", $lines);

        return Text::indent($menu, str_repeat(' ', self::MARGIN))."\n".$this->layout->rule($width);
    }

    protected function footer(): string
    {
        $width = $this->ctx->width;
        $inner = $width - self::MARGIN * 2;

        $rows = [];
        foreach (self::MENU as $key => [$label, $path]) {
            $rows[] = [$this->ctx->curl($path), $label, $key === $this->section()];
        }

        $cmdWidth = max(array_map(fn ($r) => mb_strwidth($r[0]) + 2, $rows));
        $labelWidth = max(array_map(fn ($r) => mb_strwidth($r[1]), $rows));
        $showLabels = $cmdWidth + 2 + $labelWidth <= $inner;

        $lines = [$this->ansi->comment('explore')];
        foreach ($rows as [$cmd, $label, $current]) {
            $line = $this->prompt($cmd);
            if ($showLabels) {
                $line = Text::pad($line, $cmdWidth + 2).($current ? $this->ansi->primary($label) : $this->ansi->slate($label));
            }
            $lines[] = Text::breakLines($line, $inner);
        }

        $ssh = 'ssh '.$this->ctx->sshHost().' -p '.$this->ctx->sshPort();
        $lines[] = '';
        $lines[] = Text::wrap($this->ansi->comment('prefer something interactive? try the TUI'), $inner);
        $lines[] = Text::breakLines($this->prompt($ssh), $inner);
        $lines[] = '';
        $lines[] = Text::wrap($this->ansi->comment('tip: add ?nocolor or ?width=120 to any page'), $inner);

        return $this->layout->rule($width)."\n".Text::indent(implode("\n", $lines), str_repeat(' ', self::MARGIN));
    }

    /* ------------------------------------------------------------------ */
    /* Shared building blocks */
    /* ------------------------------------------------------------------ */

    /** `$ command` line, hard broken (never word-wrapped) to `$width`. */
    protected function command(string $command, int $width): string
    {
        return Text::breakLines($this->prompt($command), $width);
    }

    protected function prompt(string $command): string
    {
        return $this->ansi->primary('$').' '.$this->ansi->white($command);
    }

    /** Page title: `_section` style label with an optional comment beneath. */
    protected function title(string $title, int $width, ?string $comment = null): string
    {
        $out = Text::wrap($this->ansi->style($title, 'primary', 'bold'), $width);
        if ($comment !== null && $comment !== '') {
            $out .= "\n".Text::wrap($this->ansi->comment($comment), $width, '   ');
        }

        return $out;
    }

    protected function comment(string $text, int $width): string
    {
        return Text::wrap($this->ansi->comment($text), $width, '   ');
    }

    protected function paragraph(?string $text, int $width, string ...$styles): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = (string) preg_replace("/\r\n?/", "\n", $text);
        $paragraphs = preg_split("/\n{2,}/", $text) ?: [];

        return implode("\n\n", array_map(
            fn ($p) => Text::wrap($styles ? $this->ansi->style($p, ...$styles) : $p, $width),
            $paragraphs,
        ));
    }

    /** Text that may be HTML (rich editor) or plain text (textarea). */
    protected function richOrPlain(?string $text, int $width): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        return $text !== strip_tags($text) ? $this->html->convert($text, $width) : $this->paragraph($text, $width);
    }

    /**
     * A list entry: `▸ title` with indented detail lines.
     *
     * @param  list<string>  $details  already styled; each wrapped under the title
     */
    protected function entry(string $title, array $details, int $width, string $bullet = '▸', string $bulletStyle = 'primary'): string
    {
        $out = Text::wrap($this->ansi->style($bullet, $bulletStyle).' '.$title, $width, '  ');
        foreach (array_filter($details, fn ($d) => $d !== null && trim(Text::strip($d)) !== '') as $detail) {
            $out .= "\n".(str_starts_with(Text::strip($detail), '$ ')
                ? Text::indent(Text::breakLines($detail, $width - 2), '  ')
                : Text::wrap($detail, $width, '  ', '  '));
        }

        return $out;
    }

    /**
     * A titled group of entries separated by blank lines ('' when empty).
     *
     * @param  list<string|null>  $entries
     */
    protected function group(?string $label, array $entries, int $width): string
    {
        $entries = array_values(array_filter($entries, fn ($e) => $e !== null && trim(Text::strip($e)) !== ''));
        if ($entries === []) {
            return '';
        }

        return ($label !== null ? $this->layout->section($label, $width)."\n\n" : '').implode("\n\n", $entries);
    }

    /** `·` separated meta line from non-empty parts. */
    protected function meta(array $parts): string
    {
        $parts = array_values(array_filter($parts, fn ($p) => $p !== null && $p !== ''));

        return implode($this->ansi->slate(' · '), $parts);
    }

    protected function date(?DateTimeInterface $date, string $format = 'M j, Y'): ?string
    {
        return $date?->format($format);
    }

    /** @param  list<string>  $chunks */
    protected function stack(array $chunks): string
    {
        return implode("\n\n", array_filter($chunks, fn ($c) => $c !== null && trim(Text::strip($c)) !== ''));
    }

    protected function money(int $minor, string $currency): string
    {
        $symbols = ['USD' => '$', 'CAD' => 'CA$', 'AUD' => 'A$', 'EUR' => '€', 'GBP' => '£'];
        $amount = number_format($minor / 100, 2);

        return isset($symbols[strtoupper($currency)]) ? $symbols[strtoupper($currency)].$amount : $amount.' '.strtoupper($currency);
    }
}
