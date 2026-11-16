<?php

namespace App\Http\Resources;

use App\Models\Photo;
use App\Support\Content\HtmlConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Photo */
class PhotoResource extends JsonResource
{
    public bool $withPrintOptions = false;

    public bool $admin = false;

    public function withPrintOptions(bool $value = true): static
    {
        $this->withPrintOptions = $value;

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

        $options = $this->withPrintOptions || $this->admin ? $this->printOptions() : null;

        $data = [
            'id' => (string) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description_html' => $this->description,
            'text' => HtmlConverter::toText($this->description),
            'web_url' => $this->web_url,
            'thumbnail_url' => $this->thumbnail_url,
            'og_image_url' => $this->og_image_url,
            'width' => $this->width,
            'height' => $this->height,
            'taken_at' => $this->taken_at?->toIso8601ZuluString(),
            'exif_summary' => $this->exif_summary,
            'camera_body' => $this->camera_body,
            'lens' => $this->lens,
            'focal_length' => $this->focal_length,
            'aperture' => $this->aperture,
            'shutter_speed' => $this->shutter_speed,
            'iso' => $this->iso,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => (string) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'is_favorite' => (bool) $this->is_favorite,
            'prints_available' => (bool) $this->prints_enabled,
            'url' => route('gallery.show', $this->resource),
        ];

        if ($this->withPrintOptions) {
            $data['print_options'] = PrintOptionResource::collection($options);
        }

        if ($this->admin) {
            $data += [
                'processing_status' => $this->processing_status?->value,
                'is_published' => (bool) $this->is_published,
                'prints_enabled' => (bool) $this->prints_enabled,
                'sort_order' => (int) $this->sort_order,
                'created_at' => $this->created_at?->toIso8601ZuluString(),
            ];
        }

        return $data;
    }
}
