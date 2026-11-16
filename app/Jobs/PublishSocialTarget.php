<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SocialTargetStatus;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\Social\SocialManager;
use App\Services\Social\SocialPublishException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publishes one post to one account.
 *
 * pending → publishing → published | failed. Retryable errors (429, 5xx,
 * network) go back to pending and are retried with backoff; non-retryable
 * SocialPublishExceptions fail the target immediately. Reauth errors also
 * flag the account so the admin can reconnect it.
 */
class PublishSocialTarget implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** Unique lock lifetime (seconds); covers the full retry window. */
    public int $uniqueFor = 3600;

    public function __construct(public SocialPostTarget $target) {}

    public function uniqueId(): string
    {
        return (string) $this->target->getKey();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(SocialManager $manager): void
    {
        $target = SocialPostTarget::query()->with(['post', 'account'])->find($this->target->getKey());

        if ($target === null || $target->status === SocialTargetStatus::PUBLISHED) {
            return;
        }

        $account = $target->account;

        if (! $account->is_active || $account->needs_reauth) {
            $this->failTarget($target, $account->needs_reauth
                ? 'The account needs to be reconnected before it can publish.'
                : 'The account is disabled.');

            return;
        }

        $target->forceFill([
            'status' => SocialTargetStatus::PUBLISHING,
            'attempts' => $target->attempts + 1,
            'error' => null,
        ])->save();

        $publisher = $manager->driver($account->provider);

        try {
            if ($account->tokenExpiresSoon() && ! $publisher->refresh($account)) {
                throw new SocialPublishException('The access token expired and could not be refreshed. Reconnect the account.', needsReauth: true);
            }

            $result = $publisher->publish($target);
        } catch (SocialPublishException $e) {
            if ($e->needsReauth) {
                $this->flagReauth($account, $e->getMessage());
            }

            if ($e->retryable && ! $e->needsReauth) {
                $this->releaseForRetry($target, $e);
            }

            $this->failTarget($target, $e->getMessage());
            $this->fail($e);

            return;
        } catch (Throwable $e) {
            $this->releaseForRetry($target, $e);
        }

        $target->forceFill([
            'status' => SocialTargetStatus::PUBLISHED,
            'provider_post_id' => $result->providerPostId,
            'provider_url' => $result->url,
            'error' => null,
            'published_at' => now(),
        ])->save();

        if ($account->last_error !== null) {
            $account->forceFill(['last_error' => null])->save();
        }

        $target->post->refreshStatus();
    }

    /** Retries exhausted (or the job was failed explicitly). */
    public function failed(?Throwable $e): void
    {
        $target = SocialPostTarget::query()->with('post')->find($this->target->getKey());

        if ($target === null || $target->status === SocialTargetStatus::PUBLISHED) {
            return;
        }

        $this->failTarget($target, $e?->getMessage() ?: 'Publishing failed.');
    }

    /**
     * Put the target back to pending with the error visible, then rethrow so
     * the queue retries (and calls failed() once tries run out).
     *
     * @throws Throwable
     */
    private function releaseForRetry(SocialPostTarget $target, Throwable $e): never
    {
        $target->forceFill([
            'status' => SocialTargetStatus::PENDING,
            'error' => Str::limit($e->getMessage(), 2000),
        ])->save();

        $target->post->refreshStatus();

        throw $e;
    }

    private function failTarget(SocialPostTarget $target, string $message): void
    {
        $target->forceFill([
            'status' => SocialTargetStatus::FAILED,
            'error' => Str::limit($message, 2000),
        ])->save();

        $target->post->refreshStatus();
    }

    private function flagReauth(SocialAccount $account, string $message): void
    {
        $account->forceFill([
            'needs_reauth' => true,
            'last_error' => Str::limit($message, 2000),
        ])->save();
    }
}
