<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\PhotoResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\SocialPostResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string
    {
        $hour = (int) now(config('app.timezone'))->format('G');
        $greeting = match (true) {
            $hour < 5 => 'Burning the midnight oil',
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return $greeting.', '.str(auth()->user()?->name)->before(' ').'.';
    }

    public function getSubheading(): ?string
    {
        return 'Here\'s what\'s happening across the blog, gallery, socials and print shop.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newPost')->label('Write')->icon('heroicon-m-pencil-square')->url(PostResource::getUrl('create')),
            Action::make('compose')->label('Compose social')->icon('heroicon-m-megaphone')->color('info')->url(SocialPostResource::getUrl('create')),
            Action::make('upload')->label('Upload photos')->icon('heroicon-m-arrow-up-tray')->color('gray')->url(PhotoResource::getUrl('index').'?action=bulkUpload'),
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
