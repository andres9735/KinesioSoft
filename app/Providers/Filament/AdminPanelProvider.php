<?php

namespace App\Providers\Filament;

use App\Filament\Resources\PermissionResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\AuditResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->spa(true)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Admin\Pages\Auth\AdminLogin::class)
            ->brandName('Kinesiosoft • Admin')
            ->favicon(asset('favicon-administrador.ico'))
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class, // si no lo querés, dejalo comentado
            ])

            // 👇 Menú agrupado "Usuarios" con 3 subitems
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                $isAdmin = (bool) (Auth::user() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('Administrador'));

                return $builder->groups([
                    NavigationGroup::make('Usuarios')->items([
                        NavigationItem::make('Usuarios')
                            ->icon('heroicon-o-user')
                            ->url(UserResource::getUrl('index'))
                            ->sort(1)
                            ->visible(fn() => $isAdmin),

                        NavigationItem::make('Roles')
                            ->icon('heroicon-o-lock-closed')
                            ->url(RoleResource::getUrl('index'))
                            ->sort(2)
                            ->visible(fn() => $isAdmin),

                        NavigationItem::make('Permisos')
                            ->icon('heroicon-o-key')
                            ->url(PermissionResource::getUrl('index'))
                            ->sort(3)
                            ->visible(fn() => $isAdmin),

                        // 👇 NUEVO: Auditoría
                        NavigationItem::make('Auditoría')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->url(AuditResource::getUrl('index'))
                            ->sort(4)
                            ->visible(fn() => $isAdmin),
                    ]),
                ]);
            })

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
                \App\Http\Middleware\RedirectNonAdminsFromAdminPanel::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
