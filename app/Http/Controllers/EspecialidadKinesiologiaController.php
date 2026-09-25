<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarEspecialidadKinesiologiaAction;
use App\Actions\Catalogos\CambiarEstadoEspecialidadKinesiologiaAction;
use App\Actions\Catalogos\CrearEspecialidadKinesiologiaAction;
use App\Http\Requests\StoreEspecialidadKinesiologiaRequest;
use App\Http\Requests\UpdateEspecialidadKinesiologiaRequest;
use App\Models\EspecialidadKinesiologia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EspecialidadKinesiologiaController extends Controller
{
    public function __construct(
        private readonly CrearEspecialidadKinesiologiaAction $crearEspecialidadKinesiologiaAction,
        private readonly ActualizarEspecialidadKinesiologiaAction $actualizarEspecialidadKinesiologiaAction,
        private readonly CambiarEstadoEspecialidadKinesiologiaAction $cambiarEstadoEspecialidadKinesiologiaAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EspecialidadKinesiologia::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $especialidades = EspecialidadKinesiologia::query()
            ->when($q !== '', fn ($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->when($estado === 'activos', fn ($query) => $query->where('activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view(
            'catalogos.especialidades.index',
            compact('especialidades', 'q', 'estado')
        );
    }

    public function show(EspecialidadKinesiologia $especialidad): View
    {
        Gate::authorize('view', $especialidad);

        return view('catalogos.especialidades.show', compact('especialidad'));
    }

    public function create(): View
    {
        Gate::authorize('create', EspecialidadKinesiologia::class);

        return view('catalogos.especialidades.create');
    }

    public function store(
        StoreEspecialidadKinesiologiaRequest $request
    ): RedirectResponse {
        Gate::authorize('create', EspecialidadKinesiologia::class);

        $data = $request->validated();

        $this->crearEspecialidadKinesiologiaAction->ejecutar($data);

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with(
                'success',
                'Especialidad de kinesiología creada correctamente.'
            );
    }

    public function edit(
        EspecialidadKinesiologia $especialidad
    ): View {
        Gate::authorize('update', $especialidad);

        return view(
            'catalogos.especialidades.edit',
            compact('especialidad')
        );
    }

    public function update(
        UpdateEspecialidadKinesiologiaRequest $request,
        EspecialidadKinesiologia $especialidad
    ): RedirectResponse {
        Gate::authorize('update', $especialidad);

        $data = $request->validated();

        $this->actualizarEspecialidadKinesiologiaAction->ejecutar(
            $especialidad,
            $data
        );

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with(
                'success',
                'Especialidad de kinesiología actualizada correctamente.'
            );
    }

    public function inactivar(
        EspecialidadKinesiologia $especialidad
    ): RedirectResponse {
        Gate::authorize('inactivar', $especialidad);

        $especialidadActualizada =
            $this->cambiarEstadoEspecialidadKinesiologiaAction->ejecutar(
                $especialidad,
                false
            );

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with(
                $especialidadActualizada->wasChanged('activo') ? 'success' : 'warning',
                $especialidadActualizada->wasChanged('activo')
                    ? 'Especialidad de kinesiología inactivada correctamente.'
                    : 'La especialidad de kinesiología ya se encontraba inactiva.'
            );
    }

    public function reactivar(
        EspecialidadKinesiologia $especialidad
    ): RedirectResponse {
        Gate::authorize('reactivar', $especialidad);

        $especialidadActualizada =
            $this->cambiarEstadoEspecialidadKinesiologiaAction->ejecutar(
                $especialidad,
                true
            );

        return redirect()
            ->route('catalogos.especialidades.index')
            ->with(
                $especialidadActualizada->wasChanged('activo') ? 'success' : 'warning',
                $especialidadActualizada->wasChanged('activo')
                    ? 'Especialidad de kinesiología reactivada correctamente.'
                    : 'La especialidad de kinesiología ya se encontraba activa.'
            );
    }
}
