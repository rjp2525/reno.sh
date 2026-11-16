<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;

/**
 * Threads OAuth: code → short-lived token → long-lived (60 day) token,
 * refreshed later with th_refresh_token by ThreadsPublisher::refresh().
 */
class ThreadsOAuth extends AbstractOAuthProvider
{
    public function provider(): SocialProvider
    {
        return SocialProvider::THREADS;
    }

    public function isConfigured(): bool
    {
        return filled(config('social.threads.app_id')) && filled(config('social.threads.app_secret'));
    }

    public function authorizationUrl(string $state, ?string $codeChallenge = null): string
    {
        return $this->buildUrl((string) config('social.threads.authorize_url'), [
            'client_id' => config('social.threads.app_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(',', $this->scopes()),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier = null): array
    {
        $short = $this->call('code exchange', fn () => $this->http()->asForm()->post($this->base('oauth/access_token'), [
            'client_id' => config('social.threads.app_id'),
            'client_secret' => config('social.threads.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]));

        $long = $this->call('token exchange', fn () => $this->http()->get($this->base('access_token'), [
            'grant_type' => 'th_exchange_token',
            'client_secret' => config('social.threads.app_secret'),
            'access_token' => $short->json('access_token'),
        ]));

        return [
            'access_token' => (string) $long->json('access_token'),
            'expires_in' => $long->json('expires_in'),
            'user_id' => $short->json('user_id'),
        ];
    }

    public function accounts(array $token): array
    {
        $me = $this->call('profile fetch', fn () => $this->http()->get(
            $this->base(config('social.threads.graph_version').'/me'),
            ['fields' => 'id,username,name,threads_profile_picture_url', 'access_token' => $token['access_token']],
        ));

        return [new ConnectedAccount(
            providerId: (string) ($me->json('id') ?? $token['user_id'] ?? ''),
            name: (string) ($me->json('name') ?: $me->json('username')),
            credentials: ['access_token' => $token['access_token']],
            username: $me->json('username'),
            avatarUrl: $me->json('threads_profile_picture_url'),
            tokenExpiresAt: now()->addSeconds((int) ($token['expires_in'] ?? 60 * 24 * 3600)),
        )];
    }

    private function base(string $path): string
    {
        return rtrim((string) config('social.threads.graph_url'), '/').'/'.ltrim($path, '/');
    }
}
