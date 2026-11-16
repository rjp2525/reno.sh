<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('copies local files into the configured disks once, keeping paths', function () {
    $public = storage_path('framework/testing/migrate/app/public');
    File::ensureDirectoryExists("$public/photos/web");
    File::put("$public/photos/web/a.jpg", 'jpeg-bytes');
    File::put("$public/.gitignore", '*');

    $this->app->useStoragePath(storage_path('framework/testing/migrate'));
    config(['filesystems.disks.public.driver' => 's3', 'filesystems.disks.photos_originals.driver' => 'local']);
    Storage::fake('public');

    $this->artisan('storage:migrate-local', ['--dry-run' => true])->assertSuccessful();
    expect(Storage::disk('public')->exists('photos/web/a.jpg'))->toBeFalse();

    $this->artisan('storage:migrate-local')->expectsOutputToContain('public: 1 copied, 0 already there')->assertSuccessful();
    expect(Storage::disk('public')->get('photos/web/a.jpg'))->toBe('jpeg-bytes')
        ->and(Storage::disk('public')->exists('.gitignore'))->toBeFalse();

    $this->artisan('storage:migrate-local')->expectsOutputToContain('public: 0 copied, 1 already there')->assertSuccessful();

    File::deleteDirectory(storage_path('framework/testing/migrate'));
});

it('builds image URLs from FILESYSTEM_PUBLIC_URL', function () {
    config(['filesystems.disks.public.url' => 'https://cdn.reno.sh']);
    Storage::forgetDisk('public');

    expect(Storage::disk('public')->url('photos/web/a.jpg'))->toBe('https://cdn.reno.sh/photos/web/a.jpg');
});
