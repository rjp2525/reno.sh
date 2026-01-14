<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ContentSection;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ContentPageResource\Pages;
use App\Models\ContentPage;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContentPageResource extends Resource
{
    protected static ?string $model = ContentPage::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::CONTENT->getLabel();
    }

    public static function getNavigationLabel(): string
    {
        return 'Content Pages';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Section::make('Page Settings')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),

                        Select::make('section')
                            ->options(ContentSection::class)
                            ->required()
                            ->native(false),

                        TextInput::make('icon')
                            ->maxLength(255)
                            ->placeholder('e.g., camera, hiking, car'),

                        TextInput::make('order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_published')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Content')
                    ->schema([
                        RichEditor::make('content')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable(),

                TextColumn::make('section')
                    ->badge()
                    ->formatStateUsing(fn (ContentSection $state) => $state->getLabel())
                    ->color(fn (ContentSection $state) => match ($state) {
                        ContentSection::PERSONAL => 'info',
                        ContentSection::HOBBIES => 'success',
                    }),

                TextColumn::make('order')
                    ->sortable(),

                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),

                TextColumn::make('updated_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->filters([
                SelectFilter::make('section')
                    ->options(ContentSection::class),

                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentPages::route('/'),
            'create' => Pages\CreateContentPage::route('/create'),
            'edit' => Pages\EditContentPage::route('/{record}/edit'),
        ];
    }
}
