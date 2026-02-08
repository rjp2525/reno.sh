<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogSeriesResource\Pages;

use App\Filament\Resources\BlogSeriesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogSeries extends CreateRecord
{
    protected static string $resource = BlogSeriesResource::class;
}
