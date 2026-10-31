<?php

namespace Tests\Integration\Uc06MariaDb\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use RuntimeException;

final class EntornoUc06MariaDb
{
    public const DATABASE = 'kinesiosoft_uc06_613_test';

    public const MARCADOR = 'AUTORIZADA_6_13';

    public const TABLAS_REQUERIDAS = [
        'migrations', 'users', 'roles', 'permissions', 'model_has_roles',
        'model_has_permissions', 'role_has_permissions', 'provincias', 'localidades',
        'titulos_profesionales', 'pacientes', 'declaraciones_salud_paciente',
        'kinesiologas', 'turnos', 'auditorias', 'intenciones_evaluacion_cupo',
    ];

    /** Guarda pura, comprobable sin bootstrap, credenciales ni conexiones. */
    public static function validarAutorizacion(array $perfil): void
    {
        foreach (['UC06_MYSQL_AUTORIZADO', 'UC06_MYSQL_DESCARTABLE', 'UC06_MYSQL_DATABASE'] as $clave) {
            if (($perfil[$clave] ?? null) !== self::DATABASE) {
                throw new RuntimeException('UC06: falta autorización expresa y destino descartable aislado.');
            }
        }
        if (($perfil['UC06_MYSQL_ETAPA_B'] ?? null) !== self::MARCADOR
            || ($perfil['APP_ENV'] ?? null) !== 'testing' || ($perfil['DB_CONNECTION'] ?? null) !== 'mysql') {
            throw new RuntimeException('UC06: ejecución MariaDB no habilitada para la etapa B.');
        }
    }

    public static function perfil(): array
    {
        $perfil = [];
        foreach (['UC06_MYSQL_ETAPA_B', 'UC06_MYSQL_AUTORIZADO', 'UC06_MYSQL_DESCARTABLE', 'UC06_MYSQL_DATABASE', 'APP_ENV', 'DB_CONNECTION'] as $clave) {
            $perfil[$clave] = getenv($clave);
        }

        return $perfil;
    }

    public static function aplicacion(): Application
    {
        self::validarAutorizacion(self::perfil());
        $raiz = dirname(__DIR__, 4);
        if (is_file($raiz.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC06: se requiere configuración sin caché.');
        }
        // Sin fallback a perfiles UC07 ni a las credenciales de desarrollo.
        $config = [
            'driver' => 'mysql', 'host' => getenv('UC06_MYSQL_HOST'),
            'port' => getenv('UC06_MYSQL_PORT'), 'database' => getenv('UC06_MYSQL_DATABASE'),
            'username' => getenv('UC06_MYSQL_USERNAME'),
            'password' => getenv('UC06_MYSQL_PASSWORD') === false ? '' : getenv('UC06_MYSQL_PASSWORD'),
            'engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'options' => [],
            'url' => '', 'unix_socket' => '',
        ];
        self::validarDestino('testing', 'mysql', $config);
        foreach ([
            'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => self::DATABASE,
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null',
            'APP_MAINTENANCE_DRIVER' => 'file',
        ] as $clave => $valor) {
            putenv($clave.'='.$valor);
            $_ENV[$clave] = $_SERVER[$clave] = $valor;
        }
        $app = require $raiz.'/bootstrap/app.php';
        // Antes de registrar/arrancar providers: sólo existe la conexión autorizada.
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) use ($config): void {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections', ['mysql' => $config]);
            $app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $evento) use ($app): void {
                try {
                    self::validarDestino($app->environment(), $app['config']->get('database.default'), $evento->connection->getConfig());
                } catch (RuntimeException $error) {
                    // DatabaseManager ya la cacheó antes del evento; no dejar un bypass en el segundo intento.
                    $app['db']->purge($evento->connection->getName());
                    throw $error;
                }
            });
        });
        $app->make(Kernel::class)->bootstrap();
        self::validarDestino($app->environment(), $app['config']->get('database.default'), $app['config']->get('database.connections.mysql'));

        return $app;
    }

    /** Pura: ningún destino alternativo, réplica o socket puede superar esta guarda. */
    public static function validarDestino(string $entorno, string $conexion, array $config): void
    {
        if ($entorno !== 'testing' || $conexion !== 'mysql'
            || ($config['driver'] ?? null) !== 'mysql' || ($config['database'] ?? null) !== self::DATABASE
            || ($config['host'] ?? null) !== '127.0.0.1' || (string) ($config['port'] ?? '') !== '3306'
            || ($config['username'] ?? null) !== 'root' || ($config['engine'] ?? null) !== 'InnoDB'
            || ! empty($config['url']) || ! empty($config['unix_socket'])
            || ! empty($config['read']) || ! empty($config['write'])) {
            throw new RuntimeException('UC06: destino exclusivo no autorizado o conexión alternativa insegura.');
        }
    }

    public static function validarServidor(string $database, array $datos, array $tablas): void
    {
        if ($database !== self::DATABASE || ($datos['db'] ?? null) !== self::DATABASE
            || (int) ($datos['puerto'] ?? 0) !== 3306 || explode('@', $datos['usuario'] ?? '')[0] !== 'root'
            || ! str_contains($datos['version'] ?? '', 'MariaDB')
            || ($datos['aislamiento'] ?? null) !== 'REPEATABLE-READ' || (int) ($datos['autocommit'] ?? 0) !== 1
            || strcasecmp($datos['engine'] ?? '', 'InnoDB') !== 0 || (int) ($datos['fk_activas'] ?? 0) !== 1) {
            throw new RuntimeException('UC06: el servidor real no coincide con el perfil aislado MariaDB/InnoDB.');
        }
        foreach ($tablas as $tabla) {
            if (strcasecmp($tabla->ENGINE ?? '', 'InnoDB') !== 0) {
                throw new RuntimeException('UC06: existen tablas que no son InnoDB.');
            }
        }
    }

    /** Sólo SELECT; también sirve antes de preparar el esquema de una base nueva. */
    public static function verificarServidor(Connection $conexion): array
    {
        self::validarAutorizacion(self::perfil());
        self::validarDestino(app()->environment(), config('database.default'), $conexion->getConfig());
        $datos = (array) $conexion->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@tx_isolation AS aislamiento, @@autocommit AS autocommit, @@sql_mode AS sql_mode, @@default_storage_engine AS engine, CONNECTION_ID() AS sesion, @@port AS puerto, CURRENT_USER() AS usuario, @@foreign_key_checks AS fk_activas');
        $tablas = $conexion->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
        self::validarServidor($conexion->getDatabaseName(), $datos, $tablas);

        return $datos;
    }

    public static function independiente(string $nombre): Connection
    {
        if (! preg_match('/\Auc06_[a-z]+\z/', $nombre)) {
            throw new RuntimeException('UC06: nombre de conexión auxiliar inválido.');
        }
        self::validarAutorizacion(self::perfil());
        $config = config('database.connections.mysql');
        self::validarDestino(app()->environment(), config('database.default'), $config);
        config(['database.connections.'.$nombre => $config]);
        $conexion = app('db')->connection($nombre);
        self::verificarServidor($conexion);

        return $conexion;
    }

    /** Sólo SELECT; esquema preparado manualmente en una autorización posterior. */
    public static function verificar(Connection $conexion, bool $exigirVacia = false, array $sesionesPermitidas = []): array
    {
        self::validarAutorizacion(self::perfil());
        $datos = self::verificarServidor($conexion);
        foreach (self::TABLAS_REQUERIDAS as $tabla) {
            if (! $conexion->getSchemaBuilder()->hasTable($tabla)) {
                throw new RuntimeException('UC06: esquema incompleto; no se ejecutarán migraciones automáticas.');
            }
        }
        $indices = $conexion->select("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intenciones_evaluacion_cupo' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
        $columnas = [];
        foreach ($indices as $indice) {
            if ((int) $indice->NON_UNIQUE === 0) {
                $columnas[$indice->INDEX_NAME][] = $indice->COLUMN_NAME;
            }
        }
        if (! in_array(['auditoria_cancelacion_id'], $columnas, true)) {
            throw new RuntimeException('UC06: falta UNIQUE exclusivo de la auditoría de cancelación.');
        }
        $relaciones = $conexion->select("SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='intenciones_evaluacion_cupo' AND k.REFERENCED_TABLE_NAME IS NOT NULL");
        $esperadas = ['auditoria_cancelacion_id' => 'auditorias', 'turno_id' => 'turnos', 'kinesiologa_id' => 'kinesiologas'];
        foreach ($relaciones as $relacion) {
            if (($esperadas[$relacion->COLUMN_NAME] ?? null) === $relacion->REFERENCED_TABLE_NAME
                && $relacion->REFERENCED_TABLE_SCHEMA === self::DATABASE && $relacion->REFERENCED_COLUMN_NAME === 'id'
                && $relacion->UPDATE_RULE === 'RESTRICT' && $relacion->DELETE_RULE === 'RESTRICT') {
                unset($esperadas[$relacion->COLUMN_NAME]);
            }
        }
        if ($esperadas !== []) {
            throw new RuntimeException('UC06: faltan claves foráneas de la intención durable.');
        }
        if ($exigirVacia) {
            $permitidas = [(int) $datos['sesion'], ...array_map('intval', $sesionesPermitidas)];
            if ($conexion->table('information_schema.PROCESSLIST')->where('DB', self::DATABASE)->whereNotIn('ID', $permitidas)->exists()) {
                throw new RuntimeException('UC06: hay conexiones ajenas a la ejecución sobre la base exclusiva.');
            }
            foreach ($conexion->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") as $tabla) {
                if ($tabla->TABLE_NAME !== 'migrations' && $conexion->table($tabla->TABLE_NAME)->exists()) {
                    throw new RuntimeException('UC06: se exige esquema vacío salvo migrations; no se borrarán datos existentes.');
                }
            }
        }

        return $datos;
    }
}
