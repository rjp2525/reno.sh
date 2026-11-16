<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use App\Services\Social\SocialScheduler;
use Illuminate\Console\Command;

class DispatchDueSocialPosts extends Command
{
    protected $signature = 'social:dispatch-due {--limit=100 : Maximum posts to dispatch per run}';

    protected $description = 'Queue publishing for scheduled social posts whose time has come';

    public function handle(SocialScheduler $scheduler): int
    {
        $ids = SocialPost::query()->due()->orderBy('scheduled_at')->limit((int) $this->option('limit'))->pluck('id');
        $dispatched = 0;

        foreach ($ids as $id) {
            // Atomic claim: only the runner that flips scheduled → publishing dispatches it,
            // so overlapping runs (or multiple servers) never double-post.
            $claimed = SocialPost::query()
                ->whereKey($id)
                ->where('status', SocialPostStatus::SCHEDULED)
                ->update(['status' => SocialPostStatus::PUBLISHING, 'updated_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            $post = SocialPost::query()->findOrFail($id);
            $jobs = $scheduler->dispatch($post);

            if ($jobs === 0) {
                // No targets left to send; settle the status instead of leaving it "publishing".
                $post->refreshStatus();
            }

            $dispatched++;
            $this->components->info("Dispatched post {$id} ({$jobs} target(s)).");
        }

        if ($dispatched === 0) {
            $this->components->info('No social posts due.');
        }

        return self::SUCCESS;
    }
}
