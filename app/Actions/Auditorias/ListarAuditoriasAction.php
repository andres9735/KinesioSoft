<?php

namespace App\Actions\Auditorias;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListarAuditoriasAction
{
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
        $fechaDesde = $this->normalizarFiltro(
            $filtros['fecha_desde'] ?? null
        );

        $fechaHasta = $this->normalizarFiltro(
            $filtros['fecha_hasta'] ?? null
        );

        $modulo = $this->normalizarFiltro(
            $filtros['modulo'] ?? null
        );

        $evento = $this->normalizarFiltro(
            $filtros['evento'] ?? null
        );

        $actor = $this->normalizarFiltro(
            $filtros['actor'] ?? null
        );

        $query = Auditoria::query();

        if ($fechaDesde !== null) {
            $query->whereDate(
                'created_at',
                '>=',
                $fechaDesde
            );
        }

        if ($fechaHasta !== null) {
            $query->whereDate(
                'created_at',
                '<=',
                $fechaHasta
            );
        }

        if ($modulo !== null) {
            $query->where(
                'modulo',
                $modulo
            );
        }

        if ($evento !== null) {
            $query->where(
                'evento',
                $evento
            );
        }

        if ($actor !== null) {
            $query->where(
                function (Builder $query) use ($actor): void {
                    $query
                        ->whereLike(
                            'actor_nombre',
                            '%' . $actor . '%',
                            caseSensitive: false
                        )
                        ->orWhereLike(
                            'actor_email',
                            '%' . $actor . '%',
                            caseSensitive: false
                        );
                }
            );
        }

        $filtrosActivos = array_filter(
            [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'modulo' => $modulo,
                'evento' => $evento,
                'actor' => $actor,
            ],
            static fn(?string $valor): bool => $valor !== null
        );

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: 10,
                page: max(1, $pagina)
            )
            ->appends($filtrosActivos);
    }

    private function normalizarFiltro(
        mixed $valor
    ): ?string {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== ''
            ? $valor
            : null;
    }
}
