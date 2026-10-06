<?php

namespace App\Services\Agenda;

use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class AgendaSnapshotService
{
    public function generarParaConfiguracion(ConfiguracionAgenda $configuracion): string
    {
        return Crypt::encryptString(json_encode(
            $this->snapshotCanonico($configuracion),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    public function verificarConfiguracion(
        string $token,
        ConfiguracionAgenda $configuracion
    ): void {
        try {
            $tokenDecodificado = base64_decode($token, true);

            if ($tokenDecodificado === false || base64_encode($tokenDecodificado) !== $token) {
                throw new SnapshotAgendaDesactualizadoException(
                    'El token de snapshot no posee una representación canónica válida.'
                );
            }

            $contenido = json_decode(
                Crypt::decryptString($token),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (SnapshotAgendaDesactualizadoException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SnapshotAgendaDesactualizadoException(
                'El estado mostrado de la configuración de agenda ya no es válido.',
                previous: $exception
            );
        }

        $actual = $this->snapshotCanonico($configuracion);

        if (! is_array($contenido)
            || ($contenido['id'] ?? null) !== $actual['id']
            || $contenido !== $actual) {
            throw new SnapshotAgendaDesactualizadoException(
                'La configuración de agenda cambió desde que fue mostrada.'
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
}
