<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // 👈 importa tu modelo

Route::get('/', function () {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user) {
        return $user->hasRole('Administrador')
            ? redirect()->route('filament.admin.pages.dashboard')
            : redirect()->route('filament.paciente.pages.dashboard');
    }

    return view('welcome');
});

Route::get('/dashboard', function () {
    /** @var User|null $user */
    $user = Auth::user();

    if (! $user) {
        return redirect()->route('login');
    }

    return $user->hasRole('Administrador')
        ? redirect()->route('filament.admin.pages.dashboard')
        : redirect()->route('filament.paciente.pages.dashboard');
})->name('dashboard');

// ✅ Ruta de prueba
Route::get('/ping', fn() => 'pong');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
