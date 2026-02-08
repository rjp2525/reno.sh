<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\SkillProficiency;
use App\Filament\Resources\SkillResource\Pages;
use App\Models\Skill;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-sparkles';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::ABOUT->getLabel();
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                TextInput::make('category')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('proficiency')
                    ->options(Arr::mapWithKeys(SkillProficiency::cases(), fn ($item, $key) => [
                        $item->value => $item->getLabel(),
                    ]))
                    ->required(),
                DatePicker::make('started')
                    ->format('m/d/Y')
                    ->required(),
                DatePicker::make('ended')
                    ->format('m/d/Y'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('category'),
                TextColumn::make('experience'),
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
            'index' => Pages\ManageSkills::route('/'),
        ];
    }
}
