<?php

namespace App\Services\Turnos\Documentos;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

final class CoordinadorBloqueoDocumentalMariaDb implements CoordinadorBloqueoDocumental
{
    public function __construct(private readonly Connection $conexion) {}

    public function adquirir(string $ruta): BloqueoDocumental
    {
        return $this->adquirirConTimeout($ruta, config('documentos_clinicos.lock_timeout_segundos'))
            ?? throw new RuntimeException('No se adquirió el bloqueo documental.');
    }

    public function intentarAdquirir(string $ruta): ?BloqueoDocumental
    {
        return $this->adquirirConTimeout($ruta, 0);
    }

    private function adquirirConTimeout(string $ruta, int $timeout): ?BloqueoDocumental
    {
        if (! in_array($this->conexion->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('La coordinación documental requiere MariaDB/MySQL; los tests deben inyectar su sustituto.');
        }

        $nombre = 'ks-doc:'.substr(hash('sha256', config('app.name').'|'.$this->conexion->getDatabaseName().'|'.$ruta), 0, 56);
        $pdo = $this->conexion->getPdo();

        // Se usa el PDO de escritura de esta Connection, sin lectura réplica ni reconexión/retry automático.
        $resultado = $this->consultar($pdo, 'SELECT GET_LOCK(?, ?)', [$nombre, $timeout]);

        if ($resultado === 0 || $resultado === '0') {
            return null;
        }

        if ($resultado !== 1 && $resultado !== '1') {
            throw new RuntimeException('No se adquirió el bloqueo documental.');
        }

        // El callback retiene este coordinador (y su Connection), además de la sesión PDO exacta.
        return new BloqueoDocumental(function () use ($pdo, $nombre): void {
            $resultado = $this->consultar($pdo, 'SELECT RELEASE_LOCK(?)', [$nombre]);

            if ($resultado !== 1 && $resultado !== '1') {
                throw new RuntimeException('No se confirmó la liberación del bloqueo documental.');
            }
        });
    }

    private function consultar(PDO $pdo, string $sql, array $parametros): mixed
    {
        $consulta = $pdo->prepare($sql);

        if ($consulta === false || ! $consulta->execute($parametros)) {
            throw new RuntimeException('Falló la consulta de coordinación documental.');
        }

        return $consulta->fetchColumn();
    }
}
