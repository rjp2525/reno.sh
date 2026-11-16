<?php

declare(strict_types=1);

namespace App\Services\Social\Providers\Concerns;

use Illuminate\Http\Client\Response;

/**
 * Error semantics shared by the Facebook, Instagram and Threads Graph APIs,
 * which return {error: {message, type, code, error_subcode, is_transient}}.
 */
trait InteractsWithMetaGraph
{
    /** OAuthException / expired / revoked / missing permission. */
    private const array REAUTH_CODES = [102, 190, 10, 200, 299];

    /** API-level throttling and transient errors. */
    private const array RETRY_CODES = [1, 2, 4, 17, 32, 341, 368, 613, 80001, 80002, 80005, 80006];

    protected function classify(Response $response): array
    {
        $status = $response->status();
        $code = (int) $response->json('error.code', 0);
        $transient = (bool) $response->json('error.is_transient', false);

        $needsReauth = $status === 401 || in_array($code, self::REAUTH_CODES, true);
        $retryable = ! $needsReauth && ($transient || in_array($code, self::RETRY_CODES, true) || $status === 429 || $status >= 500);

        return [$needsReauth, $retryable];
    }

    /** appsecret_proof, required when "Require App Secret" is on in the Meta app. */
    protected function graphAuth(string $token, ?string $appSecret): array
    {
        return array_filter([
            'access_token' => $token,
            'appsecret_proof' => filled($appSecret) ? hash_hmac('sha256', $token, (string) $appSecret) : null,
        ]);
    }
}
