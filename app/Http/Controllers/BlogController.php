<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Tags\Tag;

class BlogController extends Controller
{
    public function __invoke(Request $request, ?BlogCategory $category = null)
    {
        $query = Post::published()
            ->with(['category', 'tags', 'series'])
            ->ordered();

        if ($category) {
            $query->byCategory($category->id);
        }

        $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->get('search');
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhereRelation('tags', 'name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('tags'), function ($q) use ($request) {
                $tags = is_array($request->get('tags'))
                    ? $request->get('tags')
                    : explode(',', $request->get('tags'));

                $q->withAnyTags($tags);
            })
            ->when($request->boolean('featured'), fn ($q) => $q->featured());

        $posts = $query->paginate(12)->withQueryString();

        $posts->getCollection()->transform(function (Post $post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'featured_image' => $post->featured_image,
                'reading_time' => $post->reading_time,
                'published_at' => $post->published_at?->toIso8601String(),
                'is_featured' => $post->is_featured,
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
            ];
        });

        $categories = BlogCategory::active()
            ->ordered()
            ->withCount(['posts' => function ($query) {
                $query->published();
            }])
            ->get()
            ->map(fn (BlogCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'color' => $cat->color,
                'post_count' => $cat->posts_count,
            ]);

        $publishedPostIds = Post::published()->pluck('id');

        $allTags = Tag::whereExists(function ($query) use ($publishedPostIds) {
            $query->from('taggables')
                ->whereColumn('taggables.tag_id', 'tags.id')
                ->where('taggables.taggable_type', Post::class)
                ->whereIn('taggables.taggable_id', $publishedPostIds);
        })
            ->orderBy('name->en')
            ->get()
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name['en'] ?? $tag->name,
                'slug' => $tag->slug['en'] ?? $tag->slug,
            ]);

        return Inertia::render('Blog', [
            'posts' => $posts,
            'currentCategory' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'description' => $category->description,
            ] : null,
            'filters' => [
                'search' => $request->get('search'),
                'tags' => $request->get('tags'),
                'featured' => $request->boolean('featured'),
            ],
            'filterOptions' => [
                'categories' => $categories,
                'tags' => $allTags,
            ],
        ]);
    }
}
