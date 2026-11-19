<?php

declare(strict_types=1);

namespace App\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;

/**
 * Drop EXIF/XMP/IPTC (camera serials, GPS…) from public copies while keeping the
 * ICC colour profile, so colours don't shift. Handled by PhotoEffects.
 */
final class StripMetadata implements Transformation {}
