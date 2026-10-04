<?php

namespace App\Providers\Filament;

use App\Filament\InitialsAvatarProvider;
use App\Http\Middleware\EnsureUserIsActive;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Admin panel: /admin. Kirish — saytning umumiy login sahifasi orqali,
 * faqat role = admin bo‘lgan faol foydalanuvchilar (User::canAccessPanel).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Fikrlash · Admin')
            ->colors(['primary' => Color::hex('#2343b8'), 'gray' => Color::Stone])
            ->font('Onest')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Kontent'),
                NavigationGroup::make('Moderatsiya'),
                NavigationGroup::make('Foydalanuvchilar'),
                NavigationGroup::make('AI'),
                NavigationGroup::make('Tizim'),
            ])
            ->userMenuItems([
                Action::make('site')->label('Saytga qaytish')->url('/')->icon(Heroicon::OutlinedArrowUturnLeft),
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureUserIsActive::class,
            ]);
    }
}
