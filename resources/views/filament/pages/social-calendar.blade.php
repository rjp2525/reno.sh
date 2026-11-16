<x-filament-panels::page>
    <div class="reno-cal" wire:loading.class="opacity-60">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
            <div class="reno-cal__head">{{ $label }}</div>
        @endforeach

        @foreach ($weeks as $week)
            @foreach ($week as $day)
                <div
                    class="reno-cal__day group"
                    data-muted="{{ $day['muted'] ? 'true' : 'false' }}"
                    data-today="{{ $day['today'] ? 'true' : 'false' }}"
                    data-empty="{{ $day['posts']->isEmpty() ? 'true' : 'false' }}"
                    wire:key="day-{{ $day['date']->toDateString() }}"
                >
                    <div class="flex items-center justify-between">
                        <span class="reno-cal__num">{{ $day['date']->day }}</span>
                        <span class="text-xs text-gray-400 sm:hidden">{{ $day['date']->format('D, M j') }}</span>
                        @unless ($day['past'])
                            <a
                                href="{{ $createUrl }}?date={{ $day['date']->toDateString() }}"
                                class="rounded p-0.5 text-gray-400 opacity-0 transition group-hover:opacity-100 hover:text-primary-500 focus:opacity-100"
                                title="Schedule a post on {{ $day['date']->format('M j') }}"
                            >
                                <x-filament::icon icon="heroicon-m-plus" class="size-4" />
                            </a>
                        @endunless
                    </div>

                    @foreach ($day['posts'] as $i => $post)
                        <a
                            href="{{ $post['url'] }}"
                            wire:navigate
                            class="reno-cal__chip bg-white dark:bg-white/5"
                            style="animation-delay: {{ $i * 40 }}ms"
                            title="{{ $post['body'] }}"
                        >
                            <span class="flex -space-x-1">
                                @foreach ($post['colors'] as $color)
                                    <span class="reno-provider-dot ring-1 ring-white dark:ring-gray-900" style="background: {{ $color }}"></span>
                                @endforeach
                            </span>
                            <span class="shrink-0 font-mono text-[0.7rem] text-gray-500">{{ $post['time'] }}</span>
                            <span class="truncate">{{ $post['body'] }}</span>
                            <x-filament::icon
                                :icon="$post['status']->getIcon()"
                                @class([
                                    'ms-auto size-3.5 shrink-0',
                                    'text-success-500' => $post['status']->getColor() === 'success',
                                    'text-danger-500' => $post['status']->getColor() === 'danger',
                                    'text-warning-500' => $post['status']->getColor() === 'warning',
                                    'text-info-500' => $post['status']->getColor() === 'info',
                                    'text-gray-400' => $post['status']->getColor() === 'gray',
                                ])
                            />
                        </a>
                    @endforeach
                </div>
            @endforeach
        @endforeach
    </div>
</x-filament-panels::page>
