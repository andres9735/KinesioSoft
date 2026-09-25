<?php

namespace App\Http\Controllers;

use App\Actions\Catalogos\ActualizarCategoriaClinicaAction;
use App\Actions\Catalogos\CambiarEstadoCategoriaClinicaAction;
use App\Actions\Catalogos\CrearCategoriaClinicaAction;
use App\Enums\TipoCategoriaClinicaEnum;
use App\Http\Requests\StoreCategoriaClinicaRequest;
use App\Http\Requests\UpdateCategoriaClinicaRequest;
use App\Models\CategoriaClinica;
use App\Services\Catalogos\JerarquiaCategoriaClinicaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoriaClinicaController extends Controller
{
    public function __construct(
        private readonly CrearCategoriaClinicaAction $crearCategoriaClinicaAction,
        private readonly ActualizarCategoriaClinicaAction $actualizarCategoriaClinicaAction,
        private readonly CambiarEstadoCategoriaClinicaAction $cambiarEstadoCategoriaClinicaAction,
        private readonly JerarquiaCategoriaClinicaService $jerarquiaCategoriaClinicaService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CategoriaClinica::class);

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';
        $estado = $request->query('estado');
        $estado = in_array($estado, ['activos', 'inactivos'], true) ? $estado : 'todos';

        $categorias = CategoriaClinica::query()
            ->with('padre')
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

        return view('catalogos.categorias-clinicas.index', compact('categorias', 'q', 'estado'));
    }

    public function create(): View
    {
        Gate::authorize('create', CategoriaClinica::class);

        $tipos = TipoCategoriaClinicaEnum::cases();
        $categoriasPadre = $this->jerarquiaCategoriaClinicaService->obtenerCandidatosParaCrear();

        return view('catalogos.categorias-clinicas.create', compact('tipos', 'categoriasPadre'));
    }

    public function store(StoreCategoriaClinicaRequest $request): RedirectResponse
    {
        Gate::authorize('create', CategoriaClinica::class);

        $this->crearCategoriaClinicaAction->ejecutar($request->validated());

        return redirect()->route('catalogos.categorias.index')
            ->with('success', 'Categoría clínica creada correctamente.');
    }

    public function show(CategoriaClinica $categoriaClinica): View
    {
        Gate::authorize('view', $categoriaClinica);

        $categoriaClinica->load(['padre', 'hijas' => fn ($query) => $query->orderBy('nombre')->orderBy('id')]);

        return view('catalogos.categorias-clinicas.show', compact('categoriaClinica'));
    }

    public function edit(CategoriaClinica $categoriaClinica): View
    {
        Gate::authorize('update', $categoriaClinica);

        $categoriaClinica->load('padre');
        $tipos = TipoCategoriaClinicaEnum::cases();
        $categoriasPadre = $this->jerarquiaCategoriaClinicaService
            ->obtenerCandidatosParaEditar($categoriaClinica);
        $padreHistoricoInactivo = $categoriaClinica->padre && ! $categoriaClinica->padre->activo
            ? $categoriaClinica->padre
            : null;

        return view('catalogos.categorias-clinicas.edit', compact(
            'categoriaClinica',
            'tipos',
            'categoriasPadre',
            'padreHistoricoInactivo'
        ));
    }

    public function update(UpdateCategoriaClinicaRequest $request, CategoriaClinica $categoriaClinica): RedirectResponse
    {
        Gate::authorize('update', $categoriaClinica);

        $this->actualizarCategoriaClinicaAction->ejecutar($categoriaClinica, $request->validated());

        return redirect()->route('catalogos.categorias.index')
            ->with('success', 'Categoría clínica actualizada correctamente.');
    }

    public function inactivar(CategoriaClinica $categoriaClinica): RedirectResponse
    {
        Gate::authorize('inactivar', $categoriaClinica);

        try {
            $categoriaActual = $this->cambiarEstadoCategoriaClinicaAction->ejecutar($categoriaClinica, false);
        } catch (ValidationException $exception) {
            return redirect()->route('catalogos.categorias.index')
                ->with('error', $exception->validator->errors()->first());
        }

        return redirect()->route('catalogos.categorias.index')->with(
            $categoriaActual->wasChanged('activo') ? 'success' : 'warning',
            $categoriaActual->wasChanged('activo')
                ? 'Categoría clínica inactivada correctamente.'
                : 'La categoría clínica ya se encontraba inactiva.'
        );
    }

    public function reactivar(CategoriaClinica $categoriaClinica): RedirectResponse
    {
        Gate::authorize('reactivar', $categoriaClinica);

        $categoriaActual = $this->cambiarEstadoCategoriaClinicaAction->ejecutar($categoriaClinica, true);

        return redirect()->route('catalogos.categorias.index')->with(
            $categoriaActual->wasChanged('activo') ? 'success' : 'warning',
            $categoriaActual->wasChanged('activo')
                ? 'Categoría clínica reactivada correctamente.'
                : 'La categoría clínica ya se encontraba activa.'
        );
    }
}
