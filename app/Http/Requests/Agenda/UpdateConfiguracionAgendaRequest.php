<?php

namespace App\Http\Requests\Agenda;

use App\Models\ConfiguracionAgenda;
use App\Services\Agenda\RelojAplicacion;

class UpdateConfiguracionAgendaRequest extends ConfiguracionAgendaRequestBase
{
    public function authorize(): bool
    {
        $configuracion = $this->route('configuracion');

        return $configuracion instanceof ConfiguracionAgenda
            && ($this->user()?->can('update', $configuracion) ?? false);
    }

    public function rules(RelojAplicacion $reloj): array
    {
        return $this->reglasConfiguracion(
            reloj: $reloj,
            inicioEstrictamenteFuturo: true,
            incluyeVigenciaHasta: true,
            incluyeSnapshot: true
        );
    }
}
