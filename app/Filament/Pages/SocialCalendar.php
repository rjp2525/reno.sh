<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Resources\SocialPostResource;
use App\Models\SocialPost;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class SocialCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'social/calendar';

    protected string $view = 'filament.pages.social-calendar';

    #[Url]
    public string $month = '';

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::SOCIAL->getLabel();
    }

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function getTitle(): string
    {
        return $this->current()->format('F Y');
    }

    public function previous(): void
    {
        $this->month = $this->current()->subMonthNoOverflow()->format('Y-m');
    }

    public function next(): void
    {
        $this->month = $this->current()->addMonthNoOverflow()->format('Y-m');
    }

    public function today(): void
    {
        $this->month = now()->format('Y-m');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prev')->label('')->icon('heroicon-m-chevron-left')->color('gray')->action('previous')->tooltip('Previous month'),
            Action::make('today')->label('Today')->color('gray')->action('today'),
            Action::make('next')->label('')->icon('heroicon-m-chevron-right')->color('gray')->action('next')->tooltip('Next month'),
            Action::make('compose')->icon('heroicon-m-plus')->url(SocialPostResource::getUrl('create')),
        ];
    }

    /** @return array{weeks: list<list<array>>, tz: string} */
    public function getViewData(): array
    {
        $tz = config('app.timezone');
        $start = $this->current()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $this->current()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $posts = SocialPost::with('targets.account')
            ->where(fn ($q) => $q->whereBetween('scheduled_at', [$start->copy()->utc(), $end->copy()->endOfDay()->utc()])
                ->orWhereBetween('published_at', [$start->copy()->utc(), $end->copy()->endOfDay()->utc()]))
            ->get()
            ->groupBy(fn (SocialPost $p) => ($p->scheduled_at ?? $p->published_at)->timezone($tz)->toDateString());

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $items = ($posts[$day->toDateString()] ?? collect())->sortBy('scheduled_at')->values();
            $days[] = [
                'date' => $day->copy(),
                'muted' => $day->month !== $this->current()->month,
                'today' => $day->isToday(),
                'past' => $day->lt(now($tz)->startOfDay()),
                'posts' => $items->map(fn (SocialPost $p) => [
                    'url' => SocialPostResource::getUrl('edit', ['record' => $p]),
                    'time' => ($p->scheduled_at ?? $p->published_at)->timezone($tz)->format('g:i A'),
                    'body' => str($p->body ?: '(media only)')->limit(60),
                    'status' => $p->status,
                    'colors' => $p->targets->map(fn ($t) => $t->account?->provider->brandColor())->filter()->unique()->values(),
                ]),
            ];
        }

        return ['weeks' => array_chunk($days, 7), 'createUrl' => SocialPostResource::getUrl('create')];
    }

    private function current(): Carbon
    {
        return rescue(fn () => Carbon::createFromFormat('Y-m', $this->month, config('app.timezone'))->startOfMonth(), now()->startOfMonth(), false);
    }
}
