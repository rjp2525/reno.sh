<?php

use App\Enums\SocialProvider;
use App\Http\Controllers\Social\OAuthController;
use App\Models\SocialAccount;
use App\Services\Social\OAuth\OAuthFlow;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/Support.php';

beforeEach(function () {
    // The real routes live in routes/web.php behind the Filament auth middleware.
    Route::middleware('web')->group(function () {
        Route::get('/admin/social/connect/{provider}', [OAuthController::class, 'redirect']);
        Route::get('/admin/social/callback/{provider}', [OAuthController::class, 'callback']);
    });

    config([
        'app.url' => 'http://reno.test',
        'social.meta' => [...config('social.meta'), 'app_id' => 'meta-id', 'app_secret' => 'meta-secret', 'graph_version' => 'v24.0'],
        'social.x.client_id' => 'x-client',
        'social.x.client_secret' => null,
        'social.linkedin.client_id' => 'li-id',
        'social.linkedin.client_secret' => 'li-secret',
        'social.threads.app_id' => 'th-id',
        'social.threads.app_secret' => 'th-secret',
    ]);
    url()->forceRootUrl('http://reno.test');
});

function withOAuthState(SocialProvider $provider, string $state = 'state-123', ?string $verifier = null): void
{
    session()->put('social_oauth.'.$provider->value, ['state' => $state, 'verifier' => $verifier, 'at' => now()->getTimestamp()]);
}

it('redirects to X with state and an S256 PKCE challenge', function () {
    $response = $this->get('/admin/social/connect/x');

    $response->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $stored = session('social_oauth.x');

    expect($response->headers->get('Location'))->toStartWith('https://x.com/i/oauth2/authorize?')
        ->and($query['state'])->toBe($stored['state'])
        ->and(strlen($stored['state']))->toBe(40)
        ->and($query['code_challenge'])->toBe(OAuthFlow::challenge($stored['verifier']))
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['redirect_uri'])->toBe('http://reno.test/admin/social/callback/x')
        ->and($query['scope'])->toBe('tweet.read tweet.write users.read media.write offline.access');
});

it('redirects to the Facebook dialog with comma-separated scopes', function () {
    $location = $this->get('/admin/social/connect/facebook')->headers->get('Location');

    expect($location)->toStartWith('https://www.facebook.com/v24.0/dialog/oauth?')
        ->and($location)->toContain('scope=pages_show_list%2Cpages_read_engagement%2Cpages_manage_posts')
        ->and($location)->not->toContain('code_challenge');
});

it('reports a missing app configuration instead of redirecting', function () {
    config(['social.linkedin.client_id' => null]);

    $this->get('/admin/social/connect/linkedin')
        ->assertRedirect('http://reno.test/admin/social-accounts')
        ->assertSessionHas('error', fn ($m) => str_contains($m, "isn't configured"));
});

it('404s for manually-connected networks', function () {
    $this->get('/admin/social/connect/bluesky')->assertNotFound();
});

it('creates one account per Facebook Page and upserts on reconnect', function () {
    Http::fake([
        'graph.facebook.com/v24.0/oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short'])->push(['access_token' => 'long', 'expires_in' => 5183944])
            ->push(['access_token' => 'short2'])->push(['access_token' => 'long2']),
        'graph.facebook.com/v24.0/me/accounts*' => Http::response(['data' => [
            ['id' => '111', 'name' => 'Reno Photo', 'access_token' => 'page-111', 'picture' => ['data' => ['url' => 'https://pic/111']]],
            ['id' => '222', 'name' => 'Reno Prints', 'access_token' => 'page-222'],
        ]]),
    ]);

    withOAuthState(SocialProvider::FACEBOOK);
    $this->get('/admin/social/callback/facebook?code=abc&state=state-123')
        ->assertRedirect('http://reno.test/admin/social-accounts')
        ->assertSessionHas('success', 'Connected 2 Facebook accounts: Reno Photo, Reno Prints');

    $page = SocialAccount::query()->where('provider_id', '111')->sole();
    expect($page->provider)->toBe(SocialProvider::FACEBOOK)
        ->and($page->credential('access_token'))->toBe('page-111')
        ->and($page->avatar_url)->toBe('https://pic/111')
        ->and($page->token_expires_at)->toBeNull();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth/access_token') && ($r['code'] ?? null) === 'abc'
        && $r['redirect_uri'] === 'http://reno.test/admin/social/callback/facebook');
    Http::assertSent(fn (Request $r) => ($r['grant_type'] ?? null) === 'fb_exchange_token' && $r['fb_exchange_token'] === 'short');

    // Reconnect: same pages are updated, not duplicated, and reauth is cleared.
    $page->forceFill(['needs_reauth' => true])->save();
    withOAuthState(SocialProvider::FACEBOOK, 'state-456');
    $this->get('/admin/social/callback/facebook?code=def&state=state-456')->assertSessionHas('success');

    expect(SocialAccount::query()->count())->toBe(2)
        ->and($page->fresh()->needs_reauth)->toBeFalse();
});

it('creates Instagram accounts only for Pages with a linked IG business account', function () {
    Http::fake([
        'graph.facebook.com/v24.0/oauth/access_token*' => Http::response(['access_token' => 'tok']),
        'graph.facebook.com/v24.0/me/accounts*' => Http::response(['data' => [
            ['id' => '111', 'name' => 'Page A', 'access_token' => 'page-a', 'instagram_business_account' => [
                'id' => '17841', 'username' => 'reno.photo', 'name' => 'Reno', 'profile_picture_url' => 'https://ig/pic',
            ]],
            ['id' => '222', 'name' => 'Page B', 'access_token' => 'page-b'],
        ]]),
    ]);

    withOAuthState(SocialProvider::INSTAGRAM);
    $this->get('/admin/social/callback/instagram?code=abc&state=state-123')->assertSessionHas('success');

    $ig = SocialAccount::query()->sole();
    expect($ig->provider)->toBe(SocialProvider::INSTAGRAM)
        ->and($ig->provider_id)->toBe('17841')
        ->and($ig->username)->toBe('reno.photo')
        ->and($ig->credential('access_token'))->toBe('page-a')
        ->and($ig->meta['page_id'])->toBe('111');
});

it('explains when no Instagram accounts are linked', function () {
    Http::fake([
        'graph.facebook.com/v24.0/oauth/access_token*' => Http::response(['access_token' => 'tok']),
        'graph.facebook.com/v24.0/me/accounts*' => Http::response(['data' => [['id' => '1', 'name' => 'P', 'access_token' => 'x']]]),
    ]);

    withOAuthState(SocialProvider::INSTAGRAM);
    $this->get('/admin/social/callback/instagram?code=abc&state=state-123')
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'No Instagram professional accounts'));
});

it('exchanges the X code with the PKCE verifier', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response(['access_token' => 'xa', 'refresh_token' => 'xr', 'expires_in' => 7200, 'scope' => 'tweet.write']),
        'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '42', 'name' => 'Reno', 'username' => 'renosh', 'profile_image_url' => 'https://pbs/x.jpg']]),
    ]);

    withOAuthState(SocialProvider::X, 'st', 'the-verifier');
    $this->get('/admin/social/callback/x?code=c0de&state=st')->assertSessionHas('success');

    $account = SocialAccount::query()->sole();
    expect($account->username)->toBe('renosh')
        ->and($account->credential('refresh_token'))->toBe('xr')
        ->and($account->token_expires_at->isFuture())->toBeTrue();
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/2/oauth2/token')
        && $r['code_verifier'] === 'the-verifier' && $r['client_id'] === 'x-client' && $r['grant_type'] === 'authorization_code');
});

it('connects LinkedIn members via OpenID userinfo', function () {
    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li', 'expires_in' => 5184000]),
        'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'AbC123', 'name' => 'Reno P', 'picture' => 'https://li/pic']),
    ]);

    withOAuthState(SocialProvider::LINKEDIN);
    $this->get('/admin/social/callback/linkedin?code=abc&state=state-123')->assertSessionHas('success');

    $account = SocialAccount::query()->sole();
    expect($account->provider_id)->toBe('AbC123')
        ->and($account->meta['author_urn'])->toBe('urn:li:person:AbC123');
});

it('connects Threads with a long-lived token', function () {
    Http::fake([
        'graph.threads.net/oauth/access_token' => Http::response(['access_token' => 'short', 'user_id' => 77]),
        'graph.threads.net/access_token*' => Http::response(['access_token' => 'long', 'expires_in' => 5184000]),
        'graph.threads.net/v1.0/me*' => Http::response(['id' => '77', 'username' => 'reno', 'name' => 'Reno', 'threads_profile_picture_url' => 'https://t/pic']),
    ]);

    withOAuthState(SocialProvider::THREADS);
    $this->get('/admin/social/callback/threads?code=abc&state=state-123')->assertSessionHas('success');

    $account = SocialAccount::query()->sole();
    expect($account->credential('access_token'))->toBe('long')
        ->and($account->token_expires_at->isAfter(now()->addDays(59)))->toBeTrue();
});

it('rejects a state mismatch without calling the network', function () {
    Http::fake();
    withOAuthState(SocialProvider::FACEBOOK, 'expected');

    $this->get('/admin/social/callback/facebook?code=abc&state=forged')
        ->assertRedirect('http://reno.test/admin/social-accounts')
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'state mismatch'));

    Http::assertNothingSent();
    expect(session()->has('social_oauth.facebook'))->toBeFalse();
});

it('reports a denied consent screen', function () {
    Http::fake();
    withOAuthState(SocialProvider::X, 'st');

    $this->get('/admin/social/callback/x?error=access_denied&state=st')
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'cancelled'));
    Http::assertNothingSent();
});

it('surfaces token exchange errors', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid verification code format.', 'code' => 100]], 400)]);
    withOAuthState(SocialProvider::FACEBOOK);

    $this->get('/admin/social/callback/facebook?code=bad&state=state-123')
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid verification code format.'));
    expect(SocialAccount::query()->count())->toBe(0);
});
