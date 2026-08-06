<?php

namespace App\Http\Controllers;

use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Http\Requests\StoreColegioEmisorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ColegioEmisorController extends Controller
{
    public function index(): View
    {
        $colegios = ColegioEmisor::query()
            ->with('jurisdiccion')
            ->orderBy('nombre')
            ->paginate(10);

        return view('catalogos.colegios-emisores.index', compact('colegios'));
    }

    public function create(): View
    {
        $jurisdicciones = Jurisdiccion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('catalogos.colegios-emisores.create', compact('jurisdicciones'));
    }

    public function store(
        StoreColegioEmisorRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        $nombre = preg_replace(
            '/\s+/',
            ' ',
            trim($data['nombre'])
        );

        $sigla = mb_strtoupper(
            preg_replace(
                '/\s+/',
                ' ',
                trim($data['sigla'])
            )
        );

        ColegioEmisor::create([
            'nombre' => $nombre,
            'sigla' => $sigla,
            'jurisdiccion_id' => $data['jurisdiccion_id'],
            'activo' => true,
        ]);

        return redirect()
            ->route('catalogos.colegios.index')
            ->with(
                'success',
                'Colegio emisor creado correctamente.'
            );
    }

    public function edit(ColegioEmisor $colegio): View
    {
        $jurisdicciones = Jurisdiccion::query()
            ->where(function ($query) use ($colegio) {
                $query->where('activo', true)
                    ->orWhere('id', $colegio->jurisdiccion_id);
            })
            ->orderBy('nombre')
            ->get();

        return view('catalogos.colegios-emisores.edit', compact('colegio', 'jurisdicciones'));
    }

    public function update(Request $request, ColegioEmisor $colegio): RedirectResponse
    {
        $data = $this->validateData($request, $colegio);

        $colegio->update([
            'nombre' => $data['nombre'],
            'sigla' => $data['sigla'],
            'jurisdiccion_id' => $data['jurisdiccion_id'],
        ]);

        return redirect()
            ->route('catalogos.colegios.index')
            ->with('success', 'Colegio emisor actualizado correctamente.');
    }

    public function toggleActivo(ColegioEmisor $colegio): RedirectResponse
    {
        $colegio->update([
            'activo' => ! $colegio->activo,
        ]);

        $mensaje = $colegio->activo
            ? 'Colegio emisor activado correctamente.'
            : 'Colegio emisor inactivado correctamente.';

        return redirect()
            ->route('catalogos.colegios.index')
            ->with('success', $mensaje);
    }

    private function validateData(Request $request, ?ColegioEmisor $colegio = null): array
    {
        $colegioId = $colegio?->id;

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\pN\s\.\,\-\(\)]+$/u',
                Rule::unique('colegios_emisores', 'nombre')->ignore($colegioId),
            ],
            'sigla' => [
                'required',
                'string',
                'min:2',
                'max:20',
                'regex:/^[\pL\pN\s\-\.]+$/u',
                Rule::unique('colegios_emisores', 'sigla')->ignore($colegioId),
            ],
            'jurisdiccion_id' => [
            'required',
            'integer',

            Rule::exists('jurisdicciones', 'id')
                ->where(function ($query) use ($colegio) {
                    $query->where(function ($subquery) use ($colegio) {
                        $subquery->where('activo', true);

                        if ($colegio) {
                            $subquery->orWhere(
                                'id',
                                $colegio->jurisdiccion_id
                            );
                        }
                    });
                }),
        ],
        ], [
            'nombre.required' => 'Debe ingresar el nombre del colegio emisor.',
            'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
            'nombre.regex' => 'El nombre contiene caracteres no permitidos.',
            'nombre.unique' => 'Ya existe un colegio emisor con ese nombre.',

            'sigla.required' => 'Debe ingresar la sigla del colegio emisor.',
            'sigla.min' => 'La sigla debe tener al menos 2 caracteres.',
            'sigla.max' => 'La sigla no puede superar los 20 caracteres.',
            'sigla.regex' => 'La sigla solo puede contener letras, números, espacios, puntos y guiones.',
            'sigla.unique' => 'Ya existe un colegio emisor con esa sigla.',

            'jurisdiccion_id.required' => 'Debe seleccionar una jurisdicción.',
            'jurisdiccion_id.integer' => 'La jurisdicción seleccionada no es válida.',
            'jurisdiccion_id.exists' => 'La jurisdicción seleccionada no existe, se encuentra inactiva o no puede utilizarse para esta operación.',
        ]);

        $data['nombre'] = preg_replace('/\s+/', ' ', trim($data['nombre']));
        $data['sigla'] = mb_strtoupper(preg_replace('/\s+/', ' ', trim($data['sigla'])));

        return $data;
    }
}
