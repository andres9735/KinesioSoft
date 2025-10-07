<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Facades\Auth;

trait ChecksAdmin
{
    protected static function isAdmin(): bool
    {
        // En CLI no hay sesión; devolver true evita falsos negativos al compilar/sembrar
        if (app()->runningInConsole()) return true;

        return Auth::user()?->hasRole('Administrador') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::isAdmin();
    }

    // Si querés endurecer permisos del Resource:
    public static function canCreate(): bool
    {
        return static::isAdmin();
    }
    public static function canEdit($record): bool
    {
        return static::isAdmin();
    }
    public static function canDelete($record): bool
    {
        return static::isAdmin();
    }
    public static function canDeleteAny(): bool
    {
        return static::isAdmin();
    }
}
