<?php

namespace App\Filament\Resources\HobbyPageResource\Pages;

use App\Filament\Resources\HobbyPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHobbyPages extends ListRecords
{
    protected static string $resource = HobbyPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
