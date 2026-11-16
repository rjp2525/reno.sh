<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

/**
 * Facebook Login for Business: short-lived user token, exchanged for a
 * long-lived one, from which non-expiring Page tokens are listed via /me/accounts.
 */
abstract class MetaOAuth extends AbstractOAuthProvider
{
    public function isConfigured(): bool
    {
        return filled(config('social.meta.app_id')) && filled(config('social.meta.app_secret'));
    }

    public function authorizationUrl(string $state, ?string $codeChallenge = null): string
    {
        return $this->buildUrl(rtrim((string) config('social.meta.dialog_url'), '/').'/'.config('social.meta.graph_version').'/dialog/oauth', [
            'client_id' => config('social.meta.app_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes()),
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier = null): array
    {
        $short = $this->call('code exchange', fn () => $this->http()->get($this->graph('oauth/access_token'), [
            'client_id' => config('social.meta.app_id'),
            'client_secret' => config('social.meta.app_secret'),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]));

        $long = $this->call('token exchange', fn () => $this->http()->get($this->graph('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('social.meta.app_id'),
            'client_secret' => config('social.meta.app_secret'),
            'fb_exchange_token' => $short->json('access_token'),
        ]));

        return [
            'access_token' => (string) $long->json('access_token'),
            'expires_in' => $long->json('expires_in'),
        ];
    }

    /**
     * Every Page the user granted, following pagination.
     *
     * @return list<array<string, mixed>>
     */
    protected function pages(string $userToken, string $fields): array
    {
        $pages = [];
        $url = $this->graph('me/accounts');
        $query = ['fields' => $fields, 'limit' => 100, 'access_token' => $userToken];

        for ($guard = 0; $url && $guard < 20; $guard++) {
            $response = $this->call('page listing', fn () => $this->http()->get($url, $query));
            array_push($pages, ...(array) $response->json('data', []));
            $url = $response->json('paging.next');
            $query = []; // "next" already carries the full query string
        }

        return $pages;
    }

    protected function graph(string $path): string
    {
        return rtrim((string) config('social.meta.graph_url'), '/').'/'.config('social.meta.graph_version').'/'.ltrim($path, '/');
    }
}
