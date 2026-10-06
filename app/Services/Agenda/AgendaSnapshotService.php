<?php

namespace App\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class AgendaSnapshotService
{
    public function generarParaConfiguracion(ConfiguracionAgenda $configuracion): string
    {
        return $this->encriptar($this->snapshotCanonico($configuracion));
    }

    public function verificarConfiguracion(
        string $token,
        ConfiguracionAgenda $configuracion
    ): void {
        $contenido = $this->desencriptar(
            $token,
            'El estado mostrado de la configuración de agenda ya no es válido.'
        );

        $actual = $this->snapshotCanonico($configuracion);

        if (! is_array($contenido)
            || ($contenido['id'] ?? null) !== $actual['id']
            || $contenido !== $actual) {
            throw new SnapshotAgendaDesactualizadoException(
                'La configuración de agenda cambió desde que fue mostrada.'
            );
        }
    }

    public function generarParaBloqueo(BloqueoAgenda $bloqueo): string
    {
        return $this->encriptar($this->snapshotCanonicoBloqueo($bloqueo));
    }

    public function verificarBloqueo(string $token, BloqueoAgenda $bloqueo): void
    {
        $contenido = $this->desencriptar(
            $token,
            'El estado mostrado del bloqueo de agenda ya no es válido.'
        );
        $actual = $this->snapshotCanonicoBloqueo($bloqueo);

        if (! is_array($contenido)
            || ($contenido['id'] ?? null) !== $actual['id']
            || $contenido !== $actual) {
            throw new SnapshotAgendaDesactualizadoException(
                'El bloqueo de agenda cambió desde que fue mostrado.'
            );
        }
    }

    /**
     * @param  array{inicio_at: mixed, fin_at: mixed, motivo: mixed}  $candidato
     * @param  iterable<array-key, array<string, mixed>>  $turnosAfectados
     * @param  iterable<array-key, array<string, mixed>>  $bloqueosSuperpuestos
     */
    public function generarRevisionBloqueo(
        int $kinesiologaId,
        array $candidato,
        iterable $turnosAfectados,
        iterable $bloqueosSuperpuestos
    ): string {
        return $this->encriptar($this->revisionCanonica(
            $kinesiologaId,
            $candidato,
            $turnosAfectados,
            $bloqueosSuperpuestos
        ));
    }

    /**
     * @param  array{inicio_at: mixed, fin_at: mixed, motivo: mixed}  $candidato
     * @param  iterable<array-key, array<string, mixed>>  $turnosAfectados
     * @param  iterable<array-key, array<string, mixed>>  $bloqueosSuperpuestos
     */
    public function verificarRevisionBloqueo(
        string $token,
        int $kinesiologaId,
        array $candidato,
        iterable $turnosAfectados,
        iterable $bloqueosSuperpuestos
    ): void {
        $contenido = $this->desencriptar(
            $token,
            'La revisión del bloqueo de agenda ya no es válida.'
        );
        $actual = $this->revisionCanonica(
            $kinesiologaId,
            $candidato,
            $turnosAfectados,
            $bloqueosSuperpuestos
        );

        if (! is_array($contenido) || $contenido !== $actual) {
            throw new SnapshotAgendaDesactualizadoException(
                'La revisión del bloqueo cambió desde que fue confirmada.'
            );
        }
    }

    /**
     * @return array{
     *     id: int,
     *     vigencia_desde: string,
     *     vigencia_hasta: ?string,
     *     duracion_turno_minutos: int,
     *     anulado_at: ?string,
     *     motivo_anulacion: ?string,
     *     franjas: list<array{id: int, dia_semana: int, hora_inicio: string, hora_fin: string}>
     * }
     */
    private function snapshotCanonico(ConfiguracionAgenda $configuracion): array
    {
        $franjas = $configuracion->relationLoaded('franjasHorarias')
            ? $configuracion->franjasHorarias
            : $configuracion->franjasHorarias()->get();

        return [
            'id' => (int) $configuracion->getKey(),
            'vigencia_desde' => $configuracion->vigencia_desde->format('Y-m-d'),
            'vigencia_hasta' => $configuracion->vigencia_hasta?->format('Y-m-d'),
            'duracion_turno_minutos' => (int) $configuracion->duracion_turno_minutos,
            'anulado_at' => $configuracion->anulado_at?->format('Y-m-d H:i:sP'),
            'motivo_anulacion' => $configuracion->motivo_anulacion,
            'franjas' => $this->franjasCanonicas($franjas),
        ];
    }

    /**
     * @return array{
     *     tipo: string,
     *     id: int,
     *     inicio_at: string,
     *     fin_at: string,
     *     motivo: string,
     *     anulado_at: ?string,
     *     motivo_anulacion: ?string
     * }
     */
    private function snapshotCanonicoBloqueo(BloqueoAgenda $bloqueo): array
    {
        return [
            'tipo' => 'bloqueo_agenda',
            'id' => (int) $bloqueo->getKey(),
            'inicio_at' => $this->fechaHoraCanonica($bloqueo->inicio_at),
            'fin_at' => $this->fechaHoraCanonica($bloqueo->fin_at),
            'motivo' => (string) $bloqueo->motivo,
            'anulado_at' => $bloqueo->anulado_at === null
                ? null
                : $this->fechaHoraCanonica($bloqueo->anulado_at),
            'motivo_anulacion' => $bloqueo->motivo_anulacion,
        ];
    }

    /**
     * @param  array{inicio_at: mixed, fin_at: mixed, motivo: mixed}  $candidato
     * @param  iterable<array-key, array<string, mixed>>  $turnosAfectados
     * @param  iterable<array-key, array<string, mixed>>  $bloqueosSuperpuestos
     * @return array<string, mixed>
     */
    private function revisionCanonica(
        int $kinesiologaId,
        array $candidato,
        iterable $turnosAfectados,
        iterable $bloqueosSuperpuestos
    ): array {
        $turnos = collect($turnosAfectados)
            ->map(fn (array $turno): array => [
                'id' => (int) $turno['id'],
                'estado' => $turno['estado'] instanceof EstadoTurnoEnum
                    ? $turno['estado']->value
                    : (string) $turno['estado'],
                'inicio_at' => $this->fechaHoraCanonica($turno['inicio_at']),
                'fin_at' => $this->fechaHoraCanonica($turno['fin_at']),
            ])
            ->sortBy(fn (array $turno): array => [
                $turno['inicio_at'],
                $turno['fin_at'],
                $turno['id'],
            ])
            ->values()
            ->all();

        $bloqueos = collect($bloqueosSuperpuestos)
            ->map(fn (array $bloqueo): array => [
                'id' => (int) $bloqueo['id'],
                'inicio_at' => $this->fechaHoraCanonica($bloqueo['inicio_at']),
                'fin_at' => $this->fechaHoraCanonica($bloqueo['fin_at']),
                'anulado_at' => ($bloqueo['anulado_at'] ?? null) === null
                    ? null
                    : $this->fechaHoraCanonica($bloqueo['anulado_at']),
                'motivo' => (string) $bloqueo['motivo'],
            ])
            ->sortBy(fn (array $bloqueo): array => [
                $bloqueo['inicio_at'],
                $bloqueo['fin_at'],
                $bloqueo['id'],
            ])
            ->values()
            ->all();

        return [
            'tipo' => 'revision_bloqueo_agenda',
            'kinesiologa_id' => $kinesiologaId,
            'candidato' => [
                'inicio_at' => $this->fechaHoraCanonica($candidato['inicio_at']),
                'fin_at' => $this->fechaHoraCanonica($candidato['fin_at']),
                'motivo' => trim((string) $candidato['motivo']),
            ],
            'turnos_afectados' => $turnos,
            'bloqueos_superpuestos' => $bloqueos,
        ];
    }

    /**
     * @param  Collection<int, FranjaHorariaAgenda>  $franjas
     * @return list<array{id: int, dia_semana: int, hora_inicio: string, hora_fin: string}>
     */
    private function franjasCanonicas(Collection $franjas): array
    {
        return $franjas
            ->map(fn (FranjaHorariaAgenda $franja): array => [
                'id' => (int) $franja->getKey(),
                'dia_semana' => (int) $franja->dia_semana->value,
                'hora_inicio' => $this->horaCanonica($franja->hora_inicio),
                'hora_fin' => $this->horaCanonica($franja->hora_fin),
            ])
            ->sortBy(fn (array $franja): array => [
                $franja['dia_semana'],
                $franja['hora_inicio'],
                $franja['hora_fin'],
                $franja['id'],
            ])
            ->values()
            ->all();
    }

    private function horaCanonica(mixed $hora): string
    {
        $valor = (string) $hora;

        return strlen($valor) === 5 ? $valor.':00' : $valor;
    }

    /** @param array<string, mixed> $contenido */
    private function encriptar(array $contenido): string
    {
        return Crypt::encryptString(json_encode(
            $contenido,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    private function desencriptar(string $token, string $mensaje): mixed
    {
        try {
            $tokenDecodificado = base64_decode($token, true);

            if ($tokenDecodificado === false || base64_encode($tokenDecodificado) !== $token) {
                throw new SnapshotAgendaDesactualizadoException(
                    'El token de snapshot no posee una representación canónica válida.'
                );
            }

            return json_decode(
                Crypt::decryptString($token),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (SnapshotAgendaDesactualizadoException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SnapshotAgendaDesactualizadoException(
                $mensaje,
                previous: $exception
            );
        }
    }

    private function fechaHoraCanonica(mixed $valor): string
    {
        $fecha = $valor instanceof CarbonInterface
            ? CarbonImmutable::instance($valor)
            : CarbonImmutable::parse((string) $valor, config('app.timezone'));

        return $fecha
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:sP');
    }
}
