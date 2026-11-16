<?php

use App\Enums\SocialProvider;
use App\Services\Social\Providers\InstagramPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public', ['url' => 'https://reno.sh/img']);
    Sleep::fake();
    config(['social.meta.graph_version' => 'v24.0', 'social.meta.app_secret' => null, 'social.polling.attempts' => 3]);
});

it('publishes a single image: container, poll, publish, permalink', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/media_publish' => Http::response(['id' => 'm1']),
        'graph.facebook.com/v24.0/*/media' => Http::response(['id' => 'c1']),
        'graph.facebook.com/v24.0/c1*' => Http::sequence()->push(['status_code' => 'IN_PROGRESS'])->push(['status_code' => 'FINISHED']),
        'graph.facebook.com/v24.0/m1*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
    ]);

    $target = socialTarget(SocialProvider::INSTAGRAM, ['body' => 'Golden hour #sunset', 'media' => [socialImage()]]);
    $result = app(InstagramPublisher::class)->publish($target);

    expect($result->providerPostId)->toBe('m1')->and($result->url)->toBe('https://www.instagram.com/p/abc/');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/{$target->account->provider_id}/media")
        && $r['image_url'] === 'https://reno.sh/img/social/photo.jpg'
        && $r['caption'] === 'Golden hour #sunset');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'c1');
    Sleep::assertSleptTimes(1);
});

it('builds a carousel from child containers', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/media_publish' => Http::response(['id' => 'm2']),
        'graph.facebook.com/v24.0/*/media' => Http::sequence()->push(['id' => 'k1'])->push(['id' => 'k2'])->push(['id' => 'car']),
        'graph.facebook.com/v24.0/k*' => Http::response(['status_code' => 'FINISHED']),
        'graph.facebook.com/v24.0/car*' => Http::response(['status_code' => 'FINISHED']),
        'graph.facebook.com/v24.0/m2*' => Http::response(['permalink' => 'https://www.instagram.com/p/car/']),
    ]);

    $target = socialTarget(SocialProvider::INSTAGRAM, ['body' => 'Set', 'media' => [socialImage('a.jpg'), socialImage('b.jpg')]]);
    app(InstagramPublisher::class)->publish($target);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media') && ($r['is_carousel_item'] ?? null) === 'true');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media')
        && ($r['media_type'] ?? null) === 'CAROUSEL' && $r['children'] === 'k1,k2' && $r['caption'] === 'Set');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'car');
});

it('requires media', function () {
    Http::fake();

    $e = expectPublishError(fn () => app(InstagramPublisher::class)->publish(socialTarget(SocialProvider::INSTAGRAM, ['body' => 'Text only'])));

    expect($e->getMessage())->toContain('require at least one image')->and($e->retryable)->toBeFalse();
    Http::assertNothingSent();
});

it('fails clearly when media URLs are not public', function () {
    Storage::fake('public', ['url' => 'http://localhost:8088/img']);
    Http::fake();

    $e = expectPublishError(fn () => app(InstagramPublisher::class)->publish(socialTarget(SocialProvider::INSTAGRAM, ['media' => [socialImage()]])));

    expect($e->getMessage())->toContain('not reachable from the internet');
    Http::assertNothingSent();
});

it('fails without retry when the container errors', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/media' => Http::response(['id' => 'c1']),
        'graph.facebook.com/v24.0/c1*' => Http::response(['status_code' => 'ERROR', 'status' => 'Error: unsupported image']),
    ]);

    $e = expectPublishError(fn () => app(InstagramPublisher::class)->publish(socialTarget(SocialProvider::INSTAGRAM, ['media' => [socialImage()]])));

    expect($e->retryable)->toBeFalse()->and($e->getMessage())->toContain('unsupported image');
});

it('gives up polling after the configured attempts (retryable)', function () {
    Http::fake([
        'graph.facebook.com/v24.0/*/media' => Http::response(['id' => 'c1']),
        'graph.facebook.com/v24.0/c1*' => Http::response(['status_code' => 'IN_PROGRESS']),
    ]);

    $e = expectPublishError(fn () => app(InstagramPublisher::class)->publish(socialTarget(SocialProvider::INSTAGRAM, ['media' => [socialImage()]])));

    expect($e->retryable)->toBeTrue();
    Sleep::assertSleptTimes(2);
});

it('flags reauth on expired tokens and retries rate limits', function (int $status, array $body, bool $reauth, bool $retry) {
    Http::fake(['graph.facebook.com/*' => Http::response($body, $status)]);

    $e = expectPublishError(fn () => app(InstagramPublisher::class)->publish(socialTarget(SocialProvider::INSTAGRAM, ['media' => [socialImage()]])));

    expect($e->needsReauth)->toBe($reauth)->and($e->retryable)->toBe($retry);
})->with([
    'expired token' => [400, ['error' => ['message' => 'Session has expired', 'code' => 190, 'error_subcode' => 463]], true, false],
    'rate limited' => [400, ['error' => ['message' => 'User request limit reached', 'code' => 17]], false, true],
    'http 429' => [429, [], false, true],
]);
