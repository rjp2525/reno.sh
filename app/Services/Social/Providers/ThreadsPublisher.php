<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\SocialPostTarget;
use App\Services\Social\Providers\Concerns\InteractsWithMetaGraph;
use App\Services\Social\PublishResult;
use App\Services\Social\SocialPublishException;

/**
 * Threads API: create a container (TEXT / IMAGE / CAROUSEL) on /me/threads,
 * wait for it to finish, then /me/threads_publish. Images are fetched by
 * Threads from public URLs. Long-lived tokens (60 days) are refreshed with
 * th_refresh_token.
 */
class ThreadsPublisher extends AbstractPublisher
{
    use InteractsWithMetaGraph;

    public function provider(): SocialProvider
    {
        return SocialProvider::THREADS;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $auth = ['access_token' => $this->requireCredential($target->account, 'access_token')];

        if ($media === []) {
            $containerId = $this->createContainer($auth, ['media_type' => 'TEXT', 'text' => $body]);
        } elseif (count($media) === 1) {
            $containerId = $this->createContainer($auth, array_filter([
                'media_type' => 'IMAGE',
                'image_url' => $this->assertPublicUrl($media[0]['url']),
                'text' => $body,
            ]));
        } else {
            $children = [];

            foreach ($media as $item) {
                $children[] = $this->createContainer($auth, [
                    'media_type' => 'IMAGE',
                    'image_url' => $this->assertPublicUrl($item['url']),
                    'is_carousel_item' => 'true',
                ]);
            }

            foreach ($children as $child) {
                $this->waitUntilFinished($child, $auth);
            }

            $containerId = $this->createContainer($auth, array_filter([
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $children),
                'text' => $body,
            ]));
        }

        $this->waitUntilFinished($containerId, $auth);

        $mediaId = (string) $this->call('publish', fn () => $this->http()->asForm()
            ->post($this->url('me/threads_publish'), [...$auth, 'creation_id' => $containerId]))
            ->json('id');

        try {
            $permalink = $this->http()->get($this->url($mediaId), [...$auth, 'fields' => 'permalink'])->json('permalink');
        } catch (\Throwable) {
            $permalink = null;
        }

        return new PublishResult($mediaId, is_string($permalink) ? $permalink : null);
    }

    public function refresh(SocialAccount $account): bool
    {
        $token = $account->credential('access_token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $response = $this->call('token refresh', fn () => $this->http()->get(
                rtrim((string) config('social.threads.graph_url'), '/').'/refresh_access_token',
                ['grant_type' => 'th_refresh_token', 'access_token' => $token],
            ));
        } catch (SocialPublishException $e) {
            if ($e->needsReauth || ! $e->retryable) {
                return false;
            }

            throw $e;
        }

        $this->storeCredentials(
            $account,
            ['access_token' => (string) $response->json('access_token')],
            now()->addSeconds((int) $response->json('expires_in', 60 * 24 * 3600)),
        );

        return true;
    }

    private function createContainer(array $auth, array $params): string
    {
        return (string) $this->call('container', fn () => $this->http()->asForm()
            ->post($this->url('me/threads'), [...$auth, ...$params]))
            ->json('id');
    }

    private function waitUntilFinished(string $containerId, array $auth): void
    {
        $this->poll("container {$containerId}", function () use ($containerId, $auth) {
            $response = $this->call('container status', fn () => $this->http()
                ->get($this->url($containerId), [...$auth, 'fields' => 'status,error_message']));

            return match ($response->json('status')) {
                'FINISHED', 'PUBLISHED' => true,
                'ERROR', 'EXPIRED' => throw new SocialPublishException(
                    'Threads could not process the post: '.($response->json('error_message') ?: $response->json('status'))
                ),
                default => null,
            };
        });
    }

    private function url(string $path): string
    {
        return rtrim((string) config('social.threads.graph_url'), '/').'/'.config('social.threads.graph_version').'/'.ltrim($path, '/');
    }
}
