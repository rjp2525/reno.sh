<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\BlogCategory;
use App\Models\Post;
use App\Terminal\Context;
use App\Terminal\Text;

final class BlogPage extends Page
{
    private const PER_PAGE = 20;

    public function __construct(Context $ctx, private readonly ?BlogCategory $category = null)
    {
        parent::__construct($ctx);
    }

    public function section(): ?string
    {
        return 'blog';
    }

    protected function body(int $width): string
    {
        $search = $this->ctx->query('search') ?? $this->ctx->query('q');
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;

        $posts = Post::published()
            ->with(['category', 'series'])
            ->when($this->category, fn ($q) => $q->byCategory($this->category->id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%")))
            ->ordered()
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, (int) $this->ctx->query('page', 1)));

        $entries = $posts->getCollection()->map(fn (Post $post) => $this->entry(
            $this->ansi->style($post->title, 'white', 'bold').($post->is_featured ? $this->ansi->orange(' ★') : ''),
            [
                $this->ansi->slate($this->meta([
                    $this->date($post->published_at),
                    $post->reading_time ? $post->reading_time.' min read' : null,
                    $post->category ? $this->ansi->primary($post->category->name) : null,
                    $post->series ? 'series: '.$post->series->title : null,
                ])),
                $post->excerpt ? $this->ansi->slate($post->excerpt) : null,
                $this->prompt($this->ctx->curl(PostPage::path($post))),
            ],
            $width,
        ))->all();

        $path = $this->category ? '/blog/'.$this->category->slug : '/blog';
        $more = $posts->hasMorePages()
            ? $this->comment('older posts:', $width)."\n".$this->command($this->ctx->curl($path, ['search' => $search, 'page' => $posts->currentPage() + 1]), $width)
            : '';

        $subtitle = $this->category?->description
            ?: ($search ? "posts matching \"{$search}\"" : 'thoughts, tutorials and notes from the field');

        return $this->stack([
            $this->title('_blog'.($this->category ? ' / '.$this->category->slug : ''), $width, $subtitle),
            $this->categories($width),
            $entries === [] ? $this->comment('no posts here yet', $width) : $this->group(
                $posts->lastPage() > 1 ? "posts · page {$posts->currentPage()} of {$posts->lastPage()}" : 'posts',
                $entries,
                $width,
            ),
            $more,
        ]);
    }

    private function categories(int $width): string
    {
        $categories = BlogCategory::active()
            ->ordered()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->get()
            ->filter(fn (BlogCategory $c) => $c->posts_count > 0);

        if ($categories->isEmpty()) {
            return '';
        }

        $all = $this->category === null ? $this->ansi->style('all', 'orange', 'bold') : $this->ansi->white('all');
        $items = $categories->map(fn (BlogCategory $c) => ($this->category?->is($c) ? $this->ansi->style($c->slug, 'orange', 'bold') : $this->ansi->white($c->slug))
            .$this->ansi->slate(" ({$c->posts_count})"));

        return $this->layout->section('categories', $width)."\n\n"
            .Text::wrap(collect([$all])->merge($items)->implode($this->ansi->slate(' · ')), $width)."\n"
            .$this->command($this->ctx->curl('/blog/'.$categories->first()->slug), $width);
    }
}
