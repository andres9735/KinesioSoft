<?php

namespace App\Services\Auditorias;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Builder;

final class AuditoriaQueryService
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
    public function consultaFiltrada(
        array $filtros = []
    ): Builder {
        $filtros = $this->normalizarFiltros(
            $filtros
        );

        $query = Auditoria::query();

        if ($filtros['fecha_desde'] !== null) {
            $query->whereDate(
                'created_at',
                '>=',
                $filtros['fecha_desde']
            );
        }

        if ($filtros['fecha_hasta'] !== null) {
            $query->whereDate(
                'created_at',
                '<=',
                $filtros['fecha_hasta']
            );
        }

        if ($filtros['modulo'] !== null) {
            $query->where(
                'modulo',
                $filtros['modulo']
            );
        }

        if ($filtros['evento'] !== null) {
            $query->where(
                'evento',
                $filtros['evento']
            );
        }

        if ($filtros['actor'] !== null) {
            $actor = $filtros['actor'];

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

        return $query;
    }

    /**
     * @param  array{
     *     fecha_desde?: string|null,
     *     fecha_hasta?: string|null,
     *     modulo?: string|null,
     *     evento?: string|null,
     *     actor?: string|null
     * }  $filtros
     *
     * @return array<string, string>
     */
    public function filtrosActivos(
        array $filtros = []
    ): array {
        return array_filter(
            $this->normalizarFiltros(
                $filtros
            ),
            static fn(?string $valor): bool =>
            $valor !== null
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     *
     * @return array{
     *     fecha_desde: string|null,
     *     fecha_hasta: string|null,
     *     modulo: string|null,
     *     evento: string|null,
     *     actor: string|null
     * }
     */
    private function normalizarFiltros(
        array $filtros
    ): array {
        return [
            'fecha_desde' => $this->normalizarFiltro(
                $filtros['fecha_desde'] ?? null
            ),

            'fecha_hasta' => $this->normalizarFiltro(
                $filtros['fecha_hasta'] ?? null
            ),

            'modulo' => $this->normalizarFiltro(
                $filtros['modulo'] ?? null
            ),

            'evento' => $this->normalizarFiltro(
                $filtros['evento'] ?? null
            ),

            'actor' => $this->normalizarFiltro(
                $filtros['actor'] ?? null
            ),
        ];
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
