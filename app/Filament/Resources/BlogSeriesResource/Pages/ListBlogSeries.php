<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogSeriesResource\Pages;

use App\Filament\Resources\BlogSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogSeries extends ListRecords
{
    protected static string $resource = BlogSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
