<?php

use App\Enums\SocialProvider;
use App\Services\Social\Providers\XPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public');
    config(['social.x.client_id' => 'x-client', 'social.x.client_secret' => 'x-secret']);
});

it('posts a tweet', function () {
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '1850', 'text' => 'hi']], 201)]);

    $result = app(XPublisher::class)->publish(socialTarget(SocialProvider::X, ['body' => 'Hello X']));

    expect($result->providerPostId)->toBe('1850')->and($result->url)->toBe('https://x.com/renosh/status/1850');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.x.com/2/tweets'
        && $r->hasHeader('Authorization', 'Bearer x-token')
        && $r->data() === ['text' => 'Hello X']);
});

it('uploads media through the v2 media endpoint', function () {
    Http::fake([
        'api.x.com/2/media/upload' => Http::sequence()->push(['data' => ['id' => 'm1']])->push(['data' => ['id' => 'm2']]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '1851']], 201),
    ]);

    app(XPublisher::class)->publish(socialTarget(SocialProvider::X, ['body' => 'Pics', 'media' => [socialImage('a.jpg'), socialImage('b.jpg')]]));

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/2/media/upload') && $r->isMultipart()
        && collect($r->data())->contains(fn ($p) => $p['name'] === 'media_category' && $p['contents'] === 'tweet_image'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/2/tweets') && $r['media'] === ['media_ids' => ['m1', 'm2']]);
});

it('refuses tweets over 280 characters (URLs count as 23)', function () {
    Http::fake();

    $body = str_repeat('a', 250).' https://example.com/'.str_repeat('x', 100);
    $ok = socialTarget(SocialProvider::X, ['body' => $body]);
    expect(SocialProvider::X->length($body))->toBe(274);

    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '1']], 201)]);
    app(XPublisher::class)->publish($ok);

    $e = expectPublishError(fn () => app(XPublisher::class)->publish(socialTarget(SocialProvider::X, ['body' => str_repeat('é', 281)])));
    expect($e->getMessage())->toContain('allows 280 characters; this post is 281')->and($e->retryable)->toBeFalse();
});

it('refreshes and rotates tokens', function () {
    Http::fake(['api.x.com/2/oauth2/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 7200])]);

    $target = socialTarget(SocialProvider::X);
    $account = $target->account;

    expect(app(XPublisher::class)->refresh($account))->toBeTrue();

    $account->refresh();
    expect($account->credential('access_token'))->toBe('new-access')
        ->and($account->credential('refresh_token'))->toBe('new-refresh')
        ->and($account->token_expires_at->between(now()->addMinutes(119), now()->addMinutes(121)))->toBeTrue();

    Http::assertSent(fn (Request $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'x-refresh'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('x-client:x-secret')));
});

it('reports a revoked refresh token as needing reconnect', function () {
    Http::fake(['api.x.com/2/oauth2/token' => Http::response(['error' => 'invalid_request', 'error_description' => 'Value passed for the token was invalid.'], 400)]);

    expect(app(XPublisher::class)->refresh(socialTarget(SocialProvider::X)->account))->toBeFalse();
});

it('normalizes 401 and 429', function (int $status, bool $reauth, bool $retry) {
    Http::fake(['api.x.com/*' => Http::response(['title' => 'Err', 'detail' => 'Something', 'status' => $status], $status)]);

    $e = expectPublishError(fn () => app(XPublisher::class)->publish(socialTarget(SocialProvider::X)));

    expect($e->needsReauth)->toBe($reauth)->and($e->retryable)->toBe($retry)->and($e->getMessage())->toContain('Something');
})->with([
    'unauthorized' => [401, true, false],
    'too many requests' => [429, false, true],
    'duplicate content' => [403, false, false],
]);
