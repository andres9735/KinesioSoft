<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CategoriaClinicaController;
use App\Http\Controllers\ColegioEmisorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagnosticoClinicoController;
use App\Http\Controllers\EjercicioDomiciliarioController;
use App\Http\Controllers\EspecialidadKinesiologiaController;
use App\Http\Controllers\GeografiaController;
use App\Http\Controllers\JurisdiccionController;
use App\Http\Controllers\MisMatriculasProfesionalesController;
use App\Http\Controllers\PerfilKinesiologaController;
use App\Http\Controllers\PerfilPacienteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PruebaFuncionalController;
use App\Http\Controllers\TecnicaTerapeuticaController;
use App\Http\Controllers\TipoEquipoTerapeuticoController;
use App\Http\Controllers\TituloProfesionalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'perfil.completo', 'verified'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Completar perfil de paciente
|--------------------------------------------------------------------------
|
| Solo los usuarios autenticados con rol paciente pueden visualizar
| y enviar el formulario de perfil personal.
|
*/

Route::middleware(['auth', 'perfil.paciente'])->group(function () {
    Route::get(
        '/completar-perfil',
        [PerfilPacienteController::class, 'edit']
    )->name('perfil.completar');

    Route::post(
        '/completar-perfil/validar-paso-1',
        [PerfilPacienteController::class, 'validarPaso1']
    )->name('perfil.validar-paso-1');

    Route::post(
        '/completar-perfil/validar-paso-2',
        [PerfilPacienteController::class, 'validarPaso2']
    )->name('perfil.validar-paso-2');

    Route::post(
        '/completar-perfil',
        [PerfilPacienteController::class, 'update']
    )->name('perfil.actualizar');
});

Route::middleware(['auth', 'perfil.paciente'])
    ->prefix('geografia')
    ->name('geografia.')
    ->group(function () {
        Route::get('/provincias', [GeografiaController::class, 'provincias'])
            ->name('provincias.index');

        Route::get(
            '/provincias/{provincia}/localidades',
            [GeografiaController::class, 'localidades']
        )
            ->whereNumber('provincia')
            ->name('localidades.index');

        Route::get(
            '/localidades/{localidad}/calles',
            [GeografiaController::class, 'calles']
        )
            ->whereNumber('localidad')
            ->name('calles.index');
    });

/*
|--------------------------------------------------------------------------
| Completar perfil profesional
|--------------------------------------------------------------------------
|
| Solo los usuarios autenticados con rol kinesiólogo pueden visualizar
| y enviar el formulario de perfil profesional.
|
*/

Route::middleware(['auth', 'perfil.kinesiologo'])->group(function () {
    Route::get(
        '/completar-perfil-profesional',
        [PerfilKinesiologaController::class, 'edit']
    )->name('perfil.kinesiologa.completar');

    Route::post(
        '/completar-perfil-profesional',
        [PerfilKinesiologaController::class, 'update']
    )->name('perfil.kinesiologa.actualizar');
});

/*
|--------------------------------------------------------------------------
| Perfil general de usuario
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Matrículas profesionales de la kinesióloga
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'perfil.completo',
    'verified',
])->group(function () {
    Route::prefix('perfil-profesional/matriculas')
        ->name('perfil.kinesiologa.matriculas.')
        ->group(function () {
            Route::get(
                '/',
                [MisMatriculasProfesionalesController::class, 'index']
            )->name('index');

            Route::get(
                '/crear',
                [MisMatriculasProfesionalesController::class, 'create']
            )->name('create');

            Route::post(
                '/',
                [MisMatriculasProfesionalesController::class, 'store']
            )->name('store');

            Route::get(
                '/{matricula}/editar',
                [MisMatriculasProfesionalesController::class, 'edit']
            )->name('edit');

            Route::put(
                '/{matricula}',
                [MisMatriculasProfesionalesController::class, 'update']
            )->name('update');

            Route::patch(
                '/{matricula}/toggle-activa',
                [MisMatriculasProfesionalesController::class, 'toggleActiva']
            )->name('toggle-activa');
        });
});

/*
|--------------------------------------------------------------------------
| Administración
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'perfil.completo',
    'admin.or.superadmin',
    'verified',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auditoría
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/auditorias',
        [AuditoriaController::class, 'index']
    )->name('auditorias.index');

    Route::post(
        '/auditorias/exportar-pdf',
        [AuditoriaController::class, 'exportarPdf']
    )->name('auditorias.exportar-pdf');

    /*
    |--------------------------------------------------------------------------
    | Usuarios
    |--------------------------------------------------------------------------
    */

    Route::get('/usuarios', [UserController::class, 'index'])
        ->name('usuarios.index');

    Route::get('/usuarios/crear', [UserController::class, 'create'])
        ->name('usuarios.create');

    Route::post('/usuarios', [UserController::class, 'store'])
        ->name('usuarios.store');

    Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])
        ->name('usuarios.edit');

    Route::put('/usuarios/{user}', [UserController::class, 'update'])
        ->name('usuarios.update');

    Route::patch(
        '/usuarios/{user}/toggle-activo',
        [UserController::class, 'toggleActivo']
    )->name('usuarios.toggle-activo');

    /*
    |--------------------------------------------------------------------------
    | Catálogos
    |--------------------------------------------------------------------------
    */

    Route::prefix('catalogos')->name('catalogos.')->group(function () {
        Route::get('/', [CatalogoController::class, 'index'])
            ->name('index');

        /*
        |--------------------------------------------------------------------------
        | Jurisdicciones
        |--------------------------------------------------------------------------
        */

        Route::prefix('jurisdicciones')
            ->name('jurisdicciones.')
            ->group(function () {
                Route::get(
                    '/',
                    [JurisdiccionController::class, 'index']
                )->name('index');

                Route::get(
                    '/crear',
                    [JurisdiccionController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [JurisdiccionController::class, 'store']
                )->name('store');

                Route::get(
                    '/{jurisdiccion}',
                    [JurisdiccionController::class, 'show']
                )->name('show');

                Route::get(
                    '/{jurisdiccion}/editar',
                    [JurisdiccionController::class, 'edit']
                )->name('edit');

                Route::put(
                    '/{jurisdiccion}',
                    [JurisdiccionController::class, 'update']
                )->name('update');

                Route::patch(
                    '/{jurisdiccion}/inactivar',
                    [JurisdiccionController::class, 'inactivar']
                )->name('inactivar');

                Route::patch(
                    '/{jurisdiccion}/reactivar',
                    [JurisdiccionController::class, 'reactivar']
                )->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Títulos profesionales
        |--------------------------------------------------------------------------
        */

        Route::prefix('titulos-profesionales')
            ->name('titulos.')
            ->group(function () {
                Route::get(
                    '/',
                    [TituloProfesionalController::class, 'index']
                )->name('index');

                Route::get(
                    '/crear',
                    [TituloProfesionalController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [TituloProfesionalController::class, 'store']
                )->name('store');

                Route::get(
                    '/{titulo}',
                    [TituloProfesionalController::class, 'show']
                )->name('show');

                Route::get(
                    '/{titulo}/editar',
                    [TituloProfesionalController::class, 'edit']
                )->name('edit');

                Route::put(
                    '/{titulo}',
                    [TituloProfesionalController::class, 'update']
                )->name('update');

                Route::patch(
                    '/{titulo}/inactivar',
                    [TituloProfesionalController::class, 'inactivar']
                )->name('inactivar');

                Route::patch(
                    '/{titulo}/reactivar',
                    [TituloProfesionalController::class, 'reactivar']
                )->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Colegios emisores
        |--------------------------------------------------------------------------
        */

        Route::prefix('colegios-emisores')
            ->name('colegios.')
            ->group(function () {
                Route::get(
                    '/',
                    [ColegioEmisorController::class, 'index']
                )->name('index');

                Route::get(
                    '/crear',
                    [ColegioEmisorController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [ColegioEmisorController::class, 'store']
                )->name('store');

                Route::get(
                    '/{colegio}',
                    [ColegioEmisorController::class, 'show']
                )->name('show');

                Route::get(
                    '/{colegio}/editar',
                    [ColegioEmisorController::class, 'edit']
                )->name('edit');

                Route::put(
                    '/{colegio}',
                    [ColegioEmisorController::class, 'update']
                )->name('update');

                Route::patch(
                    '/{colegio}/inactivar',
                    [ColegioEmisorController::class, 'inactivar']
                )->name('inactivar');

                Route::patch(
                    '/{colegio}/reactivar',
                    [ColegioEmisorController::class, 'reactivar']
                )->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Especialidades de kinesiología
        |--------------------------------------------------------------------------
        */

        Route::prefix('especialidades-kinesiologia')
            ->name('especialidades.')
            ->group(function () {
                Route::get(
                    '/',
                    [EspecialidadKinesiologiaController::class, 'index']
                )->name('index');

                Route::get(
                    '/crear',
                    [EspecialidadKinesiologiaController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [EspecialidadKinesiologiaController::class, 'store']
                )->name('store');

                Route::get(
                    '/{especialidad}',
                    [EspecialidadKinesiologiaController::class, 'show']
                )->name('show');

                Route::get(
                    '/{especialidad}/editar',
                    [EspecialidadKinesiologiaController::class, 'edit']
                )->name('edit');

                Route::put(
                    '/{especialidad}',
                    [EspecialidadKinesiologiaController::class, 'update']
                )->name('update');

                Route::patch(
                    '/{especialidad}/inactivar',
                    [EspecialidadKinesiologiaController::class, 'inactivar']
                )->name('inactivar');

                Route::patch(
                    '/{especialidad}/reactivar',
                    [EspecialidadKinesiologiaController::class, 'reactivar']
                )->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Diagnósticos clínicos
        |--------------------------------------------------------------------------
        */

        Route::prefix('diagnosticos-clinicos')
            ->name('diagnosticos.')
            ->group(function () {
                Route::get(
                    '/',
                    [DiagnosticoClinicoController::class, 'index']
                )->name('index');

                Route::get(
                    '/create',
                    [DiagnosticoClinicoController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [DiagnosticoClinicoController::class, 'store']
                )->name('store');

                Route::get(
                    '/{diagnosticoClinico}',
                    [DiagnosticoClinicoController::class, 'show']
                )->name('show');

                Route::get(
                    '/{diagnosticoClinico}/edit',
                    [DiagnosticoClinicoController::class, 'edit']
                )->name('edit');

                Route::put(
                    '/{diagnosticoClinico}',
                    [DiagnosticoClinicoController::class, 'update']
                )->name('update');

                Route::patch(
                    '/{diagnosticoClinico}/inactivar',
                    [DiagnosticoClinicoController::class, 'inactivar']
                )->name('inactivar');

                Route::patch(
                    '/{diagnosticoClinico}/reactivar',
                    [DiagnosticoClinicoController::class, 'reactivar']
                )->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Pruebas funcionales
        |--------------------------------------------------------------------------
        */

        Route::prefix('pruebas-funcionales')
            ->name('pruebas.')
            ->group(function () {
                Route::get('/', [PruebaFuncionalController::class, 'index'])->name('index');
                Route::get('/create', [PruebaFuncionalController::class, 'create'])->name('create');
                Route::post('/', [PruebaFuncionalController::class, 'store'])->name('store');
                Route::get('/{pruebaFuncional}', [PruebaFuncionalController::class, 'show'])->name('show');
                Route::get('/{pruebaFuncional}/edit', [PruebaFuncionalController::class, 'edit'])->name('edit');
                Route::put('/{pruebaFuncional}', [PruebaFuncionalController::class, 'update'])->name('update');
                Route::patch('/{pruebaFuncional}/inactivar', [PruebaFuncionalController::class, 'inactivar'])->name('inactivar');
                Route::patch('/{pruebaFuncional}/reactivar', [PruebaFuncionalController::class, 'reactivar'])->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Técnicas terapéuticas
        |--------------------------------------------------------------------------
        */

        Route::prefix('tecnicas-terapeuticas')
            ->name('tecnicas.')
            ->group(function () {
                Route::get('/', [TecnicaTerapeuticaController::class, 'index'])->name('index');
                Route::get('/create', [TecnicaTerapeuticaController::class, 'create'])->name('create');
                Route::post('/', [TecnicaTerapeuticaController::class, 'store'])->name('store');
                Route::get('/{tecnicaTerapeutica}', [TecnicaTerapeuticaController::class, 'show'])->name('show');
                Route::get('/{tecnicaTerapeutica}/edit', [TecnicaTerapeuticaController::class, 'edit'])->name('edit');
                Route::put('/{tecnicaTerapeutica}', [TecnicaTerapeuticaController::class, 'update'])->name('update');
                Route::patch('/{tecnicaTerapeutica}/inactivar', [TecnicaTerapeuticaController::class, 'inactivar'])->name('inactivar');
                Route::patch('/{tecnicaTerapeutica}/reactivar', [TecnicaTerapeuticaController::class, 'reactivar'])->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Categorías clínicas
        |--------------------------------------------------------------------------
        */

        Route::prefix('categorias-clinicas')
            ->name('categorias.')
            ->group(function () {
                Route::get('/', [CategoriaClinicaController::class, 'index'])->name('index');
                Route::get('/create', [CategoriaClinicaController::class, 'create'])->name('create');
                Route::post('/', [CategoriaClinicaController::class, 'store'])->name('store');
                Route::get('/{categoriaClinica}', [CategoriaClinicaController::class, 'show'])->name('show');
                Route::get('/{categoriaClinica}/edit', [CategoriaClinicaController::class, 'edit'])->name('edit');
                Route::put('/{categoriaClinica}', [CategoriaClinicaController::class, 'update'])->name('update');
                Route::patch('/{categoriaClinica}/inactivar', [CategoriaClinicaController::class, 'inactivar'])->name('inactivar');
                Route::patch('/{categoriaClinica}/reactivar', [CategoriaClinicaController::class, 'reactivar'])->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Ejercicios domiciliarios
        |--------------------------------------------------------------------------
        */

        Route::prefix('ejercicios-domiciliarios')
            ->name('ejercicios.')
            ->group(function () {
                Route::get('/', [EjercicioDomiciliarioController::class, 'index'])->name('index');
                Route::get('/create', [EjercicioDomiciliarioController::class, 'create'])->name('create');
                Route::post('/', [EjercicioDomiciliarioController::class, 'store'])->name('store');
                Route::get('/{ejercicioDomiciliario}', [EjercicioDomiciliarioController::class, 'show'])->name('show');
                Route::get('/{ejercicioDomiciliario}/edit', [EjercicioDomiciliarioController::class, 'edit'])->name('edit');
                Route::put('/{ejercicioDomiciliario}', [EjercicioDomiciliarioController::class, 'update'])->name('update');
                Route::patch('/{ejercicioDomiciliario}/inactivar', [EjercicioDomiciliarioController::class, 'inactivar'])->name('inactivar');
                Route::patch('/{ejercicioDomiciliario}/reactivar', [EjercicioDomiciliarioController::class, 'reactivar'])->name('reactivar');
            });

        /*
        |--------------------------------------------------------------------------
        | Tipos de equipo terapéutico
        |--------------------------------------------------------------------------
        */

        Route::prefix('tipos-equipo-terapeutico')
            ->name('tipos-equipo.')
            ->group(function () {
                Route::get('/', [TipoEquipoTerapeuticoController::class, 'index'])->name('index');
                Route::get('/create', [TipoEquipoTerapeuticoController::class, 'create'])->name('create');
                Route::post('/', [TipoEquipoTerapeuticoController::class, 'store'])->name('store');
                Route::get('/{tipoEquipoTerapeutico}', [TipoEquipoTerapeuticoController::class, 'show'])->name('show');
                Route::get('/{tipoEquipoTerapeutico}/edit', [TipoEquipoTerapeuticoController::class, 'edit'])->name('edit');
                Route::put('/{tipoEquipoTerapeutico}', [TipoEquipoTerapeuticoController::class, 'update'])->name('update');
                Route::patch('/{tipoEquipoTerapeutico}/inactivar', [TipoEquipoTerapeuticoController::class, 'inactivar'])->name('inactivar');
                Route::patch('/{tipoEquipoTerapeutico}/reactivar', [TipoEquipoTerapeuticoController::class, 'reactivar'])->name('reactivar');
            });
    });
});

require __DIR__.'/auth.php';
