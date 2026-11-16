<?php

declare(strict_types=1);

namespace App\Terminal;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders the content block arrays stored on posts and content pages
 * (see docs/api.md "Rich content").
 */
final class BlockRenderer
{
    private const CALLOUTS = [
        'info' => ['i', 'blue'],
        'note' => ['✎', 'slate'],
        'tip' => ['★', 'primary'],
        'success' => ['✓', 'primary'],
        'warning' => ['!', 'orange'],
        'danger' => ['✗', 'pink'],
    ];

    private readonly Layout $layout;

    public function __construct(private readonly Ansi $ansi, private readonly HtmlConverter $html)
    {
        $this->layout = new Layout($ansi);
    }

    /**
     * @param  array<int, array{type?: string, data?: array<string, mixed>}>|null  $blocks
     */
    public function render(?array $blocks, int $width): string
    {
        $chunks = [];
        foreach ($blocks ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }
            $chunk = $this->block((string) ($block['type'] ?? ''), (array) ($block['data'] ?? []), $width);
            if (trim(Text::strip($chunk)) !== '') {
                $chunks[] = $chunk;
            }
        }

        return implode("\n\n", $chunks);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function block(string $type, array $data, int $width): string
    {
        return match ($type) {
            'rich-text', 'markdown' => $this->richText($data, $width),
            'heading' => $this->heading($data, $width),
            'two-columns' => $this->columns((string) ($data['left'] ?? ''), (string) ($data['right'] ?? ''), $width),
            'callout' => $this->callout($data, $width),
            'code' => $this->layout->code((string) ($data['code'] ?? ''), $width, $data['language'] ?? null, $data['filename'] ?? null),
            'image' => $this->image($data, $width),
            'image-gallery' => $this->gallery((array) ($data['images'] ?? []), $width),
            'divider' => $this->ansi->line(Text::pad('· · ·', $width, 'center')),
            default => '',
        };
    }

    /** @param  array<string, mixed>  $data */
    private function richText(array $data, int $width): string
    {
        $html = (string) ($data['content'] ?? '');
        if (trim($html) === '' && filled($data['markdown'] ?? null)) {
            $html = Str::markdown((string) $data['markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        }

        return $this->html->convert($html, $width);
    }

    /** @param  array<string, mixed>  $data */
    private function heading(array $data, int $width): string
    {
        $level = (int) ltrim((string) ($data['level'] ?? 'h2'), 'hH') ?: 2;
        $out = $this->html->heading($level, (string) ($data['title'] ?? ''), $width);

        if (filled($data['subtitle'] ?? null)) {
            $out .= "\n".Text::wrap($this->ansi->style((string) $data['subtitle'], 'slate', 'italic'), $width);
        }

        return $out;
    }

    private function columns(string $left, string $right, int $width): string
    {
        if ($width < 100) {
            return implode("\n\n", array_filter([$this->html->convert($left, $width), $this->html->convert($right, $width)]));
        }

        $col = intdiv($width - 3, 2);
        $l = explode("\n", $this->html->convert($left, $col));
        $r = explode("\n", $this->html->convert($right, $col));
        $sep = $this->ansi->line(' │ ');

        $out = [];
        for ($i = 0, $n = max(count($l), count($r)); $i < $n; $i++) {
            $out[] = rtrim(Text::pad($l[$i] ?? '', $col).$sep.($r[$i] ?? ''));
        }

        return implode("\n", $out);
    }

    /** @param  array<string, mixed>  $data */
    private function callout(array $data, int $width): string
    {
        $type = strtolower((string) ($data['type'] ?? 'info'));
        [$icon, $color] = self::CALLOUTS[$type] ?? self::CALLOUTS['info'];
        $bar = $this->ansi->style('┃', $color).' ';

        $title = $this->ansi->style($icon.' '.($data['title'] ?? $type), $color, 'bold');
        $body = $this->html->convert((string) ($data['content'] ?? ''), max(1, $width - 2));

        return Text::indent(Text::wrap($title, max(1, $width - 2)).($body !== '' ? "\n".$body : ''), $bar);
    }

    /** @param  array<string, mixed>  $data */
    private function image(array $data, int $width): string
    {
        $url = self::url($data['image'] ?? $data['url'] ?? null);
        $label = trim((string) ($data['alt'] ?? '')) ?: 'image';

        $out = Text::wrap($this->ansi->style('▣ ', 'purple').$this->ansi->style($label, 'purple'), $width, '  ');
        if (filled($data['caption'] ?? null)) {
            $out .= "\n".Text::wrap($this->ansi->style((string) $data['caption'], 'slate', 'italic'), $width, '  ', '  ');
        }
        if ($url !== null) {
            $out .= "\n".Text::wrap($this->ansi->slate($url), $width, '  ', '  ');
        }

        return $out;
    }

    /** @param  array<int, mixed>  $images */
    private function gallery(array $images, int $width): string
    {
        $out = [];
        foreach ($images as $image) {
            if (is_array($image)) {
                $out[] = $this->image($image, $width);
            } elseif (is_string($image)) {
                $out[] = $this->image(['image' => $image], $width);
            }
        }

        return implode("\n", $out);
    }

    public static function url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return str_starts_with($path, '/') ? url($path) : Storage::disk('public')->url($path);
    }
}
