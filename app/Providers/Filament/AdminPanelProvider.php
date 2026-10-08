<?php

namespace App\Providers\Filament;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Pages\Security;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Branding;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
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
            ->brandName(fn () => Branding::name().' · Admin')
            // Logo va favicon — "Tizim sozlamalari → Brending" dan.
            ->brandLogo(fn () => Branding::logoUrl())
            ->darkModeBrandLogo(fn () => Branding::logoUrl(dark: true) ?? Branding::logoUrl())
            ->brandLogoHeight('2rem')
            ->favicon(fn () => Branding::faviconUrl() ?? '/favicon.svg')
            // Lojuvard palitrasi: asosiy rang (#2343b8) aynan 600-soyada — tugmalar oq matnli, saytdagidek.
            ->colors(['primary' => array_map(fn (string $hex) => Color::convertToOklch($hex), [
                50 => '#eef1fb', 100 => '#dfe5f8', 200 => '#c3cef2', 300 => '#9cafe8', 400 => '#6f88d9',
                500 => '#4462c9', 600 => '#2343b8', 700 => '#1b3596', 800 => '#172c79', 900 => '#142661', 950 => '#0d173d',
            ]), 'gray' => Color::Stone])
            ->font('Onest')
            ->navigationItems([
                // API hujjatlari faqat adminlar uchun (foydalanuvchilarda API token bo‘limi yo‘q).
                NavigationItem::make('API hujjatlari')
                    ->url('/docs/api', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->group('Tizim')
                    ->sort(90),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Kontent'),
                NavigationGroup::make('Moderatsiya'),
                NavigationGroup::make('Foydalanuvchilar'),
                NavigationGroup::make('Kuzatuv')->icon(Heroicon::OutlinedEye),
                NavigationGroup::make('Monetizatsiya'),
                NavigationGroup::make('AI'),
                NavigationGroup::make('Tizim'),
            ])
            ->userMenuItems([
                Action::make('site')->label('Saytga qaytish')->url('/')->icon(Heroicon::OutlinedArrowUturnLeft),
                Action::make('security')->label('Xavfsizlik markazi')->url(fn () => Security::getUrl())->icon(Heroicon::OutlinedShieldCheck),
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Ctrl+K — foydalanuvchi va postlarni tez topish.
            ->globalSearch()
            ->globalSearchKeyBindings(['mod+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            ->databaseTransactions()
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
