<?php

namespace App\Filament\Resources\PrintProductResource\Pages;

use App\Filament\Resources\PrintProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePrintProducts extends ManageRecords
{
    protected static string $resource = PrintProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->slideOver()];
    }
}
