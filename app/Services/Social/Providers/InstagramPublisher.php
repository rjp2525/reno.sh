<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialPostTarget;
use App\Services\Social\Providers\Concerns\InteractsWithMetaGraph;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublishException;

/**
 * Instagram professional accounts (Business/Creator linked to a Facebook Page)
 * through the Graph API content publishing flow:
 *
 *   POST /{ig-user-id}/media (image_url | is_carousel_item | CAROUSEL)
 *   GET  /{container-id}?fields=status_code   until FINISHED
 *   POST /{ig-user-id}/media_publish (creation_id)
 *   GET  /{media-id}?fields=permalink
 *
 * Instagram downloads images itself, so media URLs must be public (JPEG works best).
 */
class InstagramPublisher extends AbstractPublisher
{
    use InteractsWithMetaGraph;

    public function provider(): SocialProvider
    {
        return SocialProvider::INSTAGRAM;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $account = $target->account;
        $igUserId = $account->provider_id;
        $auth = $this->graphAuth($this->requireCredential($account, 'access_token'), config('social.meta.app_secret'));

        if (count($media) === 1) {
            $containerId = $this->createContainer($igUserId, $auth, array_filter([
                'image_url' => $this->assertPublicUrl($media[0]['url']),
                'caption' => $body,
            ]));
        } else {
            $children = [];

            foreach ($media as $item) {
                $children[] = $this->createContainer($igUserId, $auth, [
                    'image_url' => $this->assertPublicUrl($item['url']),
                    'is_carousel_item' => 'true',
                ]);
            }

            foreach ($children as $child) {
                $this->waitUntilFinished($child, $auth);
            }

            $containerId = $this->createContainer($igUserId, $auth, array_filter([
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $children),
                'caption' => $body,
            ]));
        }

        $this->waitUntilFinished($containerId, $auth);

        $mediaId = (string) $this->call('publish', fn () => $this->http()->asForm()
            ->post($this->url("{$igUserId}/media_publish"), [...$auth, 'creation_id' => $containerId]))
            ->json('id');

        try {
            $permalink = $this->http()->get($this->url($mediaId), [...$auth, 'fields' => 'permalink'])->json('permalink');
        } catch (\Throwable) {
            $permalink = null;
        }

        return new PublishResult($mediaId, is_string($permalink) ? $permalink : null);
    }

    private function createContainer(string $igUserId, array $auth, array $params): string
    {
        return (string) $this->call('media container', fn () => $this->http()->asForm()
            ->post($this->url("{$igUserId}/media"), [...$auth, ...$params]))
            ->json('id');
    }

    private function waitUntilFinished(string $containerId, array $auth): void
    {
        $this->poll("media container {$containerId}", function () use ($containerId, $auth) {
            $response = $this->call('container status', fn () => $this->http()
                ->get($this->url($containerId), [...$auth, 'fields' => 'status_code,status']));

            return match ($response->json('status_code')) {
                'FINISHED', 'PUBLISHED' => true,
                'ERROR', 'EXPIRED' => throw new SocialPublishException(
                    'Instagram could not process the media: '.($response->json('status') ?: $response->json('status_code'))
                ),
                default => null,
            };
        });
    }

    private function url(string $path): string
    {
        return rtrim((string) config('social.meta.graph_url'), '/').'/'.config('social.meta.graph_version').'/'.ltrim($path, '/');
    }
}
