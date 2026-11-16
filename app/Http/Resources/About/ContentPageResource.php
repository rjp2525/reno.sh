<?php

namespace App\Http\Resources\About;

use App\Models\ContentPage;
use App\Support\Content\Blocks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentPage */
class ContentPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = $this->content ?? [];

        return [
            'id' => (string) $this->id,
            'section' => $this->section->value,
            'slug' => $this->slug,
            'title' => $this->title,
            'icon' => $this->icon,
            'order' => (int) $this->order,
            'is_published' => (bool) $this->is_published,
            'markdown' => Blocks::toMarkdown($content),
            'is_markdown' => Blocks::isMarkdownDocument($content),
            'blocks' => Blocks::withUrls($content),
            'url' => route('about.'.$this->section->value, $this->slug),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
