<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\BlogCategory;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::published()
            ->with(['category', 'series', 'tags'])
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('series'), fn ($q, $slug) => $q->whereHas('series', fn ($s) => $s->where('slug', $slug)))
            ->when($request->query('tag'), fn ($q, $tag) => $q->withAnyTags([$tag]))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('title', "%{$term}%")
                ->orWhereLike('excerpt', "%{$term}%")))
            ->ordered()
            ->paginate($this->perPage($request));

        return PostResource::collection($posts);
    }

    public function show(string $slug): PostResource
    {
        $post = Post::published()->with(['category', 'series', 'tags'])->where('slug', $slug)->firstOrFail();

        return (new PostResource($post))->detailed();
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => BlogCategory::query()->active()->ordered()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->id, 'name' => $c->name, 'slug' => $c->slug,
                'description' => $c->description, 'color' => $c->color, 'posts_count' => $c->posts_count,
            ])]);
    }
}
