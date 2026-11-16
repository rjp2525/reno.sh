<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogSeries;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Tags\Tag;

class TaxonomyController extends Controller
{
    public function blogCategories(): JsonResponse
    {
        return response()->json(['data' => BlogCategory::query()->ordered()->get(['id', 'name', 'slug', 'color'])
            ->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name, 'slug' => $c->slug, 'color' => $c->color])]);
    }

    public function blogSeries(): JsonResponse
    {
        return response()->json(['data' => BlogSeries::query()->ordered()->get(['id', 'title', 'slug'])
            ->map(fn ($s) => ['id' => (string) $s->id, 'title' => $s->title, 'slug' => $s->slug])]);
    }

    public function photoCategories(): JsonResponse
    {
        return response()->json(['data' => PhotoCategory::query()->ordered()->get(['id', 'name', 'slug'])
            ->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name, 'slug' => $c->slug])]);
    }

    /** Tag autocomplete, most used first. ?type=posts|photos&q= */
    public function tags(Request $request): JsonResponse
    {
        $model = match ($request->query('type')) {
            'photos' => Photo::class,
            'posts' => Post::class,
            default => null,
        };

        $tags = Tag::query()
            ->select('tags.*')
            ->join('taggables', 'taggables.tag_id', '=', 'tags.id')
            ->when($model, fn ($q) => $q->where('taggables.taggable_type', (new $model)->getMorphClass()))
            ->when($request->query('q'), fn ($q, $term) => $q->containing($term))
            ->groupBy('tags.id')
            ->orderByRaw('count(*) desc')
            ->limit(25)
            ->get();

        return response()->json(['data' => $tags->pluck('name')->unique()->values()]);
    }
}
