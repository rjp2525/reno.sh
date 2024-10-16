<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum SkillProficiency: string
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case PROFICIENT = 'proficient';
    case EXPERT = 'expert';

    public function getLabel(): string
    {
        return Str::title($this->value);
    }
}
