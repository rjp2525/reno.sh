<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\BlogSeries;
use App\Models\Post;
use App\Terminal\Context;

final class SeriesPage extends Page
{
    public function __construct(Context $ctx, private readonly BlogSeries $series)
    {
        parent::__construct($ctx);
    }

    public function section(): ?string
    {
        return 'blog';
    }

    protected function body(int $width): string
    {
        $posts = $this->series->posts()->published()->with('category')->get();

        $entries = $posts->values()->map(fn (Post $post, int $i) => $this->entry(
            $this->ansi->style($post->title, 'white', 'bold'),
            [
                $this->ansi->slate($this->meta([
                    $this->date($post->published_at),
                    $post->reading_time ? $post->reading_time.' min read' : null,
                    $post->category?->name,
                ])),
                $post->excerpt ? $this->ansi->slate($post->excerpt) : null,
                $this->prompt($this->ctx->curl(PostPage::path($post))),
            ],
            $width,
            ($i + 1).'.',
            'orange',
        ))->all();

        return $this->stack([
            $this->title('_blog / series / '.$this->series->slug, $width, $posts->count().' '.str('part')->plural($posts->count())),
            $this->paragraph($this->series->title, $width, 'white', 'bold'),
            $this->paragraph($this->series->description, $width, 'slate', 'italic'),
            $entries === [] ? $this->comment('no published posts in this series yet', $width) : $this->group('posts', $entries, $width),
            $this->command($this->ctx->curl('/blog'), $width),
        ]);
    }
}
