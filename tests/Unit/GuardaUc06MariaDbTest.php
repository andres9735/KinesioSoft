<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Integration\Uc06MariaDb\Support\EntornoUc06MariaDb;

class GuardaUc06MariaDbTest extends TestCase
{
    #[DataProvider('perfilesNoAutorizados')]
    public function test_missing_stage_b_authorization_or_disposable_destination_is_rejected_without_bootstrap(array $cambios): void
    {
        $this->expectException(RuntimeException::class);
        EntornoUc06MariaDb::validarAutorizacion(array_replace(self::perfilValido(), $cambios));
    }

    public static function perfilesNoAutorizados(): array
    {
        $casos = [];
        foreach (['UC06_MYSQL_ETAPA_B', 'UC06_MYSQL_AUTORIZADO', 'UC06_MYSQL_DESCARTABLE', 'UC06_MYSQL_DATABASE', 'APP_ENV', 'DB_CONNECTION'] as $clave) {
            $casos['sin '.$clave] = [[$clave => false]];
        }
        foreach (['kinesiosoft', 'kinesiosoft_db', 'kinesiosoft_uc07_test', 'otra_test'] as $destino) {
            $casos[$destino] = [['UC06_MYSQL_AUTORIZADO' => $destino, 'UC06_MYSQL_DESCARTABLE' => $destino, 'UC06_MYSQL_DATABASE' => $destino]];
        }
        $casos['marcador antiguo no habilita nueva etapa'] = [['UC06_MYSQL_ETAPA_B' => 'SI']];

        return $casos;
    }

    public function test_explicit_matching_profile_passes_only_the_pure_guard_not_a_server_certification(): void
    {
        EntornoUc06MariaDb::validarAutorizacion(self::perfilValido());
        $this->addToAssertionCount(1);
    }

    #[DataProvider('workersInseguros')]
    public function test_real_worker_refuses_before_bootstrap_even_with_old_uc07_authorization(array $cambios): void
    {
        $raiz = dirname(__DIR__, 2);
        $proceso = new Process([PHP_BINARY, $raiz.'/tests/Integration/Uc06MariaDb/Support/worker.php'], $raiz,
            array_replace(self::perfilValido(), [
                'UC07_MYSQL_AUTORIZADO' => 'kinesiosoft_uc07_test',
                'UC07_MYSQL_DATABASE' => 'kinesiosoft_uc07_test',
                'UC07_MYSQL_USERNAME' => 'root',
            ], $cambios), '{}', 5);
        $this->assertSame(2, $proceso->run());
        $salida = json_decode(trim($proceso->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('error', $salida['status']);
        $this->assertSame('guarda', $salida['etapa']);
        $this->assertNull($salida['sesion']);
        $this->assertSame(0, $salida['intentos']);
        $this->assertSame([], $salida['locks']);
        $this->assertSame([], $salida['codigos_sql']);
        $this->assertSame('', $proceso->getErrorOutput());
    }

    public static function workersInseguros(): array
    {
        return [
            'sin permiso etapa B' => [['UC06_MYSQL_ETAPA_B' => false]],
            'destino UC07' => [['UC06_MYSQL_DATABASE' => 'kinesiosoft_uc07_test']],
            'destino desarrollo' => [['UC06_MYSQL_DATABASE' => 'kinesiosoft']],
            'host alternativo' => [['UC06_MYSQL_HOST' => 'localhost']],
            'puerto alternativo' => [['UC06_MYSQL_PORT' => '3307']],
            'sin host explicito' => [['UC06_MYSQL_HOST' => false]],
            'sin usuario explicito' => [['UC06_MYSQL_USERNAME' => false]],
        ];
    }

    #[DataProvider('destinosInseguros')]
    public function test_connection_guard_rejects_redirection_without_opening_a_connection(string $entorno, string $conexion, array $cambios): void
    {
        $this->expectException(RuntimeException::class);
        EntornoUc06MariaDb::validarDestino($entorno, $conexion, array_replace(self::configuracion(), $cambios));
    }

    public static function destinosInseguros(): array
    {
        return [
            ['testing', 'mysql', ['database' => 'kinesiosoft']],
            ['testing', 'mysql', ['database' => 'kinesiosoft_uc07_test']],
            ['testing', 'mysql', ['database' => 'kinesiosoft_db']],
            ['testing', 'mysql', ['database' => 'otra_test']],
            ['local', 'mysql', []], ['production', 'mysql', []], ['testing', 'sqlite', []],
            ['testing', 'mysql', ['driver' => 'mariadb']],
            ['testing', 'mysql', ['host' => 'localhost']],
            ['testing', 'mysql', ['port' => '3307']],
            ['testing', 'mysql', ['username' => 'otro']],
            ['testing', 'mysql', ['engine' => 'MyISAM']],
            ['testing', 'mysql', ['url' => 'mysql://localhost/kinesiosoft']],
            ['testing', 'mysql', ['unix_socket' => '/mysql.sock']],
            ['testing', 'mysql', ['read' => ['host' => 'localhost']]],
            ['testing', 'mysql', ['write' => ['database' => 'kinesiosoft_uc07_test']]],
        ];
    }

    public function test_exact_destination_is_accepted_without_connecting(): void
    {
        EntornoUc06MariaDb::validarDestino('testing', 'mysql', self::configuracion());
        $this->addToAssertionCount(1);
    }

    #[DataProvider('servidoresInseguros')]
    public function test_actual_server_guard_rejects_incompatible_metadata(array $cambios, array $tablas): void
    {
        $this->expectException(RuntimeException::class);
        EntornoUc06MariaDb::validarServidor(EntornoUc06MariaDb::DATABASE, array_replace(self::servidor(), $cambios), $tablas);
    }

    public static function servidoresInseguros(): array
    {
        return [
            [['db' => 'kinesiosoft'], []], [['db' => 'kinesiosoft_uc07_test'], []],
            [['puerto' => 3307], []], [['usuario' => 'otro@localhost'], []],
            [['version' => '8.0.0-MySQL'], []], [['aislamiento' => 'READ-COMMITTED'], []],
            [['autocommit' => 0], []], [['engine' => 'MyISAM'], []], [['fk_activas' => 0], []],
            [[], [(object) ['TABLE_NAME' => 'turnos', 'ENGINE' => 'MyISAM']]],
        ];
    }

    public function test_matching_actual_server_metadata_is_accepted_without_a_database(): void
    {
        EntornoUc06MariaDb::validarServidor(EntornoUc06MariaDb::DATABASE, self::servidor(), [(object) ['TABLE_NAME' => 'turnos', 'ENGINE' => 'InnoDB']]);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('nombresAuxiliaresInseguros')]
    public function test_auxiliary_connection_cannot_fall_back_to_a_legacy_connection(string $nombre): void
    {
        $this->expectException(RuntimeException::class);
        EntornoUc06MariaDb::independiente($nombre);
    }

    public static function nombresAuxiliaresInseguros(): array
    {
        return [['uc07_observador'], ['mysql'], ['kinesiosoft'], ['uc06_observador::read']];
    }

    private static function configuracion(): array
    {
        return ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306',
            'username' => 'root', 'database' => EntornoUc06MariaDb::DATABASE, 'engine' => 'InnoDB'];
    }

    public function test_bootstrap_and_repeated_unsafe_connections_never_reach_a_pdo_connector(): void
    {
        $codigo = <<<'PHP'
<?php
require 'vendor/autoload.php';
// Tripwire: cualquier intento de abrir PDO falla en el mock, sin acceso a servidor alguno.
Mockery::mock('overload:Illuminate\Database\Connectors\MySqlConnector')->shouldReceive('connect')->never();
$app = Tests\Integration\Uc06MariaDb\Support\EntornoUc06MariaDb::aplicacion();
$config = $app['config']->get('database.connections.mysql');
$nombres = array_keys($app['config']->get('database.connections'));
$rechazos = 0;
foreach (['kinesiosoft', 'kinesiosoft_uc07_test'] as $destino) {
    $app['config']->set('database.connections.insegura', array_replace($config, ['database' => $destino]));
    for ($i = 0; $i < 2; $i++) {
        try {
            $app['db']->connection('insegura');
        } catch (RuntimeException $error) {
            if (!str_contains($error->getMessage(), 'destino exclusivo no autorizado')) {
                throw $error;
            }
            $rechazos++;
        }
    }
}
$pdoAbiertos = 0;
foreach ($app['db']->getConnections() as $conexion) {
    $pdoAbiertos += (int) ($conexion->getRawPdo() instanceof PDO);
}
Mockery::close();
echo json_encode(['database' => $config['database'], 'host' => $config['host'], 'port' => $config['port'],
    'nombres' => $nombres, 'rechazos' => $rechazos, 'pdo_abiertos' => $pdoAbiertos]), PHP_EOL;
PHP;
        $proceso = new Process([PHP_BINARY], dirname(__DIR__, 2), self::perfilValido(), $codigo, 8);
        $this->assertSame(0, $proceso->run(), 'El bootstrap aislado debe superar el tripwire sin abrir PDO.');
        $this->assertSame('', $proceso->getErrorOutput());
        $salida = json_decode(trim($proceso->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(EntornoUc06MariaDb::DATABASE, $salida['database']);
        $this->assertSame('127.0.0.1', $salida['host']);
        $this->assertSame('3306', $salida['port']);
        $this->assertSame(['mysql'], $salida['nombres']);
        $this->assertSame(4, $salida['rechazos']);
        $this->assertSame(0, $salida['pdo_abiertos']);
    }

    private static function servidor(): array
    {
        return ['db' => EntornoUc06MariaDb::DATABASE, 'puerto' => 3306, 'usuario' => 'root@localhost',
            'version' => '10.4.32-MariaDB', 'aislamiento' => 'REPEATABLE-READ',
            'autocommit' => 1, 'engine' => 'InnoDB', 'fk_activas' => 1];
    }

    private static function perfilValido(): array
    {
        return [
            'UC06_MYSQL_ETAPA_B' => EntornoUc06MariaDb::MARCADOR,
            'UC06_MYSQL_AUTORIZADO' => EntornoUc06MariaDb::DATABASE,
            'UC06_MYSQL_DESCARTABLE' => EntornoUc06MariaDb::DATABASE,
            'UC06_MYSQL_DATABASE' => EntornoUc06MariaDb::DATABASE,
            'UC06_MYSQL_HOST' => '127.0.0.1', 'UC06_MYSQL_PORT' => '3306', 'UC06_MYSQL_USERNAME' => 'root',
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
        ];
    }
}
