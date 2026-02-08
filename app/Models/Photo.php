<?php

namespace App\Models;

use App\Enums\PhotoProcessingStatus;
use App\Observers\PhotoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Tags\HasTags;

#[ObservedBy(PhotoObserver::class)]
class Photo extends Model
{
    use HasFactory;
    use HasTags;
    use HasUlids;

    protected $fillable = [
        'title',
        'description',
        'slug',
        'photo_category_id',
        'original_path',
        'web_path',
        'thumbnail_path',
        'og_image_path',
        'iso',
        'aperture',
        'shutter_speed',
        'focal_length',
        'camera_body',
        'lens',
        'gps_latitude',
        'gps_longitude',
        'taken_at',
        'width',
        'height',
        'file_size',
        'instagram_link',
        'is_favorite',
        'is_published',
        'sort_order',
        'processing_status',
    ];

    protected $casts = [
        'iso' => 'integer',
        'gps_latitude' => 'decimal:7',
        'gps_longitude' => 'decimal:7',
        'taken_at' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'is_favorite' => 'boolean',
        'is_published' => 'boolean',
        'processing_status' => PhotoProcessingStatus::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PhotoCategory::class, 'photo_category_id');
    }

    public function getWebUrlAttribute(): ?string
    {
        if (! $this->web_path) {
            return null;
        }

        return url('/img/'.$this->web_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return url('/img/'.$this->thumbnail_path);
    }

    public function getOgImageUrlAttribute(): ?string
    {
        if (! $this->og_image_path) {
            return null;
        }

        return url('/img/'.$this->og_image_path);
    }

    public function getOriginalUrlAttribute(): ?string
    {
        if (! $this->original_path) {
            return null;
        }

        $disk = config('photography.storage.originals_disk', 'local');

        return Storage::disk($disk)->url($this->original_path);
    }

    public function getExifSummaryAttribute(): ?string
    {
        $parts = [];

        if ($this->camera_body) {
            $parts[] = $this->camera_body;
        }

        if ($this->focal_length) {
            $parts[] = $this->focal_length;
        }

        if ($this->aperture) {
            $parts[] = $this->aperture;
        }

        if ($this->shutter_speed) {
            $parts[] = $this->shutter_speed;
        }

        if ($this->iso) {
            $parts[] = "ISO {$this->iso}";
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFavorite($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeProcessed($query)
    {
        return $query->where('processing_status', PhotoProcessingStatus::COMPLETED);
    }

    public function scopeByCategory($query, string $categorySlug)
    {
        return $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('taken_at')->orderByDesc('created_at');
    }

    public function deleteAllFiles(): void
    {
        $originalsDisk = config('photography.storage.originals_disk', 'photos_originals');
        $publicDisk = config('photography.storage.public_disk', 'public');

        if ($this->original_path && Storage::disk($originalsDisk)->exists($this->original_path)) {
            Storage::disk($originalsDisk)->delete($this->original_path);
        }

        if ($this->web_path && Storage::disk($publicDisk)->exists($this->web_path)) {
            Storage::disk($publicDisk)->delete($this->web_path);
        }

        if ($this->thumbnail_path && Storage::disk($publicDisk)->exists($this->thumbnail_path)) {
            Storage::disk($publicDisk)->delete($this->thumbnail_path);
        }

        if ($this->og_image_path && Storage::disk($publicDisk)->exists($this->og_image_path)) {
            Storage::disk($publicDisk)->delete($this->og_image_path);
        }
    }
}
