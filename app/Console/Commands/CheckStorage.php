<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Shows where uploads go and proves each disk works with a real write/read/delete. */
class CheckStorage extends Command
{
    protected $signature = 'storage:check';

    protected $description = 'Show the image/originals disk configuration and test a write, read, URL and delete on each';

    public function handle(): int
    {
        if (app()->configurationIsCached()) {
            $this->components->info('Config is cached: values below are what the site and queue workers use after the last optimize/deploy.');
        }

        $ok = true;

        foreach (['public' => 'Site images', 'photos_originals' => 'Photo originals'] as $name => $label) {
            $config = config("filesystems.disks.{$name}");
            $remote = ($config['driver'] ?? null) === 's3';

            $this->newLine();
            $this->components->twoColumnDetail("<fg=cyan>{$label}</> ({$name})", $remote ? '<fg=green>S3/R2</>' : '<fg=yellow>local</>');
            $this->components->twoColumnDetail('Location', $remote
                ? ($config['bucket'] ?? '—').' @ '.($config['endpoint'] ?? 'AWS default endpoint')
                : ($config['root'] ?? '—'));
            $this->components->twoColumnDetail('Public URL', $name === 'public' ? (string) ($config['url'] ?? '—') : 'signed URLs only');

            $path = '.storage-check/'.Str::random(12).'.txt';
            $disk = Storage::disk($name);

            try {
                $disk->put($path, 'ok', $name === 'public' ? [] : ['visibility' => 'private']);
                $read = $disk->get($path) === 'ok';
                $this->components->twoColumnDetail('Write + read', $read ? '<fg=green>ok</>' : '<fg=red>read back mismatch</>');
                $ok = $ok && $read;

                if ($remote && $name === 'public') {
                    $status = rescue(fn () => Http::timeout(10)->get($disk->url($path))->status(), 0, false);
                    $this->components->twoColumnDetail('Public URL fetch', $status === 200 ? '<fg=green>200</>' : "<fg=red>{$status}</> (bucket public access / custom domain?)");
                    $ok = $ok && $status === 200;
                }

                if ($remote && $name === 'photos_originals') {
                    $status = rescue(fn () => Http::timeout(10)->get($disk->temporaryUrl($path, now()->addMinute()))->status(), 0, false);
                    $this->components->twoColumnDetail('Signed URL fetch', $status === 200 ? '<fg=green>200</>' : "<fg=red>{$status}</>");
                    $ok = $ok && $status === 200;
                }
            } catch (\Throwable $e) {
                $ok = false;
                $this->components->twoColumnDetail('Write + read', '<fg=red>failed</>');
                $this->components->error($e->getMessage());
            } finally {
                rescue(fn () => $disk->delete($path), null, false);
            }
        }

        if (config('filesystems.disks.public.driver') === 's3'
            && config('filesystems.disks.public.bucket') === config('filesystems.disks.photos_originals.bucket')) {
            $this->newLine();
            $this->components->warn('Originals share the public bucket: they are publicly downloadable. Set PHOTO_ORIGINALS_BUCKET to a private bucket.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
