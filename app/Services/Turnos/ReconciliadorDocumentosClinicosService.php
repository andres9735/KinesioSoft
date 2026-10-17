<?php

namespace App\Services\Turnos;

use App\Models\DocumentoClinico;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\Documentos\ResultadoReconciliacionDocumental;
use DirectoryIterator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

final class ReconciliadorDocumentosClinicosService
{
    public function __construct(
        private readonly CoordinadorBloqueoDocumental $coordinador,
        private readonly RelojAplicacion $reloj,
    ) {}

    public function reconciliar(bool $eliminar = false): ResultadoReconciliacionDocumental
    {
        $conteos = array_fill_keys([
            'examinados', 'ignorados_nombre_invalido', 'inseguros', 'desaparecidos',
            'recientes', 'referenciados', 'en_uso_lock', 'candidatos', 'eliminados',
        ], 0);
        $errores = [];
        $etapa = 'configuracion';

        try {
            $disco = Storage::disk(config('documentos_clinicos.disk'));
            $directorio = config('documentos_clinicos.directorio');
            $horas = config('documentos_clinicos.reconciliacion.gracia_horas');
            if ($disco->getConfig()['driver'] !== 'local'
                || ! is_string($directorio)
                || ! preg_match('/\A[a-z0-9_-]+(?:\/[a-z0-9_-]+)*\z/', $directorio)
                || ! is_int($horas) || $horas < 1) {
                throw new RuntimeException('Configuración de reconciliación inválida.');
            }
            $limite = $this->reloj->ahora()->subHours($horas)->getTimestamp();
            $raiz = rtrim($disco->path(''), '/\\');
            $namespace = $raiz.'/'.$directorio;
            $this->verificarDirectorio($raiz, $namespace);

            $etapa = 'db';
            $conexion = (new DocumentoClinico)->getConnection();
            $pdo = $conexion->getPdo();
            // No reutilizar un read view anterior a la adquisición del advisory lock.
            if ($conexion->transactionLevel() !== 0 || $pdo->inTransaction()) {
                throw new RuntimeException('La reconciliación requiere una sesión sin transacción envolvente.');
            }
            if ($conexion->getDriverName() === 'mysql' || $conexion->getDriverName() === 'mariadb') {
                $autocommit = $pdo->query('SELECT @@autocommit');
                if ($autocommit === false || (int) $autocommit->fetchColumn() !== 1) {
                    throw new RuntimeException('La reconciliación requiere autocommit.');
                }
            }

            if (is_dir($namespace)) {
                $etapa = 'filesystem';
                foreach (new DirectoryIterator($namespace) as $entrada) {
                    $etapa = 'filesystem';
                    if ($entrada->isDot()) {
                        continue;
                    }
                    $conteos['examinados']++;
                    $nombre = $entrada->getFilename();
                    if (! Str::isUuid($nombre)) {
                        $conteos['ignorados_nombre_invalido']++;

                        continue;
                    }
                    $ruta = $directorio.'/'.$nombre;
                    $estado = $this->clasificar($raiz, $namespace, $nombre, $limite);
                    if ($estado !== 'antiguo') {
                        $conteos[$estado]++;

                        continue;
                    }

                    $etapa = 'coordinacion';
                    $bloqueo = $this->coordinador->intentarAdquirir($ruta);
                    if ($bloqueo === null) {
                        $conteos['en_uso_lock']++;

                        continue;
                    }
                    try {
                        $etapa = 'filesystem';
                        $estado = $this->clasificar($raiz, $namespace, $nombre, $limite);
                        if ($estado !== 'antiguo') {
                            $conteos[$estado]++;

                            continue;
                        }
                        $etapa = 'db';
                        if ($conexion->getPdo() !== $pdo || $pdo->inTransaction()) {
                            throw new RuntimeException('La sesión de reconciliación cambió.');
                        }
                        if ($this->referenciado($pdo, $ruta)) {
                            $conteos['referenciados']++;

                            continue;
                        }
                        $conteos['candidatos']++;
                        if ($eliminar) {
                            $etapa = 'eliminacion';
                            if (! $disco->delete($ruta)) {
                                throw new RuntimeException('No se confirmó la eliminación.');
                            }
                            $conteos['eliminados']++;
                        }
                    } catch (Throwable $error) {
                        $errores[] = $etapa;
                        throw $error;
                    } finally {
                        try {
                            $bloqueo->liberar();
                        } catch (Throwable) {
                            $errores[] = 'liberacion';
                            // No continuar eliminando si el estado de coordinación es incierto.
                            throw new RuntimeException('No se confirmó la liberación documental.');
                        }
                    }
                }
            }
        } catch (Throwable) {
            if ($errores === []) {
                $errores[] = $etapa;
            }
        }

        return new ResultadoReconciliacionDocumental(! $eliminar, $conteos, array_values(array_unique($errores)));
    }

    private function referenciado(PDO $pdo, string $ruta): bool
    {
        $query = DocumentoClinico::query()->select('id')->where('ruta_storage', $ruta)->limit(1);
        // PDO capturado: sin réplica ni reconexión/retry que pueda perder la coordinación.
        $consulta = $pdo->prepare($query->toSql());
        if ($consulta === false || ! $consulta->execute($query->getBindings())) {
            throw new RuntimeException('No se pudo comprobar la referencia documental.');
        }
        $id = $consulta->fetchColumn();
        if ($consulta->errorCode() !== '00000') {
            throw new RuntimeException('Lectura documental no confiable.');
        }

        return $id !== false;
    }

    private function clasificar(string $raiz, string $namespace, string $nombre, int $limite): string
    {
        $this->verificarDirectorio($raiz, $namespace);
        $archivo = $namespace.'/'.$nombre;
        clearstatcache(true, $archivo);
        if (is_link($archivo)) {
            return 'inseguros';
        }
        $stat = @lstat($archivo);
        if ($stat === false) {
            return 'desaparecidos';
        }
        if (($stat['mode'] & 0170000) !== 0100000) {
            return 'inseguros';
        }
        $real = realpath($archivo);
        if ($real === false || $this->normalizar(dirname($real)) !== $this->normalizar(realpath($namespace))) {
            return 'inseguros';
        }

        return $stat['mtime'] <= $limite ? 'antiguo' : 'recientes';
    }

    private function verificarDirectorio(string $raiz, string $namespace): void
    {
        // Revisar cada componente ANTES de realpath/iteración; nunca recorrer enlaces.
        if (preg_match('~(?:^|[/\\\\])\.\.?([/\\\\]|$)~', $raiz)
            || ! preg_match('~\A(?:[A-Za-z]:[/\\\\]|/)~', $raiz)) {
            throw new RuntimeException('Root documental inseguro.');
        }
        for ($ruta = $namespace; ; $ruta = dirname($ruta)) {
            clearstatcache(true, $ruta);
            if (is_link($ruta)) {
                throw new RuntimeException('El directorio documental contiene un enlace.');
            }
            if (dirname($ruta) === $ruta) {
                break;
            }
        }
        if (file_exists($namespace) && ! is_dir($namespace)) {
            throw new RuntimeException('Namespace documental no es un directorio.');
        }
        if (is_dir($namespace)) {
            $realRaiz = realpath($raiz);
            $realNamespace = realpath($namespace);
            if ($realRaiz === false || $realNamespace === false
                || ! str_starts_with($this->normalizar($realNamespace), $this->normalizar($realRaiz).'/')) {
                throw new RuntimeException('Namespace fuera del root documental.');
            }
        }
    }

    private function normalizar(string $ruta): string
    {
        $ruta = str_replace('\\', '/', $ruta);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($ruta) : $ruta;
    }
}
