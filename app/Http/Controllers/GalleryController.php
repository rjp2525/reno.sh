<?php

namespace App\Http\Controllers;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Models\PhotoCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Tags\Tag;

class GalleryController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Photo::published()
            ->processed()
            ->with(['category', 'tags'])
            ->ordered();

        $query
            ->when($request->filled('category'), fn ($q) => $q->byCategory($request->get('category')))
            ->when($request->filled('tags'), function ($q) use ($request) {
                $tags = is_array($request->get('tags'))
                    ? $request->get('tags')
                    : explode(',', $request->get('tags'));

                $q->withAnyTags($tags);
            })
            ->when($request->boolean('favorites'), fn ($q) => $q->favorite());

        $photos = $query->paginate(24)->withQueryString();

        $photos->getCollection()->transform(function (Photo $photo) {
            return $this->transformPhoto($photo);
        });

        $totalPhotos = Photo::published()
            ->where('processing_status', PhotoProcessingStatus::COMPLETED)
            ->count();

        $categories = PhotoCategory::active()
            ->ordered()
            ->withCount(['photos' => fn ($q) => $q->published()->where('processing_status', PhotoProcessingStatus::COMPLETED)])
            ->get()
            ->filter(fn ($cat) => $cat->photos_count > 0)
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'color' => $cat->color,
                'photo_count' => $cat->photos_count,
            ])
            ->values();

        $photoTagIds = Photo::published()
            ->where('processing_status', PhotoProcessingStatus::COMPLETED)
            ->with('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->pluck('id')
            ->unique();

        $allTags = Tag::whereIn('id', $photoTagIds)
            ->orderBy('name->en')
            ->get()
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name['en'] ?? $tag->name,
                'slug' => $tag->slug['en'] ?? $tag->slug,
            ]);

        return Inertia::render('Gallery', [
            'photos' => $photos,
            'filters' => [
                'category' => $request->get('category'),
                'tags' => $request->get('tags'),
                'favorites' => $request->boolean('favorites'),
            ],
            'filterOptions' => [
                'categories' => $categories,
                'tags' => $allTags,
                'totalPhotos' => $totalPhotos,
            ],
        ]);
    }

    private function transformPhoto(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'title' => $photo->title,
            'slug' => $photo->slug,
            'description' => $photo->description,
            'thumbnail_url' => $photo->thumbnail_url,
            'web_url' => $photo->web_url,
            'og_image_url' => $photo->og_image_url,
            'width' => $photo->width,
            'height' => $photo->height,
            'aspect_ratio' => $photo->width && $photo->height
                ? round($photo->width / $photo->height, 3)
                : 1.5, // default to 3:2 aspect ratio
            'category' => $photo->category ? [
                'id' => $photo->category->id,
                'name' => $photo->category->name,
                'slug' => $photo->category->slug,
                'color' => $photo->category->color,
            ] : null,
            'tags' => $photo->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name['en'] ?? $tag->name,
                'slug' => $tag->slug['en'] ?? $tag->slug,
            ]),
            'exif' => [
                'camera' => $photo->camera_body,
                'lens' => $photo->lens,
                'focal_length' => $photo->focal_length,
                'aperture' => $photo->aperture,
                'shutter_speed' => $photo->shutter_speed,
                'iso' => $photo->iso,
                'summary' => $photo->exif_summary,
            ],
            'taken_at' => $photo->taken_at?->format('M j, Y'),
            'is_favorite' => $photo->is_favorite,
            'instagram_link' => $photo->instagram_link,
        ];
    }
}
