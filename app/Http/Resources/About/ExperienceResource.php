<?php

namespace App\Http\Resources\About;

use App\Models\Experience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Experience */
class ExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'company' => $this->company,
            'location' => $this->location,
            'start' => $this->start?->toDateString(),
            'end' => $this->end?->toDateString(),
            'responsibilities' => array_values($this->responsibilities ?? []),
            'tenure' => $this->tenure,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
