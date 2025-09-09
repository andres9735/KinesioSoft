<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;

class AdminLogin extends BaseLogin
{
    /**
     * Después de loguear en /admin,
     * si NO es Administrador, lo mandamos al panel de Paciente.
     * Si es Administrador, va al dashboard de /admin.
     */
    protected function getRedirectUrl(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return parent::getRedirectUrl();
        }

        if (! $user->hasRole('Administrador')) {
            return route('filament.paciente.pages.dashboard');
        }

        return parent::getRedirectUrl();
    }
}
