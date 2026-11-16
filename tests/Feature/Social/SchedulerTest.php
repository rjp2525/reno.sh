<?php

use App\Enums\SocialPostStatus;
use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use App\Jobs\PublishSocialTarget;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\Social\SocialScheduler;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/Support.php';

beforeEach(fn () => Queue::fake());

it('queues one job per unpublished target and resets them to pending', function () {
    $post = SocialPost::factory()->create();
    $pending = SocialPostTarget::factory()->for($post, 'post')->create();
    $failed = SocialPostTarget::factory()->for($post, 'post')->failed()->create();
    SocialPostTarget::factory()->for($post, 'post')->published()->create();

    expect(app(SocialScheduler::class)->dispatch($post))->toBe(2);

    Queue::assertPushed(PublishSocialTarget::class, 2);
    Queue::assertPushed(PublishSocialTarget::class, fn ($job) => $job->target->is($failed));
    Queue::assertPushed(PublishSocialTarget::class, fn ($job) => $job->target->is($pending));
    expect($failed->fresh()->status)->toBe(SocialTargetStatus::PENDING)
        ->and($failed->fresh()->error)->toBeNull()
        ->and($post->fresh()->status)->toBe(SocialPostStatus::PUBLISHING);
});

it('can retry only the failed targets', function () {
    $post = SocialPost::factory()->create(['status' => SocialPostStatus::PARTIALLY_PUBLISHED]);
    SocialPostTarget::factory()->for($post, 'post')->published()->create();
    $failed = SocialPostTarget::factory()->for($post, 'post')->failed()->create();
    SocialPostTarget::factory()->for($post, 'post')->create(); // pending, untouched

    expect(app(SocialScheduler::class)->dispatch($post, onlyFailed: true))->toBe(1);

    Queue::assertPushed(PublishSocialTarget::class, 1);
    Queue::assertPushed(PublishSocialTarget::class, fn ($job) => $job->target->is($failed));
});

it('dispatches due posts exactly once', function () {
    $due = SocialPost::factory()->scheduled(now()->subMinutes(2))->create();
    SocialPostTarget::factory()->count(2)->for($due, 'post')->create();
    $future = SocialPost::factory()->scheduled(now()->addHour())->create();
    SocialPostTarget::factory()->for($future, 'post')->create();
    $draft = SocialPost::factory()->create();
    SocialPostTarget::factory()->for($draft, 'post')->create();

    $this->artisan('social:dispatch-due')->assertSuccessful();
    $this->artisan('social:dispatch-due')->assertSuccessful();

    Queue::assertPushed(PublishSocialTarget::class, 2);
    expect($due->fresh()->status)->toBe(SocialPostStatus::PUBLISHING)
        ->and($future->fresh()->status)->toBe(SocialPostStatus::SCHEDULED);
});

it('skips a due post another runner claims between select and claim', function () {
    $post = SocialPost::factory()->scheduled()->create();
    SocialPostTarget::factory()->for($post, 'post')->create();

    // Simulate a concurrent runner: right after our "due" select runs, someone else claims the post.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use ($post, &$raced) {
        if (! $raced && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"social_posts"')) {
            $raced = true;
            DB::table('social_posts')->where('id', $post->id)->update(['status' => SocialPostStatus::PUBLISHING->value]);
        }
    });

    $this->artisan('social:dispatch-due')->assertSuccessful();

    expect($raced)->toBeTrue();
    Queue::assertNothingPushed();
});

it('settles a due post whose targets were all published already', function () {
    $post = SocialPost::factory()->scheduled()->create();
    SocialPostTarget::factory()->for($post, 'post')->published()->create();

    $this->artisan('social:dispatch-due')->assertSuccessful();

    Queue::assertNothingPushed();
    expect($post->fresh()->status)->toBe(SocialPostStatus::PUBLISHED);
});

it('refreshes tokens expiring within 7 days', function () {
    config(['social.x.client_id' => 'cid', 'social.x.client_secret' => null]);
    Http::fake(['api.x.com/2/oauth2/token' => Http::sequence()
        ->push(['access_token' => 'new', 'refresh_token' => 'r2', 'expires_in' => 7200])
        ->push(['error' => 'invalid_request'], 400)]);

    $ok = SocialAccount::factory()->x()->create(['token_expires_at' => now()->addDays(3)]);
    $revoked = SocialAccount::factory()->x()->create(['token_expires_at' => now()->addDays(5)]);
    $later = SocialAccount::factory()->provider(SocialProvider::THREADS)->create(['token_expires_at' => now()->addDays(30)]);
    $never = SocialAccount::factory()->mastodon()->create();

    $this->artisan('social:refresh-tokens')->assertSuccessful();

    expect($ok->fresh()->credential('access_token'))->toBe('new')
        ->and($ok->fresh()->needs_reauth)->toBeFalse()
        ->and($revoked->fresh()->needs_reauth)->toBeTrue()
        ->and($later->fresh()->credential('access_token'))->toBe('threads-token')
        ->and($never->fresh()->needs_reauth)->toBeFalse();
    Http::assertSentCount(2);
});
