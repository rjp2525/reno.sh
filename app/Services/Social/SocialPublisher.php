<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPostTarget;

/**
 * One implementation per network (app/Services/Social/Providers/*).
 * Resolved through SocialManager::driver(SocialProvider).
 */
interface SocialPublisher
{
    /**
     * Publish the target's resolved body + its post's media as the target's account.
     *
     * Throw SocialPublishException for expected API failures (set $needsReauth when
     * the token is revoked/expired beyond refresh); anything else is retried by the job.
     */
    public function publish(SocialPostTarget $target): PublishResult;

    /** Refresh tokens if the provider supports it. Return false if the account must reconnect. */
    public function refresh(SocialAccount $account): bool;
}
