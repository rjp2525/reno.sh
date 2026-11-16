<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup;
use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup as Group;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName('reno.sh')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon-32x32.png'))
            ->homeUrl('/')
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('Geist')
            ->monoFont('Geist Mono')
            ->colors([
                'primary' => Color::hex('#43D9AD'),
                'gray' => Color::Slate,
                'info' => Color::hex('#4D5BCE'),
                'success' => Color::Emerald,
                'warning' => Color::hex('#FEA55F'),
                'danger' => Color::Rose,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')

            // Feel: SPA navigation, collapsible sidebar, ⌘K search, guard unsaved work.
            ->spa(hasPrefetching: true)
            ->spaUrlExceptions(fn () => [url('/admin/social/connect/*'), url('/')])
            ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups()
            ->maxContentWidth(Width::Full)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->errorNotifications()

            ->navigationGroups([
                Group::make(NavigationGroup::BLOG->getLabel())->icon('heroicon-o-pencil-square'),
                Group::make(NavigationGroup::SOCIAL->getLabel())->icon('heroicon-o-megaphone'),
                Group::make(NavigationGroup::CONTENT->getLabel())->icon('heroicon-o-photo'),
                Group::make(NavigationGroup::SHOP->getLabel())->icon('heroicon-o-shopping-bag'),
                Group::make(NavigationGroup::ABOUT->getLabel())->icon('heroicon-o-user')->collapsed(),
                Group::make(NavigationGroup::SYSTEM->getLabel())->icon('heroicon-o-cog-6-tooth')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('View site')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->sort(100),
            ])

            ->renderHook(PanelsRenderHook::HEAD_END, fn () => Blade::render('<meta name="theme-color" content="#011627">'))

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([Dashboard::class])
            ->bootUsing(fn () => FilamentDefaults::apply())

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
