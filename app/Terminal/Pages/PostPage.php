<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Post;
use App\Terminal\Context;
use App\Terminal\Text;

final class PostPage extends Page
{
    public function __construct(Context $ctx, private readonly Post $post)
    {
        parent::__construct($ctx);
    }

    public function section(): ?string
    {
        return 'blog';
    }

    /** Web path of a post, matching routes/web.php. */
    public static function path(Post $post): string
    {
        return $post->category ? "/blog/{$post->category->slug}/{$post->slug}" : "/blog/post/{$post->slug}";
    }

    protected function body(int $width): string
    {
        $post = $this->post->loadMissing(['category', 'tags', 'series']);

        $meta = Text::wrap($this->ansi->slate($this->meta([
            $this->date($post->published_at),
            $post->reading_time ? $post->reading_time.' min read' : null,
            $post->category ? $this->ansi->primary($post->category->name) : null,
        ])), $width);

        $tags = $post->tags->map(fn ($t) => '#'.($t->name['en'] ?? $t->name))->implode(' ');

        $header = Text::wrap($this->ansi->style($post->title, 'white', 'bold'), $width)."\n"
            .$meta
            .($tags !== '' ? "\n".Text::wrap($this->ansi->slate($tags), $width) : '');

        return $this->stack([
            $this->title('_blog / '.($post->category?->slug ?? 'post'), $width),
            $header,
            $post->excerpt ? Text::wrap($this->ansi->style($post->excerpt, 'slate', 'italic'), $width) : '',
            $this->layout->rule($width),
            $this->blocks->render($post->content, $width),
            $this->series($post, $width),
            $this->adjacent($post, $width),
            $this->comment('read it on the web: '.$this->ctx->url(self::path($post)), $width),
        ]);
    }

    private function series(Post $post, int $width): string
    {
        if (! $post->series) {
            return '';
        }

        $posts = $post->series->posts()->published()->with('category')->get();
        if ($posts->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($posts->values() as $i => $p) {
            $current = $p->is($post);
            $label = ($i + 1).'. '.$p->title;
            $lines[] = Text::wrap($current ? $this->ansi->style($label.'  ← you are here', 'orange', 'bold') : $this->ansi->white($label), $width, '   ');
            if (! $current) {
                $lines[] = Text::indent($this->command($this->ctx->curl(self::path($p)), $width - 3), '   ');
            }
        }

        return $this->layout->section('series: '.$post->series->title, $width)."\n\n"
            .implode("\n", $lines)."\n"
            .$this->command($this->ctx->curl('/blog/series/'.$post->series->slug), $width);
    }

    private function adjacent(Post $post, int $width): string
    {
        $base = Post::published()->with('category')->where('id', '!=', $post->id);
        $older = (clone $base)->where('published_at', '<', $post->published_at)->orderByDesc('published_at')->first();
        $newer = (clone $base)->where('published_at', '>', $post->published_at)->orderBy('published_at')->first();

        $entries = [];
        if ($newer) {
            $entries[] = $this->entry($this->ansi->slate('newer: ').$this->ansi->white($newer->title), [$this->prompt($this->ctx->curl(self::path($newer)))], $width, '←');
        }
        if ($older) {
            $entries[] = $this->entry($this->ansi->slate('older: ').$this->ansi->white($older->title), [$this->prompt($this->ctx->curl(self::path($older)))], $width, '→');
        }
        $entries[] = $this->entry($this->ansi->slate('all posts'), [$this->prompt($this->ctx->curl('/blog'))], $width, '↑');

        return $this->group('keep reading', $entries, $width);
    }
}
