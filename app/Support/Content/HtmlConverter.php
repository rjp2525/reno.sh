<?php

declare(strict_types=1);

namespace App\Support\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts the (trusted, editor-produced) HTML in rich-text blocks to Markdown
 * or to plain text. Deliberately small: it covers what Filament's RichEditor
 * and CommonMark emit, not arbitrary web pages.
 */
final class HtmlConverter
{
    public static function toMarkdown(?string $html): string
    {
        $root = self::parse($html);

        return $root ? self::tidy(self::blockChildren($root, 'md')) : '';
    }

    public static function toText(?string $html): string
    {
        $root = self::parse($html);

        return $root ? self::tidy(self::blockChildren($root, 'text')) : '';
    }

    private static function parse(?string $html): ?DOMNode
    {
        if (blank($html)) {
            return null;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc->getElementById('__root');
    }

    /** Render the children of a block container, separating block elements with blank lines. */
    private static function blockChildren(DOMNode $node, string $mode, int $depth = 0): string
    {
        $out = '';
        $inline = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && self::isBlock($child)) {
                if (trim($inline) !== '') {
                    $out .= trim($inline)."\n\n";
                }
                $inline = '';
                $out .= self::block($child, $mode, $depth)."\n\n";
            } else {
                $inline .= self::inline($child, $mode);
            }
        }

        if (trim($inline) !== '') {
            $out .= trim($inline)."\n\n";
        }

        return $out;
    }

    private static function isBlock(DOMElement $el): bool
    {
        return in_array(strtolower($el->tagName), [
            'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'pre', 'hr', 'figure', 'table', 'li',
        ], true);
    }

    private static function block(DOMElement $el, string $mode, int $depth): string
    {
        $tag = strtolower($el->tagName);
        $md = $mode === 'md';

        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => ($md ? str_repeat('#', (int) $tag[1]).' ' : '').trim(self::inlineChildren($el, $mode)),
            'p', 'div', 'figure' => trim(self::blockChildren($el, $mode, $depth)),
            'hr' => $md ? '---' : str_repeat('─', 24),
            'pre' => $md
                ? '```'.self::codeLanguage($el)."\n".rtrim($el->textContent)."\n```"
                : rtrim($el->textContent),
            'blockquote' => collect(explode("\n", trim(self::blockChildren($el, $mode, $depth))))
                ->map(fn ($line) => rtrim('> '.$line))
                ->implode("\n"),
            'ul', 'ol' => self::listBlock($el, $mode, $depth),
            'table' => trim($el->textContent),
            default => trim(self::blockChildren($el, $mode, $depth)),
        };
    }

    private static function listBlock(DOMElement $list, string $mode, int $depth): string
    {
        $ordered = strtolower($list->tagName) === 'ol';
        $lines = [];
        $n = (int) ($list->getAttribute('start') ?: 1);

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }

            $marker = $ordered ? ($n++).'.' : ($mode === 'md' ? '-' : '•');
            $indent = str_repeat('  ', $depth);
            $body = '';
            $nested = '';

            foreach ($item->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                    $nested .= "\n".self::listBlock($child, $mode, $depth + 1);
                } elseif ($child instanceof DOMElement && strtolower($child->tagName) === 'p') {
                    $body .= ' '.self::inlineChildren($child, $mode);
                } else {
                    $body .= self::inline($child, $mode);
                }
            }

            $lines[] = $indent.$marker.' '.trim(preg_replace('/\s+/', ' ', $body) ?? '').$nested;
        }

        return implode("\n", $lines);
    }

    private static function inlineChildren(DOMNode $node, string $mode): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::inline($child, $mode);
        }

        return $out;
    }

    private static function inline(DOMNode $node, string $mode): string
    {
        if ($node instanceof DOMText) {
            return preg_replace('/\s+/', ' ', $node->textContent) ?? '';
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $md = $mode === 'md';
        $inner = fn () => self::inlineChildren($node, $mode);

        return match (strtolower($node->tagName)) {
            'br' => $md ? "\\\n" : "\n",
            'strong', 'b' => $md ? self::wrap($inner(), '**') : $inner(),
            'em', 'i' => $md ? self::wrap($inner(), '_') : $inner(),
            's', 'del', 'strike' => $md ? self::wrap($inner(), '~~') : $inner(),
            'code' => $md ? '`'.$node->textContent.'`' : $node->textContent,
            'a' => self::link($node, $inner(), $md),
            'img' => $md
                ? '!['.$node->getAttribute('alt').']('.$node->getAttribute('src').')'
                : '['.($node->getAttribute('alt') ?: 'image').': '.$node->getAttribute('src').']',
            default => $inner(),
        };
    }

    private static function link(DOMElement $a, string $text, bool $md): string
    {
        $href = $a->getAttribute('href');
        $text = trim($text);

        if ($href === '' || $href === $text) {
            return $md && $href !== '' ? '<'.$href.'>' : $text;
        }

        return $md ? '['.$text.']('.$href.')' : $text.' ('.$href.')';
    }

    private static function wrap(string $text, string $marker): string
    {
        // Keep surrounding whitespace outside the markers: "** bold**" is not bold.
        if (trim($text) === '') {
            return $text;
        }
        preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $m);

        return $m[1].$marker.$m[2].$marker.$m[3];
    }

    private static function codeLanguage(DOMElement $pre): string
    {
        $code = $pre->getElementsByTagName('code')->item(0);
        $class = $code instanceof DOMElement ? $code->getAttribute('class') : $pre->getAttribute('class');

        return preg_match('/language-([\w+-]+)/', $class, $m) ? $m[1] : '';
    }

    private static function tidy(string $text): string
    {
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
