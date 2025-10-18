<?php

use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RedirectToPanel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

// 🏠 Página principal
Route::get('/', function () {
    // Si NO está autenticado → al login unificado
    // Si SÍ lo está → el middleware RedirectToPanel hará la redirección correspondiente
    return redirect('/login');
})->middleware(RedirectToPanel::class);

// 📊 Ruta genérica del dashboard (fallback seguro)
Route::get('/dashboard', function () {
    /** @var User|null $user */
    $user = Auth::user();

    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->hasRole('Administrador')) {
        return redirect()->route('filament.admin.pages.dashboard');
    }

    if ($user->hasRole('Kinesiologa')) {
        return redirect()->route('filament.kinesiologa.pages.dashboard');
    }

    if ($user->hasRole('Paciente')) {
        return redirect()->route('filament.paciente.pages.dashboard');
    }

    // Fallback por si no tiene rol válido
    return redirect('/');
})->name('dashboard');

// 🔍 Ruta de prueba rápida
Route::get('/ping', fn() => 'pong');

// 👤 Perfil del usuario autenticado
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 🔐 Rutas de autenticación (Laravel Breeze / Jetstream)
require __DIR__ . '/auth.php';
