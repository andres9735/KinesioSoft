<?php

namespace App\Actions\Auditorias;

use App\Services\Auditorias\AuditoriaQueryService;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListarAuditoriasAction
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
     */
    public function ejecutar(
        array $filtros = [],
        int $pagina = 1
    ): LengthAwarePaginator {
        $filtrosActivos =
            $this->auditoriaQueryService
                ->filtrosActivos(
                    $filtros
                );

        return $this->auditoriaQueryService
            ->consultaFiltrada(
                $filtrosActivos
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: 10,
                page: max(1, $pagina)
            )
            ->appends(
                $filtrosActivos
            );
    }
}
