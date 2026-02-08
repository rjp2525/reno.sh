<?php

namespace App\Http\Controllers;

use App\Models\BlogSeries;
use Inertia\Inertia;

class BlogSeriesController extends Controller
{
    public function __invoke(BlogSeries $series)
    {
        $series->load(['posts' => function ($query) {
            $query->published()->with('category');
        }]);

        return Inertia::render('BlogSeries', [
            'series' => [
                'id' => $series->id,
                'title' => $series->title,
                'slug' => $series->slug,
                'description' => $series->description,
                'featured_image' => $series->featured_image,
                'posts' => $series->posts->map(fn ($post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt,
                    'reading_time' => $post->reading_time,
                    'published_at' => $post->published_at?->toIso8601String(),
                    'series_order' => $post->series_order,
                    'category' => $post->category ? [
                        'name' => $post->category->name,
                        'slug' => $post->category->slug,
                        'color' => $post->category->color,
                    ] : null,
                ]),
            ],
        ]);
    }
}
