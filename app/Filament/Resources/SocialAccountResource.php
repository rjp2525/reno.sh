<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Enums\SocialProvider;
use App\Filament\Resources\SocialAccountResource\Pages;
use App\Models\SocialAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SocialAccountResource extends Resource
{
    protected static ?string $model = SocialAccount::class;

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $modelLabel = 'social account';

    protected static ?string $slug = 'social-accounts';

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-link';
    }

    public static function getNavigationGroup(): ?string
    {
        return NavigationGroup::SOCIAL->getLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $broken = SocialAccount::where('needs_reauth', true)->count();

        return $broken ? (string) $broken : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')->label('')->circular()->imageSize(36),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (SocialAccount $r) => $r->username ? '@'.ltrim($r->username, '@') : null)
                    ->searchable(),
                TextColumn::make('provider')->badge(),
                TextColumn::make('needs_reauth')
                    ->label('Health')
                    ->badge()
                    ->formatStateUsing(fn (SocialAccount $r) => $r->needs_reauth ? 'Reconnect needed' : ($r->tokenExpiresSoon(60 * 24 * 3) ? 'Expiring soon' : 'Connected'))
                    ->color(fn (SocialAccount $r) => $r->needs_reauth ? 'danger' : ($r->tokenExpiresSoon(60 * 24 * 3) ? 'warning' : 'success'))
                    ->tooltip(fn (SocialAccount $r) => $r->last_error),
                ToggleColumn::make('is_active')->label('Active'),
                TextColumn::make('targets_count')->counts('targets')->label('Posts')->sortable(),
                TextColumn::make('updated_at')->since()->label('Updated')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('reconnect')
                    ->icon('heroicon-m-arrow-path')
                    ->color(fn (SocialAccount $r) => $r->needs_reauth ? 'danger' : 'gray')
                    ->visible(fn (SocialAccount $r) => $r->provider->usesOAuth())
                    ->url(fn (SocialAccount $r) => url('/admin/social/connect/'.$r->provider->value)),
                Action::make('editCredentials')
                    ->label('Credentials')
                    ->icon('heroicon-m-key')
                    ->color('gray')
                    ->visible(fn (SocialAccount $r) => ! $r->provider->usesOAuth())
                    ->schema(fn (SocialAccount $r) => Pages\ManageSocialAccounts::credentialFields($r->provider))
                    ->fillForm(fn (SocialAccount $r) => Pages\ManageSocialAccounts::credentialFormState($r))
                    ->action(fn (SocialAccount $r, array $data) => Pages\ManageSocialAccounts::saveManualAccount($r->provider, $data, $r)),
                DeleteAction::make()->label('Disconnect')
                    ->modalDescription('Scheduled posts for this account will be removed from its queue. Already published posts stay online.'),
            ])
            ->emptyStateHeading('No accounts connected')
            ->emptyStateDescription('Connect Instagram, Facebook, LinkedIn, X, Threads, Bluesky or Mastodon with the buttons above.')
            ->emptyStateIcon('heroicon-o-megaphone');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSocialAccounts::route('/'),
        ];
    }

    /** Providers whose app credentials are configured (others show disabled with a hint). */
    public static function isConfigured(SocialProvider $provider): bool
    {
        return match ($provider) {
            SocialProvider::FACEBOOK, SocialProvider::INSTAGRAM => filled(config('social.meta.app_id')),
            SocialProvider::LINKEDIN => filled(config('social.linkedin.client_id')),
            SocialProvider::X => filled(config('social.x.client_id')),
            SocialProvider::THREADS => filled(config('social.threads.app_id')),
            SocialProvider::BLUESKY, SocialProvider::MASTODON => true,
        };
    }
}
