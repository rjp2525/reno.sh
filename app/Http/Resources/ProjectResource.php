<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Support\Content\Blocks;
use App\Support\Content\HtmlConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    public bool $detailed = false;

    public function detailed(bool $value = true): static
    {
        $this->detailed = $value;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'url' => $this->url,
            'github_url' => $this->github_url,
            'demo_url' => $this->demo_url,
            'featured_image_url' => Blocks::url($this->featured_image),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_ongoing' => (bool) $this->is_ongoing,
            'is_featured' => (bool) $this->is_featured,
            'technologies' => $this->whenLoaded('technologies', fn () => $this->technologies->map(fn ($t) => [
                'name' => $t->name, 'slug' => $t->slug, 'color' => $t->color,
            ])->values()),
            'web_url' => route('projects.show', $this->resource),
        ];

        if ($this->detailed) {
            $description = is_array($this->description) ? null : $this->description;
            $data += [
                'description_html' => $description,
                'text' => HtmlConverter::toText($description),
                'markdown' => HtmlConverter::toMarkdown($description),
                'gallery' => collect($this->gallery ?? [])->map(fn ($p) => Blocks::url(is_array($p) ? ($p['image'] ?? null) : $p))->filter()->values(),
            ];
        }

        return $data;
    }
}
