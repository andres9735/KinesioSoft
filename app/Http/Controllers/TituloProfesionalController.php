<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarTituloProfesionalAction;
use App\Actions\Catalogos\CambiarEstadoTituloProfesionalAction;
use App\Actions\Catalogos\CrearTituloProfesionalAction;
use App\Http\Requests\StoreTituloProfesionalRequest;
use App\Http\Requests\UpdateTituloProfesionalRequest;
use App\Models\TituloProfesional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TituloProfesionalController extends Controller
{
    public function __construct(
        private readonly CrearTituloProfesionalAction $crearTituloProfesionalAction,
        private readonly ActualizarTituloProfesionalAction $actualizarTituloProfesionalAction,
        private readonly CambiarEstadoTituloProfesionalAction $cambiarEstadoTituloProfesionalAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TituloProfesional::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $titulos = TituloProfesional::query()
            ->when($q !== '', fn ($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('catalogos.titulos.index', compact('titulos', 'q', 'estado'));
    }

    public function show(TituloProfesional $titulo): View
    {
        Gate::authorize('view', $titulo);

        return view('catalogos.titulos.show', compact('titulo'));
    }

    public function create(): View
    {
        Gate::authorize('create', TituloProfesional::class);

        return view('catalogos.titulos.create');
    }

    public function store(
        StoreTituloProfesionalRequest $request
    ): RedirectResponse {
        Gate::authorize('create', TituloProfesional::class);

        $data = $request->validated();

        $this->crearTituloProfesionalAction->ejecutar($data);

        return redirect()
            ->route('catalogos.titulos.index')
            ->with(
                'success',
                'Título profesional creado correctamente.'
            );
    }

    public function edit(TituloProfesional $titulo): View
    {
        Gate::authorize('update', $titulo);

        return view('catalogos.titulos.edit', compact('titulo'));
    }

    public function update(
        UpdateTituloProfesionalRequest $request,
        TituloProfesional $titulo
    ): RedirectResponse {
        Gate::authorize('update', $titulo);

        $data = $request->validated();

        $this->actualizarTituloProfesionalAction->ejecutar(
            $titulo,
            $data
        );

        return redirect()
            ->route('catalogos.titulos.index')
            ->with(
                'success',
                'Título profesional actualizado correctamente.'
            );
    }

    public function inactivar(
        TituloProfesional $titulo
    ): RedirectResponse {
        Gate::authorize('inactivar', $titulo);

        $tituloActualizado = $this->cambiarEstadoTituloProfesionalAction->ejecutar(
            $titulo,
            false
        );

        return redirect()
            ->route('catalogos.titulos.index')
            ->with(
                $tituloActualizado->wasChanged('activo') ? 'success' : 'warning',
                $tituloActualizado->wasChanged('activo')
                    ? 'Título profesional inactivado correctamente.'
                    : 'El título profesional ya se encontraba inactivo.'
            );
    }

    public function reactivar(
        TituloProfesional $titulo
    ): RedirectResponse {
        Gate::authorize('reactivar', $titulo);

        $tituloActualizado = $this->cambiarEstadoTituloProfesionalAction->ejecutar(
            $titulo,
            true
        );

        return redirect()
            ->route('catalogos.titulos.index')
            ->with(
                $tituloActualizado->wasChanged('activo') ? 'success' : 'warning',
                $tituloActualizado->wasChanged('activo')
                    ? 'Título profesional reactivado correctamente.'
                    : 'El título profesional ya se encontraba activo.'
            );
    }
}
