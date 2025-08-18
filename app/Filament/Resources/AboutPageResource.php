<?php

namespace App\Filament\Resources;

use App\Enums\AboutPageSection;
use App\Filament\Resources\AboutPageResource\Pages;
use App\Models\AboutPage;
use Filament\Forms\Components\Builder as BuilderComponent;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class AboutPageResource extends Resource
{
    protected static ?string $model = AboutPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'About';

    protected static ?string $navigationLabel = 'Page Content';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Select::make('section')
                    ->options(Arr::mapWithKeys(AboutPageSection::cases(), fn ($item, $key) => [
                        $item->value => $item->getLabel(),
                    ]))
                    ->required(),
                TextInput::make('order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),
                BuilderComponent::make('content')
                    ->blocks([
                        BuilderComponent\Block::make('richtext')
                            ->schema([
                                TextInput::make('column_span')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(12)
                                    ->step(1),
                                RichEditor::make('content')
                                    ->label('Rich Text')
                                    ->required(),
                            ]),
                        BuilderComponent\Block::make('image')
                            ->schema([
                                TextInput::make('column_span')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(12)
                                    ->step(1),
                                FileUpload::make('content')
                                    ->image()
                                    ->label('Image'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('section')
                    ->formatStateUsing(fn ($state) => $state->getLabel()),
                TextColumn::make('order')
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAboutPages::route('/'),
            'create' => Pages\CreateAboutPage::route('/create'),
            'edit' => Pages\EditAboutPage::route('/{record}/edit'),
        ];
    }
}
