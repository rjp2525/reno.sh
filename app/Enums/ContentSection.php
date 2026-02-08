<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContentSection: string implements HasLabel
{
    case PERSONAL = 'personal';
    case HOBBIES = 'hobbies';

    public function getLabel(): string
    {
        return match ($this) {
            self::PERSONAL => 'personal',
            self::HOBBIES => 'hobbies',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PERSONAL => 'person',
            self::HOBBIES => 'hobbies',
        };
    }
}
