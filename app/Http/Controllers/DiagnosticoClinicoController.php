<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarDiagnosticoClinicoAction;
use App\Actions\Catalogos\CambiarEstadoDiagnosticoClinicoAction;
use App\Actions\Catalogos\CrearDiagnosticoClinicoAction;
use App\Enums\SistemaOrigenDiagnosticoClinicoEnum;
use App\Http\Requests\StoreDiagnosticoClinicoRequest;
use App\Http\Requests\UpdateDiagnosticoClinicoRequest;
use App\Models\DiagnosticoClinico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DiagnosticoClinicoController extends Controller
{
    public function __construct(
        private readonly CrearDiagnosticoClinicoAction $crearDiagnosticoClinicoAction,
        private readonly ActualizarDiagnosticoClinicoAction $actualizarDiagnosticoClinicoAction,
        private readonly CambiarEstadoDiagnosticoClinicoAction $cambiarEstadoDiagnosticoClinicoAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DiagnosticoClinico::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $diagnosticos = DiagnosticoClinico::query()
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

        return view('catalogos.diagnosticos-clinicos.index', compact('diagnosticos', 'q', 'estado'));
    }

    public function create(): View
    {
        Gate::authorize('create', DiagnosticoClinico::class);

        $sistemasOrigen = SistemaOrigenDiagnosticoClinicoEnum::cases();

        return view('catalogos.diagnosticos-clinicos.create', compact('sistemasOrigen'));
    }

    public function store(StoreDiagnosticoClinicoRequest $request): RedirectResponse
    {
        Gate::authorize('create', DiagnosticoClinico::class);

        $this->crearDiagnosticoClinicoAction->ejecutar($request->validated());

        return redirect()
            ->route('catalogos.diagnosticos.index')
            ->with('success', 'Diagnóstico clínico creado correctamente.');
    }

    public function show(DiagnosticoClinico $diagnosticoClinico): View
    {
        Gate::authorize('view', $diagnosticoClinico);

        return view('catalogos.diagnosticos-clinicos.show', compact('diagnosticoClinico'));
    }

    public function edit(DiagnosticoClinico $diagnosticoClinico): View
    {
        Gate::authorize('update', $diagnosticoClinico);

        $sistemasOrigen = SistemaOrigenDiagnosticoClinicoEnum::cases();

        return view('catalogos.diagnosticos-clinicos.edit', compact('diagnosticoClinico', 'sistemasOrigen'));
    }

    public function update(
        UpdateDiagnosticoClinicoRequest $request,
        DiagnosticoClinico $diagnosticoClinico
    ): RedirectResponse {
        Gate::authorize('update', $diagnosticoClinico);

        $this->actualizarDiagnosticoClinicoAction->ejecutar($diagnosticoClinico, $request->validated());

        return redirect()
            ->route('catalogos.diagnosticos.index')
            ->with('success', 'Diagnóstico clínico actualizado correctamente.');
    }

    public function inactivar(DiagnosticoClinico $diagnosticoClinico): RedirectResponse
    {
        Gate::authorize('inactivar', $diagnosticoClinico);

        $diagnosticoActualizado = $this->cambiarEstadoDiagnosticoClinicoAction->ejecutar(
            $diagnosticoClinico,
            false
        );

        return redirect()
            ->route('catalogos.diagnosticos.index')
            ->with(
                $diagnosticoActualizado->wasChanged('activo') ? 'success' : 'warning',
                $diagnosticoActualizado->wasChanged('activo')
                    ? 'Diagnóstico clínico inactivado correctamente.'
                    : 'El diagnóstico clínico ya se encontraba inactivo.'
            );
    }

    public function reactivar(DiagnosticoClinico $diagnosticoClinico): RedirectResponse
    {
        Gate::authorize('reactivar', $diagnosticoClinico);

        $diagnosticoActualizado = $this->cambiarEstadoDiagnosticoClinicoAction->ejecutar(
            $diagnosticoClinico,
            true
        );

        return redirect()
            ->route('catalogos.diagnosticos.index')
            ->with(
                $diagnosticoActualizado->wasChanged('activo') ? 'success' : 'warning',
                $diagnosticoActualizado->wasChanged('activo')
                    ? 'Diagnóstico clínico reactivado correctamente.'
                    : 'El diagnóstico clínico ya se encontraba activo.'
            );
    }
}
