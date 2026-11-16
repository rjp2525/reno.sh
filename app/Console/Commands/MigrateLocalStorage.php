<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off copy of files uploaded while the site used local storage into the configured
 * disks (e.g. after switching FILESYSTEM_PUBLIC_DRIVER / PHOTO_ORIGINALS_DRIVER to s3 for R2).
 * Paths stay identical, so database records keep working. Safe to re-run: existing files are skipped.
 */
class MigrateLocalStorage extends Command
{
    protected $signature = 'storage:migrate-local {--dry-run : List what would be copied without uploading}';

    protected $description = 'Copy local public images and photo originals into the configured (S3/R2) disks';

    /** disk name => local directory it used before */
    private const SOURCES = [
        'public' => 'app/public',
        'photos_originals' => 'app/photos/originals',
    ];

    public function handle(): int
    {
        foreach (self::SOURCES as $diskName => $relative) {
            $target = Storage::disk($diskName);
            $root = storage_path($relative);

            if (config("filesystems.disks.{$diskName}.driver") === 'local') {
                $this->components->warn("{$diskName}: still on the local driver, nothing to migrate.");

                continue;
            }

            if (! is_dir($root)) {
                $this->components->warn("{$diskName}: {$root} not found, skipping.");

                continue;
            }

            $source = Storage::build(['driver' => 'local', 'root' => $root]);
            $copied = $skipped = $failed = 0;

            foreach ($source->allFiles() as $path) {
                if (str_starts_with(basename($path), '.')) {
                    continue;
                }

                if ($target->exists($path)) {
                    $skipped++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("  would copy {$diskName}:{$path}");
                    $copied++;

                    continue;
                }

                $stream = $source->readStream($path);

                try {
                    $target->writeStream($path, $stream, ['mimetype' => $source->mimeType($path)]);
                    $copied++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->components->error("{$diskName}:{$path}: {$e->getMessage()}");
                } finally {
                    is_resource($stream) && fclose($stream);
                }
            }

            $this->components->info(sprintf('%s: %d %s, %d already there, %d failed.', $diskName, $copied, $this->option('dry-run') ? 'to copy' : 'copied', $skipped, $failed));
        }

        return self::SUCCESS;
    }
}
