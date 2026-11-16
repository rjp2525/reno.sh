<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SocialPostStatus;
use App\Filament\Resources\SocialPostResource;
use App\Models\SocialPost;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingSocialPosts extends TableWidget
{
    protected static ?int $sort = 1;

    protected static ?string $heading = 'Up next on social';

    public function table(Table $table): Table
    {
        return $table
            ->query(SocialPost::query()->with('targets.account')
                ->whereIn('status', [SocialPostStatus::SCHEDULED, SocialPostStatus::PUBLISHING, SocialPostStatus::FAILED])
                ->orderByRaw("case when status = 'failed' then 0 else 1 end")
                ->orderBy('scheduled_at'))
            ->columns([
                TextColumn::make('body')->limit(60)->wrap()->label('Post'),
                TextColumn::make('networks')->badge()
                    ->state(fn (SocialPost $r) => $r->targets->map(fn ($t) => $t->account?->provider)->filter()->unique()->values()->all()),
                TextColumn::make('scheduled_at')->since()->label('When')
                    ->color(fn (SocialPost $r) => $r->status === SocialPostStatus::FAILED ? 'danger' : null),
            ])
            ->recordUrl(fn (SocialPost $r) => SocialPostResource::getUrl('edit', ['record' => $r]))
            ->paginated([5])
            ->emptyStateHeading('Nothing queued')
            ->emptyStateDescription('Compose a post or share a blog post to fill the queue.');
    }
}
