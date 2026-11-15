<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Entorno639;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Guardas639 as G;

/** Sin bootstrap de Laravel, conexiones, archivos de entorno ni servidor; PDO simulado. */
class GuardasAgendaDiariaMariaDb639Test extends TestCase
{
    private function perfil(string $modo = 'pruebas'): array
    {
        return [
            'AUTORIZACION' => 'AUTORIZADA_639_'.$modo,
            'DATABASE' => G::DATABASE,
            'HOST' => '127.0.0.1',
            'PORT' => '3308',
            'DATADIR' => G::datadirEsperado(),
            'USERNAME' => G::CUENTAS[$modo],
            'PASSWORD' => 'SECRETO-SIMULADO-NO-REAL',
            'RUN' => str_repeat('a', 32),
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'TIMEZONE' => 'America/Argentina/Buenos_Aires',
        ];
    }

    private function conexion(string $modo = 'pruebas'): array
    {
        return [
            'driver' => 'mysql',
            'database' => G::DATABASE,
            'host' => '127.0.0.1',
            'port' => 3308,
            'username' => G::CUENTAS[$modo],
            'engine' => 'InnoDB',
        ];
    }

    private function servidor(string $modo = 'pruebas'): array
    {
        return [
            'db' => G::DATABASE,
            'usuario' => G::CUENTAS[$modo].'@localhost',
            'version' => '10.11.19-MariaDB',
            'puerto' => 3308,
            'datadir' => G::datadirEsperado(),
            'aislamiento' => 'REPEATABLE-READ',
            'fk' => 1,
            'engine' => 'InnoDB',
            'autocommit' => 1,
            'sql_mode' => 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES',
        ];
    }

    #[DataProvider('modos')]
    public function test_profiles_are_explicit_and_role_specific_without_connections(string $modo): void
    {
        G::perfil($this->perfil($modo), $modo);
        G::conexion($this->conexion($modo), $modo);
        G::servidor($this->servidor($modo), $modo);
        G::origen(str_repeat('a', 32), str_repeat('a', 32));
        $this->addToAssertionCount(4);
    }

    public static function modos(): array
    {
        return [['pruebas'], ['migraciones']];
    }

    #[DataProvider('puertoProtegido')]
    public function test_3307_is_rejected_at_every_layer_for_both_roles(string $modo, string $capa): void
    {
        $this->expectException(RuntimeException::class);
        match ($capa) {
            'perfil' => G::perfil(array_replace($this->perfil($modo), ['PORT' => '3307']), $modo),
            'conexion' => G::conexion(array_replace($this->conexion($modo), ['port' => 3307]), $modo),
            'servidor' => G::servidor(array_replace($this->servidor($modo), ['puerto' => 3307]), $modo),
        };
    }

    public static function puertoProtegido(): array
    {
        $casos = [];
        foreach (self::modos() as [$modo]) {
            foreach (['perfil', 'conexion', 'servidor'] as $capa) {
                $casos[$modo.'-'.$capa] = [$modo, $capa];
            }
        }

        return $casos;
    }

    #[DataProvider('modos')]
    public function test_windows_datadir_formats_identify_the_same_exclusive_directory(string $modo): void
    {
        foreach ([G::datadirEsperado(), strtoupper(str_replace('/', '\\', G::datadirEsperado())).'\\', strtolower(G::datadirEsperado()).'/', str_replace('/', '//', G::datadirEsperado())] as $ruta) {
            G::perfil(array_replace($this->perfil($modo), ['DATADIR' => $ruta]), $modo);
            G::servidor(array_replace($this->servidor($modo), ['datadir' => $ruta]), $modo);
            $this->addToAssertionCount(2);
        }
    }

    #[DataProvider('directoriosInvalidos')]
    public function test_wrong_or_missing_datadir_fails_closed(string $modo, string $capa, mixed $ruta): void
    {
        $this->expectException(RuntimeException::class);
        if ($capa === 'perfil') {
            $p = $this->perfil($modo);
            if ($ruta === null) {
                unset($p['DATADIR']);
            } else {
                $p['DATADIR'] = $ruta;
            }
            G::perfil($p, $modo);
        } else {
            $s = $this->servidor($modo);
            if ($ruta === null) {
                unset($s['datadir']);
            } else {
                $s['datadir'] = $ruta;
            }
            G::servidor($s, $modo);
        }
    }

    public static function directoriosInvalidos(): array
    {
        $casos = [];
        $rutas = [null, '', [], 'C:/MariaDB-KinesioSoft/data', G::datadirEsperado().'-otra', G::datadirEsperado().'/subdirectorio', G::datadirEsperado().'/../data', G::datadirEsperado().' ', 'storage/framework/testing/agenda-mariadb-639-instancia/data', G::datadirEsperado()."\0"];
        foreach (self::modos() as [$modo]) {
            foreach (['perfil', 'servidor'] as $capa) {
                foreach ($rutas as $i => $ruta) {
                    $casos[$modo.'-'.$capa.'-'.$i] = [$modo, $capa, $ruta];
                }
            }
        }

        return $casos;
    }

    #[DataProvider('modos')]
    public function test_shared_connection_verifier_reads_datadir_before_allowing_operations(string $modo): void
    {
        $identidad = $this->servidor($modo);
        unset($identidad['datadir']);
        $fila = $this->createMock(PDOStatement::class);
        $fila->expects($this->once())->method('fetch')->willReturn($identidad);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('query')->with($this->callback(
            fn (string $sql): bool => str_starts_with($sql, 'SELECT ') && str_contains($sql, '@@datadir AS datadir')
        ))->willReturn($fila);
        $conexion = $this->createMock(Connection::class);
        $conexion->method('getConfig')->willReturn($this->conexion($modo));
        $conexion->expects($this->once())->method('getPdo')->willReturn($pdo);
        $this->expectException(RuntimeException::class);
        Entorno639::verificar($conexion, $modo, true);
    }

    #[DataProvider('identidadesRutas')]
    public function test_laravel_resolves_all_caches_inside_the_selected_run_and_pid(string $run, int $pid): void
    {
        $rutas = Entorno639::rutas($run, $pid);
        $esperada = str_replace('\\', '/', dirname(__DIR__, 2)).'/storage/framework/testing/agenda-mariadb-639/'.$run.'/'.$pid;
        $this->assertSame($esperada, $rutas['storage']);
        $this->conRutas($rutas, function (Application $app) use ($rutas, $esperada): void {
            Entorno639::verificarRutas($app, $rutas['storage']);
            foreach (['Config', 'Services', 'Packages', 'Routes', 'Events'] as $tipo) {
                $this->assertSame($esperada.'/bootstrap/'.strtolower($tipo).'.php', str_replace('\\', '/', $app->{'getCached'.$tipo.'Path'}()));
            }
        });
    }

    public static function identidadesRutas(): array
    {
        return [[str_repeat('a', 32), 123], [str_repeat('a', 32), 456], [str_repeat('b', 32), 123]];
    }

    #[DataProvider('rutasAjenas')]
    public function test_ordinary_foreign_run_foreign_pid_and_absolute_windows_caches_are_rejected(string $clave, string $tipo): void
    {
        $rutas = Entorno639::rutas(str_repeat('a', 32), 123);
        $rutas['cache'][$clave] = match ($tipo) {
            'ordinaria' => 'bootstrap/cache/config.php',
            'otro-run' => str_replace(str_repeat('a', 32), str_repeat('b', 32), $rutas['cache'][$clave]),
            'otro-pid' => str_replace('/123/', '/456/', $rutas['cache'][$clave]),
            'absoluta-windows' => str_replace('\\', '/', dirname(__DIR__, 2)).'/'.$rutas['cache'][$clave],
        };
        $this->expectException(RuntimeException::class);
        $this->conRutas($rutas, fn (Application $app) => Entorno639::verificarRutas($app, $rutas['storage']));
    }

    public static function rutasAjenas(): array
    {
        $casos = [];
        foreach (['CONFIG', 'SERVICES', 'PACKAGES', 'ROUTES', 'EVENTS'] as $clave) {
            foreach (['ordinaria', 'otro-run', 'otro-pid', 'absoluta-windows'] as $tipo) {
                $casos[$clave.'-'.$tipo] = ['APP_'.$clave.'_CACHE', $tipo];
            }
        }

        return $casos;
    }

    private function conRutas(array $rutas, callable $probar): void
    {
        $contenedor = Container::getInstance();
        $anteriores = [];
        try {
            foreach ($rutas['cache'] as $clave => $valor) {
                $anteriores[$clave] = [getenv($clave), array_key_exists($clave, $_ENV), $_ENV[$clave] ?? null, array_key_exists($clave, $_SERVER), $_SERVER[$clave] ?? null];
                putenv($clave.'='.$valor);
                $_ENV[$clave] = $_SERVER[$clave] = $valor;
            }
            $app = new Application(dirname(__DIR__, 2)); // Sólo resolución de rutas; ningún Kernel/provider.
            $app->useStoragePath($rutas['storage']);
            $probar($app);
        } finally {
            foreach ($anteriores as $clave => [$env, $tieneEnv, $valorEnv, $tieneServer, $valorServer]) {
                $env === false ? putenv($clave) : putenv($clave.'='.$env);
                if ($tieneEnv) {
                    $_ENV[$clave] = $valorEnv;
                } else {
                    unset($_ENV[$clave]);
                }
                if ($tieneServer) {
                    $_SERVER[$clave] = $valorServer;
                } else {
                    unset($_SERVER[$clave]);
                }
            }
            Container::setInstance($contenedor);
        }
    }

    #[DataProvider('variables')]
    public function test_every_missing_variable_fails_closed(string $clave): void
    {
        $p = $this->perfil();
        unset($p[$clave]);
        $this->expectException(RuntimeException::class);
        G::perfil($p, 'pruebas');
    }

    public static function variables(): array
    {
        return array_map(fn ($c) => [$c], ['AUTORIZACION', 'DATABASE', 'HOST', 'PORT', 'DATADIR', 'USERNAME', 'PASSWORD', 'RUN', 'APP_KEY', 'TIMEZONE']);
    }

    #[DataProvider('destinosRechazados')]
    public function test_every_other_database_is_protected(string $base): void
    {
        $this->expectException(RuntimeException::class);
        G::perfil(array_replace($this->perfil(), ['DATABASE' => $base]), 'pruebas');
    }

    public static function destinosRechazados(): array
    {
        return array_map(fn ($d) => [$d], [
            'kinesiosoft',
            'kinesiosoft_db',
            'kinesiosoft_uc06_613_test',
            'kinesiosoft_uc07_test',
            'test',
            'otra_base_test',
            'mysql',
            G::DATABASE.' ',
            strtoupper(G::DATABASE),
            '',
        ]);
    }

    #[DataProvider('redirecciones')]
    public function test_primary_and_auxiliary_connections_reject_alternative_profiles(array $cambio): void
    {
        $this->expectException(RuntimeException::class);
        G::conexion(array_replace($this->conexion(), $cambio), 'pruebas');
    }

    public static function redirecciones(): array
    {
        return array_map(fn ($c) => [$c], [
            ['username' => 'root'],
            ['username' => 'agenda639_migrador'],
            ['database' => 'kinesiosoft_db'],
            ['host' => 'localhost'],
            ['port' => 3306],
            ['driver' => 'sqlite'],
            ['read' => ['host' => '127.0.0.1']],
            ['write' => ['database' => 'kinesiosoft_db']],
            ['url' => 'mysql://no-usar'],
            ['unix_socket' => '/socket'],
            ['prefix' => 'otra.'],
            ['options' => [1002 => 'USE otra']],
            ['engine' => 'MyISAM'],
        ]);
    }

    #[DataProvider('identidadesInvalidas')]
    public function test_effective_identity_is_required_even_if_configuration_looks_safe(array $cambio): void
    {
        $this->expectException(RuntimeException::class);
        G::servidor(array_replace($this->servidor(), $cambio), 'pruebas');
    }

    public static function identidadesInvalidas(): array
    {
        return array_map(fn ($c) => [$c], [
            ['db' => 'kinesiosoft_db'],
            ['usuario' => 'root@localhost'],
            ['usuario' => 'agenda639_runner@%'],
            ['version' => '8.0-MySQL'],
            ['fk' => 0],
            ['aislamiento' => 'READ-COMMITTED'],
            ['engine' => 'MyISAM'],
            ['puerto' => 3306],
            ['autocommit' => 0],
            ['sql_mode' => 'NO_ENGINE_SUBSTITUTION'],
        ]);
    }

    public function test_grants_accept_only_literal_escaped_schema_and_exact_privileges(): void
    {
        $schema = str_replace('_', '\\_', G::DATABASE);
        G::privilegios([
            'GRANT USAGE ON *.* TO `agenda639_runner`@`localhost`',
            'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$schema.'`.* TO `agenda639_runner`@`localhost`',
        ], 'pruebas');
        G::privilegios(['GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, TRIGGER ON `'.$schema.'`.* TO `agenda639_migrador`@`localhost`'], 'migraciones');
        $this->addToAssertionCount(2);
    }

    #[DataProvider('privilegiosInseguros')]
    public function test_privilege_wildcards_global_permissions_roles_and_grant_option_fail(string $grant): void
    {
        $this->expectException(RuntimeException::class);
        G::privilegios([$grant], 'pruebas');
    }

    public static function privilegiosInseguros(): array
    {
        $literal = str_replace('_', '\\_', G::DATABASE);

        return array_map(fn ($g) => [$g], [
            'GRANT ALL PRIVILEGES ON *.* TO x',
            'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.G::DATABASE.'`.* TO x',
            'GRANT SELECT ON `agenda%`.* TO x',
            'GRANT SELECT ON `kinesiosoft_db`.* TO x',
            'GRANT PROCESS ON *.* TO x',
            'GRANT `rol` TO x',
            'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$literal.'`.* TO x WITH GRANT OPTION',
            'GRANT SELECT, INSERT, UPDATE, DELETE, DROP ON `'.$literal.'`.* TO x',
            'GRANT SELECT ON `'.$literal.'`.* TO x',
        ]);
    }

    #[DataProvider('sqlInseguro')]
    public function test_runtime_does_not_allow_initialization_or_cleanup(string $sql): void
    {
        $this->expectException(RuntimeException::class);
        G::sentencia($sql, 'pruebas');
    }

    public static function sqlInseguro(): array
    {
        return array_map(fn ($s) => [$s], [
            'USE kinesiosoft_db',
            'TRUNCATE turnos',
            'DROP TABLE turnos',
            'CREATE TABLE x (id INT)',
            'ALTER TABLE turnos ADD x INT',
            'SET GLOBAL sql_mode = 0',
            'GRANT ALL ON *.* TO x',
            'SELECT x INTO OUTFILE x',
        ]);
    }

    public function test_read_and_transactional_dml_are_not_confused_with_schema_reset(): void
    {
        foreach (
            [
                'select * from `turnos` where `id` = ? for update',
                'update `turnos` set `estado` = ?',
                'insert into `auditorias` (`evento`) values (?)',
                'SET SESSION innodb_lock_wait_timeout = 5',
            ] as $sql
        ) {
            G::sentencia($sql, 'pruebas');
            $this->addToAssertionCount(1);
        }
    }

    public function test_foreign_run_cannot_be_reused_and_errors_do_not_reveal_values(): void
    {
        $error = null;
        try {
            G::origen('otra-ejecucion', str_repeat('a', 32));
        } catch (RuntimeException $e) {
            $error = $e;
        }
        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertFalse(str_contains($error->getMessage(), 'otra-ejecucion'));
        $this->assertNull($error->getPrevious());
        $error = null;
        try {
            G::perfil(array_replace($this->perfil(), ['HOST' => 'SECRETO-SIMULADO-NO-REAL']), 'pruebas');
        } catch (RuntimeException $e) {
            $error = $e;
        }
        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertFalse(str_contains($error->getMessage(), 'SECRETO-SIMULADO-NO-REAL'));
        $this->assertNull($error->getPrevious());
    }

    public function test_application_entrypoint_refuses_missing_authorization_before_bootstrap(): void
    {
        $anterior = getenv('A639_AUTORIZACION');
        putenv('A639_AUTORIZACION=');
        try {
            $this->expectException(RuntimeException::class);
            Entorno639::aplicacion();
        } finally {
            $anterior === false ? putenv('A639_AUTORIZACION') : putenv('A639_AUTORIZACION='.$anterior);
        }
    }
}
