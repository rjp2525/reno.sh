<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

use App\Enums\SocialProvider;
use App\Models\SocialPostTarget;
use App\Services\Social\Providers\Concerns\InteractsWithMetaGraph;
use App\Services\Social\PublishResult;

/**
 * Facebook Page posts via the Graph API, using the Page access token
 * (derived from a long-lived user token, so it doesn't expire).
 *
 *  - text/link:   POST /{page-id}/feed
 *  - one image:   POST /{page-id}/photos (binary upload)
 *  - many images: POST /{page-id}/photos?published=false for each, then /feed with attached_media
 */
class FacebookPublisher extends AbstractPublisher
{
    use InteractsWithMetaGraph;

    public function provider(): SocialProvider
    {
        return SocialProvider::FACEBOOK;
    }

    public function publish(SocialPostTarget $target): PublishResult
    {
        [$body, $media] = $this->prepare($target);
        $account = $target->account;
        $pageId = $account->provider_id;
        $auth = $this->graphAuth($this->requireCredential($account, 'access_token'), config('social.meta.app_secret'));
        $link = $target->post->link_url;

        if (count($media) === 1) {
            $item = $media[0];
            $response = $this->call('photo upload', fn () => $this->uploadHttp()
                ->attach('source', $this->mediaContents($item['path']), $this->filename($item), ['Content-Type' => $item['mime']])
                ->post($this->url("{$pageId}/photos"), array_filter([...$auth, 'message' => $body]) + ['published' => 'true']));

            $postId = (string) ($response->json('post_id') ?? $response->json('id'));

            return new PublishResult($postId, $this->permalink($postId, $auth));
        }

        $params = array_filter([...$auth, 'message' => $body]);

        if ($media !== []) {
            foreach (array_values($media) as $i => $item) {
                $photo = $this->call('photo upload', fn () => $this->uploadHttp()
                    ->attach('source', $this->mediaContents($item['path']), $this->filename($item), ['Content-Type' => $item['mime']])
                    ->post($this->url("{$pageId}/photos"), [...$auth, 'published' => 'false']));

                $params["attached_media[{$i}]"] = json_encode(['media_fbid' => (string) $photo->json('id')]);
            }
        } elseif ($link) {
            $params['link'] = $link;
        }

        $response = $this->call('post', fn () => $this->http()->asForm()->post($this->url("{$pageId}/feed"), $params));
        $postId = (string) $response->json('id');

        return new PublishResult($postId, $this->permalink($postId, $auth));
    }

    /** Best effort: the canonical permalink, falling back to the id-based URL. */
    private function permalink(string $postId, array $auth): string
    {
        try {
            $url = $this->http()->get($this->url($postId), [...$auth, 'fields' => 'permalink_url'])->json('permalink_url');
        } catch (\Throwable) {
            $url = null;
        }

        return is_string($url) && $url !== '' ? $url : "https://www.facebook.com/{$postId}";
    }

    private function url(string $path): string
    {
        return rtrim((string) config('social.meta.graph_url'), '/').'/'.config('social.meta.graph_version').'/'.ltrim($path, '/');
    }
}
