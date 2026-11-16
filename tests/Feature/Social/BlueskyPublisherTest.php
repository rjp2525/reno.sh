<?php

use App\Enums\SocialProvider;
use App\Services\Social\Providers\BlueskyPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public');
    $this->session = fn () => Http::response([
        'accessJwt' => fakeJwt(now()->addHours(2)->getTimestamp()),
        'refreshJwt' => fakeJwt(now()->addDays(60)->getTimestamp()),
        'did' => 'did:plc:reno',
        'handle' => 'reno.bsky.social',
    ]);
});

it('logs in with the app password, posts with facets and caches the session', function () {
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => ($this->session)(),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:reno/app.bsky.feed.post/3kabc', 'cid' => 'bafy']),
    ]);

    $target = socialTarget(SocialProvider::BLUESKY, ['body' => 'Hi 👋 #photo']);
    $result = app(BlueskyPublisher::class)->publish($target);

    expect($result->providerPostId)->toBe('at://did:plc:reno/app.bsky.feed.post/3kabc')
        ->and($result->url)->toBe('https://bsky.app/profile/reno.bsky.social/post/3kabc');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'createSession')
        && $r['identifier'] === 'reno.bsky.social' && $r['password'] === 'abcd-efgh-ijkl-mnop');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'createRecord')
        && $r['repo'] === 'did:plc:reno'
        && $r['collection'] === 'app.bsky.feed.post'
        && $r['record']['text'] === 'Hi 👋 #photo'
        && $r['record']['facets'] === [[
            'index' => ['byteStart' => 8, 'byteEnd' => 14],
            'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => 'photo']],
        ]]
        && ! isset($r['record']['embed']));

    expect($target->account->fresh()->credential('did'))->toBe('did:plc:reno');

    // Second publish reuses the cached access JWT.
    app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY, ['body' => 'again'], ['credentials' => $target->account->fresh()->credentials]));
    Http::assertSentCount(3);
});

it('uploads images as blobs', function () {
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => ($this->session)(),
        'bsky.social/xrpc/com.atproto.repo.uploadBlob' => Http::response(['blob' => ['$type' => 'blob', 'ref' => ['$link' => 'bafkrei'], 'mimeType' => 'image/jpeg', 'size' => 10]]),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:reno/app.bsky.feed.post/3kimg']),
    ]);

    app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY, [
        'body' => 'Pic https://reno.sh/p/1', 'link_url' => 'https://reno.sh/p/1', 'media' => [socialImage()],
    ]));

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'uploadBlob') && $r->body() === 'JPEG-BYTES' && $r->hasHeader('Content-Type', 'image/jpeg'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'createRecord')
        && $r['record']['embed']['$type'] === 'app.bsky.embed.images'
        && $r['record']['embed']['images'][0]['image']['ref']['$link'] === 'bafkrei');
});

it('adds an external link card when there is a link and no images', function () {
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => ($this->session)(),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:reno/app.bsky.feed.post/3klink']),
        'reno.sh/blog/post' => Http::response('<html><head><meta property="og:title" content="Big &amp; Bold"><meta name="description" content="A post"></head></html>'),
    ]);

    app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY, ['body' => 'New post', 'link_url' => 'https://reno.sh/blog/post']));

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'createRecord')
        && $r['record']['text'] === "New post\n\nhttps://reno.sh/blog/post"
        && $r['record']['embed'] === ['$type' => 'app.bsky.embed.external', 'external' => [
            'uri' => 'https://reno.sh/blog/post', 'title' => 'Big & Bold', 'description' => 'A post',
        ]]
        && $r['record']['facets'][0]['features'][0]['uri'] === 'https://reno.sh/blog/post');
});

it('re-establishes the session once on ExpiredToken', function () {
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.refreshSession' => ($this->session)(),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::sequence()
            ->push(['error' => 'ExpiredToken', 'message' => 'Token has expired'], 400)
            ->push(['uri' => 'at://did:plc:reno/app.bsky.feed.post/3kre']),
    ]);

    $target = socialTarget(SocialProvider::BLUESKY, ['body' => 'retry'], ['credentials' => [
        'handle' => 'reno.bsky.social', 'app_password' => 'pw', 'did' => 'did:plc:reno',
        'access_jwt' => fakeJwt(now()->addHour()->getTimestamp()),
        'refresh_jwt' => fakeJwt(now()->addDays(30)->getTimestamp()),
    ]]);

    expect(app(BlueskyPublisher::class)->publish($target)->providerPostId)->toEndWith('/3kre');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'refreshSession'));
    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), 'createSession'));
});

it('flags reauth when the app password is rejected', function () {
    Http::fake(['bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['error' => 'AuthenticationRequired', 'message' => 'Invalid identifier or password'], 401)]);

    $e = expectPublishError(fn () => app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY)));

    expect($e->needsReauth)->toBeTrue()->and($e->getMessage())->toContain('Invalid identifier or password');
    expect(app(BlueskyPublisher::class)->refresh(socialTarget(SocialProvider::BLUESKY)->account))->toBeFalse();
});

it('marks rate limits as retryable', function () {
    Http::fake([
        'bsky.social/xrpc/com.atproto.server.createSession' => ($this->session)(),
        'bsky.social/xrpc/com.atproto.repo.createRecord' => Http::response(['error' => 'RateLimitExceeded', 'message' => 'Rate Limit Exceeded'], 429),
    ]);

    $e = expectPublishError(fn () => app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY)));

    expect($e->retryable)->toBeTrue()->and($e->needsReauth)->toBeFalse();
});

it('rejects images over the blob size limit', function () {
    Http::fake(['bsky.social/xrpc/com.atproto.server.createSession' => ($this->session)()]);

    $e = expectPublishError(fn () => app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY, [
        'media' => [socialImage('big.jpg', str_repeat('x', 1_000_001))],
    ])));

    expect($e->getMessage())->toContain('under 1,000 KB')->and($e->retryable)->toBeFalse();
});

it('honours a custom PDS', function () {
    Http::fake([
        'pds.example.org/xrpc/com.atproto.server.createSession' => ($this->session)(),
        'pds.example.org/xrpc/com.atproto.repo.createRecord' => Http::response(['uri' => 'at://did:plc:reno/app.bsky.feed.post/3kpds']),
    ]);

    app(BlueskyPublisher::class)->publish(socialTarget(SocialProvider::BLUESKY, ['body' => 'x'], ['credentials' => [
        'handle' => 'me.example.org', 'app_password' => 'pw', 'pds_url' => 'https://pds.example.org/',
    ]]));

    Http::assertSent(fn (Request $r) => $r->url() === 'https://pds.example.org/xrpc/com.atproto.repo.createRecord');
});
