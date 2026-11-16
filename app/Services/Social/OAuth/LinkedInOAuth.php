<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * LinkedIn 3-legged OAuth ("Sign In with LinkedIn using OpenID Connect" +
 * "Share on LinkedIn"). With social.linkedin.organizations enabled (Community
 * Management API), Company Pages the member administers are connected too.
 */
class LinkedInOAuth extends AbstractOAuthProvider
{
    public function provider(): SocialProvider
    {
        return SocialProvider::LINKEDIN;
    }

    public function isConfigured(): bool
    {
        return filled(config('social.linkedin.client_id')) && filled(config('social.linkedin.client_secret'));
    }

    protected function scopes(): array
    {
        $scopes = (array) config('social.scopes.linkedin', []);

        if (config('social.linkedin.organizations')) {
            $scopes = [...$scopes, ...(array) config('social.scopes.linkedin_organizations', [])];
        }

        return array_values(array_unique($scopes));
    }

    public function authorizationUrl(string $state, ?string $codeChallenge = null): string
    {
        return $this->buildUrl(rtrim((string) config('social.linkedin.oauth_url'), '/').'/authorization', [
            'response_type' => 'code',
            'client_id' => config('social.linkedin.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier = null): array
    {
        $response = $this->call('code exchange', fn () => $this->http()->asForm()
            ->post(rtrim((string) config('social.linkedin.oauth_url'), '/').'/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'client_id' => config('social.linkedin.client_id'),
                'client_secret' => config('social.linkedin.client_secret'),
            ]));

        return [
            'access_token' => (string) $response->json('access_token'),
            'expires_in' => $response->json('expires_in'),
            'refresh_token' => $response->json('refresh_token'),
            'refresh_token_expires_in' => $response->json('refresh_token_expires_in'),
        ];
    }

    public function accounts(array $token): array
    {
        $credentials = array_filter([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
        ]);
        $expiresAt = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;

        $me = $this->call('profile fetch', fn () => $this->http()->withToken($token['access_token'])->get($this->api('v2/userinfo')));
        $sub = (string) $me->json('sub');

        $accounts = [new ConnectedAccount(
            providerId: $sub,
            name: (string) ($me->json('name') ?: trim($me->json('given_name').' '.$me->json('family_name'))),
            credentials: $credentials,
            avatarUrl: $me->json('picture'),
            tokenExpiresAt: $expiresAt,
            meta: ['author_urn' => "urn:li:person:{$sub}", 'type' => 'person'],
        )];

        if (! config('social.linkedin.organizations')) {
            return $accounts;
        }

        $acls = $this->call('organization listing', fn () => $this->rest($token['access_token'])->get($this->api('rest/organizationAcls'), [
            'q' => 'roleAssignee',
            'role' => 'ADMINISTRATOR',
            'state' => 'APPROVED',
        ]));

        foreach ((array) $acls->json('elements', []) as $acl) {
            $urn = (string) ($acl['organization'] ?? '');

            if (! str_starts_with($urn, 'urn:li:organization:')) {
                continue;
            }

            $id = Str::afterLast($urn, ':');
            $org = $this->rest($token['access_token'])->get($this->api("rest/organizations/{$id}"));

            $accounts[] = new ConnectedAccount(
                providerId: $urn,
                name: (string) ($org->json('localizedName') ?: "Organization {$id}"),
                credentials: $credentials,
                username: $org->json('vanityName'),
                tokenExpiresAt: $expiresAt,
                meta: ['author_urn' => $urn, 'type' => 'organization', 'member_urn' => "urn:li:person:{$sub}"],
            );
        }

        return $accounts;
    }

    private function rest(string $token): PendingRequest
    {
        return $this->http()->withToken($token)->withHeaders([
            'LinkedIn-Version' => (string) config('social.linkedin.version'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ]);
    }

    private function api(string $path): string
    {
        return rtrim((string) config('social.linkedin.api_url'), '/').'/'.$path;
    }
}
