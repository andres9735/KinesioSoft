<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarCallesRequest;
use App\Models\Calle;
use App\Models\Localidad;
use App\Models\Provincia;
use Illuminate\Http\JsonResponse;

class GeografiaController extends Controller
{
    public function provincias(): JsonResponse
    {
        $provincias = Provincia::query()
            ->select(['id', 'nombre'])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return response()->json($provincias);
    }

    public function localidades(Provincia $provincia): JsonResponse
    {
        $localidades = Localidad::query()
            ->where('provincia_id', $provincia->id)
            ->select([
                'id',
                'nombre',
                'departamento_nombre',
                'georef_id as codigo_censal',
            ])
            ->orderBy('nombre')
            ->orderBy('departamento_nombre')
            ->orderBy('id')
            ->get();

        return response()->json($localidades);
    }

    public function calles(
        BuscarCallesRequest $request,
        Localidad $localidad
    ): JsonResponse {
        $datos = $request->validated();
        $termino = $this->escaparLike($datos['q']);
        $patronPrefijo = $termino.'%';

        $callesPrefijo = Calle::query()
            ->where('localidad_id', $localidad->id)
            ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patronPrefijo])
            ->select('nombre')
            ->distinct()
            ->orderBy('nombre')
            ->limit(10)
            ->get();

        if ($callesPrefijo->count() === 10) {
            return response()->json($callesPrefijo);
        }

        $callesContiene = Calle::query()
            ->where('localidad_id', $localidad->id)
            ->whereRaw("nombre LIKE ? ESCAPE '!'", ['%'.$termino.'%'])
            ->whereRaw("nombre NOT LIKE ? ESCAPE '!'", [$patronPrefijo])
            ->select('nombre')
            ->distinct()
            ->orderBy('nombre')
            ->limit(10 - $callesPrefijo->count())
            ->get();

        return response()->json(
            $callesPrefijo->concat($callesContiene)->values()
        );
    }

    private function escaparLike(string $termino): string
    {
        return strtr($termino, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]);
    }
}
