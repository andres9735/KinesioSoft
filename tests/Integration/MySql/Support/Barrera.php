<?php

namespace Tests\Integration\MySql\Support;

use RuntimeException;

final class Barrera
{
    public function __construct(private readonly string $directorio)
    {
        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la barrera de test.');
        }
    }

    public function marcar(string $nombre, array $datos = []): void
    {
        $ruta = $this->ruta($nombre);
        if (file_put_contents($ruta.'.tmp', json_encode($datos, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || ! rename($ruta.'.tmp', $ruta)) {
            throw new RuntimeException('No se pudo publicar la barrera de test.');
        }
    }

    public function leer(string $nombre): ?array
    {
        $ruta = $this->ruta($nombre);

        return is_file($ruta) ? json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR) : null;
    }

    public function esperar(string $nombre, float $segundos = 12): array
    {
        return self::hasta(fn () => $this->leer($nombre), $segundos);
    }

    public static function hasta(callable $condicion, float $segundos = 12, int $intervaloMicrosegundos = 10000): mixed
    {
        $limite = microtime(true) + $segundos;
        do {
            $resultado = $condicion();
            if ($resultado !== null && $resultado !== false) {
                return $resultado;
            }
            usleep($intervaloMicrosegundos);
        } while (microtime(true) < $limite);

        throw new RuntimeException('Timeout finito del harness: no se observó la condición requerida.');
    }

    private function ruta(string $nombre): string
    {
        if (! preg_match('/\A[a-z0-9_-]+\z/', $nombre)) {
            throw new RuntimeException('Nombre de barrera inválido.');
        }

        return $this->directorio.'/'.$nombre.'.json';
    }
}
