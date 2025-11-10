<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AgendaDiariaController;
use App\Http\Controllers\TurnoConfirmacionController;   // (token antiguo /r/{token})
use App\Http\Controllers\TurnoMailActionController;     // (rutas firmadas nuevas)
use App\Http\Middleware\RedirectToPanel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

// 🏠 Página principal
Route::get('/', function () {
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

    return redirect('/');
})->name('dashboard');

// 🔍 Ruta de prueba rápida
Route::get('/ping', fn() => 'pong');

/**
 * 🔓 Rutas públicas desde email
 * a) Enlaces con token propio (LEGADO) → /r/{token}
 * b) Enlaces FIRMADOS de Laravel (RECOMENDADO) → /turnos/mail-action
 *
 * Ambas NO requieren auth.
 */

/** a) LEGADO: token guardado en DB (recordatorio_token) */
Route::get('/r/{token}',            [TurnoConfirmacionController::class, 'show'])->name('recordatorio.form');
Route::post('/r/{token}/confirmar', [TurnoConfirmacionController::class, 'confirmar'])->name('recordatorio.confirmar');
Route::post('/r/{token}/cancelar',  [TurnoConfirmacionController::class, 'cancelar'])->name('recordatorio.cancelar');

/** b) NUEVO: enlaces firmados (no requiere token en DB) */
Route::get('/turnos/mail-action', [TurnoMailActionController::class, 'show'])
    ->name('turnos.mail-action')
    ->middleware('signed'); // opcional: ->middleware(['signed','throttle:30,1'])

Route::post('/turnos/mail-action', [TurnoMailActionController::class, 'store'])
    ->name('turnos.mail-action.store')
    ->middleware('signed'); // opcional: ->middleware(['signed','throttle:30,1'])

// 👤 Perfil del usuario autenticado
Route::middleware('auth')->group(function () {
    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * 📆 Agenda diaria (versión manual / “inteligente” sin cron)
 * - Solo para Administrador y Kinesiologa
 *
 * GET  /agenda-diaria/preview → previsualiza a quiénes se notificará (D+1) [JSON]
 * GET  /agenda-diaria         → vista HTML para previsualizar y disparar el módulo
 * POST /agenda-diaria/enviar  → ejecuta proceso (SIMULADO/REAL)
 */
Route::middleware(['auth', 'role:Administrador|Kinesiologa'])
    ->prefix('agenda-diaria')
    ->group(function () {
        Route::get('/',         [AgendaDiariaController::class, 'previewHtml'])->name('agenda-diaria.index');
        Route::get('/preview',  [AgendaDiariaController::class, 'preview'])->name('agenda-diaria.preview');
        Route::post('/enviar',  [AgendaDiariaController::class, 'run'])->name('agenda-diaria.enviar');
    });

// 🔐 Rutas de autenticación (Laravel Breeze / Jetstream)
require __DIR__ . '/auth.php';
