<?php

namespace App\Http\Controllers;

use App\Actions\Auditorias\ExportarAuditoriasPdfAction;
use App\Actions\Auditorias\ListarAuditoriasAction;
use App\Http\Requests\FiltrarAuditoriasRequest;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class AuditoriaController extends Controller
{
    public function __construct(
        private readonly ListarAuditoriasAction $listarAuditoriasAction,
        private readonly ExportarAuditoriasPdfAction $exportarAuditoriasPdfAction
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

        $auditorias =
            $this->listarAuditoriasAction
            ->ejecutar(
                filtros: $filtros,
                pagina: $request->integer(
                    'page',
                    1
                )
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

    public function exportarPdf(
        FiltrarAuditoriasRequest $request
    ): Response|RedirectResponse {
        Gate::authorize(
            'export',
            Auditoria::class
        );

        $filtros = $request
            ->safe()
            ->only([
                'fecha_desde',
                'fecha_hasta',
                'evento',
                'modulo',
                'actor',
            ]);

        /** @var User $exportador */
        $exportador = $request->user();

        try {
            $resultado =
                $this->exportarAuditoriasPdfAction
                ->ejecutar(
                    filtros: $filtros,
                    exportador: $exportador
                );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route(
                    'auditorias.index',
                    $filtros
                )
                ->with(
                    'error',
                    'No fue posible generar el archivo PDF. Intente nuevamente.'
                );
        }

        if ($resultado === null) {
            return redirect()
                ->route(
                    'auditorias.index',
                    $filtros
                )
                ->with(
                    'warning',
                    'No hay registros de auditoría para exportar con los filtros aplicados.'
                );
        }

        return response(
            $resultado['contenido'],
            200,
            [
                'Content-Type' =>
                'application/pdf',

                'Content-Disposition' =>
                'attachment; filename="' .
                    $resultado['nombre_archivo'] .
                    '"',
            ]
        );
    }
}
