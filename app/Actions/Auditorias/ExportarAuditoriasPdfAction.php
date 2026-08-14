<?php

namespace App\Actions\Auditorias;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\Auditorias\AuditoriaQueryService;
use Barryvdh\DomPDF\Facade\Pdf;

final class ExportarAuditoriasPdfAction
{
    public function __construct(
        private readonly AuditoriaQueryService $auditoriaQueryService
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

        $rolExportador = match (
            $exportador->getRoleNames()->first()
        ) {
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

        return [
            'contenido' => $pdf->output(),
            'nombre_archivo' => $nombreArchivo,
            'cantidad' => $auditorias->count(),
        ];
    }
}
