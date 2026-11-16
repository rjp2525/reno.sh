<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Services\Social\SocialManager;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The connect dance: build the authorization URL (state + PKCE kept in the
 * session), then on callback verify state, exchange the code, list the
 * pages/profiles and upsert one SocialAccount per (provider, provider_id).
 */
class OAuthFlow
{
    private const string SESSION_KEY = 'social_oauth';

    public function __construct(private readonly SocialManager $manager) {}

    public function connector(SocialProvider $provider): OAuthProvider
    {
        if (! $provider->usesOAuth()) {
            throw new OAuthException("{$provider->getLabel()} is connected by entering credentials, not OAuth.");
        }

        return $this->manager->oauth($provider);
    }

    public function authorizationUrl(SocialProvider $provider, Session $session): string
    {
        $connector = $this->connector($provider);

        if (! $connector->isConfigured()) {
            throw new OAuthException("{$provider->getLabel()} isn't configured: add its app id/secret to .env (see docs/social.md).");
        }

        $state = Str::random(40);
        $verifier = $connector->usesPkce() ? self::base64Url(random_bytes(48)) : null;

        $session->put(self::SESSION_KEY.'.'.$provider->value, [
            'state' => $state,
            'verifier' => $verifier,
            'at' => now()->getTimestamp(),
        ]);

        return $connector->authorizationUrl($state, $verifier !== null ? self::challenge($verifier) : null);
    }

    /**
     * @return list<SocialAccount>
     */
    public function handleCallback(SocialProvider $provider, Request $request): array
    {
        $connector = $this->connector($provider);
        $stored = $request->session()->pull(self::SESSION_KEY.'.'.$provider->value);

        if ($request->filled('error')) {
            throw new OAuthException(sprintf(
                '%s connection was cancelled: %s',
                $provider->getLabel(),
                (string) ($request->input('error_description') ?: $request->input('error_reason') ?: $request->input('error')),
            ));
        }

        $state = (string) $request->query('state', '');

        if (! is_array($stored) || ! is_string($stored['state'] ?? null) || $state === '' || ! hash_equals($stored['state'], $state)) {
            throw new OAuthException('The connect link expired or was tampered with (state mismatch). Please try again.');
        }

        if (now()->getTimestamp() - (int) ($stored['at'] ?? 0) > 900) {
            throw new OAuthException('The connect attempt took too long. Please try again.');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            throw new OAuthException("{$provider->getLabel()} didn't return an authorization code.");
        }

        $token = $connector->exchangeCode($code, $stored['verifier'] ?? null);

        return array_map(
            fn (ConnectedAccount $connected) => $this->upsert($provider, $connected),
            $connector->accounts($token),
        );
    }

    public function upsert(SocialProvider $provider, ConnectedAccount $connected): SocialAccount
    {
        $account = SocialAccount::query()->firstOrNew([
            'provider' => $provider,
            'provider_id' => $connected->providerId,
        ]);

        $account->forceFill([
            'name' => $connected->name,
            'username' => $connected->username,
            'avatar_url' => $connected->avatarUrl,
            'credentials' => $connected->credentials,
            'token_expires_at' => $connected->tokenExpiresAt,
            'meta' => array_merge($account->meta ?? [], $connected->meta),
            'needs_reauth' => false,
            'last_error' => null,
        ])->save();

        return $account;
    }

    public static function challenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
