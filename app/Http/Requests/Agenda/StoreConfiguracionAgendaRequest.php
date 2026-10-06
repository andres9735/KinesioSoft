<?php

namespace App\Http\Requests\Agenda;

use App\Models\ConfiguracionAgenda;
use App\Services\Agenda\RelojAplicacion;

class StoreConfiguracionAgendaRequest extends ConfiguracionAgendaRequestBase
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ConfiguracionAgenda::class) ?? false;
    }

    public function rules(RelojAplicacion $reloj): array
    {
        return $this->reglasConfiguracion(
            reloj: $reloj,
            inicioEstrictamenteFuturo: false,
            incluyeVigenciaHasta: true,
            incluyeSnapshot: false
        );
    }
}
