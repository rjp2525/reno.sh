<?php

declare(strict_types=1);

namespace App\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;

/** Overlay the configured watermark logo (PhotoSettings). Handled by PhotoEffects. */
final class Watermark implements Transformation {}
