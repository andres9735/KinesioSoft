<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AgendaDiariaController;
use App\Http\Controllers\TurnoMailActionController;     // (rutas firmadas nuevas)
use App\Http\Controllers\Turnos\OfertaAdelantoTurnoController;
use App\Http\Controllers\Kinesiologa\AgendaEventsController;
use App\Http\Middleware\RedirectToPanel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;

// 🏠 Página principal
Route::get('/', fn() => redirect()->route('login'))
    ->middleware(RedirectToPanel::class);

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
 * b) Enlaces FIRMADOS de Laravel (RECOMENDADO) → /turnos/mail-action/{turno}
 * c) Enlace de oferta de adelanto de turno → /oferta-adelanto/{token}
 *
 * Todas NO requieren auth.
 */

/** a) LEGADO: token guardado en DB (recordatorio_token) — dejar comentado si ya migraste */
# Route::get('/r/{token}',            [TurnoConfirmacionController::class, 'show'])->name('recordatorio.form');
# Route::post('/r/{token}/confirmar', [TurnoConfirmacionController::class, 'confirmar'])->name('recordatorio.confirmar');
# Route::post('/r/{token}/cancelar',  [TurnoConfirmacionController::class, 'cancelar'])->name('recordatorio.cancelar');

/** c) NUEVO: enlace de oferta de adelanto */
Route::get('/oferta-adelanto/{token}', OfertaAdelantoTurnoController::class)
    ->name('oferta-adelanto.handle');

/** b) NUEVO: enlaces firmados para confirmación/cancelación de turno */
Route::prefix('turnos/mail-action')->name('turnos.mail.')->group(function () {
    // Página pública con el resumen y el formulario
    Route::get('{turno}', [TurnoMailActionController::class, 'show'])
        ->middleware(['signed', 'throttle:20,1'])
        ->name('show');

    // Procesa Confirmar/Cancelar desde el formulario público
    Route::post('{turno}', [TurnoMailActionController::class, 'store'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('store');
});

// 👤 Perfil del usuario autenticado
Route::middleware('auth')->group(function () {
    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * 📆 Agenda diaria (versión manual / “inteligente” sin cron)
 * - Solo para Administrador y Kinesiologa
 */
Route::middleware(['auth', 'role:Administrador|Kinesiologa'])
    ->prefix('agenda-diaria')
    ->group(function () {
        Route::get('/',         [AgendaDiariaController::class, 'previewHtml'])->name('agenda-diaria.index');
        Route::get('/preview',  [AgendaDiariaController::class, 'preview'])->name('agenda-diaria.preview');
        Route::post('/enviar',  [AgendaDiariaController::class, 'run'])->name('agenda-diaria.enviar');
    });

    
/**
 * 🎯 Eventos JSON para la agenda (FullCalendar de la kinesióloga)
 */
Route::middleware(['auth', 'role:Kinesiologa'])
    ->get('/kinesiologa/agenda/events', [AgendaEventsController::class, 'index'])
    ->name('kinesiologa.agenda.events');


/* 📄 Stub temporal para "Historia clínica" (solo Kinesiologa/Admin autenticados) */
Route::middleware(['auth', 'role:Kinesiologa|Administrador'])
    ->get('/kinesiologa/historia/{paciente}', function (Request $request, int $paciente) {
        // Más adelante, reemplazá este abort por tu página real
        abort(501, 'Historia clínica: pendiente de implementación.');
    })
    ->whereNumber('paciente')
    ->name('hc.paciente');

// 🔐 Rutas de autenticación
require __DIR__ . '/auth.php';
