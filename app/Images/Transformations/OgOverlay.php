<?php

declare(strict_types=1);

namespace App\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;

/** Gradient plus title/EXIF caption for social cards. Handled by PhotoEffects. */
final class OgOverlay implements Transformation
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $exif = null,
    ) {}
}
