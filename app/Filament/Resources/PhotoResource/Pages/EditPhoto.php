<?php

namespace App\Filament\Resources\PhotoResource\Pages;

use App\Enums\PhotoProcessingStatus;
use App\Filament\Resources\PhotoResource;
use App\Jobs\ProcessPhotoJob;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhoto extends EditRecord
{
    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->update(['processing_status' => PhotoProcessingStatus::PENDING]);
        ProcessPhotoJob::dispatch($this->record);
    }
}
