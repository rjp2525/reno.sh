<?php

namespace App\Http\Resources;

use App\Models\Photo;
use App\Models\Post;
use App\Models\SocialPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SocialPost */
class SocialPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'body' => $this->body,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at?->toIso8601ZuluString(),
            'published_at' => $this->published_at?->toIso8601ZuluString(),
            'link_url' => $this->link_url,
            'media' => $this->mediaItems(),
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($t) => [
                'id' => (string) $t->id,
                'account' => new SocialAccountResource($t->account),
                'status' => $t->status->value,
                'body_override' => $t->body_override,
                'provider_url' => $t->provider_url,
                'error' => $t->error,
                'published_at' => $t->published_at?->toIso8601ZuluString(),
            ])->values()),
            'source' => $this->whenLoaded('source', fn () => match (true) {
                $this->source instanceof Post => ['type' => 'post', 'id' => (string) $this->source->id, 'title' => $this->source->title],
                $this->source instanceof Photo => ['type' => 'photo', 'id' => (string) $this->source->id, 'title' => $this->source->title],
                default => null,
            }),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
