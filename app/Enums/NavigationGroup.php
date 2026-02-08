<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case ABOUT = 'about';
    case BLOG = 'blog';
    case CONTENT = 'content';
    case SYSTEM = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::ABOUT => 'About',
            self::BLOG => 'Blog',
            self::CONTENT => 'Content',
            self::SYSTEM => 'System',
        };
    }
}
