<?php

namespace App\Http\Resources\About;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Skill */
class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'category' => $this->category,
            'name' => $this->name,
            'proficiency' => $this->proficiency->value,
            'level' => $this->level,
            'started' => $this->started?->toDateString(),
            'ended' => $this->ended?->toDateString(),
            'experience' => $this->experience,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
