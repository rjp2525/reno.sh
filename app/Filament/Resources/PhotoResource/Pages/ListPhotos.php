<?php

namespace App\Filament\Resources\PhotoResource\Pages;

use App\Enums\PhotoProcessingStatus;
use App\Filament\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Services\Photo\PhotoImporter;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulkUpload')
                ->label('Upload photos')
                ->icon('heroicon-m-arrow-up-tray')
                ->modalHeading('Upload photos')
                ->modalDescription('Drop a whole shoot. EXIF is read automatically and web versions are generated in the background.')
                ->modalWidth(Width::FourExtraLarge)
                ->modalSubmitActionLabel('Import')
                ->schema([
                    FileUpload::make('files')
                        ->hiddenLabel()
                        ->multiple()
                        ->image()
                        ->maxFiles(100)
                        ->maxSize(100 * 1024)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/heic', 'image/heif'])
                        ->disk(config('photography.storage.originals_disk', 'photos_originals'))
                        ->directory('uploads')
                        ->visibility('private')
                        ->storeFileNamesIn('original_names')
                        ->panelLayout('grid')
                        ->imagePreviewHeight('140')
                        ->required(),
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        Select::make('photo_category_id')
                            ->label('Category')
                            ->options(fn () => PhotoCategory::active()->ordered()->pluck('name', 'id'))
                            ->searchable(),
                        SpatieTagsInput::make('tags')->hint('Applied to every photo'),
                    ]),
                    Grid::make(['default' => 1, 'sm' => 3])->schema([
                        Toggle::make('is_published')->label('Publish')->default(true),
                        Toggle::make('prints_enabled')->label('Sell prints')->default(true),
                        Toggle::make('is_favorite')->label('Favorite')->default(false),
                    ]),
                ])
                ->action(function (array $data, PhotoImporter $importer): void {
                    $names = $data['original_names'] ?? [];
                    $count = 0;

                    foreach ((array) $data['files'] as $path) {
                        $importer->import(
                            $path,
                            [
                                'photo_category_id' => $data['photo_category_id'] ?? null,
                                'is_published' => $data['is_published'] ?? true,
                                'prints_enabled' => $data['prints_enabled'] ?? true,
                                'is_favorite' => $data['is_favorite'] ?? false,
                            ],
                            $data['tags'] ?? [],
                            $names[$path] ?? null,
                        );
                        $count++;
                    }

                    Notification::make()->success()
                        ->title("Importing {$count} photo".($count === 1 ? '' : 's'))
                        ->body('They\'ll appear as processing finishes.')
                        ->send();
                }),
            Actions\CreateAction::make()->label('Single photo')->color('gray'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'favorites' => Tab::make('Favorites')->icon('heroicon-m-star')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_favorite', true)),
            'drafts' => Tab::make('Unpublished')->icon('heroicon-m-eye-slash')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_published', false)),
            'processing' => Tab::make('Processing')->icon('heroicon-m-arrow-path')
                ->badge(fn () => Photo::whereIn('processing_status', [PhotoProcessingStatus::PENDING, PhotoProcessingStatus::PROCESSING, PhotoProcessingStatus::FAILED])->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('processing_status', [PhotoProcessingStatus::PENDING, PhotoProcessingStatus::PROCESSING, PhotoProcessingStatus::FAILED])),
        ];
    }
}
