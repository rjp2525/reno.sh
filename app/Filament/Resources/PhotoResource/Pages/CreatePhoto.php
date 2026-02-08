<?php

namespace App\Filament\Resources\PhotoResource\Pages;

use App\Filament\Resources\PhotoResource;
use App\Jobs\ProcessPhotoJob;
use Filament\Resources\Pages\CreateRecord;

class CreatePhoto extends CreateRecord
{
    protected static string $resource = PhotoResource::class;

    protected function afterCreate(): void
    {
        ProcessPhotoJob::dispatch($this->record);
    }
}
