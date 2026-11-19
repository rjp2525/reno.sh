<?php

declare(strict_types=1);

namespace App\Images;

use App\Images\Transformations\OgOverlay;
use App\Images\Transformations\StripMetadata;
use App\Images\Transformations\Watermark;
use App\Models\PhotoSettings;
use Exception;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorspace;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Photo-specific effects for Laravel's native Image pipeline. Each effect is a
 * Transformation class; the handlers below receive the Intervention image that
 * Laravel's GD/Imagick drivers are built on.
 */
final class PhotoEffects
{
    public static function register(): void
    {
        foreach (['gd', 'imagick'] as $driver) {
            Image::transformUsing($driver, Watermark::class, fn (ImageInterface $image) => (new self)->watermark($image));
            Image::transformUsing($driver, OgOverlay::class, fn (ImageInterface $image, OgOverlay $t) => (new self)->ogOverlay($image, $t));
            Image::transformUsing($driver, StripMetadata::class, fn (ImageInterface $image) => (new self)->stripMetadata($image));
        }
    }

    private function manager(ImageInterface $image): ImageManager
    {
        // Decode overlays with the same driver as the image they're composited onto.
        return ImageManager::usingDriver($image->driver());
    }

    private function isImagick(ImageInterface $image): bool
    {
        return $image->core()->native() instanceof Imagick;
    }

    // ---- Metadata --------------------------------------------------------------

    public function stripMetadata(ImageInterface $image): ImageInterface
    {
        // GD never writes metadata into its output, so there's nothing to strip.
        if (! $this->isImagick($image)) {
            return $image;
        }

        try {
            /** @var Imagick $native */
            $native = $image->core()->native();
            foreach ($native as $frame) {
                $icc = $frame->getImageProfiles('icc', true)['icc'] ?? null;
                $frame->stripImage();
                if ($icc !== null) {
                    $frame->profileImage('icc', $icc);
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to strip image metadata', ['error' => $e->getMessage()]);
        }

        return $image;
    }

    // ---- Watermark ------------------------------------------------------------

    public function watermark(ImageInterface $image): ImageInterface
    {
        $logoPath = PhotoSettings::getWatermarkPath();

        if (! $logoPath) {
            return $image;
        }

        if (! Storage::disk('local')->exists($logoPath)) {
            Log::warning('Watermark file not found', ['path' => $logoPath]);

            return $image;
        }

        $logoFullPath = Storage::disk('local')->path($logoPath);
        $extension = strtolower(pathinfo($logoFullPath, PATHINFO_EXTENSION));

        try {
            $targetWidth = max(20, (int) ($image->width() * PhotoSettings::getWatermarkScale()));
            $position = PhotoSettings::getWatermarkPosition();
            $padding = 20;
            $estimatedHeight = (int) ($targetWidth * 0.5);

            $x = match ($position) {
                'bottom-left', 'top-left' => $padding,
                'bottom-center', 'top-center', 'center' => (int) (($image->width() - $targetWidth) / 2),
                default => $image->width() - $targetWidth - $padding,
            };

            $y = match ($position) {
                'top-left', 'top-right', 'top-center' => $padding,
                'center' => (int) (($image->height() - $estimatedHeight) / 2),
                default => $image->height() - $estimatedHeight - $padding,
            };

            $shouldInvert = $this->isDarkRegion($image, $x, $y, $targetWidth, $estimatedHeight);

            if ($extension === 'svg') {
                $watermark = $this->loadSvgWatermark($image, $logoFullPath, $targetWidth, $shouldInvert);
                if (! $watermark) {
                    return $image;
                }
            } else {
                $watermark = $this->manager($image)->decode($logoFullPath);
                if ($shouldInvert) {
                    $watermark = $this->invert($image, $watermark);
                }
            }

            if ($watermark->width() > $targetWidth) {
                $watermark->scaleDown(width: $targetWidth);
            } elseif ($watermark->width() < $targetWidth && $extension !== 'svg') {
                $watermark->scale(width: $targetWidth);
            }

            $y = match ($position) {
                'top-left', 'top-right', 'top-center' => $padding,
                'center' => (int) (($image->height() - $watermark->height()) / 2),
                default => $image->height() - $watermark->height() - $padding,
            };

            $image->insert($watermark, $x, $y, 'top-left', PhotoSettings::getWatermarkOpacity() / 100);
        } catch (Exception $e) {
            Log::warning('Failed to apply watermark', ['path' => $logoPath, 'error' => $e->getMessage()]);
        }

        return $image;
    }

    /** Average luminance under where the watermark goes: invert the logo on dark areas. */
    private function isDarkRegion(ImageInterface $image, int $x, int $y, int $width, int $height): bool
    {
        try {
            $x = max(0, min($x, $image->width() - 1));
            $y = max(0, min($y, $image->height() - 1));
            $width = min($width, $image->width() - $x);
            $height = min($height, $image->height() - $y);

            if ($width <= 0 || $height <= 0) {
                return false;
            }

            $total = 0;
            $samples = 0;
            $step = max(1, (int) ($width / 10));

            for ($sx = $x; $sx < $x + $width; $sx += $step) {
                for ($sy = $y; $sy < $y + $height; $sy += $step) {
                    $color = $image->colorAt($sx, $sy)->toColorspace(RgbColorspace::class);
                    $total += 0.299 * $color->red()->value() + 0.587 * $color->green()->value() + 0.114 * $color->blue()->value();
                    $samples++;
                }
            }

            return $samples > 0 && $total / $samples < 128;
        } catch (Exception $e) {
            Log::warning('Failed to calculate region luminance', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function invert(ImageInterface $image, ImageInterface $watermark): ImageInterface
    {
        try {
            if (! $this->isImagick($image)) {
                return $watermark->invert();
            }

            $imagick = new Imagick;
            $imagick->readImageBlob((string) $watermark->encode(new PngEncoder));
            $imagick->negateImage(false, Imagick::CHANNEL_ALL & ~Imagick::CHANNEL_ALPHA);
            $result = $this->manager($image)->decode($imagick->getImageBlob());
            $imagick->clear();

            return $result;
        } catch (Exception $e) {
            Log::warning('Failed to invert watermark', ['error' => $e->getMessage()]);

            return $watermark;
        }
    }

    private function loadSvgWatermark(ImageInterface $image, string $svgPath, int $targetWidth, bool $invert): ?ImageInterface
    {
        if (! $this->isImagick($image)) {
            Log::warning('SVG watermark requires the Imagick image driver. Use a PNG watermark or install Imagick.');

            return null;
        }

        try {
            $svg = (string) file_get_contents($svgPath);
            if ($invert) {
                $svg = $this->invertSvgColors($svg);
            }

            $imagick = new Imagick;
            $imagick->setResolution(300, 300);
            $imagick->setBackgroundColor(new ImagickPixel('transparent'));
            $imagick->readImageBlob($svg);
            $imagick->thumbnailImage($targetWidth, 0);

            $canvas = new Imagick;
            $canvas->newImage($imagick->getImageWidth(), $imagick->getImageHeight(), new ImagickPixel('transparent'));
            $canvas->setImageFormat('png32');
            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, 0);
            $blob = $canvas->getImageBlob();
            $imagick->clear();
            $canvas->clear();

            return $this->manager($image)->decode($blob);
        } catch (Exception $e) {
            Log::warning('Failed to load SVG watermark', ['path' => $svgPath, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function invertSvgColors(string $svg): string
    {
        $svg = (string) preg_replace('/<svg\s/', '<svg fill="white" ', $svg, 1);

        $dark = ['#000000' => '#FFFFFF', '#000' => '#FFF', 'black' => 'white', '#111111' => '#FFFFFF', '#111' => '#FFF',
            '#222222' => '#FFFFFF', '#222' => '#FFF', '#333333' => '#FFFFFF', '#333' => '#FFF',
            'rgb(0,0,0)' => 'rgb(255,255,255)', 'rgb(0, 0, 0)' => 'rgb(255, 255, 255)', 'currentColor' => 'white'];

        foreach ($dark as $from => $to) {
            $q = preg_quote($from, '/');
            $svg = (string) preg_replace('/(fill|stroke)\s*=\s*["\']?'.$q.'["\']?/i', '$1="'.$to.'"', $svg);
            $svg = (string) preg_replace('/(fill|stroke)\s*:\s*'.$q.'/i', '$1:'.$to, $svg);
        }

        return $svg;
    }

    // ---- Social card overlay ----------------------------------------------------

    public function ogOverlay(ImageInterface $image, OgOverlay $overlay): ImageInterface
    {
        $title = PhotoSettings::getOgImageShowTitle() ? $overlay->title : null;
        $exif = PhotoSettings::getOgImageShowExif() ? $overlay->exif : null;

        if (! $title && ! $exif) {
            return $image;
        }

        $opacity = PhotoSettings::getOgImageOverlayOpacity();

        try {
            if ($this->isImagick($image)) {
                $gradient = new Imagick;
                $gradient->newPseudoImage($image->width(), (int) ($image->height() * 0.4), "gradient:transparent-rgba(0,0,0,{$opacity}%)");
                $gradient->setImageFormat('png');
                $image->insert($this->manager($image)->decode($gradient->getImageBlob()), 0, 0, 'bottom-left');
                $gradient->clear();

                $this->annotate($image, $title, $exif);
            } else {
                $band = $this->manager($image)->createImage($image->width(), (int) ($image->height() * 0.25));
                $band->fill("rgba(0, 0, 0, {$opacity}%)");
                $image->insert($band, 0, 0, 'bottom-left');
            }
        } catch (Exception $e) {
            Log::warning('Failed to apply OG overlay', ['error' => $e->getMessage()]);
        }

        return $image;
    }

    private function annotate(ImageInterface $image, ?string $title, ?string $exif): void
    {
        /** @var Imagick $native */
        $native = $image->core()->native();
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);

        $padding = 40;
        $y = $image->height() - $padding;

        if ($exif) {
            $draw->setFontSize(24);
            $draw->setFontWeight(400);
            $y -= 30;
            $native->annotateImage($draw, $padding, $y, 0, $exif);
            $y -= 20;
        }

        if ($title) {
            $draw->setFontSize(42);
            $draw->setFontWeight(700);
            $y -= 10;
            $native->annotateImage($draw, $padding, $y, 0, $title);
        }
    }
}
