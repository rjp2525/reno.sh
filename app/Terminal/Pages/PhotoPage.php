<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Photo;
use App\Models\PrintProduct;
use App\Terminal\Context;
use App\Terminal\Text;

final class PhotoPage extends Page
{
    public function __construct(Context $ctx, private readonly Photo $photo)
    {
        parent::__construct($ctx);
    }

    public function section(): ?string
    {
        return 'photography';
    }

    protected function body(int $width): string
    {
        $photo = $this->photo->loadMissing(['category', 'tags']);
        $webUrl = $this->ctx->url('/gallery/'.$photo->slug);

        $heading = Text::wrap($this->ansi->style($photo->title, 'white', 'bold'), $width)."\n"
            .Text::wrap($this->ansi->slate($this->meta([
                $photo->category?->name,
                $this->date($photo->taken_at),
                $photo->width && $photo->height ? "{$photo->width}×{$photo->height}" : null,
                $photo->is_favorite ? '★ favorite' : null,
            ])), $width);

        $exif = $this->layout->keyValue([
            'camera' => $photo->camera_body,
            'lens' => $photo->lens,
            'focal length' => $photo->focal_length,
            'aperture' => $photo->aperture,
            'shutter' => $photo->shutter_speed,
            'iso' => $photo->iso ? (string) $photo->iso : null,
        ], max(1, $width - 4), 'slate');

        $tags = $photo->tags->map(fn ($t) => '#'.($t->name['en'] ?? $t->name))->implode(' ');

        return $this->stack([
            $this->title('_photography / '.$photo->slug, $width),
            $heading,
            $this->richOrPlain($photo->description, $width),
            $exif !== '' ? $this->layout->box($exif, $width, $this->ansi->primary('exif')) : '',
            $this->view($photo, $width),
            $this->prints($photo, $webUrl, $width),
            $tags !== '' ? Text::wrap($this->ansi->slate($tags), $width) : '',
            $this->navigation($photo, $width),
        ]);
    }

    private function view(Photo $photo, int $width): string
    {
        $lines = [$this->layout->section('view', $width), ''];
        if ($photo->web_url) {
            $lines[] = $this->comment('full size image:', $width);
            $lines[] = Text::wrap($this->ansi->style($photo->web_url, 'pink', 'underline'), $width);
        }
        $lines[] = $this->comment('on the web:', $width);
        $lines[] = Text::wrap($this->ansi->style($this->ctx->url('/gallery/'.$photo->slug), 'pink', 'underline'), $width);
        if ($photo->instagram_link) {
            $lines[] = $this->comment('on instagram:', $width);
            $lines[] = Text::wrap($this->ansi->style($photo->instagram_link, 'pink', 'underline'), $width);
        }

        return implode("\n", $lines);
    }

    private function prints(Photo $photo, string $webUrl, int $width): string
    {
        $options = $photo->printOptions();
        if ($options->isEmpty()) {
            return '';
        }

        $nameWidth = (int) min(28, $options->max(fn (PrintProduct $p) => mb_strwidth($p->name)) + 2);
        $sizeWidth = (int) $options->max(fn (PrintProduct $p) => mb_strwidth($p->sizeLabel())) + 2;
        $prices = $options->map(fn (PrintProduct $p) => $this->money($p->price, $p->currency));
        $priceWidth = (int) $prices->max(fn ($p) => mb_strwidth($p));

        $rows = $options->values()->map(function (PrintProduct $product, int $i) use ($nameWidth, $sizeWidth, $priceWidth, $prices, $width) {
            if ($nameWidth + $sizeWidth + $priceWidth + 2 > $width) {
                // Narrow: name on one line, size and price below.
                return $this->entry($this->ansi->white($product->name), [
                    $this->ansi->slate($product->sizeLabel()).'  '.$this->ansi->orange($prices[$i]),
                ], $width, '•', 'primary');
            }

            return $this->ansi->primary('•').' '.Text::pad($this->ansi->white(Text::truncate($product->name, $nameWidth - 1)), $nameWidth)
                .Text::pad($this->ansi->slate($product->sizeLabel()), $sizeWidth)
                .Text::pad($this->ansi->orange($prices[$i]), $priceWidth, 'right');
        });

        $types = $options->map(fn (PrintProduct $p) => $p->type?->getLabel())->filter()->unique()->implode(', ');

        return $this->layout->section('prints', $width)."\n\n"
            .($types !== '' ? $this->comment('available as '.strtolower($types), $width)."\n" : '')
            .$rows->implode("\n")."\n\n"
            .$this->comment('order a print on the web:', $width)."\n"
            .Text::wrap($this->ansi->style($webUrl, 'pink', 'underline'), $width);
    }

    private function navigation(Photo $photo, int $width): string
    {
        $prev = Photo::published()->processed()
            ->where(fn ($q) => $q->where('sort_order', '<', $photo->sort_order)
                ->orWhere(fn ($q) => $q->where('sort_order', $photo->sort_order)->where('created_at', '>', $photo->created_at)))
            ->orderByDesc('sort_order')->orderBy('created_at')
            ->first(['slug', 'title']);

        $next = Photo::published()->processed()
            ->where(fn ($q) => $q->where('sort_order', '>', $photo->sort_order)
                ->orWhere(fn ($q) => $q->where('sort_order', $photo->sort_order)->where('created_at', '<', $photo->created_at)))
            ->orderBy('sort_order')->orderByDesc('created_at')
            ->first(['slug', 'title']);

        $entries = [];
        if ($prev) {
            $entries[] = $this->entry($this->ansi->slate('previous: ').$this->ansi->white($prev->title), [$this->prompt($this->ctx->curl('/gallery/'.$prev->slug))], $width, '←');
        }
        if ($next) {
            $entries[] = $this->entry($this->ansi->slate('next: ').$this->ansi->white($next->title), [$this->prompt($this->ctx->curl('/gallery/'.$next->slug))], $width, '→');
        }
        $entries[] = $this->entry($this->ansi->slate('back to the gallery'), [$this->prompt($this->ctx->curl('/gallery'))], $width, '↑');

        return $this->group('more photos', $entries, $width);
    }
}
