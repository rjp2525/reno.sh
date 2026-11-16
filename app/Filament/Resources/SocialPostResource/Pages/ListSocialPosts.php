<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialPostResource\Pages;

use App\Enums\SocialPostStatus;
use App\Filament\Pages\SocialCalendar;
use App\Filament\Resources\SocialPostResource;
use App\Models\SocialPost;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSocialPosts extends ListRecords
{
    protected static string $resource = SocialPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calendar')->icon('heroicon-m-calendar-days')->color('gray')->url(SocialCalendar::getUrl()),
            Actions\CreateAction::make()->label('Compose'),
        ];
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses, string $icon) => Tab::make($label)->icon($icon)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses));

        return [
            'upcoming' => $tab('Upcoming', [SocialPostStatus::SCHEDULED, SocialPostStatus::PUBLISHING], 'heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [SocialPostStatus::SCHEDULED, SocialPostStatus::PUBLISHING])->reorder('scheduled_at')),
            'drafts' => $tab('Drafts', [SocialPostStatus::DRAFT], 'heroicon-m-pencil'),
            'published' => $tab('Published', [SocialPostStatus::PUBLISHED, SocialPostStatus::PARTIALLY_PUBLISHED], 'heroicon-m-check-circle'),
            'failed' => $tab('Failed', [SocialPostStatus::FAILED, SocialPostStatus::PARTIALLY_PUBLISHED], 'heroicon-m-exclamation-triangle')
                ->badge(fn () => SocialPost::whereIn('status', [SocialPostStatus::FAILED, SocialPostStatus::PARTIALLY_PUBLISHED])->count() ?: null)
                ->badgeColor('danger'),
            'all' => Tab::make('All')->icon('heroicon-m-queue-list'),
        ];
    }
}
