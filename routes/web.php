<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\JurisdiccionController;
use App\Http\Controllers\TituloProfesionalController;
use App\Http\Controllers\ColegioEmisorController;
use App\Http\Controllers\EspecialidadKinesiologiaController;
use App\Http\Controllers\MisMatriculasProfesionalesController;
use App\Http\Controllers\PerfilPacienteController;
use App\Http\Controllers\PerfilKinesiologaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'perfil.completo'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/completar-perfil', [PerfilPacienteController::class, 'edit'])->name('perfil.completar');
    Route::post('/completar-perfil', [PerfilPacienteController::class, 'update'])->name('perfil.actualizar');

    Route::get('/completar-perfil-profesional', [PerfilKinesiologaController::class, 'edit'])->name('perfil.kinesiologa.completar');
    Route::post('/completar-perfil-profesional', [PerfilKinesiologaController::class, 'update'])->name('perfil.kinesiologa.actualizar');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->middleware('perfil.completo')
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('perfil.completo')
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('perfil.completo')
        ->name('profile.destroy');
});

Route::middleware(['auth', 'perfil.completo'])->group(function () {
    Route::prefix('perfil-profesional/matriculas')->name('perfil.kinesiologa.matriculas.')->group(function () {
        Route::get('/', [MisMatriculasProfesionalesController::class, 'index'])->name('index');
        Route::get('/crear', [MisMatriculasProfesionalesController::class, 'create'])->name('create');
        Route::post('/', [MisMatriculasProfesionalesController::class, 'store'])->name('store');
        Route::get('/{matricula}/editar', [MisMatriculasProfesionalesController::class, 'edit'])->name('edit');
        Route::put('/{matricula}', [MisMatriculasProfesionalesController::class, 'update'])->name('update');
        Route::patch('/{matricula}/toggle-activa', [MisMatriculasProfesionalesController::class, 'toggleActiva'])->name('toggle-activa');
    });
});

Route::middleware(['auth', 'perfil.completo', 'admin.or.superadmin'])->group(function () {

    // =========================
    // USUARIOS
    // =========================
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
    Route::patch('/usuarios/{user}/toggle-activo', [UserController::class, 'toggleActivo'])->name('usuarios.toggle-activo');

    // =========================
    // CATÁLOGOS
    // =========================
    Route::prefix('catalogos')->name('catalogos.')->group(function () {

        Route::get('/', [CatalogoController::class, 'index'])->name('index');

        // -------------------------
        // Jurisdicciones
        // -------------------------
        Route::prefix('jurisdicciones')->name('jurisdicciones.')->group(function () {
            Route::get('/', [JurisdiccionController::class, 'index'])->name('index');
            Route::get('/crear', [JurisdiccionController::class, 'create'])->name('create');
            Route::post('/', [JurisdiccionController::class, 'store'])->name('store');
            Route::get('/{jurisdiccion}/editar', [JurisdiccionController::class, 'edit'])->name('edit');
            Route::put('/{jurisdiccion}', [JurisdiccionController::class, 'update'])->name('update');
            Route::patch('/{jurisdiccion}/toggle-activo', [JurisdiccionController::class, 'toggleActivo'])->name('toggle-activo');
        });

        // -------------------------
        // Títulos Profesionales
        // -------------------------
        Route::prefix('titulos-profesionales')->name('titulos.')->group(function () {
            Route::get('/', [TituloProfesionalController::class, 'index'])->name('index');
            Route::get('/crear', [TituloProfesionalController::class, 'create'])->name('create');
            Route::post('/', [TituloProfesionalController::class, 'store'])->name('store');
            Route::get('/{titulo}/editar', [TituloProfesionalController::class, 'edit'])->name('edit');
            Route::put('/{titulo}', [TituloProfesionalController::class, 'update'])->name('update');
            Route::patch('/{titulo}/toggle-activo', [TituloProfesionalController::class, 'toggleActivo'])->name('toggle-activo');
        });

        // -------------------------
        // Colegios emisores
        // -------------------------
        Route::prefix('colegios-emisores')->name('colegios.')->group(function () {
            Route::get('/', [ColegioEmisorController::class, 'index'])->name('index');
            Route::get('/crear', [ColegioEmisorController::class, 'create'])->name('create');
            Route::post('/', [ColegioEmisorController::class, 'store'])->name('store');
            Route::get('/{colegio}/editar', [ColegioEmisorController::class, 'edit'])->name('edit');
            Route::put('/{colegio}', [ColegioEmisorController::class, 'update'])->name('update');
            Route::patch('/{colegio}/toggle-activo', [ColegioEmisorController::class, 'toggleActivo'])->name('toggle-activo');
        });

        // -------------------------
        // Especialidades de kinesiología
        // -------------------------
        Route::prefix('especialidades-kinesiologia')->name('especialidades.')->group(function () {
            Route::get('/', [EspecialidadKinesiologiaController::class, 'index'])->name('index');
            Route::get('/crear', [EspecialidadKinesiologiaController::class, 'create'])->name('create');
            Route::post('/', [EspecialidadKinesiologiaController::class, 'store'])->name('store');
            Route::get('/{especialidad}/editar', [EspecialidadKinesiologiaController::class, 'edit'])->name('edit');
            Route::put('/{especialidad}', [EspecialidadKinesiologiaController::class, 'update'])->name('update');
            Route::patch('/{especialidad}/toggle-activo', [EspecialidadKinesiologiaController::class, 'toggleActivo'])->name('toggle-activo');
        });
    });
});

require __DIR__ . '/auth.php';
