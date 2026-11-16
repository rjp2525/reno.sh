<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;

interface OAuthProvider
{
    public function provider(): SocialProvider;

    /** App id/secret present in config. */
    public function isConfigured(): bool;

    /** Whether the authorization request carries a PKCE (S256) challenge. */
    public function usesPkce(): bool;

    public function authorizationUrl(string $state, ?string $codeChallenge = null): string;

    /**
     * Exchange the authorization code for tokens.
     *
     * @return array{access_token: string, refresh_token?: string|null, expires_in?: int|null}&array<string, mixed>
     */
    public function exchangeCode(string $code, ?string $codeVerifier = null): array;

    /**
     * Fetch the profile(s)/pages the token can publish as.
     *
     * @return list<ConnectedAccount>
     */
    public function accounts(array $token): array;
}
