<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublishException;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Bluesky / AT Protocol via the account's PDS.
 *
 *  - session: com.atproto.server.createSession (handle + app password), cached in
 *    credentials and renewed with refreshSession
 *  - images:  com.atproto.repo.uploadBlob
 *  - post:    com.atproto.repo.createRecord (app.bsky.feed.post) with link/tag facets,
 *             images embed or an external link card
 *
 * Credentials: handle (or identifier), app_password, optional pds_url.
 */
class BlueskyPublisher extends AbstractPublisher
{
    public function provider(): SocialProvider
    {
        return SocialProvider::BLUESKY;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $account = $target->account;
        $this->session($account);

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $body,
            'createdAt' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];

        if ($facets = BlueskyRichText::facets($body)) {
            $record['facets'] = $facets;
        }

        if ($media !== []) {
            $images = [];

            foreach ($media as $item) {
                $images[] = ['alt' => '', 'image' => $this->uploadBlob($account, $this->mediaContents($item['path']), $item['mime'])];
            }

            $record['embed'] = ['$type' => 'app.bsky.embed.images', 'images' => $images];
        } elseif ($link = $target->post->link_url ?: BlueskyRichText::firstLink($body)) {
            $record['embed'] = ['$type' => 'app.bsky.embed.external', 'external' => $this->linkCard($account, $link)];
        }

        $response = $this->authed($account, 'post', fn (string $jwt) => $this->http()->withToken($jwt)
            ->post($this->xrpc($account, 'com.atproto.repo.createRecord'), [
                'repo' => $account->credential('did'),
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]));

        $uri = (string) $response->json('uri');
        $rkey = Str::afterLast($uri, '/');
        $handle = ltrim((string) ($account->credential('handle') ?: $account->username ?: $account->credential('did')), '@');

        return new PublishResult($uri, "https://bsky.app/profile/{$handle}/post/{$rkey}");
    }

    public function refresh(SocialAccount $account): bool
    {
        try {
            $this->session($account, force: true);
        } catch (SocialPublishException $e) {
            if ($e->retryable) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    /**
     * Ensure a usable access JWT is cached in credentials; returns it.
     */
    public function session(SocialAccount $account, bool $force = false): string
    {
        $access = $account->credential('access_jwt');

        if (! $force && is_string($access) && self::jwtExpiresAfter($access, 60)) {
            return $access;
        }

        $refresh = $account->credential('refresh_jwt');

        if (is_string($refresh) && self::jwtExpiresAfter($refresh, 60)) {
            $response = $this->http()->withToken($refresh)->post($this->xrpc($account, 'com.atproto.server.refreshSession'));

            if ($response->successful()) {
                return $this->storeSession($account, $response);
            }

            if ($response->status() === 429 || $response->serverError()) {
                $this->call('session refresh', fn () => $response);
            }
        }

        $identifier = $account->credential('identifier') ?: $account->credential('handle') ?: $account->username;
        $password = $account->credential('app_password');

        if (! $identifier || ! $password) {
            throw new SocialPublishException('Bluesky account needs a handle and an app password.', needsReauth: true);
        }

        $response = $this->call('login', fn () => $this->http()->post($this->xrpc($account, 'com.atproto.server.createSession'), [
            'identifier' => ltrim((string) $identifier, '@'),
            'password' => $password,
        ]));

        return $this->storeSession($account, $response);
    }

    protected function classify(Response $response): array
    {
        $error = (string) $response->json('error', '');
        $status = $response->status();

        return [
            $status === 401 || in_array($error, ['AuthenticationRequired', 'AccountTakedown', 'InvalidToken', 'ExpiredToken'], true),
            $status === 408 || $status === 429 || $status >= 500 || $error === 'RateLimitExceeded',
        ];
    }

    /**
     * Run an authenticated call; on an expired/invalid token re-establish the session once.
     *
     * @param  Closure(string): Response  $request
     */
    private function authed(SocialAccount $account, string $action, Closure $request): Response
    {
        $response = $request($this->session($account));

        if (in_array($response->json('error'), ['ExpiredToken', 'InvalidToken'], true)) {
            $response = $request($this->session($account, force: true));
        }

        return $this->call($action, fn () => $response);
    }

    private function uploadBlob(SocialAccount $account, string $bytes, string $mime): array
    {
        $max = (int) config('social.bluesky.max_image_bytes', 1_000_000);

        if (strlen($bytes) > $max) {
            throw new SocialPublishException(sprintf(
                'Bluesky images must be under %s KB; this one is %s KB. Use a smaller rendition.',
                number_format($max / 1000), number_format(strlen($bytes) / 1000),
            ));
        }

        return (array) $this->authed($account, 'image upload', fn (string $jwt) => $this->uploadHttp()
            ->withToken($jwt)
            ->withBody($bytes, $mime)
            ->post($this->xrpc($account, 'com.atproto.repo.uploadBlob')))
            ->json('blob');
    }

    /**
     * External embed card. Title/description/thumbnail come from the page's
     * OpenGraph tags when it can be fetched; otherwise the host is the title.
     */
    private function linkCard(SocialAccount $account, string $link): array
    {
        $card = ['uri' => $link, 'title' => (string) (parse_url($link, PHP_URL_HOST) ?: $link), 'description' => ''];

        try {
            $html = $this->http()->timeout(5)->withHeaders(['Accept' => 'text/html'])->get($link);
        } catch (\Throwable) {
            return $card;
        }

        if (! $html->successful()) {
            return $card;
        }

        $meta = self::openGraph($html->body());
        $card['title'] = Str::limit($meta['og:title'] ?? $meta['title'] ?? $card['title'], 300, '');
        $card['description'] = Str::limit($meta['og:description'] ?? $meta['description'] ?? '', 1000, '');

        if (! empty($meta['og:image'])) {
            try {
                $image = $this->http()->timeout(10)->get($meta['og:image']);
                $mime = strtok((string) $image->header('Content-Type'), ';') ?: 'image/jpeg';

                if ($image->successful() && str_starts_with($mime, 'image/') && strlen($image->body()) <= (int) config('social.bluesky.max_image_bytes', 1_000_000)) {
                    $card['thumb'] = $this->uploadBlob($account, $image->body(), $mime);
                }
            } catch (\Throwable) {
                // A card without a thumbnail is fine.
            }
        }

        return $card;
    }

    /** @return array<string, string> */
    private static function openGraph(string $html): array
    {
        $meta = [];

        if (preg_match_all('/<meta\s[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/(?:property|name)\s*=\s*["\']([^"\']+)["\']/i', $tag, $key)
                    && preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $tag, $content)) {
                    $meta[strtolower($key[1])] ??= html_entity_decode($content[1], ENT_QUOTES | ENT_HTML5);
                }
            }
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title)) {
            $meta['title'] = trim(html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5));
        }

        return $meta;
    }

    private function storeSession(SocialAccount $account, Response $response): string
    {
        $this->storeCredentials($account, array_filter([
            'access_jwt' => $response->json('accessJwt'),
            'refresh_jwt' => $response->json('refreshJwt'),
            'did' => $response->json('did'),
            'handle' => $response->json('handle'),
        ]));

        return (string) $response->json('accessJwt');
    }

    private function xrpc(SocialAccount $account, string $method): string
    {
        $pds = rtrim((string) ($account->credential('pds_url') ?: config('social.bluesky.default_pds')), '/');

        return "{$pds}/xrpc/{$method}";
    }

    /** True when the JWT's exp is more than $seconds away (unknown exp counts as expired). */
    private static function jwtExpiresAfter(string $jwt, int $seconds): bool
    {
        $parts = explode('.', $jwt);
        $payload = isset($parts[1]) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true) : null;
        $exp = is_array($payload) ? ($payload['exp'] ?? null) : null;

        return is_numeric($exp) && (int) $exp > now()->addSeconds($seconds)->getTimestamp();
    }
}
