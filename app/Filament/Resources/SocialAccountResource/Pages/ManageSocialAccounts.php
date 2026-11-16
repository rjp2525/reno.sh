<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialAccountResource\Pages;

use App\Enums\SocialProvider;
use App\Filament\Resources\SocialAccountResource;
use App\Models\SocialAccount;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ManageSocialAccounts extends ManageRecords
{
    protected static string $resource = SocialAccountResource::class;

    protected ?string $subheading = 'Connect the profiles and pages you publish to. Tokens are encrypted at rest.';

    public function mount(): void
    {
        parent::mount();

        // Flash from the OAuth callback controller.
        if ($message = session('success')) {
            Notification::make()->success()->title($message)->send();
        }
        if ($message = session('error')) {
            Notification::make()->danger()->title('Could not connect')->body($message)->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $oauth = collect(SocialProvider::cases())
            ->filter->usesOAuth()
            ->map(fn (SocialProvider $p) => Action::make('connect_'.$p->value)
                ->label($p->getLabel())
                ->icon($p->getIcon())
                ->disabled(! SocialAccountResource::isConfigured($p))
                ->tooltip(SocialAccountResource::isConfigured($p) ? null : 'Add the app credentials to .env first (see docs/social.md)')
                ->url(url('/admin/social/connect/'.$p->value)))
            ->values()
            ->all();

        $manual = [SocialProvider::BLUESKY, SocialProvider::MASTODON];

        return [
            ActionGroup::make([
                ...$oauth,
                ...array_map(fn (SocialProvider $p) => Action::make('connect_'.$p->value)
                    ->label($p->getLabel())
                    ->icon($p->getIcon())
                    ->modalHeading('Connect '.$p->getLabel())
                    ->modalDescription($p === SocialProvider::BLUESKY
                        ? 'Use an app password (Settings → Privacy and security → App passwords), never your main password.'
                        : 'Create an application under Preferences → Development with the write:statuses and write:media scopes, then paste its access token.')
                    ->schema(self::credentialFields($p))
                    ->action(fn (array $data) => self::saveManualAccount($p, $data)), $manual),
            ])
                ->label('Connect account')
                ->icon('heroicon-m-plus')
                ->button()
                ->color('primary'),
        ];
    }

    public static function credentialFields(SocialProvider $provider): array
    {
        return match ($provider) {
            SocialProvider::BLUESKY => [
                TextInput::make('handle')->placeholder('you.bsky.social')->required()->prefix('@'),
                TextInput::make('app_password')->password()->revealable()->required()->placeholder('xxxx-xxxx-xxxx-xxxx'),
                TextInput::make('pds_url')->label('PDS URL')->url()->placeholder('https://bsky.social')->helperText('Only if you self-host.'),
            ],
            SocialProvider::MASTODON => [
                TextInput::make('instance_url')->url()->required()->placeholder('https://mastodon.social'),
                TextInput::make('access_token')->password()->revealable()->required(),
            ],
            default => [],
        };
    }

    public static function credentialFormState(SocialAccount $account): array
    {
        return collect($account->credentials ?? [])
            ->only(['handle', 'pds_url', 'instance_url'])
            ->all();
    }

    /** Verify credentials against the network, then upsert the account. */
    public static function saveManualAccount(SocialProvider $provider, array $data, ?SocialAccount $existing = null): void
    {
        try {
            $profile = match ($provider) {
                SocialProvider::BLUESKY => self::verifyBluesky($data),
                SocialProvider::MASTODON => self::verifyMastodon($data),
            };
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Those credentials didn\'t work')->body(Str::limit($e->getMessage(), 200))->send();

            return;
        }

        $credentials = array_filter([...($existing?->credentials ?? []), ...$data], fn ($v) => filled($v));

        SocialAccount::updateOrCreate(
            ['provider' => $provider, 'provider_id' => $profile['id']],
            [
                'name' => $profile['name'],
                'username' => $profile['username'],
                'avatar_url' => $profile['avatar'],
                'credentials' => $credentials,
                'is_active' => true,
                'needs_reauth' => false,
                'last_error' => null,
            ],
        );

        Notification::make()->success()->title($provider->getLabel().' connected')->send();
    }

    private static function verifyBluesky(array $data): array
    {
        $pds = rtrim($data['pds_url'] ?? '', '/') ?: 'https://bsky.social';
        $handle = ltrim($data['handle'], '@');

        $session = Http::timeout(15)->post($pds.'/xrpc/com.atproto.server.createSession', [
            'identifier' => $handle,
            'password' => $data['app_password'],
        ])->throw()->json();

        $profile = Http::timeout(15)->withToken($session['accessJwt'])
            ->get($pds.'/xrpc/app.bsky.actor.getProfile', ['actor' => $session['did']])->json();

        return [
            'id' => $session['did'],
            'name' => $profile['displayName'] ?? $session['handle'],
            'username' => $session['handle'],
            'avatar' => $profile['avatar'] ?? null,
        ];
    }

    private static function verifyMastodon(array $data): array
    {
        $me = Http::timeout(15)->withToken($data['access_token'])
            ->get(rtrim($data['instance_url'], '/').'/api/v1/accounts/verify_credentials')
            ->throw()
            ->json();

        return [
            'id' => (string) $me['id'],
            'name' => $me['display_name'] ?: $me['username'],
            'username' => $me['acct'].'@'.parse_url($data['instance_url'], PHP_URL_HOST),
            'avatar' => $me['avatar'] ?? null,
        ];
    }
}
