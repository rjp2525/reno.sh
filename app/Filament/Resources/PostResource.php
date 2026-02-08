<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PostStatus;
use App\Filament\Blocks\ContentBlocks;
use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?int $navigationSort = 0;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-pencil-square';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::BLOG->getLabel();
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->components([
                Section::make('Post Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, $set) {
                                if ($operation === 'create') {
                                    $set('slug', str($state)->slug()->toString());
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(500),
                    ]),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Organization')
                            ->schema([
                                Select::make('blog_category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('slug')->required()->alphaDash(),
                                    ])
                                    ->native(false),

                                Select::make('blog_series_id')
                                    ->label('Series')
                                    ->relationship('series', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->native(false),

                                TextInput::make('series_order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->visible(fn ($get) => filled($get('blog_series_id'))),

                                SpatieTagsInput::make('tags'),
                            ]),

                        Section::make('Publishing')
                            ->schema([
                                Select::make('status')
                                    ->options(PostStatus::class)
                                    ->default(PostStatus::DRAFT)
                                    ->native(false)
                                    ->required(),

                                DateTimePicker::make('published_at')
                                    ->native(false),

                                Toggle::make('is_featured')
                                    ->default(false),

                                FileUpload::make('featured_image')
                                    ->label('Featured image')
                                    ->image()
                                    ->directory('blog/featured')
                                    ->disk('public'),
                            ]),
                    ]),

                Section::make('Content')
                    ->schema([
                        Builder::make('content')
                            ->blocks(ContentBlocks::all('blog/images'))
                            ->blockPickerColumns(['default' => 2, 'md' => 4])
                            ->collapsible()
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO & Metadata')
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(255)
                            ->hint('If empty, will use the post title'),

                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->hint('Recommended: 150-160 characters'),

                        TagsInput::make('meta_keywords')
                            ->hint('SEO keywords for this post'),

                        FileUpload::make('og_image')
                            ->image()
                            ->directory('blog/og')
                            ->disk('public')
                            ->hint('If empty, will use featured image'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->badge()
                    ->label('Category'),

                TextColumn::make('status')
                    ->badge(),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),

                TextColumn::make('reading_time')
                    ->suffix(' min')
                    ->label('Read Time'),

                TextColumn::make('published_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PostStatus::class),

                SelectFilter::make('blog_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
