<?php

namespace App\Http\Resources\About;

use App\Models\Education;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Education */
class EducationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'school_name' => $this->school_name,
            'location' => $this->location,
            'degree' => $this->degree,
            'minor' => $this->minor,
            'started' => $this->started?->toDateString(),
            'graduated' => $this->graduated?->toDateString(),
            'description' => $this->description,
            'achievements' => array_values($this->achievements ?? []),
            'projects' => array_values($this->projects ?? []),
            'extracurriculars' => array_values($this->extracurriculars ?? []),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
