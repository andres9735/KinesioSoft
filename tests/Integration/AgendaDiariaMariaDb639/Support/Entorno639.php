<?php

namespace Tests\Integration\AgendaDiariaMariaDb639\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Monolog\Handler\NullHandler;
use PDO;
use RuntimeException;
use Throwable;

final class Entorno639
{
    private static int $lecturasIdentidad = 0;

    public static function lecturasIdentidad(): int
    {
        return self::$lecturasIdentidad;
    }

    public static function perfil(string $modo = 'pruebas'): array
    {
        $p = [];
        foreach (['AUTORIZACION', 'DATABASE', 'HOST', 'PORT', 'DATADIR', 'USERNAME', 'PASSWORD', 'RUN', 'APP_KEY', 'TIMEZONE'] as $clave) {
            $p[$clave] = getenv('A639_'.$clave);
        }
        Guardas639::perfil($p, $modo);

        return $p;
    }

    /** Rutas puras: las cachés relativas evitan el prefijo doble C:/... en Laravel sobre Windows. */
    public static function rutas(string $run, int $pid): array
    {
        if (! preg_match('/\A[a-f0-9]{32}\z/', $run) || $pid < 1) {
            throw new RuntimeException('639: identidad de rutas no autorizada.');
        }
        $relativa = 'storage/framework/testing/agenda-mariadb-639/'.$run.'/'.$pid;
        $cache = [];
        foreach (['CONFIG' => 'config', 'SERVICES' => 'services', 'PACKAGES' => 'packages', 'ROUTES' => 'routes', 'EVENTS' => 'events'] as $clave => $archivo) {
            $cache['APP_'.$clave.'_CACHE'] = $relativa.'/bootstrap/'.$archivo.'.php';
        }

        return ['storage' => str_replace('\\', '/', dirname(__DIR__, 4)).'/'.$relativa, 'cache' => $cache];
    }

    /** Sin bootstrap ni E/S: comprobar la resolución real de Laravel antes de generar cachés. */
    public static function verificarRutas(Application $app, string $storage): void
    {
        $normalizar = static fn (string $ruta): string => strtolower(str_replace('\\', '/', $ruta));
        if ($normalizar($app->storagePath()) !== $normalizar($storage)) {
            throw new RuntimeException('639: storage fuera del ensayo.');
        }
        foreach (['Config', 'Services', 'Packages', 'Routes', 'Events'] as $tipo) {
            $ruta = $app->{'getCached'.$tipo.'Path'}();
            if ($normalizar($ruta) !== $normalizar($storage.'/bootstrap/'.strtolower($tipo).'.php')) {
                throw new RuntimeException('639: caché fuera del ensayo.');
            }
        }
    }

    public static function aplicacion(string $modo = 'pruebas'): Application
    {
        $p = self::perfil($modo); // Antes de bootstrap, mkdir o apertura de PDO.
        if (PDO::getAvailableDrivers() !== ['mysql'] || ! extension_loaded('pdo_mysql')) {
            throw new RuntimeException('639: se requiere el proceso PHP exclusivo PDO MySQL.');
        }
        ini_set('zend.exception_ignore_args', '1');
        if (ini_get('zend.exception_ignore_args') !== '1') {
            throw new RuntimeException('639: no se pudo proteger la representación de excepciones.');
        }
        $raiz = dirname(__DIR__, 4);
        $rutas = self::rutas($p['RUN'], getmypid());
        $storage = $rutas['storage'];
        // Nunca cargar .env/.env.testing ni reutilizar cachés de la aplicación ordinaria.
        foreach (array_keys(getenv()) as $clave) {
            if (preg_match('/\A(APP_|DB_|DATABASE_|MYSQL|MARIADB|UC06_|UC07_|MAIL_|QUEUE_|CACHE_|SESSION_|REDIS_|AWS_|LARAVEL_|VIEW_|LOG_)/', $clave)) {
                putenv($clave);
                unset($_ENV[$clave], $_SERVER[$clave]);
            }
        }
        foreach (['APP_ENV' => 'testing', 'APP_KEY' => $p['APP_KEY'], 'LARAVEL_STORAGE_PATH' => $storage] + $rutas['cache'] as $clave => $valor) {
            putenv($clave.'='.$valor);
            $_ENV[$clave] = $_SERVER[$clave] = $valor;
        }
        foreach (['bootstrap', 'framework/views', 'framework/cache', 'app/private', 'app/public', 'logs'] as $dir) {
            if (! is_dir($storage.'/'.$dir) && ! mkdir($storage.'/'.$dir, 0700, true) && ! is_dir($storage.'/'.$dir)) {
                throw new RuntimeException('639: storage aislado no disponible.');
            }
        }
        if (is_file($storage.'/bootstrap/config.php') || is_file($storage.'/bootstrap/routes.php')
            || is_dir($storage.'/sin-env')) {
            throw new RuntimeException('639: no se admite configuración o entorno persistidos.');
        }
        $config = ['driver' => 'mysql', 'host' => $p['HOST'], 'port' => $p['PORT'], 'database' => $p['DATABASE'],
            'username' => $p['USERNAME'], 'password' => $p['PASSWORD'], 'url' => '', 'unix_socket' => '',
            'engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'options' => []];
        Guardas639::conexion($config, $modo);
        $app = require $raiz.'/bootstrap/app.php';
        $app->useStoragePath($storage);
        self::verificarRutas($app, $storage);
        $app->useEnvironmentPath($storage.'/sin-env');
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) use ($config, $p, $storage, $modo): void {
            $app['config']->set([
                'database.default' => 'mysql', 'database.connections' => ['mysql' => $config],
                'app.url' => 'https://consultorio.example.test', 'app.timezone' => $p['TIMEZONE'], 'app.debug' => false,
                'mail.default' => 'array', 'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
                'filesystems.default' => 'local', 'filesystems.disks.local.root' => $storage.'/app/private',
                'filesystems.disks.public.root' => $storage.'/app/public',
                'filesystems.disks.documentos_clinicos.root' => $storage.'/app/private/documentos',
                'view.compiled' => $storage.'/framework/views', 'logging.default' => 'null',
                'logging.deprecations.channel' => 'null', 'logging.channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]],
            ]);
            $app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $e) use ($app, $modo): void {
                try {
                    self::verificar($e->connection, $modo, true);
                    $e->connection->beforeExecuting(function (string $sql) use ($e, $modo): void {
                        Guardas639::sentencia($sql, $modo);
                        self::verificar($e->connection, $modo);
                    });
                } catch (Throwable) {
                    $app['db']->purge($e->connection->getName());
                    throw new RuntimeException('639: conexión rechazada sin efectos de prueba.');
                }
            });
        });
        $app->make(Kernel::class)->bootstrap();
        if (! $app->environment('testing') || $app->configurationIsCached() || $app->routesAreCached()
            || ! $app['log']->channel()->getLogger()->getHandlers()[0] instanceof NullHandler) {
            throw new RuntimeException('639: bootstrap no aislado.');
        }
        self::verificar($app['db']->connection(), $modo, true);
        if ($modo === 'pruebas') {
            self::origen($app['db']->connection());
        }

        return $app;
    }

    public static function verificar(Connection $c, string $modo = 'pruebas', bool $privilegios = false): array
    {
        try {
            Guardas639::conexion($c->getConfig(), $modo);
            $pdo = $c->getPdo();
            // PDO directo: no recursión del listener; también detecta USE o reconexión silenciosa.
            self::$lecturasIdentidad++;
            $s = $pdo->query('SELECT DATABASE() AS db, CURRENT_USER() AS usuario, VERSION() AS version, @@port AS puerto, @@datadir AS datadir, @@tx_isolation AS aislamiento, @@foreign_key_checks AS fk, @@autocommit AS autocommit, @@sql_mode AS sql_mode, @@default_storage_engine AS engine, CONNECTION_ID() AS sesion')->fetch(PDO::FETCH_ASSOC);
            Guardas639::servidor($s, $modo);
            if ($privilegios) {
                Guardas639::privilegios($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN), $modo);
                if ($modo === 'pruebas') {
                    // Antes de cualquier operación de providers, consultas auxiliares o fixtures.
                    $run = $pdo->query('SELECT run FROM certificacion_agenda_639 WHERE id=1')->fetchColumn();
                    Guardas639::origen(is_string($run) ? $run : null, self::perfil()['RUN']);
                }
            }

            return $s;
        } catch (Throwable) {
            throw new RuntimeException('639: identidad o permisos de conexión no autorizados.');
        }
    }

    public static function origen(Connection $c): void
    {
        try {
            self::verificar($c);
            $run = $c->table('certificacion_agenda_639')->where('id', 1)->value('run');
            Guardas639::origen($run, self::perfil()['RUN']);
        } catch (Throwable) {
            throw new RuntimeException('639: falta procedencia de esta ejecución; no se prepararán fixtures.');
        }
    }

    public static function independiente(string $nombre): Connection
    {
        if (! preg_match('/\Aa639_[a-z]+\z/', $nombre)) {
            throw new RuntimeException('639: conexión auxiliar no autorizada.');
        }
        config(['database.connections.'.$nombre => config('database.connections.mysql')]);
        $c = app('db')->connection($nombre);
        self::verificar($c, privilegios: true);
        self::origen($c);

        return $c;
    }
}
