<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarTipoEquipoTerapeuticoAction;
use App\Actions\Catalogos\CambiarEstadoTipoEquipoTerapeuticoAction;
use App\Actions\Catalogos\CrearTipoEquipoTerapeuticoAction;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Http\Requests\StoreTipoEquipoTerapeuticoRequest;
use App\Http\Requests\UpdateTipoEquipoTerapeuticoRequest;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TipoEquipoTerapeuticoController extends Controller
{
    public function __construct(
        private readonly CrearTipoEquipoTerapeuticoAction $crearTipoEquipoTerapeuticoAction,
        private readonly ActualizarTipoEquipoTerapeuticoAction $actualizarTipoEquipoTerapeuticoAction,
        private readonly CambiarEstadoTipoEquipoTerapeuticoAction $cambiarEstadoTipoEquipoTerapeuticoAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TipoEquipoTerapeutico::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true) ? $estado : 'todos';

        $tiposEquipo = TipoEquipoTerapeutico::query()
            ->with('categoriaClinica')
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

        return view('catalogos.tipos-equipo-terapeutico.index', compact('tiposEquipo', 'q', 'estado'));
    }

    public function create(): View
    {
        Gate::authorize('create', TipoEquipoTerapeutico::class);

        $categoriasDisponibles = $this->categoriasDisponibles();

        return view('catalogos.tipos-equipo-terapeutico.create', compact('categoriasDisponibles'));
    }

    public function store(StoreTipoEquipoTerapeuticoRequest $request): RedirectResponse
    {
        Gate::authorize('create', TipoEquipoTerapeutico::class);

        $this->crearTipoEquipoTerapeuticoAction->ejecutar($request->validated());

        return redirect()->route('catalogos.tipos-equipo.index')
            ->with('success', 'Tipo de equipo terapéutico creado correctamente.');
    }

    public function show(TipoEquipoTerapeutico $tipoEquipoTerapeutico): View
    {
        Gate::authorize('view', $tipoEquipoTerapeutico);

        $tipoEquipoTerapeutico->load('categoriaClinica');

        return view('catalogos.tipos-equipo-terapeutico.show', compact('tipoEquipoTerapeutico'));
    }

    public function edit(TipoEquipoTerapeutico $tipoEquipoTerapeutico): View
    {
        Gate::authorize('update', $tipoEquipoTerapeutico);

        $tipoEquipoTerapeutico->load('categoriaClinica');
        $categoriasDisponibles = $this->categoriasDisponibles();
        $categoriaHistoricaActual = $categoriasDisponibles->contains(
            'id',
            $tipoEquipoTerapeutico->categoria_clinica_id
        ) ? null : $tipoEquipoTerapeutico->categoriaClinica;

        return view('catalogos.tipos-equipo-terapeutico.edit', compact(
            'tipoEquipoTerapeutico',
            'categoriasDisponibles',
            'categoriaHistoricaActual'
        ));
    }

    public function update(
        UpdateTipoEquipoTerapeuticoRequest $request,
        TipoEquipoTerapeutico $tipoEquipoTerapeutico
    ): RedirectResponse {
        Gate::authorize('update', $tipoEquipoTerapeutico);

        $this->actualizarTipoEquipoTerapeuticoAction->ejecutar(
            $tipoEquipoTerapeutico,
            $request->validated()
        );

        return redirect()->route('catalogos.tipos-equipo.index')
            ->with('success', 'Tipo de equipo terapéutico actualizado correctamente.');
    }

    public function inactivar(TipoEquipoTerapeutico $tipoEquipoTerapeutico): RedirectResponse
    {
        Gate::authorize('inactivar', $tipoEquipoTerapeutico);

        $tipoActual = $this->cambiarEstadoTipoEquipoTerapeuticoAction->ejecutar(
            $tipoEquipoTerapeutico,
            false
        );

        return redirect()->route('catalogos.tipos-equipo.index')->with(
            $tipoActual->wasChanged('activo') ? 'success' : 'warning',
            $tipoActual->wasChanged('activo')
                ? 'Tipo de equipo terapéutico inactivado correctamente.'
                : 'El tipo de equipo terapéutico ya se encontraba inactivo.'
        );
    }

    public function reactivar(TipoEquipoTerapeutico $tipoEquipoTerapeutico): RedirectResponse
    {
        Gate::authorize('reactivar', $tipoEquipoTerapeutico);

        try {
            $tipoActual = $this->cambiarEstadoTipoEquipoTerapeuticoAction->ejecutar(
                $tipoEquipoTerapeutico,
                true
            );
        } catch (ValidationException $exception) {
            return redirect()->route('catalogos.tipos-equipo.index')
                ->with('error', $exception->validator->errors()->first());
        }

        return redirect()->route('catalogos.tipos-equipo.index')->with(
            $tipoActual->wasChanged('activo') ? 'success' : 'warning',
            $tipoActual->wasChanged('activo')
                ? 'Tipo de equipo terapéutico reactivado correctamente.'
                : 'El tipo de equipo terapéutico ya se encontraba activo.'
        );
    }

    private function categoriasDisponibles()
    {
        return CategoriaClinica::query()
            ->where('activo', true)
            ->where('tipo', TipoCategoriaClinicaEnum::EQUIPAMIENTO->value)
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
    }
}
