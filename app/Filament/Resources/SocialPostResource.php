<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use App\Filament\Resources\SocialPostResource\Pages;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Social\SocialScheduler;
use App\Support\Content\Blocks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static ?string $navigationLabel = 'Posts';

    protected static ?string $modelLabel = 'social post';

    protected static ?string $slug = 'social-posts';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::SOCIAL->getLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = SocialPost::where('status', SocialPostStatus::SCHEDULED)->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 5])
            ->components([
                Group::make()->columnSpan(['lg' => 3])->schema([
                    Section::make()->schema([
                        CheckboxList::make('account_ids')
                            ->label('Publish to')
                            ->options(fn () => SocialAccount::orderBy('provider')->get()
                                ->mapWithKeys(fn (SocialAccount $a) => [(string) $a->id => $a->displayName()]))
                            ->descriptions(fn () => SocialAccount::where('needs_reauth', true)->pluck('id')
                                ->mapWithKeys(fn ($id) => [(string) $id => 'Needs reconnecting'])->all())
                            ->disableOptionWhen(fn (string $value) => SocialAccount::whereKey($value)->where(fn ($q) => $q->where('needs_reauth', true)->orWhere('is_active', false))->exists())
                            ->columns(['default' => 1, 'sm' => 2])
                            ->bulkToggleable()
                            ->searchable(fn () => SocialAccount::count() > 6)
                            ->live()
                            ->required(),

                        Textarea::make('body')
                            ->label('Post')
                            ->placeholder("What's happening? Links, #hashtags and emoji all work.")
                            ->rows(6)
                            ->live(debounce: 250)
                            ->belowContent(fn (Get $get) => self::counters($get)),

                        TextInput::make('link_url')
                            ->label('Link')
                            ->url()
                            ->prefixIcon('heroicon-m-link')
                            ->placeholder('https://reno.sh/blog/…')
                            ->live(onBlur: true)
                            ->helperText('Appended to the text unless it already contains it.'),

                        FileUpload::make('media')
                            ->label('Images')
                            ->image()
                            ->multiple()
                            ->maxFiles(10)
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['1:1', '4:5', '1.91:1', '16:9', null])
                            ->directory('social')
                            ->panelLayout('grid')
                            ->live()
                            ->helperText('Instagram needs at least one image. X and Bluesky take up to 4.'),

                    ]),

                    Section::make('Customize per account')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->description('Optional. Leave blank to use the main text.')
                        ->collapsible()
                        ->collapsed()
                        ->visible(fn (Get $get) => count((array) $get('account_ids')) > 1)
                        ->schema(fn (Get $get) => self::selectedAccounts($get)->map(fn (SocialAccount $a) => Textarea::make('overrides.'.$a->id)
                            ->label($a->displayName())
                            ->placeholder('Same as main text')
                            ->rows(2)
                            ->live(debounce: 400))->all()),
                ]),

                Group::make()->columnSpan(['lg' => 2])->extraAttributes(['class' => 'lg:sticky lg:top-20'])->schema([
                    Section::make('When')->icon('heroicon-o-clock')->schema([
                        ToggleButtons::make('when')
                            ->hiddenLabel()
                            ->options(['now' => 'Now', 'later' => 'Schedule', 'draft' => 'Draft'])
                            ->icons(['now' => 'heroicon-m-bolt', 'later' => 'heroicon-m-calendar', 'draft' => 'heroicon-m-pencil'])
                            ->default('later')
                            ->inline()
                            ->grouped()
                            ->live()
                            ->dehydrated(),
                        DateTimePicker::make('scheduled_at')
                            ->hiddenLabel()
                            ->minDate(fn (string $operation) => $operation === 'create' ? now()->startOfMinute() : null)
                            ->default(now()->addHour()->startOfHour())
                            ->required(fn (Get $get) => $get('when') === 'later')
                            ->visible(fn (Get $get) => $get('when') !== 'now')
                            ->helperText(fn () => 'Times are '.config('app.timezone').'.'),
                    ]),

                    Section::make('Preview')->icon('heroicon-o-eye')->schema([
                        Text::make(fn (Get $get) => self::preview($get)),
                    ]),

                    Section::make('Delivery')
                        ->icon('heroicon-o-signal')
                        ->visible(fn (?SocialPost $record) => $record?->targets()->exists())
                        ->schema([
                            Text::make(fn (?SocialPost $record) => self::delivery($record)),
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body')
                    ->label('Post')
                    ->limit(80)
                    ->wrap()
                    ->searchable()
                    ->description(fn (SocialPost $r) => $r->media ? count($r->media).' image(s)' : null),
                TextColumn::make('networks')
                    ->label('Networks')
                    ->badge()
                    ->state(fn (SocialPost $r) => $r->targets->map(fn ($t) => $t->account?->provider)->filter()->unique()->values()->all()),
                TextColumn::make('status')->badge(),
                TextColumn::make('scheduled_at')
                    ->label('When')
                    ->dateTime('M j · g:i A')
                    ->description(fn (SocialPost $r) => $r->scheduled_at?->diffForHumans())
                    ->sortable(),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordUrl(fn (SocialPost $r) => static::getUrl('edit', ['record' => $r]))
            ->poll(fn () => SocialPost::where('status', SocialPostStatus::PUBLISHING)->exists() ? '5s' : null)
            ->filters([
                SelectFilter::make('status')->options(SocialPostStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('retry')
                        ->label(fn (SocialPost $r) => $r->status === SocialPostStatus::SCHEDULED ? 'Publish now' : 'Retry failed')
                        ->icon('heroicon-m-arrow-path')
                        ->color('warning')
                        ->visible(fn (SocialPost $r) => in_array($r->status, [SocialPostStatus::FAILED, SocialPostStatus::PARTIALLY_PUBLISHED, SocialPostStatus::SCHEDULED], true))
                        ->requiresConfirmation()
                        ->action(fn (SocialPost $r, SocialScheduler $s) => $s->dispatch($r, onlyFailed: $r->status !== SocialPostStatus::SCHEDULED))
                        ->successNotificationTitle('Queued'),
                    ReplicateAction::make()
                        ->label('Duplicate')
                        ->excludeAttributes(['status', 'published_at', 'scheduled_at'])
                        ->after(function (SocialPost $replica, SocialPost $record) {
                            $replica->update(['status' => SocialPostStatus::DRAFT]);
                            foreach ($record->targets as $t) {
                                $replica->targets()->create(['social_account_id' => $t->social_account_id, 'body_override' => $t->body_override, 'status' => SocialTargetStatus::PENDING]);
                            }
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Nothing scheduled')
            ->emptyStateDescription('Compose a post, or use “Share to social” on any blog post or photo.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('targets.account');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSocialPosts::route('/'),
            'create' => Pages\CreateSocialPost::route('/create'),
            'edit' => Pages\EditSocialPost::route('/{record}/edit'),
        ];
    }

    /** Selected accounts, deduped by network. */
    private static function selectedAccounts(Get $get)
    {
        return SocialAccount::whereIn('id', (array) $get('account_ids'))->get();
    }

    private static function text(Get $get, ?string $accountId = null): string
    {
        $override = $accountId ? data_get($get('overrides'), $accountId) : null;
        $body = trim((string) ($override ?: $get('body')));
        $link = (string) $get('link_url');

        return $link && ! str_contains($body, $link) ? trim($body."\n\n".$link) : $body;
    }

    private static function counters(Get $get): HtmlString
    {
        $media = count(array_filter((array) $get('media')));

        $chips = self::selectedAccounts($get)->unique(fn ($a) => $a->provider->value)->map(function (SocialAccount $a) use ($get, $media) {
            $limits = $a->provider->limits();
            $len = $a->provider->length(self::text($get, (string) $a->id));
            $over = $len > $limits['max_chars'] || $media > $limits['max_media'] || ($limits['requires_media'] && $media === 0);

            return '<span class="me-3 inline-flex items-center gap-1.5"><span class="reno-provider-dot" style="background:'.e($a->provider->brandColor()).'"></span>'
                .e($a->provider->getLabel()).' <span class="reno-char-count" data-over="'.($over ? 'true' : 'false').'">'.$len.'/'.$limits['max_chars'].'</span></span>';
        })->implode('');

        return new HtmlString('<div class="flex flex-wrap text-sm text-gray-500 dark:text-gray-400">'.($chips ?: 'Pick at least one account.').'</div>');
    }

    private static function preview(Get $get): HtmlString
    {
        $text = self::text($get);
        if ($text === '' && ! $get('media')) {
            return new HtmlString('<p class="text-sm text-gray-400">Your post preview shows up here.</p>');
        }

        $html = e($text);
        $html = preg_replace('~(https?://\S+)~', '<span class="text-primary-500">$1</span>', $html);
        $html = preg_replace('~(^|\s)(#[\pL\pN_]+)~u', '$1<span class="text-primary-500">$2</span>', (string) $html);

        $images = collect((array) $get('media'))->filter()->take(4)->map(function ($file) {
            $url = is_string($file) ? Blocks::url($file) : (method_exists($file, 'temporaryUrl') ? rescue(fn () => $file->temporaryUrl(), null, false) : null);

            return $url ? '<img src="'.e($url).'" class="aspect-square w-full rounded-md object-cover" alt="">' : '';
        })->implode('');

        return new HtmlString(
            '<div class="rounded-xl p-3 ring-1 ring-gray-950/5 dark:ring-white/10">'
            .'<div class="mb-2 flex items-center gap-2"><span class="size-8 rounded-full bg-primary-500/30"></span><span class="text-sm font-semibold">'.e(config('site.name')).'</span></div>'
            .'<p class="text-sm whitespace-pre-wrap">'.$html.'</p>'
            .($images ? '<div class="mt-2 grid grid-cols-2 gap-1">'.$images.'</div>' : '')
            .'</div>'
        );
    }

    private static function delivery(?SocialPost $record): HtmlString
    {
        $rows = $record?->targets()->with('account')->get()->map(function ($t) {
            $class = match ($t->status) {
                SocialTargetStatus::PUBLISHED => 'text-success-600 dark:text-success-400',
                SocialTargetStatus::FAILED => 'text-danger-600 dark:text-danger-400',
                SocialTargetStatus::PUBLISHING => 'text-warning-600 dark:text-warning-400',
                default => 'text-gray-500',
            };
            $status = '<span class="font-medium '.$class.'">'.e($t->status->getLabel()).'</span>';
            $link = $t->provider_url ? ' <a class="text-primary-500 underline" target="_blank" rel="noopener" href="'.e($t->provider_url).'">view</a>' : '';
            $err = $t->error ? '<div class="text-xs text-danger-500">'.e(Str::limit($t->error, 200)).'</div>' : '';

            return '<li class="py-1.5"><div class="flex justify-between gap-2 text-sm"><span>'.e($t->account?->displayName()).'</span><span>'.$status.$link.'</span></div>'.$err.'</li>';
        })->implode('') ?? '';

        return new HtmlString('<ul class="divide-y divide-gray-950/5 dark:divide-white/10">'.$rows.'</ul>');
    }
}
