<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\ProjectType;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers\TagsRelationManager;
use App\Models\Project;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-code-bracket-square';
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
                        Section::make('Basic Information')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->columnSpanFull(),

                                Textarea::make('summary')
                                    ->maxLength(500)
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->maxLength(65535)
                                    ->rows(6)
                                    ->columnSpanFull(),

                                Grid::make(2)
                                    ->schema([
                                        Select::make('type')
                                            ->options(ProjectType::class)
                                            ->native(false),

                                        Select::make('status')
                                            ->options([
                                                'active' => 'Active',
                                                'archived' => 'Archived',
                                                'in_development' => 'In Development',
                                            ])
                                            ->default('active')
                                            ->native(false),
                                    ]),
                            ])
                            ->columnSpan(2),

                        Section::make('Display Options')
                            ->schema([
                                Toggle::make('is_published')
                                    ->default(true),

                                Toggle::make('is_featured')
                                    ->default(false),

                                Toggle::make('is_ongoing')
                                    ->default(false),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->step(1),
                            ])
                            ->columnSpan(1),
                    ]),

                Section::make('Media & URLs')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('featured_image')
                                    ->image()
                                    ->directory('projects/featured')
                                    ->columnSpan(1),

                                Repeater::make('gallery')
                                    ->schema([
                                        FileUpload::make('image')
                                            ->image()
                                            ->directory('projects/gallery')
                                            ->required(),
                                    ])
                                    ->columns(1)
                                    ->columnSpan(1),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('url')
                                    ->url()
                                    ->maxLength(255)
                                    ->label('Primary URL'),

                                TextInput::make('github_url')
                                    ->url()
                                    ->maxLength(255)
                                    ->label('GitHub URL'),

                                TextInput::make('demo_url')
                                    ->url()
                                    ->maxLength(255)
                                    ->label('Demo URL'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Timeline')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->native(false),

                                DatePicker::make('end_date')
                                    ->native(false)
                                    ->disabled(fn ($get) => $get('is_ongoing')),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('SEO & Metadata')
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(255)
                            ->hint('If empty, will use the project title')
                            ->columnSpanFull(),

                        Textarea::make('meta_description')
                            ->maxLength(500)
                            ->rows(3)
                            ->hint('Recommended: 150-160 characters')
                            ->columnSpanFull(),

                        TagsInput::make('meta_keywords')
                            ->hint('SEO keywords for this project')
                            ->columnSpanFull(),

                        FileUpload::make('og_image')
                            ->image()
                            ->directory('projects/og')
                            ->hint('If empty, will use featured image')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Tags & Categories')
                    ->schema([
                        TagsInput::make('tags')
                            ->hint('General tags for categorization')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (ProjectType $state): string => match ($state?->value) {
                        'personal' => 'info',
                        'professional' => 'success',
                        'open_source' => 'warning',
                        'case_study' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'in_development' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    }),

                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),

                TextColumn::make('start_date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ProjectType::class),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archived',
                        'in_development' => 'In Development',
                    ]),

                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                EditAction::make()
                    ->slideOver(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            TagsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
