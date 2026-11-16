<?php

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\Social\Providers\LinkedInPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    Storage::fake('public');
    config(['social.linkedin.version' => '202610']);
});

it('creates a text post as the member with versioned headers', function () {
    Http::fake([
        'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:7001']),
    ]);

    $target = socialTarget(SocialProvider::LINKEDIN, ['body' => 'Hello (world) #photography']);
    $result = app(LinkedInPublisher::class)->publish($target);

    expect($result->providerPostId)->toBe('urn:li:share:7001')
        ->and($result->url)->toBe('https://www.linkedin.com/feed/update/urn:li:share:7001/');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.linkedin.com/rest/posts'
        && $r->hasHeader('LinkedIn-Version', '202610')
        && $r->hasHeader('X-Restli-Protocol-Version', '2.0.0')
        && $r->hasHeader('Authorization', 'Bearer li-token')
        && $r['author'] === 'urn:li:person:'.$target->account->provider_id
        && $r['commentary'] === 'Hello \\(world\\) {hashtag|\\#|photography}'
        && $r['lifecycleState'] === 'PUBLISHED'
        && ! isset($r['content']));
});

it('uploads images and creates a multi-image post as an organization', function () {
    Http::fake([
        'api.linkedin.com/rest/images?action=initializeUpload' => Http::sequence()
            ->push(['value' => ['uploadUrl' => 'https://upload.linkedin.test/1', 'image' => 'urn:li:image:1']])
            ->push(['value' => ['uploadUrl' => 'https://upload.linkedin.test/2', 'image' => 'urn:li:image:2']]),
        'upload.linkedin.test/*' => Http::response('', 201),
        'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:7002']),
    ]);

    $account = SocialAccount::factory()->linkedinOrganization('42')->create();
    $post = SocialPost::factory()->create(['body' => 'Gallery', 'media' => [socialImage('a.jpg'), socialImage('b.jpg')]]);
    $target = SocialPostTarget::factory()->for($post, 'post')->for($account, 'account')->create();

    app(LinkedInPublisher::class)->publish($target);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'initializeUpload')
        && $r['initializeUploadRequest']['owner'] === 'urn:li:organization:42');
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === 'https://upload.linkedin.test/1' && $r->body() === 'JPEG-BYTES');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/rest/posts')
        && $r['author'] === 'urn:li:organization:42'
        && $r['content'] === ['multiImage' => ['images' => [['id' => 'urn:li:image:1', 'altText' => ''], ['id' => 'urn:li:image:2', 'altText' => '']]]]);
});

it('attaches an article for link posts without images', function () {
    Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:7003'])]);

    app(LinkedInPublisher::class)->publish(socialTarget(SocialProvider::LINKEDIN, ['body' => 'Read', 'link_url' => 'https://reno.sh/blog/x']));

    Http::assertSent(fn (Request $r) => $r['content'] === ['article' => ['source' => 'https://reno.sh/blog/x', 'title' => 'reno.sh']]);
});

it('normalizes 401 to reauth and 429 to retryable', function (int $status, bool $reauth, bool $retry) {
    Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'nope', 'status' => $status], $status)]);

    $e = expectPublishError(fn () => app(LinkedInPublisher::class)->publish(socialTarget(SocialProvider::LINKEDIN)));

    expect($e->needsReauth)->toBe($reauth)->and($e->retryable)->toBe($retry);
})->with([
    'revoked' => [401, true, false],
    'throttled' => [429, false, true],
    'duplicate' => [422, false, false],
]);

it('escapes little-text reserved characters', function () {
    expect(LinkedInPublisher::littleText('a_b *c* @me [x] <y> ~z | {q} \\ #tag #1 x#y'))
        ->toBe('a\\_b \\*c\\* \\@me \\[x\\] \\<y\\> \\~z \\| \\{q\\} \\\\ {hashtag|\\#|tag} {hashtag|\\#|1} x\\#y');
});
