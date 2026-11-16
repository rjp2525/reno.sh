<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\Content\Blocks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    /** Include blocks/text/markdown (detail views). */
    public bool $detailed = false;

    /** Include draft/admin fields. */
    public bool $admin = false;

    public function detailed(bool $value = true): static
    {
        $this->detailed = $value;

        return $this;
    }

    public function admin(bool $value = true): static
    {
        $this->admin = $value;

        return $this;
    }

    public function toArray(Request $request): array
    {
        // Everything under /api/v1/admin gets the admin shape.
        $this->admin = $this->admin || $request->routeIs('api.v1.admin.*');

        $summary = [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'url' => $this->url(),
            'featured_image_url' => Blocks::url($this->featured_image),
            'reading_time' => (int) $this->reading_time,
            'published_at' => $this->published_at?->toIso8601ZuluString(),
            'is_featured' => (bool) $this->is_featured,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => (string) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'color' => $this->category->color,
            ] : null),
            'series' => $this->whenLoaded('series', fn () => $this->series ? [
                'id' => (string) $this->series->id,
                'title' => $this->series->title,
                'slug' => $this->series->slug,
                'order' => (int) $this->series_order,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
        ];

        if ($this->detailed || $this->admin) {
            $summary += [
                'blocks' => Blocks::withUrls($this->content),
                'text' => Blocks::toText($this->content),
                'markdown' => Blocks::toMarkdown($this->content),
                'is_markdown' => Blocks::isMarkdownDocument($this->content),
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
            ];
        }

        if ($this->admin) {
            $summary += [
                'status' => $this->status?->value,
                'featured_image' => $this->featured_image,
                'blog_category_id' => $this->blog_category_id ? (string) $this->blog_category_id : null,
                'blog_series_id' => $this->blog_series_id ? (string) $this->blog_series_id : null,
                'series_order' => (int) $this->series_order,
                'created_at' => $this->created_at?->toIso8601ZuluString(),
                'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            ];
        }

        return $summary;
    }
}
