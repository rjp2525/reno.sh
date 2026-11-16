<?php

use App\Enums\SocialPostStatus;
use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use App\Jobs\PublishSocialTarget;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\Social\SocialManager;
use App\Services\Social\SocialPublishException;
use Illuminate\Contracts\Queue\ShouldBeUnique;

require_once __DIR__.'/Support.php';

function fakeDriver(?Throwable $throw = null, bool $refresh = true): FakeSocialPublisher
{
    $fake = new FakeSocialPublisher($throw, $refresh);
    app(SocialManager::class)->extend(SocialProvider::MASTODON, fn () => $fake);

    return $fake;
}

function runJob(SocialPostTarget $target): void
{
    app()->call([new PublishSocialTarget($target), 'handle']);
}

beforeEach(function () {
    app()->singleton(SocialManager::class);
});

it('is unique per target with bounded retries', function () {
    $target = SocialPostTarget::factory()->create();
    $job = new PublishSocialTarget($target);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe($target->id)
        ->and($job->tries)->toBe(4)
        ->and($job->backoff())->toHaveCount(3);
});

it('marks the target published and rolls up the post', function () {
    $fake = fakeDriver();
    $post = SocialPost::factory()->create(['status' => SocialPostStatus::PUBLISHING]);
    $target = SocialPostTarget::factory()->for($post, 'post')->create();

    runJob($target);

    $target->refresh();
    expect($target->status)->toBe(SocialTargetStatus::PUBLISHED)
        ->and($target->provider_post_id)->toBe('remote-'.$target->id)
        ->and($target->provider_url)->toBe('https://example.test/p/'.$target->id)
        ->and($target->attempts)->toBe(1)
        ->and($target->published_at)->not->toBeNull()
        ->and($post->fresh()->status)->toBe(SocialPostStatus::PUBLISHED)
        ->and($fake->publishes)->toBe(1);
});

it('does not republish an already published target', function () {
    $fake = fakeDriver();
    $target = SocialPostTarget::factory()->published()->create();

    runJob($target);

    expect($fake->publishes)->toBe(0);
});

it('fails immediately on non-retryable errors', function () {
    fakeDriver(new SocialPublishException('X allows 280 characters; this post is 300.'));
    $target = SocialPostTarget::factory()->create();

    runJob($target);

    $target->refresh();
    expect($target->status)->toBe(SocialTargetStatus::FAILED)
        ->and($target->error)->toContain('280 characters')
        ->and($target->attempts)->toBe(1)
        ->and($target->post->status)->toBe(SocialPostStatus::FAILED)
        ->and($target->account->needs_reauth)->toBeFalse();
});

it('flags the account on reauth errors', function () {
    fakeDriver(new SocialPublishException('Token revoked', needsReauth: true));
    $target = SocialPostTarget::factory()->create();

    runJob($target);

    $account = $target->account->fresh();
    expect($target->fresh()->status)->toBe(SocialTargetStatus::FAILED)
        ->and($account->needs_reauth)->toBeTrue()
        ->and($account->last_error)->toBe('Token revoked');
});

it('rethrows retryable errors and leaves the target pending', function () {
    fakeDriver(new SocialPublishException('Rate limited', retryable: true));
    $target = SocialPostTarget::factory()->create();

    expect(fn () => runJob($target))->toThrow(SocialPublishException::class, 'Rate limited');

    $target->refresh();
    expect($target->status)->toBe(SocialTargetStatus::PENDING)
        ->and($target->error)->toBe('Rate limited')
        ->and($target->attempts)->toBe(1)
        ->and($target->post->status)->toBe(SocialPostStatus::PUBLISHING);

    // Retries exhausted.
    (new PublishSocialTarget($target))->failed(new SocialPublishException('Rate limited', retryable: true));

    expect($target->fresh()->status)->toBe(SocialTargetStatus::FAILED)
        ->and($target->post->fresh()->status)->toBe(SocialPostStatus::FAILED);
});

it('retries unexpected exceptions', function () {
    fakeDriver(new RuntimeException('boom'));
    $target = SocialPostTarget::factory()->create();

    expect(fn () => runJob($target))->toThrow(RuntimeException::class);
    expect($target->fresh()->status)->toBe(SocialTargetStatus::PENDING);
});

it('refreshes expiring tokens before publishing', function () {
    $fake = fakeDriver();
    $account = SocialAccount::factory()->mastodon()->expiringSoon()->create();
    $target = SocialPostTarget::factory()->for($account, 'account')->create();

    runJob($target);

    expect($fake->refreshes)->toBe(1)->and($fake->publishes)->toBe(1)
        ->and($target->fresh()->status)->toBe(SocialTargetStatus::PUBLISHED);
});

it('requires reconnect when refresh fails', function () {
    $fake = fakeDriver(refresh: false);
    $account = SocialAccount::factory()->mastodon()->expiringSoon()->create();
    $target = SocialPostTarget::factory()->for($account, 'account')->create();

    runJob($target);

    expect($fake->publishes)->toBe(0)
        ->and($account->fresh()->needs_reauth)->toBeTrue()
        ->and($target->fresh()->status)->toBe(SocialTargetStatus::FAILED);
});

it('skips accounts that already need reauth', function () {
    $fake = fakeDriver();
    $target = SocialPostTarget::factory()->for(SocialAccount::factory()->needsReauth(), 'account')->create();

    runJob($target);

    expect($fake->publishes)->toBe(0)
        ->and($target->fresh()->status)->toBe(SocialTargetStatus::FAILED)
        ->and($target->fresh()->error)->toContain('reconnected');
});

it('rolls up partial success', function () {
    fakeDriver();
    $post = SocialPost::factory()->create(['status' => SocialPostStatus::PUBLISHING]);
    SocialPostTarget::factory()->for($post, 'post')->failed()->create();
    $target = SocialPostTarget::factory()->for($post, 'post')->create();

    runJob($target);

    expect($post->fresh()->status)->toBe(SocialPostStatus::PARTIALLY_PUBLISHED)
        ->and($post->fresh()->published_at)->not->toBeNull();
});

it('publishes end to end through the sync queue', function () {
    fakeDriver(new SocialPublishException('Bad request'));
    $target = SocialPostTarget::factory()->create();

    PublishSocialTarget::dispatch($target);

    expect($target->fresh()->status)->toBe(SocialTargetStatus::FAILED)
        ->and($target->fresh()->attempts)->toBe(1);
});
