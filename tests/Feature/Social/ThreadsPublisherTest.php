<?php

use App\Enums\SocialProvider;
use App\Services\Social\Providers\ThreadsPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public', ['url' => 'https://reno.sh/img']);
    Sleep::fake();
    config(['social.threads.graph_version' => 'v1.0', 'social.polling.attempts' => 3]);
});

it('publishes a text thread', function () {
    Http::fake([
        'graph.threads.net/v1.0/me/threads_publish' => Http::response(['id' => 't1']),
        'graph.threads.net/v1.0/me/threads' => Http::response(['id' => 'c1']),
        'graph.threads.net/v1.0/c1*' => Http::response(['status' => 'FINISHED']),
        'graph.threads.net/v1.0/t1*' => Http::response(['permalink' => 'https://www.threads.com/@reno/post/abc']),
    ]);

    $result = app(ThreadsPublisher::class)->publish(socialTarget(SocialProvider::THREADS, ['body' => 'Morning']));

    expect($result->providerPostId)->toBe('t1')->and($result->url)->toBe('https://www.threads.com/@reno/post/abc');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/me/threads') && $r['media_type'] === 'TEXT' && $r['text'] === 'Morning' && $r['access_token'] === 'threads-token');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/me/threads_publish') && $r['creation_id'] === 'c1');
});

it('publishes image and carousel threads', function () {
    Http::fake([
        'graph.threads.net/v1.0/me/threads_publish' => Http::response(['id' => 't2']),
        'graph.threads.net/v1.0/me/threads' => Http::sequence()->push(['id' => 'i1'])->push(['id' => 'i2'])->push(['id' => 'car']),
        'graph.threads.net/v1.0/*' => Http::response(['status' => 'FINISHED']),
    ]);

    app(ThreadsPublisher::class)->publish(socialTarget(SocialProvider::THREADS, ['body' => 'Two', 'media' => [socialImage('a.jpg'), socialImage('b.jpg')]]));

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/me/threads') && ($r['is_carousel_item'] ?? null) === 'true'
        && $r['image_url'] === 'https://reno.sh/img/a.jpg' && $r['media_type'] === 'IMAGE');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/me/threads') && $r['media_type'] === 'CAROUSEL' && $r['children'] === 'i1,i2');
});

it('refreshes the long-lived token', function () {
    Http::fake(['graph.threads.net/refresh_access_token*' => Http::response(['access_token' => 'fresh', 'token_type' => 'bearer', 'expires_in' => 5184000])]);

    $account = socialTarget(SocialProvider::THREADS)->account;

    expect(app(ThreadsPublisher::class)->refresh($account))->toBeTrue();
    expect($account->fresh()->credential('access_token'))->toBe('fresh')
        ->and($account->fresh()->token_expires_at->isAfter(now()->addDays(59)))->toBeTrue();
    Http::assertSent(fn (Request $r) => $r['grant_type'] === 'th_refresh_token' && $r['access_token'] === 'threads-token');
});

it('cannot refresh an expired token', function () {
    Http::fake(['graph.threads.net/*' => Http::response(['error' => ['message' => 'Session has expired', 'code' => 190]], 400)]);

    expect(app(ThreadsPublisher::class)->refresh(socialTarget(SocialProvider::THREADS)->account))->toBeFalse();
});

it('normalizes token and rate-limit errors', function (int $status, array $body, bool $reauth, bool $retry) {
    Http::fake(['graph.threads.net/*' => Http::response($body, $status)]);

    $e = expectPublishError(fn () => app(ThreadsPublisher::class)->publish(socialTarget(SocialProvider::THREADS)));

    expect($e->needsReauth)->toBe($reauth)->and($e->retryable)->toBe($retry);
})->with([
    'invalid token' => [400, ['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], true, false],
    'rate limited' => [429, ['error' => ['message' => 'Too many calls', 'code' => 4]], false, true],
]);
