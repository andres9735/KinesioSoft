<?php

namespace App\Actions\Auditorias;

use App\Models\Auditoria;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListarAuditoriasAction
{
    public function ejecutar(
        int $pagina = 1
    ): LengthAwarePaginator {
        return Auditoria::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: 10,
                page: max(1, $pagina)
            );
    }
}
