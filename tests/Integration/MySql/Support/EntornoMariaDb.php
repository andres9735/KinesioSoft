<?php

namespace Tests\Integration\MySql\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use RuntimeException;

final class EntornoMariaDb
{
    public const DATABASE = 'kinesiosoft_uc07_test';

    /** No abre conexiones. La autorización debe recibirse antes de habilitar este marcador. */
    public static function validarDestino(string $entorno, string $conexion, array $config, string $autorizada): void
    {
        $database = $config['database'] ?? '';
        if ($entorno !== 'testing' || $conexion !== 'mysql'
            || ($config['driver'] ?? null) !== 'mysql'
            || $database !== self::DATABASE
            || $database === 'kinesiosoft_db' || $database !== $autorizada
            || ! empty($config['url']) || ! empty($config['unix_socket'])
            || ! empty($config['read']) || ! empty($config['write'])
            || ($config['host'] ?? null) !== '127.0.0.1' || (string) ($config['port'] ?? '') !== '3306'
            || ($config['username'] ?? null) !== 'root'
            || ($config['engine'] ?? null) !== 'InnoDB') {
            throw new RuntimeException('AUTORIZACIÓN MARIADB REQUERIDA: destino exclusivo de tests no autorizado o inseguro.');
        }
    }

    public static function aplicacion(): Application
    {
        $raiz = dirname(__DIR__, 4);
        if (is_file($raiz.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('El harness requiere configuración sin cache para verificar su destino.');
        }
        $database = getenv('UC07_MYSQL_DATABASE') ?: self::DATABASE;
        $host = getenv('UC07_MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('UC07_MYSQL_PORT') ?: '3306';
        $usuario = getenv('UC07_MYSQL_USERNAME');
        $autorizada = getenv('UC07_MYSQL_AUTORIZADO') ?: '';
        if ($usuario === false || $usuario === '') {
            throw new RuntimeException('Debe indicar UC07_MYSQL_USERNAME explícitamente.');
        }
        $config = ['driver' => 'mysql', 'host' => $host, 'port' => $port, 'username' => $usuario, 'database' => $database, 'engine' => 'InnoDB'];
        self::validarDestino(getenv('APP_ENV') ?: '', getenv('DB_CONNECTION') ?: '', $config, $autorizada);

        // Antes del bootstrap: .env no puede seleccionar la DB/credenciales de desarrollo.
        foreach ([
            'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_HOST' => $host, 'DB_PORT' => $port,
            'DB_USERNAME' => $usuario, 'DB_PASSWORD' => getenv('UC07_MYSQL_PASSWORD') ?: '',
            'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null',
            'APP_MAINTENANCE_DRIVER' => 'file',
        ] as $clave => $valor) {
            putenv($clave.'='.$valor);
            $_ENV[$clave] = $_SERVER[$clave] = $valor;
        }
        $app = require $raiz.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('database.connections.mysql.engine', 'InnoDB');
        self::validarDestino($app->environment(), $app['config']->get('database.default'), $app['config']->get('database.connections.mysql'), $autorizada);

        return $app;
    }

    /** Sólo SELECT. Debe preceder a toda preparación destructiva. */
    public static function verificar(Connection $conexion): array
    {
        self::validarDestino(app()->environment(), config('database.default'), $conexion->getConfig(), getenv('UC07_MYSQL_AUTORIZADO') ?: '');
        $datos = (array) $conexion->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@tx_isolation AS aislamiento, @@autocommit AS autocommit, @@sql_mode AS sql_mode, @@default_storage_engine AS engine, CONNECTION_ID() AS sesion, @@port AS puerto, CURRENT_USER() AS usuario');
        $tablas = $conexion->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
        self::validarServidor($conexion->getDatabaseName(), $datos, $tablas);

        return $datos;
    }

    public static function validarServidor(string $database, array $datos, array $tablas): void
    {
        if ($database !== self::DATABASE || ($datos['db'] ?? null) !== self::DATABASE
            || (int) ($datos['puerto'] ?? 0) !== 3306
            || explode('@', $datos['usuario'] ?? '')[0] !== 'root'
            || ! str_contains($datos['version'] ?? '', 'MariaDB')
            || ($datos['aislamiento'] ?? null) !== 'REPEATABLE-READ' || (int) ($datos['autocommit'] ?? 0) !== 1
            || strcasecmp($datos['engine'] ?? '', 'InnoDB') !== 0) {
            throw new RuntimeException('El servidor real no coincide con el contrato MariaDB/InnoDB/REPEATABLE-READ del harness.');
        }
        foreach ($tablas as $tabla) {
            if (strcasecmp($tabla->ENGINE ?? '', 'InnoDB') !== 0) {
                throw new RuntimeException('La base de tests contiene tablas que no son InnoDB; no se modificará.');
            }
        }

    }

    public static function independiente(string $nombre): Connection
    {
        if (! preg_match('/\Auc07_[a-z]+\z/', $nombre)) {
            throw new RuntimeException('Nombre de conexión auxiliar inválido.');
        }
        config(['database.connections.'.$nombre => config('database.connections.mysql')]);
        $conexion = app('db')->connection($nombre);
        self::verificar($conexion);

        return $conexion;
    }

    public static function configurarStorage(string $escenario): void
    {
        if (! preg_match('/\A[a-f0-9]{32}\z/', $escenario)) {
            throw new RuntimeException('Namespace de escenario inválido.');
        }
        config([
            'filesystems.disks.documentos_clinicos.root' => storage_path('framework/testing/uc07-mysql/'.$escenario.'/documentos'),
            'documentos_clinicos.lock_timeout_segundos' => 0,
        ]);
    }
}
