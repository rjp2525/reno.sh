<?php

declare(strict_types=1);

namespace App\Services\Photo;

use App\Images\Transformations\OgOverlay;
use App\Images\Transformations\StripMetadata;
use App\Images\Transformations\Watermark;
use App\Models\PhotoSettings;
use Illuminate\Image\Image as PendingImage;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the public versions of an uploaded photo with Laravel's native Image API:
 *
 *   web        watermarked, scaled to the max width, WebP
 *   thumbnail  cropped to the thumbnail size, WebP
 *   og         1200×630 social card, JPEG (WebP previews aren't supported everywhere)
 *
 * Public copies have EXIF/GPS stripped (colour profile kept) and are stored with a
 * one-year immutable Cache-Control, since every version gets a fresh random name.
 */
class ImageProcessorService
{
    /** Cache headers for generated files; names are random so they never change. */
    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function generateWebVersion(string $originalPath): ?string
    {
        return $this->store(
            $this->original($originalPath)
                ?->scale(width: PhotoSettings::getWebMaxWidth())
                ->transform(new Watermark)
                ->transform(new StripMetadata)
                ->optimize('webp', PhotoSettings::getWebQuality()),
            'photos/web',
            'webp',
        );
    }

    public function generateThumbnail(string $originalPath): ?string
    {
        return $this->store(
            $this->original($originalPath)
                ?->cover(PhotoSettings::getThumbnailWidth(), PhotoSettings::getThumbnailHeight())
                ->transform(new StripMetadata)
                ->optimize('webp', PhotoSettings::getThumbnailQuality()),
            'photos/thumbnails',
            'webp',
        );
    }

    public function generateOgImage(string $originalPath, ?string $title = null, ?string $exifSummary = null): ?string
    {
        if (! PhotoSettings::getOgImageEnabled()) {
            return null;
        }

        return $this->store(
            $this->original($originalPath)
                ?->cover(1200, 630)
                ->transform(new OgOverlay($title, $exifSummary))
                ->transform(new StripMetadata)
                ->optimize('jpg', 82),
            'photos/og',
            'jpg',
        );
    }

    public function deleteProcessedFiles(?string $webPath = null, ?string $thumbnailPath = null, ?string $ogImagePath = null): void
    {
        $disk = Storage::disk($this->publicDisk());

        foreach (array_filter([$webPath, $thumbnailPath, $ogImagePath]) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function original(string $path): ?PendingImage
    {
        $disk = config('photography.storage.originals_disk', 'photos_originals');

        return Storage::disk($disk)->exists($path) ? Image::fromStorage($path, $disk) : null;
    }

    private function store(?PendingImage $image, string $directory, string $extension): ?string
    {
        if (! $image) {
            return null;
        }

        $path = $image->storeAs($directory, Str::random(40).'.'.$extension, $this->publicDisk(), [
            'CacheControl' => self::CACHE_CONTROL,
            'ContentType' => $extension === 'webp' ? 'image/webp' : 'image/jpeg',
        ]);

        return $path ?: null;
    }

    private function publicDisk(): string
    {
        return config('photography.storage.public_disk', 'public');
    }
}
