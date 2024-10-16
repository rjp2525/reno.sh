<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EducationResource\Pages;
use App\Models\Education;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EducationResource extends Resource
{
    protected static ?string $model = Education::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Tables\Columns\TextColumn::make('school_name'),
                Tables\Columns\TextColumn::make('degree'),
            ])
            ->filters([
                //
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
            'index' => Pages\ManageEducation::route('/'),
        ];
    }
}
