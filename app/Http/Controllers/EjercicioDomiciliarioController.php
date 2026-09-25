<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarEjercicioDomiciliarioAction;
use App\Actions\Catalogos\CambiarEstadoEjercicioDomiciliarioAction;
use App\Actions\Catalogos\CrearEjercicioDomiciliarioAction;
use App\Enums\DificultadEjercicioEnum;
use App\Enums\FrecuenciaPeriodoEjercicioEnum;
use App\Enums\OrigenRecursoEjercicioEnum;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Enums\TipoRecursoEjercicioEnum;
use App\Http\Requests\StoreEjercicioDomiciliarioRequest;
use App\Http\Requests\UpdateEjercicioDomiciliarioRequest;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EjercicioDomiciliarioController extends Controller
{
    public function __construct(
        private readonly CrearEjercicioDomiciliarioAction $crearEjercicioDomiciliarioAction,
        private readonly ActualizarEjercicioDomiciliarioAction $actualizarEjercicioDomiciliarioAction,
        private readonly CambiarEstadoEjercicioDomiciliarioAction $cambiarEstadoEjercicioDomiciliarioAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EjercicioDomiciliario::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true) ? $estado : 'todos';

        $ejercicios = EjercicioDomiciliario::query()
            ->withCount([
                'categorias',
                'recursos',
                'recursos as recursos_activos_count' => fn ($query) => $query->where('activo', true),
            ])
            ->when($q !== '', fn ($query) => $query->where(
                fn ($busqueda) => $busqueda
                    ->where('codigo', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
            ))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('catalogos.ejercicios-domiciliarios.index', compact('ejercicios', 'q', 'estado'));
    }

    public function create(): View
    {
        Gate::authorize('create', EjercicioDomiciliario::class);

        return view('catalogos.ejercicios-domiciliarios.create', [
            'categoriasDisponibles' => $this->categoriasDisponibles(),
            'dificultades' => DificultadEjercicioEnum::cases(),
            'periodosFrecuencia' => FrecuenciaPeriodoEjercicioEnum::cases(),
            'tiposRecurso' => TipoRecursoEjercicioEnum::cases(),
            'origenesRecurso' => OrigenRecursoEjercicioEnum::cases(),
            'recursosIniciales' => [],
        ]);
    }

    public function store(StoreEjercicioDomiciliarioRequest $request): RedirectResponse
    {
        Gate::authorize('create', EjercicioDomiciliario::class);

        $this->crearEjercicioDomiciliarioAction->ejecutar($request->validated());

        return redirect()->route('catalogos.ejercicios.index')
            ->with('success', 'Ejercicio domiciliario creado correctamente.');
    }

    public function show(EjercicioDomiciliario $ejercicioDomiciliario): View
    {
        Gate::authorize('view', $ejercicioDomiciliario);

        $ejercicioDomiciliario->load([
            'categorias' => fn ($query) => $query->orderBy('tipo')->orderBy('nombre')->orderBy('id'),
            'recursos' => fn ($query) => $query->orderBy('orden')->orderBy('id'),
        ]);

        return view('catalogos.ejercicios-domiciliarios.show', compact('ejercicioDomiciliario'));
    }

    public function edit(EjercicioDomiciliario $ejercicioDomiciliario): View
    {
        Gate::authorize('update', $ejercicioDomiciliario);

        $ejercicioDomiciliario->load([
            'categorias',
            'recursos' => fn ($query) => $query->orderBy('orden')->orderBy('id'),
        ]);

        return view('catalogos.ejercicios-domiciliarios.edit', [
            'ejercicioDomiciliario' => $ejercicioDomiciliario,
            'categoriasDisponibles' => $this->categoriasDisponibles($ejercicioDomiciliario),
            'dificultades' => DificultadEjercicioEnum::cases(),
            'periodosFrecuencia' => FrecuenciaPeriodoEjercicioEnum::cases(),
            'tiposRecurso' => TipoRecursoEjercicioEnum::cases(),
            'origenesRecurso' => OrigenRecursoEjercicioEnum::cases(),
            'recursosIniciales' => $ejercicioDomiciliario->recursos->map(
                fn ($recurso): array => [
                    'id' => (int) $recurso->id,
                    'tipo' => $recurso->tipo->value,
                    'origen' => $recurso->origen->value,
                    'origen_original' => $recurso->origen->value,
                    'titulo' => $recurso->titulo,
                    'orden' => (int) $recurso->orden,
                    'activo' => (bool) $recurso->activo,
                    'url_externa' => $recurso->url_externa,
                    'tiene_archivo_actual' => $recurso->origen === OrigenRecursoEjercicioEnum::LOCAL,
                ]
            )->values()->all(),
        ]);
    }

    public function update(
        UpdateEjercicioDomiciliarioRequest $request,
        EjercicioDomiciliario $ejercicioDomiciliario
    ): RedirectResponse {
        Gate::authorize('update', $ejercicioDomiciliario);

        $this->actualizarEjercicioDomiciliarioAction->ejecutar(
            $ejercicioDomiciliario,
            $request->validated()
        );

        return redirect()->route('catalogos.ejercicios.index')
            ->with('success', 'Ejercicio domiciliario actualizado correctamente.');
    }

    public function inactivar(EjercicioDomiciliario $ejercicioDomiciliario): RedirectResponse
    {
        Gate::authorize('inactivar', $ejercicioDomiciliario);

        $ejercicioActual = $this->cambiarEstadoEjercicioDomiciliarioAction->ejecutar(
            $ejercicioDomiciliario,
            false
        );

        return redirect()->route('catalogos.ejercicios.index')->with(
            $ejercicioActual->wasChanged('activo') ? 'success' : 'warning',
            $ejercicioActual->wasChanged('activo')
                ? 'Ejercicio domiciliario inactivado correctamente.'
                : 'El ejercicio domiciliario ya se encontraba inactivo.'
        );
    }

    public function reactivar(EjercicioDomiciliario $ejercicioDomiciliario): RedirectResponse
    {
        Gate::authorize('reactivar', $ejercicioDomiciliario);

        $ejercicioActual = $this->cambiarEstadoEjercicioDomiciliarioAction->ejecutar(
            $ejercicioDomiciliario,
            true
        );

        return redirect()->route('catalogos.ejercicios.index')->with(
            $ejercicioActual->wasChanged('activo') ? 'success' : 'warning',
            $ejercicioActual->wasChanged('activo')
                ? 'Ejercicio domiciliario reactivado correctamente.'
                : 'El ejercicio domiciliario ya se encontraba activo.'
        );
    }

    private function categoriasDisponibles(?EjercicioDomiciliario $ejercicio = null): Collection
    {
        $idsHistoricos = $ejercicio?->categorias->modelKeys() ?? [];
        $tiposPermitidos = [
            TipoCategoriaClinicaEnum::ANATOMICA->value,
            TipoCategoriaClinicaEnum::FUNCIONAL->value,
            TipoCategoriaClinicaEnum::TERAPEUTICA->value,
        ];

        return CategoriaClinica::query()
            ->where(function ($query) use ($idsHistoricos, $tiposPermitidos): void {
                $query->where(function ($disponibles) use ($tiposPermitidos): void {
                    $disponibles->where('activo', true)->whereIn('tipo', $tiposPermitidos);
                });

                if ($idsHistoricos !== []) {
                    $query->orWhereIn('id', $idsHistoricos);
                }
            })
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
    }
}
