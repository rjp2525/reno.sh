<?php

namespace App\Filament\Resources\HobbyPageResource\Pages;

use App\Filament\Resources\HobbyPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHobbyPage extends EditRecord
{
    protected static string $resource = HobbyPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
