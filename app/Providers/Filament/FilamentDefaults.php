<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Table;

/**
 * Panel-wide component defaults so every screen behaves the same way:
 * drag & drop uploads with previews + editor, searchable non-native selects,
 * auto-growing textareas, consistent tables. Individual fields can still
 * override any of these.
 */
final class FilamentDefaults
{
    public static function apply(): void
    {
        FileUpload::configureUsing(function (FileUpload $upload): void {
            $upload
                ->disk('public')
                ->visibility('public')
                ->openable()
                ->downloadable()
                ->previewable()
                ->pasteable()
                ->reorderable()
                ->orientImagesFromExif()
                ->imagePreviewHeight('220')
                ->panelAspectRatio(null)
                ->loadingIndicatorPosition('center')
                ->uploadProgressIndicatorPosition('center')
                ->removeUploadedFileButtonPosition('right')
                ->uploadButtonPosition('right')
                ->maxSize(50 * 1024)
                ->maxParallelUploads(4)
                ->placeholder('Drag & drop files here, paste, or <span class="filepond--label-action">browse</span>')
                ->hint('Drag to reorder · paste from clipboard');
        });

        Select::configureUsing(fn (Select $select) => $select->native(false));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
            ->native(false)
            ->seconds(false)
            ->displayFormat('M j, Y · g:i A')
            ->firstDayOfWeek(1)
            ->closeOnDateSelection());

        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('M j, Y')
            ->closeOnDateSelection());

        Textarea::configureUsing(fn (Textarea $textarea) => $textarea->autosize()->rows(3));

        Toggle::configureUsing(fn (Toggle $toggle) => $toggle->onColor('primary'));

        TagsInput::configureUsing(fn (TagsInput $tags) => $tags->reorderable()->splitKeys(['Tab', ',']));
        SpatieTagsInput::configureUsing(fn (SpatieTagsInput $tags) => $tags->splitKeys(['Tab', ',']));

        RichEditor::configureUsing(fn (RichEditor $editor) => $editor
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public'));

        // Markdown attachments are always public (Filament enforces it).
        MarkdownEditor::configureUsing(fn (MarkdownEditor $editor) => $editor->fileAttachmentsDisk('public'));

        Section::configureUsing(fn (Section $section) => $section->compact());

        Table::configureUsing(fn (Table $table) => $table
            ->striped()
            ->deferLoading()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->searchDebounce('300ms')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->extremePaginationLinks()
            ->emptyStateIcon('heroicon-o-sparkles'));

        CreateAction::configureUsing(fn (CreateAction $action) => $action->icon('heroicon-m-plus'));
        EditAction::configureUsing(fn (EditAction $action) => $action->icon('heroicon-m-pencil-square'));
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->requiresConfirmation());

        Notification::configureUsing(fn (Notification $n) => $n->duration(4000));
    }
}
