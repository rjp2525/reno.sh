<?php

use App\Enums\SocialProvider;
use App\Services\Social\Providers\MastodonPublisher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public');
    Sleep::fake();
});

it('uploads media and posts a status with an idempotency key', function () {
    Http::fake([
        'mastodon.example/api/v2/media' => Http::sequence()
            ->push(['id' => 'm1', 'url' => 'https://files/m1.jpg'])
            ->push(['id' => 'm2', 'url' => null], 202),
        'mastodon.example/api/v1/media/m2' => Http::sequence()->push(['id' => 'm2', 'url' => null], 206)->push(['id' => 'm2', 'url' => 'https://files/m2.jpg']),
        'mastodon.example/api/v1/statuses' => Http::response(['id' => '1100', 'url' => 'https://mastodon.example/@reno/1100']),
    ]);

    $target = socialTarget(SocialProvider::MASTODON, ['body' => 'Toot', 'media' => [socialImage('a.jpg'), socialImage('b.jpg')]]);
    $result = app(MastodonPublisher::class)->publish($target);

    expect($result->providerPostId)->toBe('1100')->and($result->url)->toBe('https://mastodon.example/@reno/1100');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/v1/statuses')
        && $r->hasHeader('Idempotency-Key', $target->id)
        && $r->hasHeader('Authorization', 'Bearer masto-token')
        && $r['status'] === 'Toot' && $r['media_ids'] === ['m1', 'm2'] && $r['visibility'] === 'public');
    Http::assertSentCount(5);
});

it('normalizes 401 and 429', function (int $status, bool $reauth, bool $retry) {
    Http::fake(['mastodon.example/*' => Http::response(['error' => 'The access token is invalid'], $status)]);

    $e = expectPublishError(fn () => app(MastodonPublisher::class)->publish(socialTarget(SocialProvider::MASTODON)));

    expect($e->needsReauth)->toBe($reauth)->and($e->retryable)->toBe($retry)->and($e->getMessage())->toContain('The access token is invalid');
})->with([
    'invalid token' => [401, true, false],
    'rate limited' => [429, false, true],
    'validation' => [422, false, false],
]);

it('treats connection failures as retryable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $e = expectPublishError(fn () => app(MastodonPublisher::class)->publish(socialTarget(SocialProvider::MASTODON)));

    expect($e->retryable)->toBeTrue();
});
