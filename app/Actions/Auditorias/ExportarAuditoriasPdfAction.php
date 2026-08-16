<?php

namespace App\Actions\Auditorias;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Auditorias\AuditoriaQueryService;
use Barryvdh\DomPDF\Facade\Pdf;

final class ExportarAuditoriasPdfAction
{
    public function __construct(
        private readonly AuditoriaQueryService $auditoriaQueryService,
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * @param  array{
     *     fecha_desde?: string|null,
     *     fecha_hasta?: string|null,
     *     modulo?: string|null,
     *     evento?: string|null,
     *     actor?: string|null
     * }  $filtros
     *
     * @return array{
     *     contenido: string,
     *     nombre_archivo: string,
     *     cantidad: int
     * }
     */
    public function ejecutar(
        array $filtros,
        User $exportador
    ): array {
        $filtrosActivos =
            $this->auditoriaQueryService
            ->filtrosActivos(
                $filtros
            );

        /*
         * El conjunto a exportar se obtiene antes de registrar
         * la propia exportación. De esta forma, el evento
         * auditoria_exportada_pdf nunca puede incluirse a sí mismo.
         */
        $auditorias =
            $this->auditoriaQueryService
            ->consultaFiltrada(
                $filtrosActivos
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $generadoEn = now();

        $nombreArchivo = sprintf(
            'kinesiosoft_auditoria_%s.pdf',
            $generadoEn->format('Ymd_His')
        );

        $rolExportador = match ($exportador->getRoleNames()->first()) {
            RoleEnum::SUPERADMIN->value =>
            'Superadministrador',

            RoleEnum::ADMINISTRADOR->value =>
            'Administrador',

            default =>
            'Sin rol administrativo',
        };

        $pdf = Pdf::loadView(
            'auditorias.pdf.registros',
            [
                'auditorias' => $auditorias,
                'filtros' => $filtrosActivos,
                'generadoEn' => $generadoEn,
                'exportador' => $exportador,
                'rolExportador' => $rolExportador,
            ]
        )->setPaper(
            'a4',
            'landscape'
        );

        /*
         * Renderizamos primero para que Dompdf conozca
         * la cantidad definitiva de páginas.
         */
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();

        $canvas->page_script(
            function (
                int $numeroPagina,
                int $cantidadPaginas,
                $canvas,
                $fontMetrics
            ): void {
                $fuente = $fontMetrics->getFont(
                    'DejaVu Sans',
                    'normal'
                );

                $tamano = 7;

                $texto = sprintf(
                    'Página %d de %d',
                    $numeroPagina,
                    $cantidadPaginas
                );

                $anchoTexto = $fontMetrics->getTextWidth(
                    $texto,
                    $fuente,
                    $tamano
                );

                $x = $canvas->get_width()
                    - $anchoTexto
                    - 28;

                $y = $canvas->get_height()
                    - 22;

                $canvas->text(
                    $x,
                    $y,
                    $texto,
                    $fuente,
                    $tamano,
                    [0.42, 0.45, 0.50]
                );
            }
        );

        /*
         * Obtenemos primero los bytes definitivos del documento.
         * Sólo una vez que la generación terminó correctamente
         * registramos el evento de exportación.
         */
        $contenido = $pdf->output();

        $cantidadRegistros = $auditorias->count();

        $this->auditoriaService->registrar(
            evento: 'auditoria_exportada_pdf',
            modulo: 'Auditoría',
            metadata: [
                'filtros' => $filtrosActivos,
                'cantidad_registros' => $cantidadRegistros,
                'nombre_archivo' => $nombreArchivo,
            ],
            actor: $exportador
        );

        return [
            'contenido' => $contenido,
            'nombre_archivo' => $nombreArchivo,
            'cantidad' => $cantidadRegistros,
        ];
    }
}
