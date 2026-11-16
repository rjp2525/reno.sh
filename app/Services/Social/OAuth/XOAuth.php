<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;

/** X OAuth 2.0 Authorization Code with PKCE (S256); offline.access yields a refresh token. */
class XOAuth extends AbstractOAuthProvider
{
    public function provider(): SocialProvider
    {
        return SocialProvider::X;
    }

    public function isConfigured(): bool
    {
        return filled(config('social.x.client_id'));
    }

    public function usesPkce(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state, ?string $codeChallenge = null): string
    {
        return $this->buildUrl((string) config('social.x.authorize_url'), [
            'response_type' => 'code',
            'client_id' => config('social.x.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier = null): array
    {
        $response = $this->call('code exchange', function () use ($code, $codeVerifier) {
            $request = $this->http()->asForm();

            if (filled(config('social.x.client_secret'))) {
                $request = $request->withBasicAuth((string) config('social.x.client_id'), (string) config('social.x.client_secret'));
            }

            return $request->post($this->api('2/oauth2/token'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'code_verifier' => $codeVerifier,
                'client_id' => config('social.x.client_id'),
            ]);
        });

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => $response->json('expires_in'),
            'scope' => $response->json('scope'),
        ];
    }

    public function accounts(array $token): array
    {
        $me = $this->call('profile fetch', fn () => $this->http()->withToken($token['access_token'])
            ->get($this->api('2/users/me'), ['user.fields' => 'profile_image_url']));

        return [new ConnectedAccount(
            providerId: (string) $me->json('data.id'),
            name: (string) ($me->json('data.name') ?? $me->json('data.username')),
            credentials: array_filter([
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
            ]),
            username: $me->json('data.username'),
            avatarUrl: $me->json('data.profile_image_url'),
            tokenExpiresAt: isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            meta: ['scope' => $token['scope'] ?? null],
        )];
    }

    private function api(string $path): string
    {
        return rtrim((string) config('social.x.api_url'), '/').'/'.$path;
    }
}
