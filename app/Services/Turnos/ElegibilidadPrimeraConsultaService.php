<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Database\Eloquent\Builder;

final class ElegibilidadPrimeraConsultaService
{
    public function __construct(private readonly RelojAplicacion $reloj) {}

    /**
     * @return Builder<Turno>
     */
    public function primerasConsultasFuturasActivasDe(Paciente $paciente): Builder
    {
        // D-01 de UC-07 conserva sus estados activos, independientemente de la ocupación de Agenda.
        return Turno::query()
            ->where('paciente_id', $paciente->getKey())
            ->where('tipo', TipoTurnoEnum::PRIMERA_CONSULTA)
            ->whereIn('estado', [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO])
            ->where('inicio_at', '>', $this->reloj->ahora());
    }

    public function tienePrimeraConsultaFuturaActiva(Paciente $paciente): bool
    {
        return $this->primerasConsultasFuturasActivasDe($paciente)->exists();
    }
}
