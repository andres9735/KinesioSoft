<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\RecordatorioTurno;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class SeleccionarCandidatoAdelantoService
{
    public function __construct(private readonly ValidarDestinoAdelantoService $destino) {}

    /**
     * CA-G04/G05/G10: selección interna de solo lectura para el cupo indicado.
     * El llamador aporta su profesional/intervalo y los pacientes ya ofertados
     * por esa cancelación. No consume intenciones, reserva ni activa ofertas.
     * El resultado debe revalidarse al emitir y al aceptar; no garantiza exclusividad.
     *
     * @param  list<int>  $pacientesYaOfertados
     */
    public function seleccionar(
        Kinesiologa $kinesiologa,
        CarbonInterface $inicioCupo,
        CarbonInterface $finCupo,
        array $pacientesYaOfertados = [],
    ): ?Turno {
        foreach ($pacientesYaOfertados as $pacienteId) {
            if (! is_int($pacienteId) || $pacienteId <= 0) {
                throw new InvalidArgumentException('Las exclusiones deben identificar pacientes mediante enteros positivos.');
            }
        }
        if (! $kinesiologa->exists || $kinesiologa->isDirty('id')) {
            return null;
        }

        $zona = (string) config('app.timezone');
        $inicio = CarbonImmutable::instance($inicioCupo)->setTimezone($zona);
        $fin = CarbonImmutable::instance($finCupo)->setTimezone($zona);
        if (! $fin->gt($inicio)) {
            return null;
        }
        // Siete días corridos desde el instante del inicio, sin cortes de semana.
        $hasta = $inicio->utc()->addHours(7 * 24)->setTimezone($zona);
        $candidatos = Turno::query()->useWritePdo()
            ->with(['paciente:id,user_id', 'paciente.user:id,email'])
            ->where('kinesiologa_id', $kinesiologa->getKey())
            ->where('tipo', TipoTurnoEnum::PRIMERA_CONSULTA->value)
            ->whereIn('estado', [EstadoTurnoEnum::PENDIENTE->value, EstadoTurnoEnum::CONFIRMADO->value])
            ->where('inicio_at', '>', $inicio->format('Y-m-d H:i:s.u'))
            ->where('inicio_at', '<=', $hasta->format('Y-m-d H:i:s.u'))
            ->whereNotIn('paciente_id', $pacientesYaOfertados)
            ->orderByDesc('inicio_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($candidatos as $candidato) {
            // Predicado de formato ya utilizado por las comunicaciones de turnos;
            // no requiere DNS, buzón real ni verificación de correo del portal.
            if (! RecordatorioTurno::correoUtilizable($candidato->paciente?->user?->email)) {
                continue;
            }
            if ($this->destino->validar($candidato, $kinesiologa, $inicio, $fin) !== null) {
                return $candidato;
            }
        }

        return null;
    }
}
