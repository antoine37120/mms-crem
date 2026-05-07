<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Enums\Width;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Navigation\NavigationItem;

use Livewire\Livewire;

class MmsAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('mms-admin')
            ->path('mms-admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                /*AccountWidget::class,
                FilamentInfoWidget::class,*/
            ])
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
            ])
            ->navigationGroups([
                NavigationGroup::make('Gestion des Archives')
                    //->icon('heroicon-o-archive-box')
                    ->collapsible(),
                NavigationGroup::make('Médias associés')
                    //->icon('heroicon-o-photo')
                    ->collapsible(),
                NavigationGroup::make('Recherche & Exploration')
                    //->icon('heroicon-o-folder-tree')
                    ->collapsible(),
                NavigationGroup::make('Aide')
                    //->icon('heroicon-o-photo')
                    ->collapsible(),
                NavigationGroup::make('Administration')
                    //->icon('heroicon-o-cog-6-tooth')
                    ->collapsible(),
            ])
            ->navigationItems([
                NavigationItem::make('tasksDashboard')
                    ->group('Administration')
                    ->openUrlInNewTab()
                    ->label(fn (): string => 'Traitements médias')
                    ->icon('heroicon-o-command-line')
                    ->url(fn (): string => route('vantage.dashboard'))
                    ->visible(fn(): bool => auth()->user()->isAdmin()),
            ])
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('Edit profile'),
                // ...
            ])

            ->brandName('MMS CREM')
            ->spa()
            ->viteTheme('resources/css/filament/mms-admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->topbar(false)
            ->brandLogo(asset('images/icone_archives-logo.svg'))
            ->renderHook(
                'panels::body.end',
                fn (): string => view('filament.hooks.upload-manager-button')->render()

            )
            ->assets([
                Css::make('plyr-stylesheet', resource_path('css/plyr.css')),
                Js::make('hls-script', resource_path('js/hls.min.js')),
                Js::make('plyr-script', resource_path('js/plyr.js')),
                Js::make('spark-md5', resource_path('js/spark-md5.min.js')),
            ]);

    }
}
