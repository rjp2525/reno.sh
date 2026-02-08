<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\EducationResource\Pages;
use App\Models\Education;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EducationResource extends Resource
{
    protected static ?string $model = Education::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::ABOUT->getLabel();
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                TextInput::make('school_name')
                    ->required(),
                TextInput::make('location'),
                TextInput::make('degree')
                    ->required(),
                TextInput::make('minor'),
                DatePicker::make('started')
                    ->format('m/d/Y')
                    ->required(),
                DatePicker::make('graduated')
                    ->format('m/d/Y'),
                TextInput::make('description')
                    ->columnSpanFull(),
                Repeater::make('achievements')
                    ->simple(TextInput::make('achievement')->required())
                    ->columnSpanFull(),
                Repeater::make('projects')
                    ->simple(TextInput::make('project')->required())
                    ->columnSpanFull(),
                Repeater::make('extracurriculars')
                    ->simple(TextInput::make('extracurricular')->required())
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('school_name'),
                TextColumn::make('degree'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEducation::route('/'),
        ];
    }
}
