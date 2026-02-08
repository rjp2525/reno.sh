<?php

declare(strict_types=1);

namespace App\Services\Photo;

use App\Models\PhotoSettings;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class ImageProcessorService
{
    private ImageManager $manager;

    private bool $hasImagick;

    public function __construct()
    {
        $this->hasImagick = extension_loaded('imagick');

        $driver = $this->hasImagick ? new ImagickDriver : new GdDriver;
        $this->manager = new ImageManager($driver);
    }

    public function generateWebVersion(string $originalPath): ?string
    {
        $originalsDisk = config('photography.storage.originals_disk', 'photos_originals');
        $publicDisk = config('photography.storage.public_disk', 'public');

        if (! Storage::disk($originalsDisk)->exists($originalPath)) {
            return null;
        }

        $image = $this->manager->read(
            Storage::disk($originalsDisk)->get($originalPath)
        );

        $maxWidth = PhotoSettings::getWebMaxWidth();
        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $this->applyWatermark($image);

        $filename = Str::random(40).'.jpg';
        $webPath = 'photos/web/'.$filename;

        Storage::disk($publicDisk)->makeDirectory('photos/web');

        $quality = PhotoSettings::getWebQuality();
        $encoded = $image->toJpeg($quality);

        Storage::disk($publicDisk)->put($webPath, (string) $encoded);

        return $webPath;
    }

    public function generateThumbnail(string $originalPath): ?string
    {
        $originalsDisk = config('photography.storage.originals_disk', 'photos_originals');
        $publicDisk = config('photography.storage.public_disk', 'public');

        if (! Storage::disk($originalsDisk)->exists($originalPath)) {
            return null;
        }

        $image = $this->manager->read(
            Storage::disk($originalsDisk)->get($originalPath)
        );

        $width = PhotoSettings::getThumbnailWidth();
        $height = PhotoSettings::getThumbnailHeight();

        $image->cover($width, $height);

        $filename = Str::random(40).'.jpg';
        $thumbnailPath = 'photos/thumbnails/'.$filename;

        Storage::disk($publicDisk)->makeDirectory('photos/thumbnails');

        $encoded = $image->toJpeg(80);

        Storage::disk($publicDisk)->put($thumbnailPath, (string) $encoded);

        return $thumbnailPath;
    }

    public function generateOgImage(string $originalPath, ?string $title = null, ?string $exifSummary = null): ?string
    {
        if (! PhotoSettings::getOgImageEnabled()) {
            return null;
        }

        $originalsDisk = config('photography.storage.originals_disk', 'photos_originals');
        $publicDisk = config('photography.storage.public_disk', 'public');

        if (! Storage::disk($originalsDisk)->exists($originalPath)) {
            return null;
        }

        $image = $this->manager->read(
            Storage::disk($originalsDisk)->get($originalPath)
        );

        $ogWidth = 1200;
        $ogHeight = 630;

        $image->cover($ogWidth, $ogHeight);

        $showTitle = PhotoSettings::getOgImageShowTitle() && $title;
        $showExif = PhotoSettings::getOgImageShowExif() && $exifSummary;

        if ($showTitle || $showExif) {
            $this->applyOgOverlay($image, $title, $exifSummary, $showTitle, $showExif);
        }

        $filename = Str::random(40).'.jpg';
        $ogPath = 'photos/og/'.$filename;

        Storage::disk($publicDisk)->makeDirectory('photos/og');

        $encoded = $image->toJpeg(90);

        Storage::disk($publicDisk)->put($ogPath, (string) $encoded);

        return $ogPath;
    }

    private function applyOgOverlay(ImageInterface $image, ?string $title, ?string $exifSummary, bool $showTitle, bool $showExif): void
    {
        $overlayOpacity = PhotoSettings::getOgImageOverlayOpacity();

        if ($this->hasImagick) {
            $this->applyGradientOverlayImagick($image, $overlayOpacity);
        } else {
            $this->applyGradientOverlayGd($image, $overlayOpacity);
        }

        if ($this->hasImagick) {
            $this->addOgText($image, $title, $exifSummary, $showTitle, $showExif);
        }
    }

    private function applyGradientOverlayImagick(ImageInterface $image, int $opacity): void
    {
        try {
            $width = $image->width();
            $height = $image->height();
            $gradientHeight = (int) ($height * 0.4);

            $gradient = new Imagick;
            $gradient->newPseudoImage($width, $gradientHeight, "gradient:transparent-rgba(0,0,0,{$opacity}%)");
            $gradient->setImageFormat('png');

            $gradientImage = $this->manager->read($gradient->getImageBlob());
            $image->place($gradientImage, 'bottom-left', 0, 0);

            $gradient->clear();
            $gradient->destroy();
        } catch (Exception $e) {
            Log::warning('Failed to apply OG gradient overlay', ['error' => $e->getMessage()]);
        }
    }

    private function applyGradientOverlayGd(ImageInterface $image, int $opacity): void
    {
        try {
            $width = $image->width();
            $height = $image->height();
            $overlayHeight = (int) ($height * 0.25);

            $overlay = $this->manager->create($width, $overlayHeight);
            $alphaValue = (int) (($opacity / 100) * 127);
            $overlay->fill("rgba(0, 0, 0, {$opacity}%)");

            $image->place($overlay, 'bottom-left', 0, 0);
        } catch (Exception $e) {
            Log::warning('Failed to apply OG overlay', ['error' => $e->getMessage()]);
        }
    }

    private function addOgText(ImageInterface $image, ?string $title, ?string $exifSummary, bool $showTitle, bool $showExif): void
    {
        try {
            $draw = new ImagickDraw;
            $draw->setFillColor(new ImagickPixel('white'));
            $draw->setTextAlignment(Imagick::ALIGN_LEFT);

            $imagick = new Imagick;
            $imagick->readImageBlob((string) $image->toJpeg(100));

            $padding = 40;
            $bottomY = $image->height() - $padding;

            if ($showExif && $exifSummary) {
                $draw->setFontSize(24);
                $draw->setFontWeight(400);
                $bottomY -= 30;
                $imagick->annotateImage($draw, $padding, $bottomY, 0, $exifSummary);
                $bottomY -= 20;
            }

            if ($showTitle && $title) {
                $draw->setFontSize(42);
                $draw->setFontWeight(700);
                $bottomY -= 10;
                $imagick->annotateImage($draw, $padding, $bottomY, 0, $title);
            }

            $image->fill('transparent');
            $modifiedBlob = $imagick->getImageBlob();

            $imagick->clear();
            $imagick->destroy();

            $modified = $this->manager->read($modifiedBlob);
            $image->place($modified, 'top-left', 0, 0);

        } catch (Exception $e) {
            Log::warning('Failed to add OG text', ['error' => $e->getMessage()]);
        }
    }

    private function applyWatermark(ImageInterface $image): void
    {
        $logoPath = PhotoSettings::getWatermarkPath();

        if (! $logoPath) {
            return;
        }

        if (! Storage::disk('local')->exists($logoPath)) {
            Log::warning('Watermark file not found', ['path' => $logoPath]);

            return;
        }

        $logoFullPath = Storage::disk('local')->path($logoPath);
        $extension = strtolower(pathinfo($logoFullPath, PATHINFO_EXTENSION));

        try {
            $scale = PhotoSettings::getWatermarkScale();
            $targetWidth = (int) ($image->width() * $scale);
            if ($targetWidth < 20) {
                $targetWidth = 20;
            }

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

            $shouldInvert = $this->shouldInvertWatermark($image, $x, $y, $targetWidth, $estimatedHeight);

            if ($extension === 'svg') {
                $watermark = $this->loadSvgWatermark($logoFullPath, $image->width(), $shouldInvert);
                if (! $watermark) {
                    return;
                }
            } else {
                $watermark = $this->manager->read($logoFullPath);
                if ($shouldInvert) {
                    $watermark = $this->invertWatermark($watermark);
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

            $opacity = PhotoSettings::getWatermarkOpacity();

            $image->place($watermark, 'top-left', $x, $y, $opacity);

        } catch (Exception $e) {
            Log::warning('Failed to apply watermark', [
                'path' => $logoPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldInvertWatermark(ImageInterface $image, int $x, int $y, int $width, int $height): bool
    {
        try {
            $x = max(0, min($x, $image->width() - 1));
            $y = max(0, min($y, $image->height() - 1));
            $width = min($width, $image->width() - $x);
            $height = min($height, $image->height() - $y);

            if ($width <= 0 || $height <= 0) {
                return false;
            }

            $region = clone $image;
            $region->crop($width, $height, $x, $y);

            $totalLuminance = 0;
            $sampleCount = 0;
            $sampleStep = max(1, (int) ($width / 10));

            for ($sx = 0; $sx < $width; $sx += $sampleStep) {
                for ($sy = 0; $sy < $height; $sy += $sampleStep) {
                    $color = $region->pickColor($sx, $sy);
                    $r = $color->red()->toInt();
                    $g = $color->green()->toInt();
                    $b = $color->blue()->toInt();
                    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $totalLuminance += $luminance;
                    $sampleCount++;
                }
            }

            if ($sampleCount === 0) {
                return false;
            }

            $avgLuminance = $totalLuminance / $sampleCount;

            return $avgLuminance < 128;

        } catch (Exception $e) {
            Log::warning('Failed to calculate region luminance', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function invertWatermark(ImageInterface $watermark): ImageInterface
    {
        try {
            if ($this->hasImagick) {
                $imagick = new Imagick;
                $imagick->readImageBlob((string) $watermark->toPng());
                $imagick->negateImage(false, Imagick::CHANNEL_ALL & ~Imagick::CHANNEL_ALPHA);
                $result = $this->manager->read($imagick->getImageBlob());
                $imagick->clear();
                $imagick->destroy();

                return $result;
            } else {
                $watermark->invert();

                return $watermark;
            }
        } catch (Exception $e) {
            Log::warning('Failed to invert watermark', ['error' => $e->getMessage()]);

            return $watermark;
        }
    }

    private function loadSvgWatermark(string $svgPath, int $imageWidth, bool $invert = false): ?ImageInterface
    {
        if (! $this->hasImagick) {
            Log::warning('SVG watermark requires Imagick extension. Please use PNG instead or install Imagick.');

            return null;
        }

        try {
            $scale = PhotoSettings::getWatermarkScale();
            $targetWidth = (int) ($imageWidth * $scale);

            if ($targetWidth < 20) {
                $targetWidth = 20;
            }

            $svgContent = file_get_contents($svgPath);

            if ($invert) {
                $svgContent = $this->invertSvgColors($svgContent);
            }

            $imagick = new Imagick;

            $imagick->setResolution(300, 300);

            $imagick->setBackgroundColor(new ImagickPixel('transparent'));

            $imagick->readImageBlob($svgContent);

            $origWidth = $imagick->getImageWidth();
            $origHeight = $imagick->getImageHeight();

            $scaledHeight = (int) ($origHeight * ($targetWidth / $origWidth));

            $canvas = new Imagick;
            $canvas->newImage($targetWidth, $scaledHeight, new ImagickPixel('transparent'));
            $canvas->setImageFormat('png32');

            $imagick->thumbnailImage($targetWidth, 0);

            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, 0);

            $imagick->clear();
            $imagick->destroy();

            $imagick = $canvas;

            $pngBlob = $imagick->getImageBlob();

            $imagick->clear();
            $imagick->destroy();

            return $this->manager->read($pngBlob);

        } catch (Exception $e) {
            Log::warning('Failed to load SVG watermark', [
                'path' => $svgPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function invertSvgColors(string $svgContent): string
    {
        $svgContent = preg_replace(
            '/<svg\s/',
            '<svg fill="white" ',
            $svgContent,
            1
        );

        $darkColors = [
            '#000000' => '#FFFFFF',
            '#000' => '#FFF',
            'black' => 'white',
            '#111111' => '#FFFFFF',
            '#111' => '#FFF',
            '#222222' => '#FFFFFF',
            '#222' => '#FFF',
            '#333333' => '#FFFFFF',
            '#333' => '#FFF',
            'rgb(0,0,0)' => 'rgb(255,255,255)',
            'rgb(0, 0, 0)' => 'rgb(255, 255, 255)',
        ];

        foreach ($darkColors as $dark => $light) {
            $svgContent = preg_replace(
                '/fill\s*[=:]\s*["\']?'.preg_quote($dark, '/').'["\']?/i',
                'fill="'.$light.'"',
                $svgContent
            );
            $svgContent = preg_replace(
                '/stroke\s*[=:]\s*["\']?'.preg_quote($dark, '/').'["\']?/i',
                'stroke="'.$light.'"',
                $svgContent
            );
            $svgContent = preg_replace(
                '/fill\s*:\s*'.preg_quote($dark, '/').'/i',
                'fill:'.$light,
                $svgContent
            );
            $svgContent = preg_replace(
                '/stroke\s*:\s*'.preg_quote($dark, '/').'/i',
                'stroke:'.$light,
                $svgContent
            );
        }

        $svgContent = preg_replace(
            '/fill\s*[=:]\s*["\']?currentColor["\']?/i',
            'fill="white"',
            $svgContent
        );
        $svgContent = preg_replace(
            '/stroke\s*[=:]\s*["\']?currentColor["\']?/i',
            'stroke="white"',
            $svgContent
        );

        return $svgContent;
    }

    public function deleteProcessedFiles(?string $webPath = null, ?string $thumbnailPath = null, ?string $ogImagePath = null): void
    {
        $publicDisk = config('photography.storage.public_disk', 'public');

        if ($webPath && Storage::disk($publicDisk)->exists($webPath)) {
            Storage::disk($publicDisk)->delete($webPath);
        }

        if ($thumbnailPath && Storage::disk($publicDisk)->exists($thumbnailPath)) {
            Storage::disk($publicDisk)->delete($thumbnailPath);
        }

        if ($ogImagePath && Storage::disk($publicDisk)->exists($ogImagePath)) {
            Storage::disk($publicDisk)->delete($ogImagePath);
        }
    }
}
