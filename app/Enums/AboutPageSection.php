<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum AboutPageSection: string
{
    case PROFESSIONAL = 'professional';
    case PERSONAL = 'personal';
    case HOBBIES = 'hobbies';

    public function getLabel(): string
    {
        return Str::title($this->value);
    }
}
