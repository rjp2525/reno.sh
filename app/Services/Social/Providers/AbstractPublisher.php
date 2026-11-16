<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\Social\SocialPublisher;
use App\Services\Social\SocialPublishException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Shared plumbing for every network: HTTP defaults, limit checks, media
 * loading from the public disk and error normalization.
 */
abstract class AbstractPublisher implements SocialPublisher
{
    abstract public function provider(): SocialProvider;

    /** Default: tokens that don't expire (or are handled lazily) need no refresh. */
    public function refresh(SocialAccount $account): bool
    {
        return true;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('social.http.timeout', 30))
            ->connectTimeout((int) config('social.http.connect_timeout', 10));
    }

    protected function uploadHttp(): PendingRequest
    {
        return $this->http()->timeout((int) config('social.http.upload_timeout', 120));
    }

    /**
     * Run a request, turning transport failures and non-2xx responses into
     * SocialPublishException with the right reauth/retry flags.
     *
     * @param  Closure(): Response  $request
     */
    protected function call(string $action, Closure $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new SocialPublishException($this->label()." {$action} failed: ".$e->getMessage(), retryable: true);
        }

        if ($response->successful()) {
            return $response;
        }

        [$needsReauth, $retryable] = $this->classify($response);

        throw new SocialPublishException(
            sprintf('%s %s failed (HTTP %d): %s', $this->label(), $action, $response->status(), $this->errorMessage($response)),
            needsReauth: $needsReauth,
            retryable: $retryable,
        );
    }

    /**
     * @return array{0: bool, 1: bool} [needsReauth, retryable]
     */
    protected function classify(Response $response): array
    {
        $status = $response->status();

        return [
            $status === 401,
            $status === 408 || $status === 429 || $status >= 500,
        ];
    }

    protected function errorMessage(Response $response): string
    {
        $json = $response->json();

        $message = is_array($json) ? (
            data_get($json, 'error.message')
            ?? data_get($json, 'error_description')
            ?? data_get($json, 'detail')
            ?? data_get($json, 'message')
            ?? data_get($json, 'errors.0.message')
            ?? data_get($json, 'title')
            ?? (is_string($json['error'] ?? null) ? $json['error'] : null)
        ) : null;

        return Str::limit(is_string($message) && $message !== '' ? $message : (trim($response->body()) ?: $response->reason()), 500);
    }

    /**
     * Validate the resolved text + media against the network's limits.
     * Nothing is ever truncated: over-limit posts fail with a clear message.
     *
     * @return array{0: string, 1: list<array{path: string, url: string, mime: string}>}
     */
    protected function prepare(SocialPostTarget $target): array
    {
        $provider = $this->provider();
        $limits = $provider->limits();
        $body = $target->resolvedBody();
        $media = $target->post->mediaItems();

        $length = $body === '' ? 0 : $provider->length($body);

        if ($length > $limits['max_chars']) {
            throw new SocialPublishException(sprintf(
                '%s allows %d characters; this post is %d. Shorten it or set a per-account override.',
                $this->label(), $limits['max_chars'], $length,
            ));
        }

        if (count($media) > $limits['max_media']) {
            throw new SocialPublishException(sprintf(
                '%s allows %d media items per post; this post has %d.', $this->label(), $limits['max_media'], count($media),
            ));
        }

        if ($limits['requires_media'] && $media === []) {
            throw new SocialPublishException($this->label().' posts require at least one image.');
        }

        foreach ($media as $item) {
            if (! str_starts_with($item['mime'], 'image/')) {
                throw new SocialPublishException(sprintf(
                    '%s: only images are supported right now (%s is %s).', $this->label(), $item['path'], $item['mime'],
                ));
            }
        }

        if ($body === '' && $media === []) {
            throw new SocialPublishException('Nothing to publish: the post has no text and no media.');
        }

        return [$body, $media];
    }

    /** Raw bytes of a public-disk file. */
    protected function mediaContents(string $path): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new SocialPublishException("Media file is missing from storage: {$path}");
        }

        return (string) $disk->get($path);
    }

    /**
     * For networks that fetch media by URL (Instagram, Threads): fail early
     * when the URL obviously can't be reached from the internet.
     */
    protected function assertPublicUrl(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host !== '' && preg_match('/^(localhost|127\.|0\.0\.0\.0|10\.|192\.168\.|\[::1\])|\.(test|local|localhost|internal)$/i', $host)) {
            throw new SocialPublishException(sprintf(
                '%s downloads media from a public URL, but %s is not reachable from the internet. Set APP_URL to a public host (e.g. a tunnel) for local testing.',
                $this->label(), $url,
            ));
        }

        return $url;
    }

    /**
     * Poll until $check returns a non-null value, bounded by config.
     *
     * @template T
     *
     * @param  Closure(): (T|null)  $check
     * @return T
     */
    protected function poll(string $what, Closure $check): mixed
    {
        $attempts = max(1, (int) config('social.polling.attempts', 10));
        $interval = max(0, (int) config('social.polling.interval', 3));

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                Sleep::for($interval)->seconds();
            }

            $result = $check();

            if ($result !== null) {
                return $result;
            }
        }

        throw new SocialPublishException("{$this->label()}: {$what} was still processing after {$attempts} checks.", retryable: true);
    }

    /** Merge new credential values into the account and persist them. */
    protected function storeCredentials(SocialAccount $account, array $values, mixed $expiresAt = false): void
    {
        $attributes = ['credentials' => array_merge($account->credentials ?? [], $values)];

        if ($expiresAt !== false) {
            $attributes['token_expires_at'] = $expiresAt;
        }

        $account->forceFill($attributes)->save();
    }

    protected function requireCredential(SocialAccount $account, string $key): string
    {
        $value = $account->credential($key);

        if (! is_string($value) || $value === '') {
            throw new SocialPublishException("{$this->label()} account is missing its {$key}; reconnect it.", needsReauth: true);
        }

        return $value;
    }

    protected function label(): string
    {
        return $this->provider()->getLabel();
    }

    protected function filename(array $item): string
    {
        return basename($item['path']);
    }
}
