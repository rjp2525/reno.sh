<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPhotoJob;
use App\Models\Photo;
use Illuminate\Console\Command;

/** Regenerate the public versions of existing photos, e.g. after changing photo settings. */
class ReprocessPhotos extends Command
{
    protected $signature = 'photos:reprocess
        {--all : Every photo, not just ones whose web version is still a JPEG}
        {--sync : Process now instead of queueing}';

    protected $description = 'Regenerate web/thumbnail/OG versions of photos (WebP), replacing the old files';

    public function handle(): int
    {
        $query = Photo::query()->whereNotNull('original_path')
            ->unless($this->option('all'), fn ($q) => $q->where(fn ($q) => $q->whereNull('web_path')->orWhere('web_path', 'not like', '%.webp')));

        $count = $query->count();
        if ($count === 0) {
            $this->components->info('Nothing to reprocess.');

            return self::SUCCESS;
        }

        $this->components->info(($this->option('sync') ? 'Processing' : 'Queueing')." {$count} photo(s)…");

        $query->lazyById()->each(fn (Photo $photo) => $this->option('sync')
            ? ProcessPhotoJob::dispatchSync($photo)
            : ProcessPhotoJob::dispatch($photo));

        $this->components->info($this->option('sync') ? 'Done.' : 'Queued. The queue worker will pick them up.');

        return self::SUCCESS;
    }
}
