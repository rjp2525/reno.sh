<?php

declare(strict_types=1);

namespace App\Services\Photo;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessPhotoJob;
use App\Models\Photo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an original already stored on the originals disk into a Photo:
 * reads EXIF, derives a title, and queues web/thumbnail/OG generation.
 * Used by the admin bulk uploader and the API.
 */
class PhotoImporter
{
    public function __construct(private readonly ExifExtractorService $exif) {}

    /**
     * @param  array<string, mixed>  $attributes  description, photo_category_id, is_published, is_favorite, prints_enabled, sort_order
     * @param  list<string>  $tags
     */
    public function import(string $path, array $attributes = [], array $tags = [], ?string $originalName = null): Photo
    {
        $disk = Storage::disk(config('photography.storage.originals_disk', 'photos_originals'));
        $local = $disk->path($path);
        $meta = is_file($local) ? $this->exif->extract($local) : [];

        $title = filled($attributes['title'] ?? null)
            ? $attributes['title']
            : $this->titleFrom($originalName ?? basename($path), $meta['taken_at'] ?? null);

        $photo = Photo::create([
            ...Arr::only($attributes, ['description', 'photo_category_id', 'is_published', 'is_favorite', 'prints_enabled', 'sort_order']),
            ...Arr::only($meta, ['iso', 'aperture', 'shutter_speed', 'focal_length', 'camera_body', 'lens', 'gps_latitude', 'gps_longitude', 'taken_at', 'width', 'height']),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'original_path' => $path,
            'file_size' => $disk->exists($path) ? $disk->size($path) : null,
            'processing_status' => PhotoProcessingStatus::PENDING,
        ]);

        if ($tags !== []) {
            $photo->syncTags($tags);
        }

        ProcessPhotoJob::dispatch($photo);

        return $photo;
    }

    /** "DSC_0042.jpg" is not a title: fall back to the capture date for camera-style names. */
    private function titleFrom(string $filename, mixed $takenAt): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/^(IMG|DSC|DSCF|_?DSC|PXL|P\d|GOPR|[0-9A-Z]{20,})[_-]?\d*/i', $name) && $takenAt) {
            return 'Untitled · '.Carbon::parse($takenAt)->format('M j, Y');
        }

        return Str::headline($name) ?: 'Untitled';
    }
}
