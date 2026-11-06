<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\Agenda\RelojAplicacion;
use Carbon\CarbonImmutable;
use SensitiveParameter;

final class ValidarEnlaceAsistenciaTurnoService
{
    public function __construct(private RelojAplicacion $reloj) {}

    /**
     * Consulta inicial exclusivamente de lectura. No autoriza una transición:
     * el ejecutor futuro debe revalidar enlace/turno/plazo dentro de los locks de UC–06.
     */
    public function validar(#[SensitiveParameter] string $token): AccesoEnlaceAsistencia
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $token) !== 1) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::INVALIDO);
        }
        $enlace = EnlaceAsistenciaTurno::query()->useWritePdo()->where('token_hash', hash('sha256', $token))->first();
        if ($enlace === null || ! $enlace->coincideConToken($token) || $enlace->alcance !== EnlaceAsistenciaTurno::ALCANCE) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::INVALIDO);
        }
        if ($enlace->revocado_at !== null) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::REVOCADO);
        }
        $ahora = $this->reloj->ahora();
        $emitido = CarbonImmutable::parse($enlace->getRawOriginal('emitido_at'), $enlace->zona_horaria);
        $vence = CarbonImmutable::parse($enlace->getRawOriginal('vence_at'), $enlace->zona_horaria);
        if ($ahora->lt($emitido)) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::INVALIDO);
        }
        if (! $ahora->lt($vence)) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::VENCIDO);
        }
        $turno = Turno::query()->useWritePdo()->find($enlace->turno_id, [
            'id', 'paciente_id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at',
        ]);
        if ($turno === null || ! in_array($turno->tipo, TipoTurnoEnum::cases(), true)) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::INVALIDO);
        }
        if (! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::ESTADO_INCOMPATIBLE);
        }
        if (! $enlace->vigentePara($turno, $ahora)) {
            return new AccesoEnlaceAsistencia(AccesoEnlaceAsistencia::PROGRAMACION_MODIFICADA);
        }
        $inicio = CarbonImmutable::parse($turno->getRawOriginal('inicio_at'), $enlace->zona_horaria);
        $fin = CarbonImmutable::parse($turno->getRawOriginal('fin_at'), $enlace->zona_horaria);
        $turno->load(['kinesiologa:id,user_id', 'kinesiologa.user:id,name,apellido']);
        $profesional = $turno->kinesiologa?->user;

        return new AccesoEnlaceAsistencia(
            AccesoEnlaceAsistencia::VALIDO,
            [
                'tipo' => $turno->tipo === TipoTurnoEnum::PRIMERA_CONSULTA ? 'Primera consulta' : 'Seguimiento',
                'fecha' => $inicio->format('d/m/Y'), 'horario' => $inicio->format('H:i').'–'.$fin->format('H:i'),
                'kinesiologa' => trim(($profesional?->name ?? '').' '.($profesional?->apellido ?? '')),
                'estado' => $turno->estado === EstadoTurnoEnum::PENDIENTE ? 'Pendiente' : 'Confirmado',
            ],
            $turno->estado === EstadoTurnoEnum::PENDIENTE && $ahora->lt($inicio),
            ! $inicio->utc()->lt($ahora->utc()->addHours(12)),
        );
    }
}
