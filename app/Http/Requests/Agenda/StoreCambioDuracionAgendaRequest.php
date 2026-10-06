<?php

namespace App\Http\Requests\Agenda;

use App\Models\ConfiguracionAgenda;
use App\Services\Agenda\RelojAplicacion;

class StoreCambioDuracionAgendaRequest extends ConfiguracionAgendaRequestBase
{
    public function authorize(): bool
    {
        return $this->user()?->can('cambiarDuracion', ConfiguracionAgenda::class) ?? false;
    }

    public function rules(RelojAplicacion $reloj): array
    {
        return $this->reglasConfiguracion(
            reloj: $reloj,
            inicioEstrictamenteFuturo: true,
            incluyeVigenciaHasta: false,
            incluyeSnapshot: true
        );
    }
}
