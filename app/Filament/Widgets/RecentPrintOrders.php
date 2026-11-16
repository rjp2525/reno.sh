<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\PrintOrderResource;
use App\Models\PrintOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentPrintOrders extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Recent print orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(PrintOrder::query()->with('items')->whereNotNull('paid_at')->latest('paid_at'))
            ->columns([
                TextColumn::make('number')->fontFamily('mono')
                    ->description(fn (PrintOrder $r) => $r->items->pluck('photo_title')->implode(', ')),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')->money(fn (PrintOrder $r) => $r->currency, divideBy: 100),
            ])
            ->recordUrl(fn (PrintOrder $r) => PrintOrderResource::getUrl('view', ['record' => $r]))
            ->paginated([5])
            ->emptyStateHeading('No orders yet');
    }
}
