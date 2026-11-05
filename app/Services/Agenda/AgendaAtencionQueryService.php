<?php

namespace App\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

final class AgendaAtencionQueryService
{
    /**
     * Consulta interna: el consumidor debe autorizar el acceso antes de exponer datos.
     * La fecha civil recibida se interpreta en la zona configurada del consultorio,
     * conforme a la programación local persistida. No representa un instante UTC.
     * Un turno pertenece a la jornada en la que comienza; conserva su intervalo completo.
     *
     * @return Collection<int, Turno>
     */
    public function turnosDeJornada(Kinesiologa $kinesiologa, CarbonInterface $fecha): Collection
    {
        return $this->turnosDePeriodo($kinesiologa, $fecha, $fecha);
    }

    /**
     * Fechas civiles desde/hasta inclusivas: [inicio de desde, inicio del día posterior a hasta).
     * Comparte con la jornada la pertenencia por inicio y no limita la anticipación ni el historial.
     * El consumidor debe autorizar el ámbito profesional antes de exponer los resultados.
     *
     * @return Collection<int, Turno>
     */
    public function turnosDePeriodo(
        Kinesiologa $kinesiologa,
        CarbonInterface $desde,
        CarbonInterface $hasta,
    ): Collection {
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d', $desde->toDateString(), config('app.timezone'));
        $ultimoDia = CarbonImmutable::createFromFormat('!Y-m-d', $hasta->toDateString(), config('app.timezone'));
        if ($ultimoDia->lt($inicio)) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        return Turno::query()
            ->where('kinesiologa_id', $kinesiologa->getKey())
            ->where('inicio_at', '>=', $inicio)
            ->where('inicio_at', '<', $ultimoDia->addDay())
            ->orderBy('inicio_at')
            ->orderBy('id')
            ->get(['id', 'paciente_id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at']);
    }

    /**
     * Representación administrativa interna común a UC–05 y UC–29, sin datos clínicos
     * ni serialización de modelos completos. No concede autorización por sí misma.
     * Los intervalos conservan las fechas y horas persistidas, incluso si cruzan medianoche.
     * No se infieren equipos: Turno no dispone de una relación de equipos registrados.
     *
     * @return list<array{
     *     id: int,
     *     paciente: array{id: int, nombre: ?string},
     *     kinesiologa: array{id: int, nombre: ?string},
     *     tipo: TipoTurnoEnum,
     *     estado: EstadoTurnoEnum,
     *     inicio_at: CarbonImmutable,
     *     fin_at: CarbonImmutable
     * }>
     */
    public function informacionDePeriodo(
        Kinesiologa $kinesiologa,
        CarbonInterface $desde,
        CarbonInterface $hasta,
    ): array {
        $turnos = $this->turnosDePeriodo($kinesiologa, $desde, $hasta);
        $turnos->load([
            'paciente:id,user_id',
            'paciente.user:id,name,apellido',
            'kinesiologa:id,user_id',
            'kinesiologa.user:id,name,apellido',
        ]);

        return $turnos->map(function (Turno $turno): array {
            $paciente = $turno->paciente?->user;
            $profesional = $turno->kinesiologa?->user;

            return [
                'id' => $turno->id,
                'paciente' => [
                    'id' => $turno->paciente_id,
                    'nombre' => $paciente === null ? null : trim($paciente->name.' '.$paciente->apellido),
                ],
                'kinesiologa' => [
                    'id' => $turno->kinesiologa_id,
                    'nombre' => $profesional === null ? null : trim($profesional->name.' '.$profesional->apellido),
                ],
                'tipo' => $turno->tipo,
                'estado' => $turno->estado,
                'inicio_at' => $turno->inicio_at,
                'fin_at' => $turno->fin_at,
            ];
        })->all();
    }
}
