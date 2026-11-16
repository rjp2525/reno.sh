<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostResource\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\PostResource;
use App\Models\Post;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New post'),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (PostStatus $s) => Post::where('status', $s)->count();

        return [
            'all' => Tab::make('All')->icon('heroicon-m-queue-list'),
            'drafts' => Tab::make('Drafts')->icon('heroicon-m-pencil')
                ->badge($count(PostStatus::DRAFT))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PostStatus::DRAFT)),
            'scheduled' => Tab::make('Scheduled')->icon('heroicon-m-clock')
                ->badge($count(PostStatus::SCHEDULED))->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PostStatus::SCHEDULED)),
            'published' => Tab::make('Published')->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PostStatus::PUBLISHED)),
            'archived' => Tab::make('Archived')->icon('heroicon-m-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PostStatus::ARCHIVED)),
        ];
    }
}
