<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialPostTarget;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublishException;

/**
 * Mastodon (any instance): POST /api/v2/media for each image (polling
 * /api/v1/media/{id} while it's processed asynchronously), then
 * POST /api/v1/statuses with an Idempotency-Key so retries never double-post.
 *
 * Credentials: instance_url, access_token (an app token with write:statuses + write:media).
 */
class MastodonPublisher extends AbstractPublisher
{
    public function provider(): SocialProvider
    {
        return SocialProvider::MASTODON;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $account = $target->account;
        $instance = rtrim($this->requireCredential($account, 'instance_url'), '/');
        $token = $this->requireCredential($account, 'access_token');

        if (! str_starts_with($instance, 'http')) {
            $instance = 'https://'.$instance;
        }

        $mediaIds = [];

        foreach ($media as $item) {
            $response = $this->call('media upload', fn () => $this->uploadHttp()
                ->withToken($token)
                ->attach('file', $this->mediaContents($item['path']), $this->filename($item), ['Content-Type' => $item['mime']])
                ->post("{$instance}/api/v2/media"));

            $id = (string) $response->json('id');

            if ($response->status() === 202 || $response->json('url') === null) {
                $this->poll("media {$id}", function () use ($instance, $token, $id) {
                    $check = $this->http()->withToken($token)->get("{$instance}/api/v1/media/{$id}");

                    if ($check->status() === 206) {
                        return null;
                    }

                    $this->call('media status', fn () => $check);

                    return $check->json('url') !== null ? true : null;
                });
            }

            $mediaIds[] = $id;
        }

        $response = $this->call('post', fn () => $this->http()
            ->withToken($token)
            ->withHeaders(['Idempotency-Key' => (string) $target->getKey()])
            ->post("{$instance}/api/v1/statuses", array_filter([
                'status' => $body,
                'media_ids' => $mediaIds,
                'visibility' => config('social.mastodon.visibility', 'public'),
            ])));

        $id = $response->json('id');

        if (! $id) {
            throw new SocialPublishException('Mastodon returned no status id.', retryable: true);
        }

        return new PublishResult((string) $id, $response->json('url'));
    }
}
