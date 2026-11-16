<?php

use App\Enums\SocialProvider;
use App\Services\Social\Providers\FacebookPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public');
    config(['social.meta.graph_version' => 'v24.0', 'social.meta.app_secret' => 'secret']);
});

it('posts text with a link to the page feed', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/feed' => Http::response(['id' => '111_222']),
        'graph.facebook.com/v24.0/111_222*' => Http::response(['permalink_url' => 'https://www.facebook.com/reno/posts/222']),
    ]);

    $target = socialTarget(SocialProvider::FACEBOOK, ['body' => 'New photos', 'link_url' => 'https://reno.sh/p/1']);

    $result = app(FacebookPublisher::class)->publish($target);

    expect($result->providerPostId)->toBe('111_222')
        ->and($result->url)->toBe('https://www.facebook.com/reno/posts/222');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/{$target->account->provider_id}/feed")
        && $r['message'] === "New photos\n\nhttps://reno.sh/p/1"
        && $r['link'] === 'https://reno.sh/p/1'
        && $r['access_token'] === 'fb-page-token'
        && $r['appsecret_proof'] === hash_hmac('sha256', 'fb-page-token', 'secret'));
});

it('uploads a single image to /photos', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/photos' => Http::response(['id' => '999', 'post_id' => '111_333']),
        'graph.facebook.com/v24.0/111_333*' => Http::response([], 500),
    ]);

    $target = socialTarget(SocialProvider::FACEBOOK, ['body' => 'One pic', 'media' => [socialImage()]]);

    $result = app(FacebookPublisher::class)->publish($target);

    expect($result->providerPostId)->toBe('111_333')
        ->and($result->url)->toBe('https://www.facebook.com/111_333');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/photos') && $r->isMultipart()
        && collect($r->data())->contains(fn ($part) => $part['name'] === 'source' && $part['contents'] === 'JPEG-BYTES'));
});

it('creates unpublished photos and attaches them for multi-image posts', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/photos' => Http::sequence()->push(['id' => 'p1'])->push(['id' => 'p2']),
        'graph.facebook.com/v24.0/*/feed' => Http::response(['id' => '111_444']),
        'graph.facebook.com/v24.0/111_444*' => Http::response(['permalink_url' => 'https://fb.com/x']),
    ]);

    $target = socialTarget(SocialProvider::FACEBOOK, ['body' => 'Two pics', 'media' => [socialImage('a.jpg'), socialImage('b.jpg')]]);

    app(FacebookPublisher::class)->publish($target);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/photos')
        && collect($r->data())->contains(fn ($p) => $p['name'] === 'published' && $p['contents'] === 'false'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/feed')
        && $r['attached_media[0]'] === json_encode(['media_fbid' => 'p1'])
        && $r['attached_media[1]'] === json_encode(['media_fbid' => 'p2'])
        && str_contains($r->body(), 'attached_media%5B1%5D=')
        && ! isset($r['link']));
});

it('flags reauth on an invalid token (code 190)', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token', 'code' => 190]], 400)]);

    $e = expectPublishError(fn () => app(FacebookPublisher::class)->publish(socialTarget(SocialProvider::FACEBOOK)));

    expect($e->needsReauth)->toBeTrue()->and($e->retryable)->toBeFalse()
        ->and($e->getMessage())->toContain('Error validating access token');
});

it('marks rate limits and server errors as retryable', function (int $status, array $body) {
    Http::fake(['graph.facebook.com/*' => Http::response($body, $status)]);

    $e = expectPublishError(fn () => app(FacebookPublisher::class)->publish(socialTarget(SocialProvider::FACEBOOK)));

    expect($e->retryable)->toBeTrue()->and($e->needsReauth)->toBeFalse();
})->with([
    'app rate limit' => [400, ['error' => ['message' => 'Application request limit reached', 'code' => 4]]],
    'http 429' => [429, []],
    'server error' => [503, []],
]);

it('rejects posts over the media limit without calling the API', function () {
    Http::fake();
    $media = array_map(fn ($i) => socialImage("p{$i}.jpg"), range(1, 11));

    $e = expectPublishError(fn () => app(FacebookPublisher::class)->publish(socialTarget(SocialProvider::FACEBOOK, ['media' => $media])));

    expect($e->getMessage())->toContain('10 media items')->and($e->retryable)->toBeFalse();
    Http::assertNothingSent();
});
