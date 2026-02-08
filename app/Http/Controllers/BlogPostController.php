<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\Post;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    public function __invoke(BlogCategory $category, Post $post): Response
    {
        abort_unless($post->blog_category_id === $category->id, 404);

        return $this->renderPost($post);
    }

    public function uncategorized(Post $post): Response
    {
        return $this->renderPost($post);
    }

    private function renderPost(Post $post): Response
    {
        $post->load(['category', 'tags', 'series' => function ($query) {
            $query->with(['posts' => function ($q) {
                $q->published()->ordered();
            }]);
        }]);

        $tableOfContents = collect($post->content ?? [])
            ->filter(fn ($block) => ($block['type'] ?? '') === 'heading')
            ->map(fn ($block) => [
                'title' => $block['data']['title'] ?? '',
                'level' => $block['data']['level'] ?? 'h2',
                'id' => Str::slug($block['data']['title'] ?? ''),
            ])
            ->values()
            ->all();

        $relatedPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->where(function ($query) use ($post) {
                $query->where('blog_category_id', $post->blog_category_id)
                    ->orWhereHas('tags', function ($tagQuery) use ($post) {
                        $tagQuery->whereIn('tags.id', $post->tags->pluck('id'));
                    });
            })
            ->with(['category', 'tags'])
            ->ordered()
            ->limit(3)
            ->get()
            ->map(fn (Post $related) => [
                'id' => $related->id,
                'title' => $related->title,
                'slug' => $related->slug,
                'excerpt' => $related->excerpt,
                'featured_image' => $related->featured_image,
                'reading_time' => $related->reading_time,
                'published_at' => $related->published_at?->toIso8601String(),
                'category' => $related->category ? [
                    'name' => $related->category->name,
                    'slug' => $related->category->slug,
                    'color' => $related->category->color,
                ] : null,
            ]);

        $seriesPosts = null;
        if ($post->series) {
            $seriesPosts = $post->series->posts
                ->map(fn (Post $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'series_order' => $p->series_order,
                    'is_current' => $p->id === $post->id,
                    'category_slug' => $p->category?->slug,
                ]);
        }

        $adjacentBase = Post::published()->where('id', '!=', $post->id);

        $olderPost = (clone $adjacentBase)
            ->where('published_at', '<', $post->published_at)
            ->orderBy('published_at', 'desc')
            ->first(['id', 'title', 'slug', 'blog_category_id']);

        $newerPost = (clone $adjacentBase)
            ->where('published_at', '>', $post->published_at)
            ->orderBy('published_at', 'asc')
            ->first(['id', 'title', 'slug', 'blog_category_id']);

        $formatAdjacent = function (?Post $p) {
            if (! $p) {
                return null;
            }
            $p->load('category:id,slug');

            return [
                'title' => $p->title,
                'slug' => $p->slug,
                'category_slug' => $p->category?->slug,
            ];
        };

        return Inertia::render('BlogPost', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'featured_image' => $post->featured_image,
                'reading_time' => $post->reading_time,
                'published_at' => $post->published_at?->toIso8601String(),
                'updated_at' => $post->updated_at?->toIso8601String(),
                'is_featured' => $post->is_featured,
                'meta_title' => $post->meta_title,
                'meta_description' => $post->meta_description,
                'meta_keywords' => $post->meta_keywords,
                'og_image' => $post->og_image_url,
                'category' => $post->category ? [
                    'name' => $post->category->name,
                    'slug' => $post->category->slug,
                    'color' => $post->category->color,
                ] : null,
                'tags' => $post->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name['en'] ?? $tag->name,
                    'slug' => $tag->slug['en'] ?? $tag->slug,
                ]),
                'series' => $post->series ? [
                    'title' => $post->series->title,
                    'slug' => $post->series->slug,
                ] : null,
            ],
            'tableOfContents' => $tableOfContents,
            'relatedPosts' => $relatedPosts,
            'seriesPosts' => $seriesPosts,
            'olderPost' => $formatAdjacent($olderPost),
            'newerPost' => $formatAdjacent($newerPost),
        ]);
    }
}
