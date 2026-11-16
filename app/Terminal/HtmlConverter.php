<?php

declare(strict_types=1);

namespace App\Terminal;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text as TextNode;
use Illuminate\Support\Str;

/**
 * Converts rich-text HTML into wrapped, styled terminal text.
 *
 * Links become `text [n]` with a numbered list collected for the end of the
 * page, so one converter instance should be reused across all blocks of a
 * document to keep footnote numbers unique.
 */
final class HtmlConverter
{
    private const BLOCK_TAGS = [
        'p', 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'figure', 'figcaption',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'hr', 'table', 'details',
        'summary', 'dl', 'dt', 'dd',
    ];

    private const SKIP_TAGS = ['script', 'style', 'template', 'noscript', 'head', 'title', 'meta', 'link'];

    private const BULLETS = ['•', '◦', '▪'];

    /** @var array<string, int> url => footnote number */
    private array $links = [];

    private readonly Layout $layout;

    public function __construct(private readonly Ansi $ansi)
    {
        $this->layout = new Layout($ansi);
    }

    public function convert(?string $html, int $width): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>'.$html.'</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );

        return $this->blocks($doc->body, max(1, $width));
    }

    /**
     * @return array<string, int>
     */
    public function links(): array
    {
        return $this->links;
    }

    /** Numbered `[n] url` list for the links seen so far ('' when none). */
    public function footnotes(int $width): string
    {
        if ($this->links === []) {
            return '';
        }

        $numberWidth = strlen((string) count($this->links)) + 3;
        $out = [];
        foreach ($this->links as $url => $n) {
            $label = $this->ansi->slate(Text::pad("[{$n}]", $numberWidth));
            $out[] = Text::wrap($this->ansi->style($url, 'pink'), $width, str_repeat(' ', $numberWidth), $label);
        }

        return implode("\n", $out);
    }

    /** Footnote number for a URL, registering it if new. */
    public function footnote(string $url): int
    {
        return $this->links[$url] ??= count($this->links) + 1;
    }

    private function blocks(Node $parent, int $width, string $separator = "\n\n"): string
    {
        $chunks = [];
        $inline = '';

        $flush = function () use (&$inline, &$chunks, $width): void {
            $text = $this->tidy($inline);
            if (Text::strip($text) !== '' && trim(Text::strip($text)) !== '') {
                $chunks[] = Text::wrap($text, $width);
            }
            $inline = '';
        };

        foreach ($parent->childNodes as $child) {
            if ($child instanceof Element && $this->isBlock($child)) {
                $flush();
                $chunks[] = $this->block($child, $width);

                continue;
            }

            $inline .= $this->inline($child);
        }
        $flush();

        return implode($separator, array_filter($chunks, fn ($c) => $c !== ''));
    }

    private function isBlock(Element $el): bool
    {
        return in_array(strtolower($el->localName), self::BLOCK_TAGS, true);
    }

    private function block(Element $el, int $width): string
    {
        $tag = strtolower($el->localName);

        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->heading((int) $tag[1], $this->tidy($this->inlineChildren($el)), $width),
            'ul', 'ol' => $this->listBlock($el, $width, 0),
            'blockquote' => $this->quote($this->blocks($el, max(1, $width - 2)), $width),
            'pre' => $this->layout->code($el->textContent ?? '', $width, $this->codeLanguage($el)),
            'hr' => $this->layout->rule($width),
            'table' => $this->table($el, $width),
            'figcaption' => Text::wrap($this->ansi->style($this->tidy($this->inlineChildren($el)), 'slate', 'italic'), $width),
            'dt' => Text::wrap($this->ansi->bold($this->tidy($this->inlineChildren($el))), $width),
            'dd' => Text::indent($this->blocks($el, max(1, $width - 2), "\n"), '  '),
            'li' => $this->listBlock($el, $width, 0),
            default => $this->blocks($el, $width),
        };
    }

    public function heading(int $level, string $text, int $width): string
    {
        $text = trim($text);
        if (Text::strip($text) === '') {
            return '';
        }

        return match (true) {
            $level <= 1 => $this->underlined($this->ansi->style(mb_strtoupper(Text::strip($text)), 'primary', 'bold'), $width, '━'),
            $level === 2 => $this->underlined($this->ansi->style($text, 'primary', 'bold'), $width, '─'),
            $level === 3 => Text::wrap($this->ansi->style('▸ ', 'orange').$this->ansi->style($text, 'orange', 'bold'), $width, '  '),
            default => Text::wrap($this->ansi->style($text, 'white', 'bold'), $width),
        };
    }

    private function underlined(string $text, int $width, string $char): string
    {
        $wrapped = Text::wrap($text, $width);

        return $wrapped."\n".$this->layout->rule(min($width, Text::maxWidth($wrapped)), $char);
    }

    private function listBlock(Element $list, int $width, int $depth): string
    {
        $ordered = strtolower($list->localName) === 'ol';
        $n = $ordered && is_numeric($list->getAttribute('start')) ? (int) $list->getAttribute('start') : 1;

        $items = strtolower($list->localName) === 'li' ? [$list] : array_filter(
            iterator_to_array($list->childNodes),
            fn ($c) => $c instanceof Element && strtolower($c->localName) === 'li',
        );

        $out = [];
        foreach ($items as $item) {
            $marker = $ordered ? ($n++).'.' : self::BULLETS[$depth % count(self::BULLETS)];
            $markerWidth = Text::width($marker) + 1;
            $inner = max(1, $width - $markerWidth);

            $content = $this->listItem($item, $inner, $depth);
            $styledMarker = $this->ansi->style($marker, $ordered ? 'orange' : 'primary').' ';
            $out[] = Text::indent($content === '' ? '' : $content, str_repeat(' ', $markerWidth), $styledMarker);
        }

        return implode("\n", $out);
    }

    private function listItem(Element $item, int $width, int $depth): string
    {
        $chunks = [];
        $inline = '';
        $flush = function () use (&$inline, &$chunks, $width): void {
            $text = $this->tidy($inline);
            if (trim(Text::strip($text)) !== '') {
                $chunks[] = Text::wrap($text, $width);
            }
            $inline = '';
        };

        foreach ($item->childNodes as $child) {
            if ($child instanceof Element && in_array(strtolower($child->localName), ['ul', 'ol'], true)) {
                $flush();
                $chunks[] = $this->listBlock($child, $width, $depth + 1);
            } elseif ($child instanceof Element && $this->isBlock($child)) {
                $flush();
                $chunks[] = $this->block($child, $width);
            } else {
                $inline .= $this->inline($child);
            }
        }
        $flush();

        return implode("\n", array_filter($chunks, fn ($c) => $c !== ''));
    }

    private function quote(string $content, int $width): string
    {
        $bar = $this->ansi->style('│', 'slate').' ';

        return Text::indent($content, $bar, $bar);
    }

    private function table(Element $table, int $width): string
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            $isHeader = false;
            foreach ($tr->childNodes as $cell) {
                if (! $cell instanceof Element || ! in_array(strtolower($cell->localName), ['td', 'th'], true)) {
                    continue;
                }
                $isHeader = $isHeader || strtolower($cell->localName) === 'th';
                $cells[] = trim($this->tidy($this->inlineChildren($cell)));
            }
            if ($cells === []) {
                continue;
            }
            $line = implode($this->ansi->slate(' │ '), $cells);
            $rows[] = Text::wrap($isHeader ? $this->ansi->bold($line) : $line, $width, '  ');
        }

        return implode("\n", $rows);
    }

    private function inlineChildren(Node $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->inline($child);
        }

        return $out;
    }

    private function inline(Node $node): string
    {
        if ($node instanceof TextNode) {
            return (string) preg_replace('/\s+/u', ' ', $node->textContent ?? '');
        }

        if (! $node instanceof Element) {
            return '';
        }

        $tag = strtolower($node->localName);
        if (in_array($tag, self::SKIP_TAGS, true)) {
            return '';
        }

        if ($this->isBlock($node)) {
            // Block inside an inline context: keep it on its own line.
            return "\n".$this->inlineChildren($node)."\n";
        }

        $inner = fn () => $this->inlineChildren($node);

        return match ($tag) {
            'br' => "\n",
            'strong', 'b' => $this->ansi->style($inner(), 'bold', 'white'),
            'em', 'i', 'cite' => $this->ansi->style($inner(), 'italic'),
            'u', 'ins' => $this->ansi->style($inner(), 'underline'),
            's', 'del', 'strike' => $this->ansi->enabled() ? $this->ansi->style($inner(), 'strike', 'slate') : '~'.$inner().'~',
            'code', 'kbd', 'samp' => $this->ansi->enabled() ? $this->ansi->style($inner(), 'orange') : '`'.$inner().'`',
            'mark' => $this->ansi->style($inner(), 'orange', 'bold'),
            'a' => $this->link($node, $inner()),
            'img' => $this->image($node),
            'iframe', 'video', 'audio' => $this->embed($node),
            default => $inner(),
        };
    }

    private function link(Element $a, string $text): string
    {
        $href = trim((string) $a->getAttribute('href'));
        $plain = trim(Text::strip($text));

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return $text;
        }

        $url = $this->absolute($href);
        $styled = $this->ansi->style($text, 'pink', 'underline');

        if ($plain === '') {
            return $this->ansi->style($url, 'pink', 'underline');
        }

        $bare = fn (string $u) => rtrim((string) preg_replace('#^(https?://|mailto:)#i', '', $u), '/');
        if ($bare($plain) === $bare($url)) {
            return $styled;
        }

        return $styled.$this->ansi->slate('['.$this->footnote($url).']');
    }

    private function image(Element $img): string
    {
        $src = trim((string) $img->getAttribute('src'));
        $alt = trim((string) $img->getAttribute('alt')) ?: 'image';

        $label = $this->ansi->style('[image: '.$alt.']', 'purple');

        return $src === '' ? $label : $label.' '.$this->ansi->slate($this->absolute($src));
    }

    private function embed(Element $el): string
    {
        $src = trim((string) $el->getAttribute('src'));

        return $src === '' ? '' : $this->ansi->style('[embed]', 'purple').' '.$this->ansi->slate($this->absolute($src));
    }

    private function absolute(string $href): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) || str_starts_with($href, '//')) {
            return str_starts_with($href, '//') ? 'https:'.$href : $href;
        }

        return url($href);
    }

    private function codeLanguage(Element $pre): ?string
    {
        $classes = (string) $pre->getAttribute('class');
        foreach ($pre->childNodes as $child) {
            if ($child instanceof Element && strtolower($child->localName) === 'code') {
                $classes .= ' '.$child->getAttribute('class');
            }
        }

        return preg_match('/(?:language|lang)-([\w+#-]+)/', $classes, $m) ? $m[1] : null;
    }

    /** Collapse whitespace while keeping explicit line breaks. */
    private function tidy(string $text): string
    {
        $lines = array_map(
            fn ($line) => trim((string) preg_replace('/ {2,}/', ' ', $line)),
            explode("\n", $text),
        );

        return trim(implode("\n", $lines), "\n");
    }

    public static function plain(?string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::replace(['<br>', '</p>'], ' ', (string) $html)))));
    }
}
