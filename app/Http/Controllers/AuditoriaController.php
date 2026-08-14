<?php

namespace App\Http\Controllers;

use App\Actions\Auditorias\ListarAuditoriasAction;
use App\Http\Requests\FiltrarAuditoriasRequest;
use App\Models\Auditoria;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function __construct(
        private readonly ListarAuditoriasAction $listarAuditoriasAction
    ) {}

    public function index(
        FiltrarAuditoriasRequest $request
    ): View {
        Gate::authorize(
            'viewAny',
            Auditoria::class
        );

        $filtros = $request->safe()->only([
            'fecha_desde',
            'fecha_hasta',
            'evento',
            'modulo',
            'actor',
        ]);

        $auditorias = $this->listarAuditoriasAction->ejecutar(
            filtros: $filtros,
            pagina: $request->integer('page', 1)
        );

        $eventos = Auditoria::query()
            ->whereNotNull('evento')
            ->where('evento', '<>', '')
            ->distinct()
            ->orderBy('evento')
            ->pluck('evento');

        $modulos = Auditoria::query()
            ->whereNotNull('modulo')
            ->where('modulo', '<>', '')
            ->distinct()
            ->orderBy('modulo')
            ->pluck('modulo');

        return view(
            'auditorias.index',
            compact(
                'auditorias',
                'eventos',
                'modulos',
                'filtros'
            )
        );
    }
}
