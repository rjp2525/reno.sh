<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PhotoProcessingStatus;
use App\Enums\PostStatus;
use App\Enums\PrintOrderStatus;
use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use App\Filament\Resources\PhotoResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\PrintOrderResource;
use App\Filament\Resources\SocialPostResource;
use App\Models\Photo;
use App\Models\Post;
use App\Models\PrintOrder;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $drafts = Post::where('status', PostStatus::DRAFT)->count();
        $failedSocial = SocialPostTarget::where('status', SocialTargetStatus::FAILED)->where('updated_at', '>=', now()->subDays(7))->count();
        $processing = Photo::whereIn('processing_status', [PhotoProcessingStatus::PENDING, PhotoProcessingStatus::PROCESSING])->count();
        $revenue = (int) PrintOrder::whereNotNull('paid_at')->where('paid_at', '>=', now()->subDays(30))->whereNot('status', PrintOrderStatus::CANCELLED)->sum('total');

        $daily = fn (string $model, string $column) => collect(range(13, 0))
            ->map(fn ($d) => $model::whereDate($column, now()->subDays($d))->count())
            ->all();

        return [
            Stat::make('Published posts', Post::published()->count())
                ->description($drafts ? "{$drafts} draft".($drafts === 1 ? '' : 's').' in progress' : 'No drafts')
                ->descriptionIcon('heroicon-m-pencil')
                ->chart($daily(Post::class, 'published_at'))
                ->color('primary')
                ->url(PostResource::getUrl('index')),
            Stat::make('Scheduled social', SocialPost::where('status', SocialPostStatus::SCHEDULED)->count())
                ->description($failedSocial ? "{$failedSocial} failed this week" : 'All deliveries OK')
                ->descriptionIcon($failedSocial ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failedSocial ? 'danger' : 'info')
                ->url(SocialPostResource::getUrl('index')),
            Stat::make('Photos', Photo::count())
                ->description($processing ? "{$processing} processing" : Photo::published()->count().' published')
                ->descriptionIcon('heroicon-m-camera')
                ->chart($daily(Photo::class, 'created_at'))
                ->color('warning')
                ->url(PhotoResource::getUrl('index')),
            Stat::make('Print sales · 30d', (new PrintOrder(['currency' => config('prints.currency')]))->formatMoney($revenue))
                ->description(PrintOrder::open()->count().' open orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($daily(PrintOrder::class, 'paid_at'))
                ->color('success')
                ->url(PrintOrderResource::getUrl('index')),
        ];
    }
}
