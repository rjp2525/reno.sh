<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Shows where uploads go and proves each disk works with a real write/read/delete. */
class CheckStorage extends Command
{
    protected $signature = 'storage:check {--fix-cors : Apply the CORS policy the admin needs to each R2/S3 bucket}';

    protected $description = 'Show the image/originals disk configuration and test a write, read, URL, CORS and delete on each';

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

                if ($remote) {
                    $url = $name === 'public' ? $disk->url($path) : $disk->temporaryUrl($path, now()->addMinutes(2));
                    $status = rescue(fn () => Http::timeout(10)->get($url)->status(), 0, false);
                    $this->components->twoColumnDetail($name === 'public' ? 'Public URL fetch' : 'Signed URL fetch', $status === 200
                        ? '<fg=green>200</>'
                        : "<fg=red>{$status}</>".($name === 'public' ? ' (bucket public access / custom domain?)' : ''));
                    $ok = $ok && $status === 200;

                    if ($this->option('fix-cors')) {
                        $this->applyCors($disk, $config);
                    }

                    // Filament's upload previews fetch() the file from the browser, which needs CORS.
                    $cors = $this->corsAllows($url);
                    $this->components->twoColumnDetail('CORS for '.$this->origin(), $cors
                        ? '<fg=green>allowed</>'
                        : '<fg=red>missing</> (admin image previews won\'t load; run with --fix-cors)');
                    $ok = $ok && $cors;
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

    private function origin(): string
    {
        $url = parse_url((string) config('app.url'));

        return ($url['scheme'] ?? 'https').'://'.($url['host'] ?? 'localhost').(isset($url['port']) ? ':'.$url['port'] : '');
    }

    /** Does a cross-origin request from the site get an Access-Control-Allow-Origin back? */
    private function corsAllows(string $url): bool
    {
        return (bool) rescue(function () use ($url) {
            $allowed = Http::timeout(10)->withHeaders(['Origin' => $this->origin()])->get($url)->header('Access-Control-Allow-Origin');

            return $allowed === '*' || $allowed === $this->origin();
        }, false, false);
    }

    /** The bucket CORS rules the admin needs: browsers on the site may GET/HEAD objects. */
    public static function corsRules(string $origin): array
    {
        return [[
            'AllowedOrigins' => [$origin],
            'AllowedMethods' => ['GET', 'HEAD'],
            'AllowedHeaders' => ['*'],
            'ExposeHeaders' => ['ETag', 'Content-Length', 'Content-Type'],
            'MaxAgeSeconds' => 3600,
        ]];
    }

    private function applyCors(Filesystem $disk, array $config): void
    {
        $rules = self::corsRules($this->origin());

        try {
            $disk->getClient()->putBucketCors([
                'Bucket' => $config['bucket'],
                'CORSConfiguration' => ['CORSRules' => $rules],
            ]);
            $this->components->twoColumnDetail('Apply CORS policy', '<fg=green>done</>');
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Apply CORS policy', '<fg=red>refused</>');
            $this->components->warn('The API token can\'t change bucket settings (R2 needs an Admin Read & Write token for this). '
                ."Add it in Cloudflare → R2 → {$config['bucket']} → Settings → CORS policy instead:");
            $this->line(json_encode(array_map(fn ($r) => [
                'AllowedOrigins' => $r['AllowedOrigins'],
                'AllowedMethods' => $r['AllowedMethods'],
                'AllowedHeaders' => $r['AllowedHeaders'],
                'ExposeHeaders' => $r['ExposeHeaders'],
                'MaxAgeSeconds' => $r['MaxAgeSeconds'],
            ], $rules), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
