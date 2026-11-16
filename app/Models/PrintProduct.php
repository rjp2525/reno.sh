<?php

namespace App\Models;

use App\Enums\PrintProductType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PrintProduct extends Model
{
    use HasUlids;

    /** How far a photo's aspect ratio may differ from the print's before we hide that size (Prodigi crops to fill). */
    public const ASPECT_TOLERANCE = 0.06;

    protected $fillable = [
        'name', 'type', 'sku', 'width_in', 'height_in', 'price', 'currency',
        'description', 'attributes', 'min_dpi', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => PrintProductType::class,
            'width_in' => 'float',
            'height_in' => 'float',
            'price' => 'integer',
            'attributes' => 'array',
            'min_dpi' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('price');
    }

    public function sizeLabel(): string
    {
        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return $fmt($this->width_in).'×'.$fmt($this->height_in).' in';
    }

    /**
     * Whether a photo of the given pixel size can be printed on this product
     * without heavy cropping or visible upscaling. Orientation-agnostic.
     */
    public function fits(?int $width, ?int $height): bool
    {
        if (! $width || ! $height) {
            return false;
        }

        $photoLong = max($width, $height);
        $photoShort = min($width, $height);
        $printLong = max($this->width_in, $this->height_in);
        $printShort = min($this->width_in, $this->height_in);

        $photoRatio = $photoLong / $photoShort;
        $printRatio = $printLong / $printShort;

        if (abs($photoRatio - $printRatio) / $printRatio > self::ASPECT_TOLERANCE) {
            return false;
        }

        return $photoLong >= $printLong * $this->min_dpi;
    }
}
