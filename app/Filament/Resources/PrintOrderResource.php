<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PrintOrderStatus;
use App\Filament\Resources\PrintOrderResource\Pages;
use App\Jobs\SubmitPrintOrder;
use App\Models\PrintOrder;
use App\Services\Prints\ProdigiClient;
use App\Services\Prints\ProdigiSync;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrintOrderResource extends Resource
{
    protected static ?string $model = PrintOrder::class;

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-receipt-percent';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::SHOP->getLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = PrintOrder::whereIn('status', [PrintOrderStatus::PAID, PrintOrderStatus::FAILED])->count();

        return $n ? (string) $n : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'email', 'name'];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Group::make()->columnSpan(['lg' => 2])->schema([
                Section::make('Items')->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->contained(false)->schema([
                        Grid::make(['default' => 4])->schema([
                            ImageEntry::make('photo.thumbnail_url')->hiddenLabel()->imageHeight(64)->extraImgAttributes(['class' => 'rounded-md']),
                            TextEntry::make('photo_title')->label('Photo')->weight('semibold'),
                            TextEntry::make('product_name')->label('Product')->helperText(fn ($record) => $record->sku),
                            TextEntry::make('quantity')->label('Qty')->suffix(fn ($record) => ' × '.$record->order->formatMoney($record->unit_price)),
                        ]),
                    ]),
                ]),
                Section::make('Fulfilment')->schema([
                    TextEntry::make('prodigi_order_id')->label('Prodigi order')->placeholder('Not submitted yet')->copyable()->fontFamily('mono'),
                    TextEntry::make('tracking')
                        ->label('Tracking')
                        ->placeholder('No shipments yet')
                        ->state(fn (PrintOrder $r) => collect($r->tracking ?? [])->map(fn ($t) => trim(($t['carrier'] ?? '').' '.($t['number'] ?? '')))->all())
                        ->listWithLineBreaks()
                        ->url(fn (PrintOrder $r) => $r->tracking[0]['url'] ?? null, shouldOpenInNewTab: true),
                    TextEntry::make('error')->color('danger')->visible(fn (PrintOrder $r) => filled($r->error)),
                ]),
            ]),
            Group::make()->schema([
                Section::make('Summary')->schema([
                    TextEntry::make('status')->badge(),
                    TextEntry::make('total')->formatStateUsing(fn (PrintOrder $r) => $r->formatMoney($r->total))
                        ->helperText(fn (PrintOrder $r) => 'Subtotal '.$r->formatMoney($r->subtotal).' · shipping '.$r->formatMoney($r->shipping).' · tax '.$r->formatMoney($r->tax)),
                    TextEntry::make('paid_at')->dateTime()->placeholder('Unpaid'),
                    TextEntry::make('shipped_at')->dateTime()->placeholder('—'),
                ]),
                Section::make('Customer')->schema([
                    TextEntry::make('name')->placeholder('—'),
                    TextEntry::make('email')->copyable()->placeholder('—'),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('shipping_address')
                        ->label('Ship to')
                        ->state(fn (PrintOrder $r) => collect($r->shipping_address ?? [])->only(['line1', 'line2', 'city', 'state', 'postal_code', 'country'])->filter()->implode(', '))
                        ->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->fontFamily('mono')->weight('semibold')->searchable()
                    ->description(fn (PrintOrder $r) => $r->items->map(fn ($i) => $i->photo_title)->implode(', ')),
                TextColumn::make('name')->description(fn (PrintOrder $r) => $r->email)->searchable(['name', 'email']),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')->money(fn (PrintOrder $r) => $r->currency, divideBy: 100)->sortable(),
                TextColumn::make('created_at')->since()->label('Placed')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(PrintOrderStatus::class)])
            ->recordActions([
                ViewAction::make(),
                ...self::fulfilmentActions(),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders appear here after a customer completes checkout.');
    }

    /** @return list<Action> */
    public static function fulfilmentActions(): array
    {
        return [
            Action::make('resubmit')
                ->label('Submit to Prodigi')
                ->icon('heroicon-m-paper-airplane')
                ->color('warning')
                ->visible(fn (PrintOrder $r) => in_array($r->status, [PrintOrderStatus::PAID, PrintOrderStatus::FAILED], true) && ! $r->prodigi_order_id)
                ->requiresConfirmation()
                ->action(fn (PrintOrder $r) => SubmitPrintOrder::dispatch($r))
                ->successNotificationTitle('Queued for submission'),
            Action::make('sync')
                ->label('Refresh status')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->visible(fn (PrintOrder $r) => filled($r->prodigi_order_id))
                ->action(fn (PrintOrder $r, ProdigiClient $prodigi, ProdigiSync $sync) => $sync->apply($r, $prodigi->getOrder($r->prodigi_order_id)))
                ->successNotificationTitle('Status updated'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('items');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintOrders::route('/'),
            'view' => Pages\ViewPrintOrder::route('/{record}'),
        ];
    }
}
