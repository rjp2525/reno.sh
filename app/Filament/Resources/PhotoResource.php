<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PhotoProcessingStatus;
use App\Filament\Resources\PhotoResource\Pages;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Services\Photo\ExifExtractorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoResource extends Resource
{
    protected static ?string $model = Photo::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-camera';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::CONTENT->getLabel();
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('Upload')
                            ->schema([
                                FileUpload::make('original_path')
                                    ->label('Photo')
                                    ->image()
                                    ->required()
                                    ->disk(config('photography.storage.originals_disk', 'photos_originals'))
                                    ->directory('uploads')
                                    ->visibility('private')
                                    ->imagePreviewHeight('300')
                                    ->maxSize(100 * 1024)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/tiff'])
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $filePath = null;

                                        if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                            $filePath = $state->getRealPath();
                                        } elseif (is_string($state)) {
                                            $disk = config('photography.storage.originals_disk', 'photos_originals');
                                            $filePath = Storage::disk($disk)->path($state);
                                        }

                                        if (! $filePath || ! file_exists($filePath)) {
                                            return;
                                        }

                                        $extractor = new ExifExtractorService;
                                        $exif = $extractor->extract($filePath);

                                        $set('iso', $exif['iso']);
                                        $set('aperture', $exif['aperture']);
                                        $set('shutter_speed', $exif['shutter_speed']);
                                        $set('focal_length', $exif['focal_length']);
                                        $set('camera_body', $exif['camera_body']);
                                        $set('lens', $exif['lens']);
                                        $set('gps_latitude', $exif['gps_latitude']);
                                        $set('gps_longitude', $exif['gps_longitude']);
                                        $set('taken_at', $exif['taken_at']);
                                        $set('width', $exif['width']);
                                        $set('height', $exif['height']);

                                        $set('file_size', filesize($filePath));
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(2),

                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_published')
                                    ->default(true),

                                Toggle::make('is_favorite')
                                    ->default(false),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->step(1),

                                Placeholder::make('processing_status')
                                    ->label('Processing Status')
                                    ->content(fn (?Photo $record): string => $record?->processing_status?->getLabel() ?? 'Pending')
                                    ->visible(fn (?Photo $record): bool => $record !== null),
                            ])
                            ->columnSpan(1),
                    ]),

                Section::make('Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state).'-'.Str::random(6))),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),

                        RichEditor::make('description')
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

                        Grid::make(2)
                            ->schema([
                                Select::make('photo_category_id')
                                    ->label('Category')
                                    ->options(PhotoCategory::active()->ordered()->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false),

                                SpatieTagsInput::make('tags')
                                    ->hint('Add tags for filtering'),
                            ]),

                        TextInput::make('instagram_link')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://instagram.com/p/...'),
                    ]),

                Section::make('EXIF Metadata')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('camera_body')
                                    ->label('Camera')
                                    ->maxLength(255),

                                TextInput::make('lens')
                                    ->maxLength(255),

                                TextInput::make('focal_length')
                                    ->maxLength(50),

                                TextInput::make('aperture')
                                    ->maxLength(50),

                                TextInput::make('shutter_speed')
                                    ->maxLength(50),

                                TextInput::make('iso')
                                    ->numeric(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('taken_at')
                                    ->label('Date Taken'),

                                Placeholder::make('dimensions')
                                    ->label('Dimensions')
                                    ->content(fn ($get): string => ($get('width') && $get('height'))
                                        ? $get('width').' × '.$get('height').' px'
                                        : 'Unknown'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Location')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gps_latitude')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->step(0.0000001),

                                TextInput::make('gps_longitude')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->step(0.0000001),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($get): bool => $get('gps_latitude') || $get('gps_longitude')),

                TextInput::make('width')->hidden()->dehydrated(),
                TextInput::make('height')->hidden()->dehydrated(),
                TextInput::make('file_size')->hidden()->dehydrated(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail_path')
                    ->label('Thumbnail')
                    ->disk('public')
                    ->width(80)
                    ->height(60),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('category.name')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'primary' : 'gray')
                    ->placeholder('Uncategorized'),

                TextColumn::make('camera_body')
                    ->label('Camera')
                    ->toggleable()
                    ->limit(20),

                TextColumn::make('taken_at')
                    ->label('Date Taken')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('processing_status')
                    ->badge()
                    ->color(fn (PhotoProcessingStatus $state): string => $state->getColor()),

                IconColumn::make('is_favorite')
                    ->boolean()
                    ->label('Fav')
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning'),

                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Pub'),

                TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('photo_category_id')
                    ->label('Category')
                    ->options(PhotoCategory::active()->ordered()->pluck('name', 'id')),

                SelectFilter::make('processing_status')
                    ->options(PhotoProcessingStatus::class),

                TernaryFilter::make('is_favorite')
                    ->label('Favorites'),

                TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->slideOver(),
                Action::make('reprocess')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Photo $record): bool => $record->processing_status === PhotoProcessingStatus::FAILED)
                    ->action(function (Photo $record) {
                        $record->update(['processing_status' => PhotoProcessingStatus::PENDING]);
                        \App\Jobs\ProcessPhotoJob::dispatch($record);
                    }),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Photo')
                    ->modalDescription('This will permanently delete the photo and all associated files (original, web, thumbnail, OG image). This action cannot be undone.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Photos')
                        ->modalDescription('This will permanently delete the selected photos and all associated files. This action cannot be undone.'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhotos::route('/'),
            'create' => Pages\CreatePhoto::route('/create'),
            'view' => Pages\ViewPhoto::route('/{record}'),
            'edit' => Pages\EditPhoto::route('/{record}/edit'),
        ];
    }
}
