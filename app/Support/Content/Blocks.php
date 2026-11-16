<?php

declare(strict_types=1);

namespace App\Support\Content;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Helpers for the block arrays stored on posts, projects and content pages.
 * See docs/api.md "Rich content" for the shapes.
 */
final class Blocks
{
    /** Safe GitHub-flavored Markdown: raw HTML is stripped, javascript: links are dropped. */
    public static function markdownToHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    /** A single block holding a Markdown document. */
    public static function fromMarkdown(string $markdown): array
    {
        return [['type' => 'markdown', 'data' => ['markdown' => $markdown, 'content' => self::markdownToHtml($markdown)]]];
    }

    /**
     * Prepare blocks for storage: (re)render Markdown blocks to HTML so every
     * consumer (Vue, API, terminal) can rely on data.content being present.
     */
    public static function normalize(?array $blocks): array
    {
        return collect($blocks ?? [])
            ->filter(fn ($block) => is_array($block) && isset($block['type']))
            ->map(function (array $block) {
                $block['data'] ??= [];

                if ($block['type'] === 'markdown') {
                    $block['data']['markdown'] = (string) ($block['data']['markdown'] ?? '');
                    $block['data']['content'] = self::markdownToHtml($block['data']['markdown']);
                }

                return $block;
            })
            ->values()
            ->all();
    }

    /** HTML-bearing fields per block type (rich text the site renders unescaped). */
    private const HTML_FIELDS = [
        'rich-text' => ['content'],
        'callout' => ['content'],
        'two-columns' => ['left', 'right'],
    ];

    /**
     * Blocks received over the API (e.g. from the iOS editor): normalize, restrict rich-text HTML
     * to the tags the web editor produces, and turn image URLs on our public disk back into paths
     * so they are stored the same way as uploads from the admin panel.
     */
    public static function fromApi(?array $blocks): array
    {
        return collect(self::normalize($blocks))->map(function (array $block) {
            $data = $block['data'];

            foreach (self::HTML_FIELDS[$block['type']] ?? [] as $field) {
                if (is_string($data[$field] ?? null)) {
                    $data[$field] = self::sanitizeHtml($data[$field]);
                }
            }

            if ($block['type'] === 'image' && is_string($data['image'] ?? null)) {
                $data['image'] = self::path($data['image']);
            }

            if ($block['type'] === 'image-gallery') {
                $data['images'] = collect($data['images'] ?? [])
                    ->filter(fn ($img) => is_array($img))
                    ->map(fn (array $img) => [...$img, 'image' => is_string($img['image'] ?? null) ? self::path($img['image']) : null])
                    ->values()->all();
            }

            return [...$block, 'data' => $data];
        })->all();
    }

    /** Allow-list matching the Filament rich editor toolbar (headings, lists, quotes, code, links, basic marks). */
    public static function sanitizeHtml(string $html): string
    {
        static $sanitizer;

        $sanitizer ??= new HtmlSanitizer((new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('hr')
            ->allowElement('h2')->allowElement('h3')->allowElement('h4')
            ->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')
            ->allowElement('u')->allowElement('s')->allowElement('del')
            ->allowElement('ul')->allowElement('ol', ['start'])->allowElement('li')
            ->allowElement('blockquote')->allowElement('pre')->allowElement('code', ['class'])
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withMaxInputLength(200_000));

        return $sanitizer->sanitize($html);
    }

    /** Inverse of url() for files on the public disk; anything else is returned unchanged. */
    public static function path(string $value): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return Str::startsWith($value, $base) ? Str::after($value, $base) : $value;
    }

    /** Copy of the blocks with stored image paths turned into absolute URLs. */
    public static function withUrls(?array $blocks): array
    {
        return collect($blocks ?? [])->map(function (array $block) {
            $data = $block['data'] ?? [];

            if (($block['type'] ?? null) === 'image' && filled($data['image'] ?? null)) {
                $data['image'] = self::url($data['image']);
            }

            if (($block['type'] ?? null) === 'image-gallery') {
                $data['images'] = collect($data['images'] ?? [])
                    ->map(fn ($img) => [...$img, 'image' => self::url($img['image'] ?? null)])
                    ->all();
            }

            return ['type' => $block['type'] ?? 'unknown', 'data' => (object) $data];
        })->all();
    }

    public static function toMarkdown(?array $blocks): string
    {
        return self::render($blocks, 'md');
    }

    public static function toText(?array $blocks): string
    {
        return self::render($blocks, 'text');
    }

    /** Was this content authored as a single Markdown document (and so is round-trippable as Markdown)? */
    public static function isMarkdownDocument(?array $blocks): bool
    {
        return $blocks !== null && count($blocks) === 1 && ($blocks[0]['type'] ?? null) === 'markdown';
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://']) ? $path : Storage::disk('public')->url($path);
    }

    private static function render(?array $blocks, string $mode): string
    {
        $md = $mode === 'md';
        $html = fn (?string $h) => $md ? HtmlConverter::toMarkdown($h) : HtmlConverter::toText($h);

        return collect($blocks ?? [])->map(function (array $block) use ($md, $html) {
            $d = $block['data'] ?? [];

            return match ($block['type'] ?? null) {
                'markdown' => $md ? trim((string) ($d['markdown'] ?? '')) : HtmlConverter::toText($d['content'] ?? self::markdownToHtml((string) ($d['markdown'] ?? ''))),
                'rich-text' => filled($d['markdown'] ?? null) && $md ? trim($d['markdown']) : $html($d['content'] ?? ''),
                'heading' => trim(($md ? (($d['level'] ?? 'h2') === 'h3' ? '### ' : '## ') : '').($d['title'] ?? '')
                    .(filled($d['subtitle'] ?? null) ? "\n\n".($md ? '_'.$d['subtitle'].'_' : $d['subtitle']) : '')),
                'two-columns' => trim($html($d['left'] ?? '')."\n\n".$html($d['right'] ?? '')),
                'callout' => self::callout($d, $md, $html),
                'code' => $md
                    ? '```'.($d['language'] ?? '')."\n".rtrim((string) ($d['code'] ?? ''))."\n```"
                    : rtrim((string) ($d['code'] ?? '')),
                'image' => self::image($d, $md),
                'image-gallery' => collect($d['images'] ?? [])->map(fn ($img) => self::image($img, $md))->implode("\n"),
                'divider' => $md ? '---' : str_repeat('─', 24),
                default => '',
            };
        })->filter(fn ($s) => trim($s) !== '')->implode("\n\n");
    }

    private static function callout(array $d, bool $md, callable $html): string
    {
        $title = strtoupper((string) ($d['type'] ?? 'note')).(filled($d['title'] ?? null) ? ': '.$d['title'] : '');
        $body = $html($d['content'] ?? '');

        if (! $md) {
            return $title."\n".$body;
        }

        return collect(explode("\n", '**'.$title.'**'."\n\n".$body))->map(fn ($l) => rtrim('> '.$l))->implode("\n");
    }

    private static function image(array $d, bool $md): string
    {
        $url = self::url($d['image'] ?? null);
        if (! $url) {
            return '';
        }
        $alt = $d['alt'] ?? $d['caption'] ?? '';

        return $md
            ? '!['.$alt.']('.$url.')'.(filled($d['caption'] ?? null) ? "\n_".$d['caption'].'_' : '')
            : '['.($alt ?: 'image').': '.$url.']';
    }
}
