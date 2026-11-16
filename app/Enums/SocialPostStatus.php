<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SocialPostStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case PARTIALLY_PUBLISHED = 'partially_published';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::PUBLISHING => 'Publishing',
            self::PUBLISHED => 'Published',
            self::PARTIALLY_PUBLISHED => 'Partially published',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'info',
            self::PUBLISHING => 'warning',
            self::PUBLISHED => 'success',
            self::PARTIALLY_PUBLISHED => 'warning',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil',
            self::SCHEDULED => 'heroicon-o-clock',
            self::PUBLISHING => 'heroicon-o-arrow-path',
            self::PUBLISHED => 'heroicon-o-check-circle',
            self::PARTIALLY_PUBLISHED => 'heroicon-o-exclamation-triangle',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::FAILED], true);
    }
}
