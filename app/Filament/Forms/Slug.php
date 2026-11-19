<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slugs that fill themselves in from a name/title field as you type.
 *
 *     Slug::source(TextInput::make('title')->required()),
 *     Slug::field(from: 'title'),
 *
 * The slug follows the source until you edit it by hand; taken slugs get -2, -3…
 */
final class Slug
{
    public static function source(TextInput $field, string $slug = 'slug'): TextInput
    {
        return $field
            ->live(debounce: 400)
            ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set, TextInput $component) use ($slug): void {
                if (! self::isInSync((string) $get($slug), $old)) {
                    return; // Edited by hand; leave it alone.
                }

                $set($slug, self::unique((string) $state, $component, $slug));
            });
    }

    public static function field(string $from = 'title', string $name = 'slug'): TextInput
    {
        return TextInput::make($name)
            ->required()
            ->maxLength(255)
            ->alphaDash()
            ->unique(ignoreRecord: true)
            ->placeholder('filled-in-from-the-'.$from)
            ->suffixAction(
                Action::make('regenerate'.Str::studly($name))
                    ->icon('heroicon-m-arrow-path')
                    ->tooltip('Regenerate from '.$from)
                    ->action(fn (Get $get, Set $set, TextInput $component) => $set($name, self::unique((string) $get($from), $component, $name))),
            );
    }

    /** Is the slug still the generated one for the previous source value (or empty)? */
    public static function isInSync(string $slug, ?string $previousSource): bool
    {
        if ($slug === '') {
            return true;
        }

        return preg_replace('/-\d+$/', '', $slug) === Str::slug((string) $previousSource)
            || $slug === Str::slug((string) $previousSource);
    }

    /** Str::slug($text), with -2, -3… appended if another record already uses it. */
    public static function unique(string $text, TextInput $component, string $column = 'slug'): string
    {
        $base = Str::slug($text);
        $model = $component->getModel();

        if ($base === '' || ! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return $base;
        }

        $record = $component->getRecord();
        // Without global scopes so soft-deleted rows (which still hold the slug) count as taken.
        $taken = fn (string $slug) => $model::query()->withoutGlobalScopes()
            ->where($column, $slug)
            ->when($record?->exists, fn ($q) => $q->whereKeyNot($record->getKey()))
            ->exists();

        $slug = $base;
        for ($i = 2; $taken($slug) && $i < 100; $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
