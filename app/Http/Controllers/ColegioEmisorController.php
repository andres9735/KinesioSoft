<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarColegioEmisorAction;
use App\Actions\Catalogos\CambiarEstadoColegioEmisorAction;
use App\Actions\Catalogos\CrearColegioEmisorAction;
use App\Http\Requests\StoreColegioEmisorRequest;
use App\Http\Requests\UpdateColegioEmisorRequest;
use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ColegioEmisorController extends Controller
{
    public function __construct(
        private readonly CrearColegioEmisorAction $crearColegioEmisorAction,
        private readonly ActualizarColegioEmisorAction $actualizarColegioEmisorAction,
        private readonly CambiarEstadoColegioEmisorAction $cambiarEstadoColegioEmisorAction
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ColegioEmisor::class);

        $colegios = ColegioEmisor::query()
            ->with('jurisdiccion')
            ->orderBy('nombre')
            ->paginate(10);

        return view(
            'catalogos.colegios-emisores.index',
            compact('colegios')
        );
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

    public function toggleActivo(
        ColegioEmisor $colegio
    ): RedirectResponse {
        Gate::authorize('toggleActivo', $colegio);

        $colegioActualizado =
            $this->cambiarEstadoColegioEmisorAction->ejecutar(
                $colegio
            );

        $mensaje = $colegioActualizado->activo
            ? 'Colegio emisor activado correctamente.'
            : 'Colegio emisor inactivado correctamente.';

        return redirect()
            ->route('catalogos.colegios.index')
            ->with('success', $mensaje);
    }
}
