<?php

namespace App\Filament\Resources\PrintOrderResource\Pages;

use App\Enums\PrintOrderStatus;
use App\Filament\Resources\PrintOrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPrintOrders extends ListRecords
{
    protected static string $resource = PrintOrderResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'shipped' => Tab::make('Shipped')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [PrintOrderStatus::SHIPPED, PrintOrderStatus::COMPLETED])),
            'abandoned' => Tab::make('Abandoned')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [PrintOrderStatus::PENDING_PAYMENT, PrintOrderStatus::CANCELLED])),
            'all' => Tab::make('All'),
        ];
    }
}
