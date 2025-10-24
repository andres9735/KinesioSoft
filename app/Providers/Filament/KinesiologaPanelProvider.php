<?php

namespace App\Providers\Filament;

use App\Filament\Resources\BloqueDisponibilidadResource;
use App\Filament\Kinesiologa\Pages\MiAgendaSemanal;
use App\Filament\Resources\ExcepcionDisponibilidadResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class KinesiologaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->spa(true)
            ->id('kinesiologa')
            ->path('kinesiologa')
            ->login()
            ->brandName('Kinesiosoft')
            ->favicon(asset('favicon-kinesiologa.ico'))
            ->colors([
                'primary' => Color::Emerald, // diferenciá del paciente/admin
            ])

            // Descubrí SOLO lo propio del panel Kinesiologa (si más adelante agregás páginas/widgets específicos)
            ->discoverPages(in: app_path('Filament/Kinesiologa/Pages'), for: 'App\\Filament\\Kinesiologa\\Pages')
            ->discoverWidgets(in: app_path('Filament/Kinesiologa/Widgets'), for: 'App\\Filament\\Kinesiologa\\Widgets')

            // Registra explícitamente las resources compartidas que querés que se vean aquí:
            ->resources([
                ExcepcionDisponibilidadResource::class,
            ])

            ->pages([
                MiAgendaSemanal::class,
                Pages\Dashboard::class,
            ])
            ->widgets([
                \Filament\Widgets\AccountWidget::class,
                \App\Filament\Kinesiologa\Widgets\BloquesActivosHoy::class,
                \App\Filament\Kinesiologa\Widgets\ProximasExcepciones::class,
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
            ]);
    }
}
