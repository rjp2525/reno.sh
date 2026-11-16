<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Resources\SocialPostResource;
use App\Models\Photo;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Social\SocialComposer;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** "Share" on a post or photo: prefilled composer in a slide-over. */
class ShareToSocialAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'shareToSocial';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Share to social')
            ->icon('heroicon-m-megaphone')
            ->color('info')
            ->slideOver()
            ->modalWidth(Width::ExtraLarge)
            ->modalSubmitActionLabel('Schedule')
            ->visible(fn () => SocialAccount::active()->exists())
            ->fillForm(fn (Model $record) => [
                'body' => $this->shareBody($record),
                'account_ids' => SocialAccount::active()->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'when' => 'now',
            ])
            ->schema([
                CheckboxList::make('account_ids')
                    ->label('Post to')
                    ->options(fn () => SocialAccount::active()->get()->mapWithKeys(fn ($a) => [(string) $a->id => $a->displayName()]))
                    ->columns(2)
                    ->bulkToggleable()
                    ->live()
                    ->required(),

                Textarea::make('body')
                    ->rows(6)
                    ->live(debounce: 300)
                    ->required()
                    ->belowContent(fn (Get $get) => $this->shareCounters((string) $get('body'), (array) $get('account_ids'))),

                ToggleButtons::make('when')
                    ->options(['now' => 'Post now', 'later' => 'Schedule'])
                    ->icons(['now' => 'heroicon-m-bolt', 'later' => 'heroicon-m-clock'])
                    ->inline()
                    ->grouped()
                    ->live(),

                DateTimePicker::make('scheduled_at')
                    ->minDate(now())
                    ->default(now()->addHour()->startOfHour())
                    ->required(fn (Get $get) => $get('when') === 'later')
                    ->visible(fn (Get $get) => $get('when') === 'later'),
            ])
            ->action(function (array $data, Model $record, SocialComposer $composer): void {
                try {
                    $post = $composer->create([
                        'body' => $data['body'],
                        'account_ids' => $data['account_ids'],
                        'link_url' => $this->shareLink($record),
                        'media' => $this->shareMedia($record),
                        'scheduled_at' => $data['scheduled_at'] ?? null,
                        'publish_now' => $data['when'] === 'now',
                    ], auth()->id(), $record);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Could not schedule')->body(collect($e->errors())->flatten()->implode("\n"))->send();
                    $this->halt();
                }

                Notification::make()
                    ->success()
                    ->title($data['when'] === 'now' ? 'Publishing now' : 'Scheduled')
                    ->actions([Action::make('open')->url(SocialPostResource::getUrl('edit', ['record' => $post]))])
                    ->send();
            });
    }

    private function shareBody(Model $record): string
    {
        return match (true) {
            $record instanceof Post => trim($record->title."\n\n".$record->excerpt),
            $record instanceof Photo => trim($record->title.($record->exif_summary ? "\n📷 ".$record->exif_summary : '')),
            default => '',
        };
    }

    private function shareLink(Model $record): ?string
    {
        return match (true) {
            $record instanceof Post => $record->url(),
            $record instanceof Photo => route('gallery.show', $record),
            default => null,
        };
    }

    private function shareMedia(Model $record): array
    {
        return array_values(array_filter(match (true) {
            $record instanceof Post => [$record->og_image ?: $record->featured_image],
            $record instanceof Photo => [$record->web_path],
            default => [],
        }));
    }

    /** Live "123 / 280" per selected network. */
    private function shareCounters(string $body, array $accountIds): HtmlString
    {
        $accounts = SocialAccount::whereIn('id', $accountIds)->get()->unique(fn ($a) => $a->provider->value);

        $chips = $accounts->map(function (SocialAccount $a) use ($body) {
            $max = $a->provider->limits()['max_chars'];
            $link = $this->getRecord() ? $this->shareLink($this->getRecord()) : null;
            $len = $a->provider->length(trim($body.($link ? "\n\n".$link : '')));
            $over = $len > $max ? 'true' : 'false';

            return '<span class="me-3 inline-flex items-center gap-1.5"><span class="reno-provider-dot" style="background:'.e($a->provider->brandColor()).'"></span>'
                .e($a->provider->getLabel()).' <span class="reno-char-count" data-over="'.$over.'">'.$len.'/'.$max.'</span></span>';
        })->implode('');

        return new HtmlString('<div class="flex flex-wrap text-sm text-gray-500 dark:text-gray-400">'.$chips.'</div>');
    }
}
