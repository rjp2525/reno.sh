<?php

namespace App\Http\Controllers;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use Inertia\Inertia;

class PhotoShowController extends Controller
{
    public function __invoke(Photo $photo)
    {
        if (! $photo->is_published || $photo->processing_status !== PhotoProcessingStatus::COMPLETED) {
            abort(404);
        }

        $photo->load(['category', 'tags']);

        $prevPhoto = Photo::published()
            ->processed()
            ->where(function ($query) use ($photo) {
                $query->where('sort_order', '<', $photo->sort_order)
                    ->orWhere(function ($q) use ($photo) {
                        $q->where('sort_order', $photo->sort_order)
                            ->where('created_at', '>', $photo->created_at);
                    });
            })
            ->orderByDesc('sort_order')
            ->orderBy('created_at')
            ->first(['slug', 'title']);

        $nextPhoto = Photo::published()
            ->processed()
            ->where(function ($query) use ($photo) {
                $query->where('sort_order', '>', $photo->sort_order)
                    ->orWhere(function ($q) use ($photo) {
                        $q->where('sort_order', $photo->sort_order)
                            ->where('created_at', '<', $photo->created_at);
                    });
            })
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->first(['slug', 'title']);

        return Inertia::render('PhotoShow', [
            'photo' => $this->transformPhoto($photo),
            'prevPhoto' => $prevPhoto ? ['slug' => $prevPhoto->slug, 'title' => $prevPhoto->title] : null,
            'nextPhoto' => $nextPhoto ? ['slug' => $nextPhoto->slug, 'title' => $nextPhoto->title] : null,
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
                : 1.5,
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
            'gps_latitude' => $photo->gps_latitude,
            'gps_longitude' => $photo->gps_longitude,
        ];
    }
}
