<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PhotoProcessingStatus;
use App\Filament\Actions\ShareToSocialAction;
use App\Filament\Forms\Slug;
use App\Filament\Resources\PhotoResource\Pages;
use App\Jobs\ProcessPhotoJob;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\PrintProduct;
use App\Models\SocialAccount;
use App\Services\Photo\ExifExtractorService;
use App\Services\Photo\PhotoDetailsSuggester;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $filePath = self::localPath($state);

                                        if (! $filePath) {
                                            return;
                                        }

                                        $exif = (new ExifExtractorService)->extract($filePath);

                                        foreach (['iso', 'aperture', 'shutter_speed', 'focal_length', 'camera_body', 'lens', 'gps_latitude', 'gps_longitude', 'taken_at', 'width', 'height'] as $field) {
                                            $set($field, $exif[$field]);
                                        }

                                        $set('file_size', filesize($filePath));

                                        // Fresh uploads only: draft the details, without touching anything already typed.
                                        if ($state instanceof TemporaryUploadedFile) {
                                            self::fillSuggestions($set, $get, $filePath, $state->getClientOriginalName(), $exif, overwrite: false);
                                        }
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

                                Toggle::make('prints_enabled')
                                    ->label('Sell prints')
                                    ->default(true)
                                    ->helperText(fn ($get) => ($get('width') && $get('height'))
                                        ? PrintProduct::active()->get()->filter->fits((int) $get('width'), (int) $get('height'))->count().' print sizes fit this photo'
                                        : null),

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
                    ->headerActions([
                        Action::make('suggestDetails')
                            ->label('Suggest')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->tooltip('Have Claude suggest the title, description, category and tags from the photo')
                            ->visible(fn () => app(PhotoDetailsSuggester::class)->enabled())
                            ->action(function (Set $set, Get $get) {
                                $path = self::localPath($get('original_path'));

                                if (! $path) {
                                    Notification::make()->warning()->title('Upload a photo first')->send();

                                    return;
                                }

                                $exif = collect(['iso', 'aperture', 'shutter_speed', 'focal_length', 'camera_body', 'lens', 'gps_latitude', 'gps_longitude', 'taken_at'])
                                    ->mapWithKeys(fn ($f) => [$f => $get($f)])->all();

                                self::fillSuggestions($set, $get, $path, null, $exif, overwrite: true);
                            }),
                    ])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Slug::source(TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)),

                                Slug::field(from: 'title'),
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

                IconColumn::make('prints_enabled')
                    ->boolean()
                    ->trueIcon('heroicon-s-shopping-bag')
                    ->falseIcon('heroicon-o-shopping-bag')
                    ->label('Prints')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(fn () => Photo::whereIn('processing_status', [PhotoProcessingStatus::PENDING, PhotoProcessingStatus::PROCESSING])->exists() ? '5s' : null)
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
            ->recordActions([
                ViewAction::make(),
                ShareToSocialAction::make()
                    ->visible(fn (Photo $record) => $record->is_published && $record->processing_status === PhotoProcessingStatus::COMPLETED && SocialAccount::active()->exists()),
                EditAction::make()
                    ->slideOver(),
                Action::make('reprocess')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Photo $record): bool => $record->processing_status === PhotoProcessingStatus::FAILED)
                    ->action(function (Photo $record) {
                        $record->update(['processing_status' => PhotoProcessingStatus::PENDING]);
                        ProcessPhotoJob::dispatch($record);
                    }),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Photo')
                    ->modalDescription('This will permanently delete the photo and all associated files (original, web, thumbnail, OG image). This action cannot be undone.'),
            ])
            ->toolbarActions([
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

    /**
     * A local file for the photo in the upload field: the temporary upload, or the stored
     * original (copied down from R2 when the disk is remote).
     */
    public static function localPath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = Arr::first($state);
        }

        if ($state instanceof TemporaryUploadedFile) {
            return $state->getRealPath() ?: null;
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        $diskName = config('photography.storage.originals_disk', 'photos_originals');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return $disk->exists($state) ? $disk->path($state) : null;
        }

        $stream = rescue(fn () => $disk->readStream($state), null, false);
        if (! $stream) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($path, $stream);
        register_shutdown_function(fn () => @unlink($path));

        return $path;
    }

    /** Ask Claude for the details and put them in the form. */
    public static function fillSuggestions(Set $set, Get $get, string $path, ?string $filename, array $exif, bool $overwrite): void
    {
        $suggester = app(PhotoDetailsSuggester::class);

        if (! $suggester->enabled()) {
            return;
        }

        $details = $suggester->suggest($path, $filename, $exif);

        if (! $details) {
            Notification::make()->warning()->title('Couldn\'t suggest details for this photo')->body('Fill them in yourself, or try ✨ Suggest again.')->send();

            return;
        }

        $fill = fn (string $field) => $overwrite || self::isBlank($get($field));

        if ($fill('title')) {
            // Calls the title's hooks too, so the slug follows.
            $set('title', $details['title'], shouldCallUpdatedHooks: true);
        }

        if ($fill('description')) {
            $set('description', $details['description']);
        }

        if ($details['photo_category_id'] && $fill('photo_category_id')) {
            $set('photo_category_id', $details['photo_category_id']);
        }

        if ($details['tags'] !== [] && $fill('tags')) {
            $set('tags', $details['tags']);
        }

        Notification::make()->success()->title('Details suggested')->body('Check them over before saving.')->send();
    }

    /** Empty, including a rich-text editor holding only empty paragraphs (HTML or JSON document). */
    private static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [] || (isset($value['type']) && ! str_contains((string) json_encode($value), '"text"'));
        }

        return blank(trim(html_entity_decode(strip_tags((string) $value))));
    }
}
