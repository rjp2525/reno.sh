<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class ContentBlocks
{
    public static function all(string $uploadDirectory = 'content'): array
    {
        return [
            static::richText(),
            static::image($uploadDirectory),
            static::imageGallery($uploadDirectory),
            static::heading(),
            static::twoColumns(),
            static::callout(),
            static::divider(),
            static::code(),
        ];
    }

    public static function richText(): Block
    {
        return Block::make('rich-text')
            ->label('Rich Text')
            ->icon('heroicon-o-document-text')
            ->schema([
                RichEditor::make('content')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'underline',
                        'undo',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function image(string $directory = 'content'): Block
    {
        return Block::make('image')
            ->label('Image')
            ->icon('heroicon-o-photo')
            ->schema([
                FileUpload::make('image')
                    ->image()
                    ->directory($directory)
                    ->disk('public')
                    ->required(),
                TextInput::make('caption')
                    ->maxLength(255),
                Select::make('alignment')
                    ->options([
                        'left' => 'Left',
                        'center' => 'Center',
                        'right' => 'Right',
                        'full' => 'Full Width',
                    ])
                    ->default('center')
                    ->native(false),
            ]);
    }

    public static function imageGallery(string $directory = 'content'): Block
    {
        return Block::make('image-gallery')
            ->label('Image Gallery')
            ->icon('heroicon-o-squares-2x2')
            ->schema([
                Repeater::make('images')
                    ->schema([
                        FileUpload::make('image')
                            ->image()
                            ->directory($directory)
                            ->disk('public')
                            ->required(),
                        TextInput::make('caption')
                            ->maxLength(255),
                    ])
                    ->minItems(1)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? null),
                Select::make('columns')
                    ->options([
                        2 => '2 Columns',
                        3 => '3 Columns',
                        4 => '4 Columns',
                    ])
                    ->default(3)
                    ->native(false),
            ]);
    }

    public static function heading(): Block
    {
        return Block::make('heading')
            ->label('Heading')
            ->icon('heroicon-o-hashtag')
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->maxLength(255),
                Select::make('level')
                    ->options([
                        'h2' => 'H2',
                        'h3' => 'H3',
                        'h4' => 'H4',
                    ])
                    ->default('h2')
                    ->native(false),
            ]);
    }

    public static function twoColumns(): Block
    {
        return Block::make('two-columns')
            ->label('Two Columns')
            ->icon('heroicon-o-view-columns')
            ->schema([
                RichEditor::make('left')
                    ->label('Left Column')
                    ->toolbarButtons([
                        'bold',
                        'bulletList',
                        'italic',
                        'link',
                        'orderedList',
                    ]),
                RichEditor::make('right')
                    ->label('Right Column')
                    ->toolbarButtons([
                        'bold',
                        'bulletList',
                        'italic',
                        'link',
                        'orderedList',
                    ]),
                Select::make('ratio')
                    ->options([
                        '50-50' => '50 / 50',
                        '33-67' => '33 / 67',
                        '67-33' => '67 / 33',
                    ])
                    ->default('50-50')
                    ->native(false),
            ]);
    }

    public static function callout(): Block
    {
        return Block::make('callout')
            ->label('Callout')
            ->icon('heroicon-o-megaphone')
            ->schema([
                Select::make('type')
                    ->options([
                        'info' => 'Info',
                        'tip' => 'Tip',
                        'warning' => 'Warning',
                        'note' => 'Note',
                    ])
                    ->default('info')
                    ->native(false),
                RichEditor::make('content')
                    ->toolbarButtons([
                        'bold',
                        'bulletList',
                        'italic',
                        'link',
                        'orderedList',
                    ]),
            ]);
    }

    public static function divider(): Block
    {
        return Block::make('divider')
            ->label('Divider')
            ->icon('heroicon-o-minus')
            ->schema([
                Select::make('style')
                    ->options([
                        'solid' => 'Solid',
                        'dashed' => 'Dashed',
                        'dotted' => 'Dotted',
                        'gradient' => 'Gradient',
                    ])
                    ->default('solid')
                    ->native(false),
            ]);
    }

    public static function code(): Block
    {
        return Block::make('code')
            ->label('Code')
            ->icon('heroicon-o-code-bracket')
            ->schema([
                Select::make('language')
                    ->options([
                        'php' => 'PHP',
                        'javascript' => 'JavaScript',
                        'typescript' => 'TypeScript',
                        'html' => 'HTML',
                        'css' => 'CSS',
                        'vue' => 'Vue',
                        'bash' => 'Bash',
                        'sql' => 'SQL',
                        'json' => 'JSON',
                        'yaml' => 'YAML',
                        'python' => 'Python',
                        'rust' => 'Rust',
                        'go' => 'Go',
                        'plaintext' => 'Plain Text',
                    ])
                    ->default('php')
                    ->native(false)
                    ->required(),
                Textarea::make('code')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull(),
                TextInput::make('filename')
                    ->maxLength(255)
                    ->placeholder('e.g., app/Models/Post.php'),
            ]);
    }
}
