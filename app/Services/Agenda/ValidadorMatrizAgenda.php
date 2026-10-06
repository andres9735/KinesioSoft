<?php

namespace App\Services\Agenda;

use InvalidArgumentException;

final class ValidadorMatrizAgenda
{
    public const ERROR_MATRIZ_VACIA = 1;

    public const ERROR_DIA_INVALIDO = 2;

    public const ERROR_HORA_INVALIDA = 3;

    public const ERROR_ORDEN_INVALIDO = 4;

    public const ERROR_DURACION_INSUFICIENTE = 5;

    public const ERROR_SOLAPAMIENTO = 6;

    public const ERROR_DURACION_INVALIDA = 7;

    /**
     * @param  iterable<array<string, mixed>>  $franjas
     * @return list<array{dia_semana: int, hora_inicio: string, hora_fin: string, id?: int}>
     */
    public function validar(iterable $franjas, int $duracionTurnoMinutos): array
    {
        if ($duracionTurnoMinutos <= 0) {
            throw new InvalidArgumentException(
                'La duración estándar debe ser un entero positivo.',
                self::ERROR_DURACION_INVALIDA
            );
        }

        $normalizadas = [];

        foreach ($franjas as $indice => $franja) {
            $normalizadas[] = $this->normalizarFranja($franja, $indice);
        }

        if ($normalizadas === []) {
            throw new InvalidArgumentException(
                'La matriz debe contener al menos una franja horaria.',
                self::ERROR_MATRIZ_VACIA
            );
        }

        usort($normalizadas, static function (array $a, array $b): int {
            return [$a['dia_semana'], $a['hora_inicio'], $a['hora_fin'], $a['id'] ?? 0]
                <=> [$b['dia_semana'], $b['hora_inicio'], $b['hora_fin'], $b['id'] ?? 0];
        });

        $anteriorPorDia = [];

        foreach ($normalizadas as $indice => $franja) {
            $inicioSegundos = $this->segundosDesdeMedianoche($franja['hora_inicio']);
            $finSegundos = $this->segundosDesdeMedianoche($franja['hora_fin']);

            if ($inicioSegundos >= $finSegundos) {
                throw new InvalidArgumentException(
                    "La hora de inicio de la franja {$indice} debe ser anterior a la hora de fin.",
                    self::ERROR_ORDEN_INVALIDO
                );
            }

            if (($finSegundos - $inicioSegundos) < ($duracionTurnoMinutos * 60)) {
                throw new InvalidArgumentException(
                    "La franja {$indice} es menor que la duración estándar del turno.",
                    self::ERROR_DURACION_INSUFICIENTE
                );
            }

            $dia = $franja['dia_semana'];
            $anterior = $anteriorPorDia[$dia] ?? null;

            if ($anterior !== null && $franja['hora_inicio'] < $anterior['hora_fin']) {
                throw new InvalidArgumentException(
                    "Las franjas del día {$dia} se superponen.",
                    self::ERROR_SOLAPAMIENTO
                );
            }

            $anteriorPorDia[$dia] = $franja;
        }

        return $normalizadas;
    }

    /**
     * @param  array<string, mixed>  $franja
     * @return array{dia_semana: int, hora_inicio: string, hora_fin: string, id?: int}
     */
    private function normalizarFranja(array $franja, int|string $indice): array
    {
        $dia = $this->normalizarDia($franja['dia_semana'] ?? null);

        if ($dia === null) {
            throw new InvalidArgumentException(
                "El día de la franja {$indice} debe pertenecer al rango ISO 1..7.",
                self::ERROR_DIA_INVALIDO
            );
        }

        $horaInicio = $this->normalizarHora($franja['hora_inicio'] ?? null);
        $horaFin = $this->normalizarHora($franja['hora_fin'] ?? null);

        if ($horaInicio === null || $horaFin === null) {
            throw new InvalidArgumentException(
                "Las horas de la franja {$indice} deben usar el formato H:i o H:i:s.",
                self::ERROR_HORA_INVALIDA
            );
        }

        $normalizada = [
            'dia_semana' => $dia,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ];

        if (array_key_exists('id', $franja) && is_int($franja['id'])) {
            $normalizada['id'] = $franja['id'];
        }

        return $normalizada;
    }

    private function normalizarDia(mixed $dia): ?int
    {
        if (is_string($dia) && preg_match('/^[1-7]$/', $dia) === 1) {
            $dia = (int) $dia;
        }

        return is_int($dia) && $dia >= 1 && $dia <= 7
            ? $dia
            : null;
    }

    private function normalizarHora(mixed $hora): ?string
    {
        if (! is_string($hora)) {
            return null;
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora) === 1) {
            return $hora.':00';
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $hora) === 1) {
            return $hora;
        }

        return null;
    }

    private function segundosDesdeMedianoche(string $hora): int
    {
        [$horas, $minutos, $segundos] = array_map('intval', explode(':', $hora));

        return ($horas * 3600) + ($minutos * 60) + $segundos;
    }
}
