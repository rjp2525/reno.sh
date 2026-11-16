<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublishException;
use Illuminate\Http\Client\PendingRequest;

/**
 * LinkedIn Posts API (versioned /rest endpoints).
 *
 *  - images: POST /rest/images?action=initializeUpload, then PUT the bytes to uploadUrl
 *  - post:   POST /rest/posts (single image, multiImage or article content); id in x-restli-id
 *
 * Author is meta.author_urn when set (organization pages), else urn:li:person:{provider_id}.
 */
class LinkedInPublisher extends AbstractPublisher
{
    public function provider(): SocialProvider
    {
        return SocialProvider::LINKEDIN;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $account = $target->account;
        $token = $this->requireCredential($account, 'access_token');
        $author = self::authorUrn($account);

        $imageUrns = [];

        foreach ($media as $item) {
            $init = $this->call('image upload init', fn () => $this->rest($token)
                ->post($this->url('rest/images?action=initializeUpload'), [
                    'initializeUploadRequest' => ['owner' => $author],
                ]));

            $uploadUrl = (string) $init->json('value.uploadUrl');
            $imageUrns[] = (string) $init->json('value.image');

            $this->call('image upload', fn () => $this->uploadHttp()
                ->withToken($token)
                ->withBody($this->mediaContents($item['path']), $item['mime'])
                ->put($uploadUrl));
        }

        $payload = [
            'author' => $author,
            'commentary' => self::littleText($body),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $link = $target->post->link_url;

        if (count($imageUrns) === 1) {
            $payload['content'] = ['media' => ['id' => $imageUrns[0]]];
        } elseif (count($imageUrns) > 1) {
            $payload['content'] = ['multiImage' => ['images' => array_map(fn ($urn) => ['id' => $urn, 'altText' => ''], $imageUrns)]];
        } elseif ($link) {
            $payload['content'] = ['article' => [
                'source' => $link,
                'title' => (string) (parse_url($link, PHP_URL_HOST) ?: $link),
            ]];
        }

        $response = $this->call('post', fn () => $this->rest($token)->post($this->url('rest/posts'), $payload));
        $urn = (string) ($response->header('x-restli-id') ?: $response->json('id'));

        if ($urn === '') {
            throw new SocialPublishException('LinkedIn accepted the post but returned no id.');
        }

        return new PublishResult($urn, "https://www.linkedin.com/feed/update/{$urn}/");
    }

    public function refresh(SocialAccount $account): bool
    {
        // Programmatic refresh tokens are only issued to approved partners;
        // otherwise the member must reconnect every 60 days.
        $refreshToken = $account->credential('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            return ! $account->tokenExpiresSoon(0);
        }

        try {
            $response = $this->call('token refresh', fn () => $this->http()->asForm()
                ->post(rtrim((string) config('social.linkedin.oauth_url'), '/').'/accessToken', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => config('social.linkedin.client_id'),
                    'client_secret' => config('social.linkedin.client_secret'),
                ]));
        } catch (SocialPublishException $e) {
            if ($e->retryable) {
                throw $e;
            }

            return false;
        }

        $this->storeCredentials($account, array_filter([
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?: $refreshToken,
        ]), now()->addSeconds((int) $response->json('expires_in', 60 * 24 * 3600)));

        return true;
    }

    public static function authorUrn(SocialAccount $account): string
    {
        $urn = data_get($account->meta, 'author_urn');

        if (is_string($urn) && $urn !== '') {
            return $urn;
        }

        return str_starts_with($account->provider_id, 'urn:li:')
            ? $account->provider_id
            : 'urn:li:person:'.$account->provider_id;
    }

    /**
     * LinkedIn "little text" commentary: reserved characters must be escaped
     * or the post gets cut off at the first one. Hashtags become hashtag templates.
     */
    public static function littleText(string $text): string
    {
        return (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}_&])#([\p{L}\p{N}]+)|[\\\\|{}@\[\]()<>#*_~]/u',
            fn (array $m) => isset($m[1]) && $m[1] !== ''
                ? '{hashtag|\\#|'.$m[1].'}'
                : '\\'.$m[0],
            $text,
        );
    }

    private function rest(string $token): PendingRequest
    {
        return $this->http()->withToken($token)->asJson()->withHeaders([
            'LinkedIn-Version' => (string) config('social.linkedin.version'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('social.linkedin.api_url'), '/').'/'.ltrim($path, '/');
    }
}
