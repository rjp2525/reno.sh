<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\Photo\PhotoImporter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PhotoResource::collection(Photo::with(['category', 'tags'])
            ->when($request->query('q'), fn ($q, $term) => $q->whereLike('title', "%{$term}%"))
            ->when($request->query('category'), fn ($q, $slug) => $q->byCategory($slug))
            ->when($request->query('status'), fn ($q, $s) => $q->where('processing_status', $s))
            ->latest()
            ->paginate($this->perPage($request)));
    }

    public function store(Request $request, PhotoImporter $importer): PhotoResource
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/tiff,image/heic,image/heif', 'max:102400'],
            ...$this->metadataRules(),
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $data['file'];
        $path = $file->storeAs(
            'uploads',
            Str::ulid()->toBase32().'.'.strtolower($file->guessExtension() ?: 'jpg'),
            config('photography.storage.originals_disk', 'photos_originals'),
        );

        $photo = $importer->import($path, Arr::except($data, ['file', 'tags']), $data['tags'] ?? [], $file->getClientOriginalName());

        return new PhotoResource($photo->load(['category', 'tags']));
    }

    public function show(Photo $photo): PhotoResource
    {
        return new PhotoResource($photo->load(['category', 'tags']));
    }

    public function update(Request $request, Photo $photo): PhotoResource
    {
        $data = $request->validate([...$this->metadataRules(), 'title' => ['sometimes', 'string', 'max:255']]);

        $photo->update(Arr::except($data, ['tags']));
        if (array_key_exists('tags', $data)) {
            $photo->syncTags($data['tags'] ?? []);
        }

        return new PhotoResource($photo->fresh(['category', 'tags']));
    }

    public function destroy(Photo $photo): Response
    {
        $photo->delete();

        return response()->noContent();
    }

    private function metadataRules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'photo_category_id' => ['sometimes', 'nullable', 'exists:photo_categories,id'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_published' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'prints_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
