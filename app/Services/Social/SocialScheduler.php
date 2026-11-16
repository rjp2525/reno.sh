<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use App\Jobs\PublishSocialTarget;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Illuminate\Support\Facades\DB;

class SocialScheduler
{
    /**
     * Queue one publish job per target. Already-published targets are never
     * re-sent; with $onlyFailed only failed targets are retried.
     *
     * @return int Number of jobs queued.
     */
    public function dispatch(SocialPost $post, bool $onlyFailed = false): int
    {
        $targets = DB::transaction(function () use ($post, $onlyFailed) {
            $query = $post->targets()->lockForUpdate();

            $onlyFailed
                ? $query->where('status', SocialTargetStatus::FAILED)
                : $query->whereIn('status', [SocialTargetStatus::PENDING, SocialTargetStatus::FAILED]);

            $targets = $query->get();

            if ($targets->isEmpty()) {
                return $targets;
            }

            SocialPostTarget::query()->whereKey($targets->modelKeys())->update([
                'status' => SocialTargetStatus::PENDING,
                'error' => null,
                'updated_at' => now(),
            ]);

            $post->forceFill(['status' => SocialPostStatus::PUBLISHING])->save();

            return $targets;
        });

        foreach ($targets as $target) {
            PublishSocialTarget::dispatch($target->setRelation('post', $post));
        }

        return $targets->count();
    }
}
