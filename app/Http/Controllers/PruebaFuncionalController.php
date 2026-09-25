<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarPruebaFuncionalAction;
use App\Actions\Catalogos\CambiarEstadoPruebaFuncionalAction;
use App\Actions\Catalogos\CrearPruebaFuncionalAction;
use App\Enums\SistemaOrigenPruebaFuncionalEnum;
use App\Http\Requests\StorePruebaFuncionalRequest;
use App\Http\Requests\UpdatePruebaFuncionalRequest;
use App\Models\PruebaFuncional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PruebaFuncionalController extends Controller
{
    public function __construct(
        private readonly CrearPruebaFuncionalAction $crearPruebaFuncionalAction,
        private readonly ActualizarPruebaFuncionalAction $actualizarPruebaFuncionalAction,
        private readonly CambiarEstadoPruebaFuncionalAction $cambiarEstadoPruebaFuncionalAction
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PruebaFuncional::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true)
            ? $estado
            : 'todos';

        $pruebas = PruebaFuncional::query()
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

        return view('catalogos.pruebas-funcionales.index', compact('pruebas', 'q', 'estado'));
    }

    public function create(): View
    {
        Gate::authorize('create', PruebaFuncional::class);

        $sistemasOrigen = SistemaOrigenPruebaFuncionalEnum::cases();

        return view('catalogos.pruebas-funcionales.create', compact('sistemasOrigen'));
    }

    public function store(StorePruebaFuncionalRequest $request): RedirectResponse
    {
        Gate::authorize('create', PruebaFuncional::class);

        $this->crearPruebaFuncionalAction->ejecutar($request->validated());

        return redirect()
            ->route('catalogos.pruebas.index')
            ->with('success', 'Prueba funcional creada correctamente.');
    }

    public function show(PruebaFuncional $pruebaFuncional): View
    {
        Gate::authorize('view', $pruebaFuncional);

        return view('catalogos.pruebas-funcionales.show', compact('pruebaFuncional'));
    }

    public function edit(PruebaFuncional $pruebaFuncional): View
    {
        Gate::authorize('update', $pruebaFuncional);

        $sistemasOrigen = SistemaOrigenPruebaFuncionalEnum::cases();

        return view('catalogos.pruebas-funcionales.edit', compact('pruebaFuncional', 'sistemasOrigen'));
    }

    public function update(
        UpdatePruebaFuncionalRequest $request,
        PruebaFuncional $pruebaFuncional
    ): RedirectResponse {
        Gate::authorize('update', $pruebaFuncional);

        $this->actualizarPruebaFuncionalAction->ejecutar($pruebaFuncional, $request->validated());

        return redirect()
            ->route('catalogos.pruebas.index')
            ->with('success', 'Prueba funcional actualizada correctamente.');
    }

    public function inactivar(PruebaFuncional $pruebaFuncional): RedirectResponse
    {
        Gate::authorize('inactivar', $pruebaFuncional);

        $pruebaActualizada = $this->cambiarEstadoPruebaFuncionalAction->ejecutar(
            $pruebaFuncional,
            false
        );

        return redirect()
            ->route('catalogos.pruebas.index')
            ->with(
                $pruebaActualizada->wasChanged('activo') ? 'success' : 'warning',
                $pruebaActualizada->wasChanged('activo')
                    ? 'Prueba funcional inactivada correctamente.'
                    : 'La prueba funcional ya se encontraba inactiva.'
            );
    }

    public function reactivar(PruebaFuncional $pruebaFuncional): RedirectResponse
    {
        Gate::authorize('reactivar', $pruebaFuncional);

        $pruebaActualizada = $this->cambiarEstadoPruebaFuncionalAction->ejecutar(
            $pruebaFuncional,
            true
        );

        return redirect()
            ->route('catalogos.pruebas.index')
            ->with(
                $pruebaActualizada->wasChanged('activo') ? 'success' : 'warning',
                $pruebaActualizada->wasChanged('activo')
                    ? 'Prueba funcional reactivada correctamente.'
                    : 'La prueba funcional ya se encontraba activa.'
            );
    }
}
