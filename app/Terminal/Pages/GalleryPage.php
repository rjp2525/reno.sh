<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Terminal\Text;

final class GalleryPage extends Page
{
    private const PER_PAGE = 24;

    public function section(): ?string
    {
        return 'photography';
    }

    protected function body(int $width): string
    {
        $category = $this->ctx->query('category');
        $category = is_string($category) && $category !== '' ? $category : null;
        $favorites = filter_var($this->ctx->query('favorites'), FILTER_VALIDATE_BOOLEAN);

        $photos = Photo::published()
            ->processed()
            ->with('category')
            ->when($category, fn ($q) => $q->byCategory($category))
            ->when($favorites, fn ($q) => $q->favorite())
            ->ordered()
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, (int) $this->ctx->query('page', 1)));

        $entries = $photos->getCollection()->map(fn (Photo $photo) => $this->entry(
            $this->ansi->style($photo->title, 'white', 'bold').($photo->is_favorite ? $this->ansi->orange(' ★') : ''),
            [
                $this->ansi->slate($this->meta([$photo->category?->name, $this->date($photo->taken_at)])),
                $photo->exif_summary ? $this->ansi->primary($photo->exif_summary) : null,
                $this->prompt($this->ctx->curl('/gallery/'.$photo->slug)),
            ],
            $width,
        ))->all();

        $categories = PhotoCategory::active()
            ->ordered()
            ->withCount(['photos' => fn ($q) => $q->published()->processed()])
            ->get()
            ->filter(fn ($c) => $c->photos_count > 0);
        $example = $categories->first()?->slug;
        $categories = $categories->map(fn ($c) => ($c->slug === $category ? $this->ansi->style($c->slug, 'orange', 'bold') : $this->ansi->white($c->slug)).$this->ansi->slate(" ({$c->photos_count})"));

        $filter = $categories->isEmpty() ? '' : $this->layout->section('categories', $width)."\n\n"
            .Text::wrap($categories->implode($this->ansi->slate(' · ')), $width)."\n"
            .$this->command($this->ctx->curl('/gallery', ['category' => $example]), $width);

        $more = $photos->hasMorePages()
            ? $this->comment('more photos:', $width)."\n".$this->command($this->ctx->curl('/gallery', ['category' => $category, 'page' => $photos->currentPage() + 1]), $width)
            : '';

        $subtitle = $photos->total().' '.str('photo')->plural($photos->total())
            .($category ? " in {$category}" : '')
            .($photos->lastPage() > 1 ? " · page {$photos->currentPage()} of {$photos->lastPage()}" : '');

        return $this->stack([
            $this->title('_photography', $width, $subtitle),
            $filter,
            $entries === [] ? $this->comment('no photos here yet', $width) : $this->group('photos', $entries, $width),
            $more,
        ]);
    }
}
