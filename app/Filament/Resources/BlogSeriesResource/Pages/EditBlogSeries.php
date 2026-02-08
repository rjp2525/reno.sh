<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogSeriesResource\Pages;

use App\Filament\Resources\BlogSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogSeries extends EditRecord
{
    protected static string $resource = BlogSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
