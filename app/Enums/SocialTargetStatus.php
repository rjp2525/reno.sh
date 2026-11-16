<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SocialTargetStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PUBLISHING => 'warning',
            self::PUBLISHED => 'success',
            self::FAILED => 'danger',
        };
    }
}
