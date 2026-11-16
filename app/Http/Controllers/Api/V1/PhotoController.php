<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\PhotoCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PhotoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PhotoResource::collection(Photo::published()->processed()
            ->with(['category', 'tags'])
            ->when($request->query('category'), fn ($q, $slug) => $q->byCategory($slug))
            ->when($request->boolean('favorites'), fn ($q) => $q->favorite())
            ->when($request->boolean('printable'), fn ($q) => $q->where('prints_enabled', true))
            ->ordered()
            ->paginate($this->perPage($request)));
    }

    public function show(string $slug): PhotoResource
    {
        $photo = Photo::published()->processed()->with(['category', 'tags'])->where('slug', $slug)->firstOrFail();

        return (new PhotoResource($photo))->withPrintOptions();
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => PhotoCategory::query()->active()->ordered()
            ->withCount(['photos' => fn ($q) => $q->published()->processed()])
            ->get()
            ->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name, 'slug' => $c->slug, 'color' => $c->color, 'photos_count' => $c->photos_count])]);
    }
}
