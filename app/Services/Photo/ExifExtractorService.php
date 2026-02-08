<?php

declare(strict_types=1);

namespace App\Services\Photo;

use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;

class ExifExtractorService
{
    public function extract(string $filePath): array
    {
        $data = [
            'iso' => null,
            'aperture' => null,
            'shutter_speed' => null,
            'focal_length' => null,
            'camera_body' => null,
            'lens' => null,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'taken_at' => null,
            'width' => null,
            'height' => null,
        ];

        if (! file_exists($filePath)) {
            Log::warning('EXIF extraction failed: file not found', ['path' => $filePath]);

            return $data;
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo !== false) {
            $data['width'] = $imageInfo[0];
            $data['height'] = $imageInfo[1];
        }

        if (! function_exists('exif_read_data')) {
            Log::warning('EXIF functions not available');

            return $data;
        }

        try {
            $exif = @exif_read_data($filePath, 'ANY_TAG', true);

            if ($exif === false) {
                return $data;
            }

            $data['iso'] = $this->extractIso($exif);
            $data['aperture'] = $this->extractAperture($exif);
            $data['shutter_speed'] = $this->extractShutterSpeed($exif);
            $data['focal_length'] = $this->extractFocalLength($exif);
            $data['camera_body'] = $this->extractCameraBody($exif);
            $data['lens'] = $this->extractLens($exif);
            $gps = $this->extractGps($exif);
            $data['gps_latitude'] = $gps['latitude'];
            $data['gps_longitude'] = $gps['longitude'];
            $data['taken_at'] = $this->extractDateTaken($exif);

        } catch (Exception $e) {
            Log::warning('EXIF extraction error', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return $data;
    }

    private function extractIso(array $exif): ?int
    {
        $iso = $exif['EXIF']['ISOSpeedRatings'] ?? null;

        if (is_array($iso)) {
            $iso = $iso[0] ?? null;
        }

        return $iso ? (int) $iso : null;
    }

    private function extractAperture(array $exif): ?string
    {
        $fnumber = $exif['EXIF']['FNumber'] ?? $exif['COMPUTED']['ApertureFNumber'] ?? null;

        if ($fnumber === null) {
            return null;
        }

        if (is_string($fnumber) && str_starts_with($fnumber, 'f/')) {
            return $fnumber;
        }

        if (is_string($fnumber) && str_contains($fnumber, '/')) {
            [$num, $den] = explode('/', $fnumber);
            $value = (float) $num / (float) $den;

            return 'f/'.number_format($value, 1);
        }

        return 'f/'.$fnumber;
    }

    private function extractShutterSpeed(array $exif): ?string
    {
        $exposure = $exif['EXIF']['ExposureTime'] ?? null;

        if ($exposure === null) {
            return null;
        }

        if (is_string($exposure) && str_contains($exposure, '/')) {
            [$num, $den] = explode('/', $exposure);
            $value = (float) $num / (float) $den;

            if ($value >= 1) {
                return $value.'s';
            }

            return '1/'.round(1 / $value);
        }

        return (string) $exposure;
    }

    private function extractFocalLength(array $exif): ?string
    {
        $focal = $exif['EXIF']['FocalLength'] ?? null;

        if ($focal === null) {
            return null;
        }

        if (is_string($focal) && str_contains($focal, '/')) {
            [$num, $den] = explode('/', $focal);
            $value = (float) $num / (float) $den;

            return round($value).'mm';
        }

        return round((float) $focal).'mm';
    }

    private function extractCameraBody(array $exif): ?string
    {
        $make = trim($exif['IFD0']['Make'] ?? '');
        $model = trim($exif['IFD0']['Model'] ?? '');

        if (! $model) {
            return null;
        }

        if ($make && ! str_contains(strtolower($model), strtolower($make))) {
            return $make.' '.$model;
        }

        return $model;
    }

    private function extractLens(array $exif): ?string
    {
        return $exif['EXIF']['LensModel']
            ?? $exif['EXIF']['UndefinedTag:0xA434']
            ?? null;
    }

    private function extractGps(array $exif): array
    {
        $gps = $exif['GPS'] ?? [];

        if (empty($gps['GPSLatitude']) || empty($gps['GPSLongitude'])) {
            return ['latitude' => null, 'longitude' => null];
        }

        $latitude = $this->gpsToDecimal(
            $gps['GPSLatitude'],
            $gps['GPSLatitudeRef'] ?? 'N'
        );

        $longitude = $this->gpsToDecimal(
            $gps['GPSLongitude'],
            $gps['GPSLongitudeRef'] ?? 'E'
        );

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function gpsToDecimal(array $coordinate, string $ref): float
    {
        $degrees = $this->rationalToFloat($coordinate[0] ?? '0');
        $minutes = $this->rationalToFloat($coordinate[1] ?? '0');
        $seconds = $this->rationalToFloat($coordinate[2] ?? '0');

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if (in_array($ref, ['S', 'W'])) {
            $decimal *= -1;
        }

        return round($decimal, 7);
    }

    private function rationalToFloat(string $rational): float
    {
        if (str_contains($rational, '/')) {
            [$num, $den] = explode('/', $rational);

            return (float) $den > 0 ? (float) $num / (float) $den : 0;
        }

        return (float) $rational;
    }

    private function extractDateTaken(array $exif): ?string
    {
        $date = $exif['EXIF']['DateTimeOriginal']
            ?? $exif['EXIF']['DateTimeDigitized']
            ?? $exif['IFD0']['DateTime']
            ?? null;

        if (! $date) {
            return null;
        }

        $date = str_replace(':', '-', substr($date, 0, 10)).substr($date, 10);

        try {
            return (new DateTime($date))->format('Y-m-d H:i:s');
        } catch (Exception) {
            return null;
        }
    }
}
