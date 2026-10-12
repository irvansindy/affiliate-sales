<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Affiliate\Pages\Auth\Register;
use App\Http\Middleware\SetPanelLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AffiliatePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('affiliate')
            ->path('affiliate')
            ->brandName(fn (): string => __('panels.affiliate.brand'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->registration(Register::class)
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.affiliate.registration-pending'))
            ->colors([
                'danger' => '#DC2626',
                'gray' => Color::Slate,
                'info' => '#0284C7',
                'primary' => '#4F46E5',
                'success' => '#16A34A',
                'warning' => '#D97706',
            ])
            ->discoverResources(in: app_path('Filament/Affiliate/Resources'), for: 'App\Filament\Affiliate\Resources')
            ->discoverPages(in: app_path('Filament/Affiliate/Pages'), for: 'App\Filament\Affiliate\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Affiliate/Widgets'), for: 'App\Filament\Affiliate\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                SetPanelLocale::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
