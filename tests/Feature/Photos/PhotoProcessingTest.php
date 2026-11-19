<?php

use App\Console\Commands\CheckStorage;
use App\Jobs\ProcessPhotoJob;
use App\Models\Photo;
use App\Models\PhotoSettings;
use App\Services\Photo\ImageProcessorService;
use Illuminate\Support\Facades\Storage;

/** Minimal EXIF block (TIFF, little-endian) with Make and a camera serial number. */
function exifBlob(): string
{
    $make = "TestCam\0";
    $serial = "SN-123456\0";
    $ifd = pack('v', 2)
        .pack('vvVV', 0x010F, 2, strlen($make), 8 + 2 + 2 * 12 + 4)
        .pack('vvVV', 0xA431, 2, strlen($serial), 8 + 2 + 2 * 12 + 4 + strlen($make))
        .pack('V', 0);

    return "Exif\0\0".'II'.pack('v', 42).pack('V', 8).$ifd.$make.$serial;
}

/** A photo-like 3000×2000 JPEG with EXIF and (when available) an sRGB ICC profile. */
function samplePhoto(): string
{
    static $bytes;
    if ($bytes !== null) {
        return $bytes;
    }

    $im = new Imagick;
    $im->newPseudoImage(3000, 2000, 'plasma:');
    $im->setImageFormat('jpeg');
    $im->setImageCompressionQuality(95);
    $im->profileImage('exif', exifBlob());
    if (is_file($icc = '/usr/share/color/icc/ghostscript/srgb.icc')) {
        $im->profileImage('icc', file_get_contents($icc));
    }

    return $bytes = $im->getImageBlob();
}

function inspect(string $bytes): array
{
    $im = new Imagick;
    $im->readImageBlob($bytes);

    return [
        'format' => $im->getImageFormat(),
        'w' => $im->getImageWidth(),
        'h' => $im->getImageHeight(),
        'profiles' => array_keys($im->getImageProfiles('*', false)),
        'exif' => $im->getImageProperties('exif:*'),
        'bytes' => strlen($bytes),
    ];
}

beforeEach(function () {
    Storage::fake('photos_originals');
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('photos_originals')->put('photos/originals/sample.jpg', samplePhoto());
    $this->photo = Photo::factory()->create(['original_path' => 'photos/originals/sample.jpg', 'title' => 'Golden hour']);
});

it('builds compressed WebP web and thumbnail versions and a JPEG social card', function () {
    (new ProcessPhotoJob($this->photo))->handle(app(ImageProcessorService::class));
    $photo = $this->photo->fresh();

    expect($photo->processing_status->value)->toBe('completed')
        ->and($photo->web_path)->toStartWith('photos/web/')->toEndWith('.webp')
        ->and($photo->thumbnail_path)->toEndWith('.webp')
        ->and($photo->og_image_path)->toEndWith('.jpg');

    $web = inspect(Storage::disk('public')->get($photo->web_path));
    $thumb = inspect(Storage::disk('public')->get($photo->thumbnail_path));
    $og = inspect(Storage::disk('public')->get($photo->og_image_path));

    expect([$web['format'], $web['w'], $web['h']])->toBe(['WEBP', 2000, 1333])
        ->and([$thumb['format'], $thumb['w'], $thumb['h']])->toBe(['WEBP', 400, 300])
        ->and([$og['format'], $og['w'], $og['h']])->toBe(['JPEG', 1200, 630]);

    // Smaller than the old pipeline's JPEG at quality 85 for the same pixels.
    $jpeg = new Imagick;
    $jpeg->readImageBlob(samplePhoto());
    $jpeg->resizeImage(2000, 1333, Imagick::FILTER_LANCZOS, 1);
    $jpeg->setImageCompressionQuality(85);
    expect($web['bytes'])->toBeLessThan(strlen($jpeg->getImageBlob()));
});

it('strips camera metadata from public copies but keeps the colour profile', function () {
    $original = inspect(Storage::disk('photos_originals')->get('photos/originals/sample.jpg'));
    expect($original['exif'])->toHaveKey('exif:Make');

    (new ProcessPhotoJob($this->photo))->handle(app(ImageProcessorService::class));
    $photo = $this->photo->fresh();

    foreach ([$photo->web_path, $photo->thumbnail_path, $photo->og_image_path] as $path) {
        $out = inspect(Storage::disk('public')->get($path));
        expect($out['exif'])->toBeEmpty()->and($out['profiles'])->not->toContain('exif');
        if (in_array('icc', $original['profiles'], true)) {
            expect($out['profiles'])->toContain('icc');
        }
    }

    // The private original is untouched.
    expect(inspect(Storage::disk('photos_originals')->get('photos/originals/sample.jpg'))['exif'])->toHaveKey('exif:Make');
});

it('applies the watermark to the web version', function () {
    $service = app(ImageProcessorService::class);
    $plain = inspect(Storage::disk('public')->get($service->generateWebVersion('photos/originals/sample.jpg')));

    $logo = new Imagick;
    $logo->newImage(400, 200, new ImagickPixel('white'));
    $logo->setImageFormat('png');
    Storage::disk('local')->put('watermarks/logo.png', $logo->getImageBlob());
    PhotoSettings::set('watermark_path', 'watermarks/logo.png');
    PhotoSettings::set('watermark_opacity', 100);
    PhotoSettings::set('watermark_position', 'bottom-right');

    $marked = Storage::disk('public')->get($service->generateWebVersion('photos/originals/sample.jpg'));
    $im = new Imagick;
    $im->readImageBlob($marked);
    $corner = $im->getImagePixelColor(2000 - 40, 1333 - 30)->getColor();

    // A solid logo at 100% opacity: white, or inverted to black over a dark area.
    $isSolid = fn (array $c) => max($c['r'], $c['g'], $c['b']) < 30 || min($c['r'], $c['g'], $c['b']) > 225;
    expect($isSolid($corner))->toBeTrue()
        ->and(strlen($marked))->not->toBe($plain['bytes']);
});

it('stores public versions with long-lived cache headers', function () {
    expect(ImageProcessorService::CACHE_CONTROL)->toBe('public, max-age=31536000, immutable');
});

it('reprocesses old JPEG photos into WebP and deletes the old files', function () {
    Storage::disk('public')->put('photos/web/old.jpg', 'x');
    Storage::disk('public')->put('photos/thumbnails/old.jpg', 'x');
    $this->photo->update(['web_path' => 'photos/web/old.jpg', 'thumbnail_path' => 'photos/thumbnails/old.jpg', 'processing_status' => 'completed']);
    $done = Photo::factory()->create(['original_path' => 'photos/originals/sample.jpg', 'web_path' => 'photos/web/new.webp']);

    $this->artisan('photos:reprocess', ['--sync' => true])->expectsOutputToContain('Processing 1 photo')->assertSuccessful();

    $photo = $this->photo->fresh();
    expect($photo->web_path)->toEndWith('.webp')
        ->and(Storage::disk('public')->exists($photo->web_path))->toBeTrue()
        ->and(Storage::disk('public')->exists('photos/web/old.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('photos/thumbnails/old.jpg'))->toBeFalse()
        ->and($done->fresh()->web_path)->toBe('photos/web/new.webp');
});

it('describes the bucket CORS policy the admin previews need', function () {
    expect(CheckStorage::corsRules('https://reno.sh'))->toBe([[
        'AllowedOrigins' => ['https://reno.sh'],
        'AllowedMethods' => ['GET', 'HEAD'],
        'AllowedHeaders' => ['*'],
        'ExposeHeaders' => ['ETag', 'Content-Length', 'Content-Type'],
        'MaxAgeSeconds' => 3600,
    ]]);
});

it('keeps working across queue jobs, which discard the scoped image manager', function () {
    $service = app(ImageProcessorService::class);
    expect($service->generateWebVersion('photos/originals/sample.jpg'))->toEndWith('.webp');

    // What the queue worker does between jobs.
    app()->forgetScopedInstances();

    expect($service->generateWebVersion('photos/originals/sample.jpg'))->toEndWith('.webp')
        ->and($service->generateThumbnail('photos/originals/sample.jpg'))->toEndWith('.webp')
        ->and($service->generateOgImage('photos/originals/sample.jpg', 'Golden hour', 'f/8'))->toEndWith('.jpg');
});
