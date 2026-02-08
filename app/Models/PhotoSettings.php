<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PhotoSettings extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("photo_settings.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("photo_settings.{$key}");
    }

    public static function getWatermarkPath(): ?string
    {
        return static::get('watermark_path');
    }

    public static function getWatermarkOpacity(): int
    {
        return (int) static::get('watermark_opacity', 40);
    }

    public static function getWatermarkScale(): float
    {
        return (float) static::get('watermark_scale', 0.08);
    }

    public static function getWatermarkPosition(): string
    {
        return static::get('watermark_position', 'bottom-right');
    }

    public static function getWebMaxWidth(): int
    {
        return (int) static::get('web_max_width', 2000);
    }

    public static function getWebQuality(): int
    {
        return (int) static::get('web_quality', 85);
    }

    public static function getThumbnailWidth(): int
    {
        return (int) static::get('thumbnail_width', 400);
    }

    public static function getThumbnailHeight(): int
    {
        return (int) static::get('thumbnail_height', 300);
    }

    public static function getOgImageEnabled(): bool
    {
        return (bool) static::get('og_image_enabled', true);
    }

    public static function getOgImageShowTitle(): bool
    {
        return (bool) static::get('og_image_show_title', true);
    }

    public static function getOgImageShowExif(): bool
    {
        return (bool) static::get('og_image_show_exif', true);
    }

    public static function getOgImageOverlayOpacity(): int
    {
        return (int) static::get('og_image_overlay_opacity', 60);
    }
}
