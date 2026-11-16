<?php

declare(strict_types=1);

namespace App\Services\Social\OAuth;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class AbstractOAuthProvider implements OAuthProvider
{
    public function usesPkce(): bool
    {
        return false;
    }

    /** {APP_URL}/admin/social/callback/{provider}; must match the URL registered with the network. */
    public function redirectUri(): string
    {
        return url('/admin/social/callback/'.$this->provider()->value);
    }

    /** @return list<string> */
    protected function scopes(): array
    {
        return (array) config('social.scopes.'.$this->provider()->value, []);
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('social.http.timeout', 30))
            ->connectTimeout((int) config('social.http.connect_timeout', 10));
    }

    /** @param  Closure(): Response  $request */
    protected function call(string $action, Closure $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new OAuthException("{$this->provider()->getLabel()} {$action} failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            $message = $response->json('error.message')
                ?? $response->json('error_description')
                ?? $response->json('detail')
                ?? $response->json('message')
                ?? (is_string($response->json('error')) ? $response->json('error') : null)
                ?? $response->reason();

            throw new OAuthException(sprintf(
                '%s %s failed (HTTP %d): %s', $this->provider()->getLabel(), $action, $response->status(), Str::limit((string) $message, 300),
            ));
        }

        return $response;
    }

    protected function buildUrl(string $base, array $params): string
    {
        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(array_filter($params, fn ($v) => $v !== null && $v !== ''), '', '&', PHP_QUERY_RFC3986);
    }
}
