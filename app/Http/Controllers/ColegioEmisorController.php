<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarColegioEmisorAction;
use App\Actions\Catalogos\CambiarEstadoColegioEmisorAction;
use App\Actions\Catalogos\CrearColegioEmisorAction;
use App\Http\Requests\StoreColegioEmisorRequest;
use App\Http\Requests\UpdateColegioEmisorRequest;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ColegioEmisorController extends Controller
{
    public function __construct(
        private readonly CrearColegioEmisorAction $crearColegioEmisorAction,
        private readonly ActualizarColegioEmisorAction $actualizarColegioEmisorAction,
        private readonly CambiarEstadoColegioEmisorAction $cambiarEstadoColegioEmisorAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ColegioEmisor::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $colegios = ColegioEmisor::query()
            ->with('jurisdiccion')
            ->when($q !== '', fn ($query) => $query->where(
                fn ($busqueda) => $busqueda
                    ->where('nombre', 'like', "%{$q}%")
                    ->orWhere('sigla', 'like', "%{$q}%")
            ))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view(
            'catalogos.colegios-emisores.index',
            compact('colegios', 'q', 'estado')
        );
    }

    public function show(ColegioEmisor $colegio): View
    {
        Gate::authorize('view', $colegio);

        $colegio->load('jurisdiccion');

        return view('catalogos.colegios-emisores.show', compact('colegio'));
    }

    public function create(): View
    {
        Gate::authorize('create', ColegioEmisor::class);

        $jurisdicciones = Jurisdiccion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'catalogos.colegios-emisores.create',
            compact('jurisdicciones')
        );
    }

    public function store(
        StoreColegioEmisorRequest $request
    ): RedirectResponse {
        Gate::authorize('create', ColegioEmisor::class);

        $data = $request->validated();

        $this->crearColegioEmisorAction->ejecutar($data);

        return redirect()
            ->route('catalogos.colegios.index')
            ->with(
                'success',
                'Colegio emisor creado correctamente.'
            );
    }

    public function edit(ColegioEmisor $colegio): View
    {
        Gate::authorize('update', $colegio);

        $jurisdicciones = Jurisdiccion::query()
            ->where(function ($query) use ($colegio) {
                $query->where('activo', true)
                    ->orWhere('id', $colegio->jurisdiccion_id);
            })
            ->orderBy('nombre')
            ->get();

        return view(
            'catalogos.colegios-emisores.edit',
            compact('colegio', 'jurisdicciones')
        );
    }

    public function update(
        UpdateColegioEmisorRequest $request,
        ColegioEmisor $colegio
    ): RedirectResponse {
        Gate::authorize('update', $colegio);

        $data = $request->validated();

        $this->actualizarColegioEmisorAction->ejecutar(
            $colegio,
            $data
        );

        return redirect()
            ->route('catalogos.colegios.index')
            ->with(
                'success',
                'Colegio emisor actualizado correctamente.'
            );
    }

    public function inactivar(
        ColegioEmisor $colegio
    ): RedirectResponse {
        Gate::authorize('inactivar', $colegio);

        $colegioActualizado =
            $this->cambiarEstadoColegioEmisorAction->ejecutar(
                $colegio,
                false
            );

        return redirect()
            ->route('catalogos.colegios.index')
            ->with(
                $colegioActualizado->wasChanged('activo') ? 'success' : 'warning',
                $colegioActualizado->wasChanged('activo')
                    ? 'Colegio emisor inactivado correctamente.'
                    : 'El colegio emisor ya se encontraba inactivo.'
            );
    }

    public function reactivar(
        ColegioEmisor $colegio
    ): RedirectResponse {
        Gate::authorize('reactivar', $colegio);

        try {
            $colegioActualizado =
                $this->cambiarEstadoColegioEmisorAction->ejecutar(
                    $colegio,
                    true
                );
        } catch (DomainException $exception) {
            return redirect()
                ->route('catalogos.colegios.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('catalogos.colegios.index')
            ->with(
                $colegioActualizado->wasChanged('activo') ? 'success' : 'warning',
                $colegioActualizado->wasChanged('activo')
                    ? 'Colegio emisor reactivado correctamente.'
                    : 'El colegio emisor ya se encontraba activo.'
            );
    }
}
