<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case ABOUT = 'about';
    case BLOG = 'blog';
    case CONTENT = 'content';
    case SOCIAL = 'social';
    case SHOP = 'shop';
    case SYSTEM = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::ABOUT => 'About',
            self::BLOG => 'Blog',
            self::CONTENT => 'Photography',
            self::SOCIAL => 'Social',
            self::SHOP => 'Print shop',
            self::SYSTEM => 'System',
        };
    }
}
