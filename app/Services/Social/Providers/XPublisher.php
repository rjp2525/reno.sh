<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublishException;

/**
 * X API v2 with an OAuth 2.0 user-context token (Authorization Code + PKCE).
 *
 *  - media: POST /2/media/upload (multipart, media_category=tweet_image), needs media.write
 *  - post:  POST /2/tweets {text, media.media_ids}
 *  - token: POST /2/oauth2/token grant_type=refresh_token (offline.access); refresh tokens rotate.
 */
class XPublisher extends AbstractPublisher
{
    public function provider(): SocialProvider
    {
        return SocialProvider::X;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $account = $target->account;
        $token = $this->requireCredential($account, 'access_token');
        $api = rtrim((string) config('social.x.api_url'), '/');

        $mediaIds = [];

        foreach ($media as $item) {
            $response = $this->call('media upload', fn () => $this->uploadHttp()
                ->withToken($token)
                ->attach('media', $this->mediaContents($item['path']), $this->filename($item), ['Content-Type' => $item['mime']])
                ->post("{$api}/2/media/upload", [
                    'media_category' => 'tweet_image',
                    'media_type' => $item['mime'],
                ]));

            $id = $response->json('data.id') ?? $response->json('media_id_string');

            if (! $id) {
                throw new SocialPublishException('X media upload returned no media id.', retryable: true);
            }

            $mediaIds[] = (string) $id;
        }

        $payload = array_filter(['text' => $body]);

        if ($mediaIds !== []) {
            $payload['media'] = ['media_ids' => $mediaIds];
        }

        $response = $this->call('post', fn () => $this->http()->withToken($token)->post("{$api}/2/tweets", $payload));
        $id = (string) $response->json('data.id');

        $handle = ltrim((string) ($account->username ?: 'i/web'), '@');

        return new PublishResult($id, "https://x.com/{$handle}/status/{$id}");
    }

    public function refresh(SocialAccount $account): bool
    {
        $refreshToken = $account->credential('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            return false;
        }

        $clientId = (string) config('social.x.client_id');
        $secret = config('social.x.client_secret');

        try {
            $response = $this->call('token refresh', function () use ($clientId, $secret, $refreshToken) {
                $request = $this->http()->asForm();

                if (filled($secret)) {
                    $request = $request->withBasicAuth($clientId, (string) $secret);
                }

                return $request->post(rtrim((string) config('social.x.api_url'), '/').'/2/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                ]);
            });
        } catch (SocialPublishException $e) {
            // invalid_grant (revoked/used refresh token) comes back as 400/401.
            if ($e->retryable) {
                throw $e;
            }

            return false;
        }

        $this->storeCredentials($account, array_filter([
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?: $refreshToken,
        ]), now()->addSeconds((int) $response->json('expires_in', 7200)));

        return true;
    }
}
