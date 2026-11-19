<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PrintProductType;
use App\Filament\Resources\PrintProductResource\Pages;
use App\Models\PrintProduct;
use App\Services\Prints\ProdigiClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PrintProductResource extends Resource
{
    protected static ?string $model = PrintProduct::class;

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-swatch';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::SHOP->getLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['default' => 1, 'md' => 2])->columnSpanFull()->schema([
                TextInput::make('name')->required()->placeholder('Fine Art Print'),
                Select::make('type')->options(PrintProductType::class)->required(),
                TextInput::make('sku')
                    ->label('Prodigi SKU')
                    ->required()
                    ->placeholder('GLOBAL-FAP-12X18')
                    ->helperText('Find SKUs in the Prodigi dashboard product catalogue, then use “Verify” to check them.'),
                Grid::make(2)->schema([
                    TextInput::make('width_in')->label('Width (in)')->numeric()->required()->minValue(1),
                    TextInput::make('height_in')->label('Height (in)')->numeric()->required()->minValue(1),
                ]),
                TextInput::make('price')
                    ->label('Price')
                    ->numeric()
                    ->required()
                    ->prefix('$')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state / 100, 2, '.', '') : null)
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                    ->helperText('What the customer pays, before shipping. Prodigi bills you their wholesale price.'),
                TextInput::make('min_dpi')
                    ->label('Minimum DPI')
                    ->numeric()
                    ->default(150)
                    ->helperText('Photos without enough pixels for this size are not offered it.'),
                Textarea::make('description')->columnSpanFull()->placeholder('Hahnemühle Photo Rag 308gsm, museum-grade archival inks…'),
                KeyValue::make('attributes')
                    ->label('Prodigi attributes')
                    ->keyLabel('Attribute')
                    ->valueLabel('Value')
                    ->helperText('E.g. color = black for frames, wrap = ImageWrap for canvas.')
                    ->columnSpanFull(),
                Toggle::make('is_active')->default(true),
                TextInput::make('sort_order')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (PrintProduct $r) => $r->type->getLabel())->searchable(),
                TextColumn::make('size')->state(fn (PrintProduct $r) => $r->sizeLabel()),
                TextColumn::make('sku')->fontFamily('mono')->copyable()->searchable(),
                TextColumn::make('price')->money(fn (PrintProduct $r) => $r->currency, divideBy: 100)->sortable(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([SelectFilter::make('type')->options(PrintProductType::class)])
            ->recordActions([
                Action::make('verify')
                    ->icon('heroicon-m-check-badge')
                    ->color('gray')
                    ->action(function (PrintProduct $record, ProdigiClient $prodigi) {
                        if (! $prodigi->isConfigured()) {
                            Notification::make()->warning()->title('Add PRODIGI_API_KEY to .env first')->send();

                            return;
                        }

                        $product = rescue(fn () => $prodigi->getProduct($record->sku), null);

                        $product
                            ? Notification::make()->success()->title('SKU found')
                                ->body(($product['description'] ?? $record->sku).' · attributes: '.(implode(', ', array_keys($product['attributes'] ?? [])) ?: 'none'))
                                ->send()
                            : Notification::make()->danger()->title('Prodigi doesn\'t know '.$record->sku)->send();
                    }),
                EditAction::make()->slideOver(),
                ReplicateAction::make()->label('Duplicate')->slideOver(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No print products')
            ->emptyStateDescription('Run `php artisan db:seed --class=PrintProductSeeder` for a starter catalog, then verify the SKUs.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePrintProducts::route('/')];
    }
}
