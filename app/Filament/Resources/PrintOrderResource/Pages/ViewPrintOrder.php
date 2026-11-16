<?php

namespace App\Filament\Resources\PrintOrderResource\Pages;

use App\Filament\Resources\PrintOrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPrintOrder extends ViewRecord
{
    protected static string $resource = PrintOrderResource::class;

    public function getTitle(): string
    {
        return 'Order '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return PrintOrderResource::fulfilmentActions();
    }
}
