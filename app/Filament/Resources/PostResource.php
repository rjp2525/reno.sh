<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\PostStatus;
use App\Filament\Actions\ShareToSocialAction;
use App\Filament\Blocks\ContentBlocks;
use App\Filament\Forms\Slug;
use App\Filament\Resources\PostResource\Pages;
use App\Models\BlogCategory;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-pencil-square';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::BLOG->getLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $drafts = Post::where('status', PostStatus::DRAFT)->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Drafts';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'excerpt'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Status' => $record->status?->getLabel(), 'Category' => $record->category?->name];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                // ---- Writing area ------------------------------------------------
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Slug::source(TextInput::make('title')
                            ->hiddenLabel()
                            ->placeholder('Post title')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'reno-title-input'])),

                        Slug::field(from: 'title')
                            ->hiddenLabel()
                            ->prefix(fn (Get $get) => url('/blog/'.(BlogCategory::find($get('blog_category_id'))?->slug ?? 'post')).'/'),

                        Textarea::make('excerpt')
                            ->placeholder('A one or two sentence teaser shown in lists, feeds and social cards…')
                            ->maxLength(500)
                            ->live(debounce: 500)
                            ->hint(fn (?string $state) => Str::length((string) $state).' / 500'),

                        Builder::make('content')
                            ->hiddenLabel()
                            ->blocks(ContentBlocks::all('blog/images'))
                            ->default([['type' => 'markdown', 'data' => ['markdown' => '']]])
                            ->blockPickerColumns(['default' => 2, 'md' => 3])
                            ->blockPickerWidth('2xl')
                            ->addActionLabel('Add block')
                            ->addBetweenActionLabel('Insert block')
                            ->collapsible()
                            ->cloneable()
                            ->reorderableWithDragAndDrop()
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ]),

                // ---- Sidebar -----------------------------------------------------
                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->extraAttributes(['class' => 'lg:sticky lg:top-20'])
                    ->schema([
                        Section::make('Publish')
                            ->icon('heroicon-o-paper-airplane')
                            ->schema([
                                ToggleButtons::make('status')
                                    ->options(PostStatus::class)
                                    ->default(PostStatus::DRAFT)
                                    ->inline()
                                    ->grouped()
                                    ->live()
                                    ->required(),

                                DateTimePicker::make('published_at')
                                    ->label(fn (Get $get) => self::isStatus($get('status'), PostStatus::SCHEDULED) ? 'Publish at' : 'Published on')
                                    ->required(fn (Get $get) => self::isStatus($get('status'), PostStatus::SCHEDULED))
                                    ->minDate(fn (Get $get, string $operation) => self::isStatus($get('status'), PostStatus::SCHEDULED) && $operation === 'create' ? now() : null)
                                    ->visible(fn (Get $get) => ! self::isStatus($get('status'), PostStatus::DRAFT))
                                    ->helperText(fn (Get $get) => self::isStatus($get('status'), PostStatus::PUBLISHED) ? 'Leave empty to use “now”.' : null),

                                Toggle::make('is_featured')
                                    ->label('Feature on the blog')
                                    ->inline(),

                                Text::make(fn (?Post $record) => $record?->exists
                                    ? new HtmlString('Last saved '.$record->updated_at?->diffForHumans().' · '.$record->reading_time.' min read')
                                    : 'Not saved yet')
                                    ->color('gray')
                                    ->size('sm'),
                            ]),

                        Section::make('Featured image')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                FileUpload::make('featured_image')
                                    ->hiddenLabel()
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatioOptions(['16:9', '4:3', '1:1', null])
                                    ->directory('blog/featured'),
                            ]),

                        Section::make('Organize')
                            ->icon('heroicon-o-folder')
                            ->schema([
                                Select::make('blog_category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->createOptionForm([
                                        Slug::source(TextInput::make('name')->required()->maxLength(255)),
                                        Slug::field(from: 'name')->notIn(['series', 'feed.xml']),
                                    ]),

                                Select::make('blog_series_id')
                                    ->label('Series')
                                    ->relationship('series', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->live(),

                                TextInput::make('series_order')
                                    ->label('Part #')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->visible(fn (Get $get) => filled($get('blog_series_id'))),

                                SpatieTagsInput::make('tags')
                                    ->placeholder('laravel, vue, homelab…'),
                            ]),

                        Section::make('SEO & sharing')
                            ->icon('heroicon-o-magnifying-glass')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255)
                                    ->placeholder(fn (Get $get) => $get('title'))
                                    ->live(debounce: 500)
                                    ->hint(fn (?string $state, Get $get) => Str::length((string) ($state ?: $get('title'))).' / 60'),

                                Textarea::make('meta_description')
                                    ->maxLength(500)
                                    ->placeholder(fn (Get $get) => $get('excerpt'))
                                    ->live(debounce: 500)
                                    ->hint(fn (?string $state) => Str::length((string) $state).' / 160'),

                                TagsInput::make('meta_keywords')
                                    ->placeholder('Add keyword'),

                                FileUpload::make('og_image')
                                    ->label('Social card image')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatioOptions(['1.91:1'])
                                    ->directory('blog/og')
                                    ->helperText('1200×630 works best. Falls back to the featured image.'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featured_image')
                    ->label('')
                    ->disk('public')
                    ->imageWidth(72)
                    ->imageHeight(44)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover'])
                    ->defaultImageUrl(fn () => 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="72" height="44"><rect width="72" height="44" rx="6" fill="#1e2d3d"/></svg>')),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Post $record) => Str::limit((string) $record->excerpt, 90))
                    ->wrap(),

                TextColumn::make('category.name')
                    ->badge()
                    ->label('Category')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge(),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->label('')
                    ->action(fn (Post $record) => $record->update(['is_featured' => ! $record->is_featured]))
                    ->tooltip('Toggle featured'),

                TextColumn::make('reading_time')
                    ->suffix(' min')
                    ->label('Read')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->label('Publish date')
                    ->dateTime('M j, Y')
                    ->description(fn (Post $record) => $record->status === PostStatus::SCHEDULED ? $record->published_at?->diffForHumans() : null)
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->label('Edited')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Post $record) => static::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('blog_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),
                TernaryFilter::make('is_featured')->label('Featured'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View on site')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->url(fn (Post $record) => $record->url(), shouldOpenInNewTab: true)
                        ->visible(fn (Post $record) => $record->status === PostStatus::PUBLISHED),
                    EditAction::make(),
                    Action::make('publish')
                        ->icon('heroicon-m-paper-airplane')
                        ->color('success')
                        ->visible(fn (Post $record) => $record->status !== PostStatus::PUBLISHED)
                        ->requiresConfirmation()
                        ->action(fn (Post $record) => $record->update(['status' => PostStatus::PUBLISHED, 'published_at' => $record->published_at ?? now()]))
                        ->successNotificationTitle('Published'),
                    ShareToSocialAction::make(),
                    ReplicateAction::make()
                        ->label('Duplicate')
                        ->excludeAttributes(['slug', 'published_at', 'reading_time'])
                        ->mutateRecordDataUsing(fn (array $data) => [...$data, 'title' => $data['title'].' (copy)', 'status' => PostStatus::DRAFT])
                        ->beforeReplicaSaved(fn (Post $replica) => $replica->slug = Str::slug($replica->title).'-'.Str::lower(Str::random(4))),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->icon('heroicon-m-paper-airplane')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Post $p) => $p->update(['status' => PostStatus::PUBLISHED, 'published_at' => $p->published_at ?? now()])))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('archive')
                        ->icon('heroicon-m-archive-box')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => PostStatus::ARCHIVED]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No posts yet')
            ->emptyStateDescription('Write your first post, or draft one on the go from the iOS app.');
    }

    public static function getRecordRouteBindingEloquentQuery(): EloquentBuilder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        return parent::getEloquentQuery()->with('category')->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }

    private static function isStatus(mixed $state, PostStatus $status): bool
    {
        return ($state instanceof PostStatus ? $state : PostStatus::tryFrom((string) $state)) === $status;
    }
}
