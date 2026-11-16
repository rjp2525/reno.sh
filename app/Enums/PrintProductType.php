<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PrintProductType: string implements HasLabel
{
    case FINE_ART = 'fine_art';
    case PHOTO_PRINT = 'photo_print';
    case CANVAS = 'canvas';
    case METAL = 'metal';
    case ACRYLIC = 'acrylic';
    case FRAMED = 'framed';

    public function getLabel(): string
    {
        return match ($this) {
            self::FINE_ART => 'Fine art giclée',
            self::PHOTO_PRINT => 'Photo print',
            self::CANVAS => 'Stretched canvas',
            self::METAL => 'Metal',
            self::ACRYLIC => 'Acrylic',
            self::FRAMED => 'Framed print',
        };
    }
}
