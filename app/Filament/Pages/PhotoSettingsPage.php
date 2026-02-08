<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\PhotoSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class PhotoSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Photo Settings';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.photo-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::CONTENT->getLabel();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Photo Settings';
    }

    public function mount(): void
    {
        $this->form->fill([
            'watermark_path' => PhotoSettings::getWatermarkPath(),
            'watermark_opacity' => PhotoSettings::getWatermarkOpacity(),
            'watermark_scale' => PhotoSettings::getWatermarkScale() * 100,
            'watermark_position' => PhotoSettings::getWatermarkPosition(),
            'web_max_width' => PhotoSettings::getWebMaxWidth(),
            'web_quality' => PhotoSettings::getWebQuality(),
            'thumbnail_width' => PhotoSettings::getThumbnailWidth(),
            'thumbnail_height' => PhotoSettings::getThumbnailHeight(),
            'og_image_enabled' => PhotoSettings::getOgImageEnabled(),
            'og_image_show_title' => PhotoSettings::getOgImageShowTitle(),
            'og_image_show_exif' => PhotoSettings::getOgImageShowExif(),
            'og_image_overlay_opacity' => PhotoSettings::getOgImageOverlayOpacity(),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Watermark Settings')
                    ->description('Configure the watermark that will be applied to web-sized images. SVG, PNG, or JPG formats are supported.')
                    ->schema([
                        FileUpload::make('watermark_path')
                            ->label('Watermark Logo')
                            ->disk('local')
                            ->directory('watermarks')
                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->image()
                            ->imagePreviewHeight('100')
                            ->helperText('Recommended: PNG with transparency or SVG. Max 2MB.'),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('watermark_opacity')
                                    ->label('Opacity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(40)
                                    ->helperText('0 = invisible, 100 = solid'),

                                TextInput::make('watermark_scale')
                                    ->label('Size')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->suffix('% of image width')
                                    ->default(8)
                                    ->helperText('Watermark width as percentage of photo'),

                                Select::make('watermark_position')
                                    ->label('Position')
                                    ->options([
                                        'bottom-right' => 'Bottom Right',
                                        'bottom-center' => 'Bottom Center',
                                        'bottom-left' => 'Bottom Left',
                                        'center' => 'Center',
                                        'top-right' => 'Top Right',
                                        'top-center' => 'Top Center',
                                        'top-left' => 'Top Left',
                                    ])
                                    ->default('bottom-right')
                                    ->native(false),
                            ]),
                    ]),

                Section::make('Web Image Settings')
                    ->description('Settings for the watermarked web-resolution images.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('web_max_width')
                                    ->label('Max Width')
                                    ->numeric()
                                    ->minValue(800)
                                    ->maxValue(4000)
                                    ->suffix('px')
                                    ->default(2000)
                                    ->helperText('Images wider than this will be resized'),

                                TextInput::make('web_quality')
                                    ->label('JPEG Quality')
                                    ->numeric()
                                    ->minValue(50)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(85)
                                    ->helperText('Higher = better quality, larger file'),
                            ]),
                    ]),

                Section::make('Thumbnail Settings')
                    ->description('Settings for the thumbnail images used in the gallery grid.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('thumbnail_width')
                                    ->label('Width')
                                    ->numeric()
                                    ->minValue(100)
                                    ->maxValue(800)
                                    ->suffix('px')
                                    ->default(400),

                                TextInput::make('thumbnail_height')
                                    ->label('Height')
                                    ->numeric()
                                    ->minValue(100)
                                    ->maxValue(800)
                                    ->suffix('px')
                                    ->default(300),
                            ]),
                    ]),

                Section::make('Open Graph Image Settings')
                    ->description('Settings for OG images used when sharing photos on social media. Images are 1200x630px.')
                    ->schema([
                        Toggle::make('og_image_enabled')
                            ->label('Generate OG Images')
                            ->default(true)
                            ->helperText('Automatically generate Open Graph images for social sharing'),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('og_image_show_title')
                                    ->label('Show Title')
                                    ->default(true)
                                    ->helperText('Overlay photo title on OG image'),

                                Toggle::make('og_image_show_exif')
                                    ->label('Show Camera Info')
                                    ->default(true)
                                    ->helperText('Overlay camera/EXIF info on OG image'),

                                TextInput::make('og_image_overlay_opacity')
                                    ->label('Overlay Opacity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(60)
                                    ->helperText('Darkness of gradient overlay'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PhotoSettings::set('watermark_path', $data['watermark_path'] ?? null);
        PhotoSettings::set('watermark_opacity', $data['watermark_opacity'] ?? 40);
        PhotoSettings::set('watermark_scale', ($data['watermark_scale'] ?? 8) / 100);
        PhotoSettings::set('watermark_position', $data['watermark_position'] ?? 'bottom-right');

        PhotoSettings::set('web_max_width', $data['web_max_width'] ?? 2000);
        PhotoSettings::set('web_quality', $data['web_quality'] ?? 85);

        PhotoSettings::set('thumbnail_width', $data['thumbnail_width'] ?? 400);
        PhotoSettings::set('thumbnail_height', $data['thumbnail_height'] ?? 300);

        PhotoSettings::set('og_image_enabled', $data['og_image_enabled'] ?? true);
        PhotoSettings::set('og_image_show_title', $data['og_image_show_title'] ?? true);
        PhotoSettings::set('og_image_show_exif', $data['og_image_show_exif'] ?? true);
        PhotoSettings::set('og_image_overlay_opacity', $data['og_image_overlay_opacity'] ?? 60);

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
