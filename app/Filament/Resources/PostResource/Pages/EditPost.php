<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostResource\Pages;

use App\Enums\PostStatus;
use App\Filament\Actions\ShareToSocialAction;
use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')
                ->label('View')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->url(), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->status === PostStatus::PUBLISHED),
            ShareToSocialAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
                Actions\ForceDeleteAction::make(),
            ]),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Saved';
    }
}
