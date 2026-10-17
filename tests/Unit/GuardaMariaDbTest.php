<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Integration\MySql\Support\EntornoMariaDb;

class GuardaMariaDbTest extends TestCase
{
    #[DataProvider('destinosInseguros')]
    public function test_rejects_unsafe_destination_without_opening_any_database(string $entorno, string $conexion, array $cambios, string $autorizada): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AUTORIZACIÓN MARIADB REQUERIDA');
        EntornoMariaDb::validarDestino($entorno, $conexion, array_replace(self::configuracion(), $cambios), $autorizada);
    }

    public static function destinosInseguros(): array
    {
        $db = EntornoMariaDb::DATABASE;

        return [
            'desarrollo incluso con marcador' => ['testing', 'mysql', ['database' => 'kinesiosoft_db'], 'kinesiosoft_db'],
            'APP_ENV local' => ['local', 'mysql', [], $db],
            'APP_ENV production' => ['production', 'mysql', [], $db],
            'conexion no explicita' => ['testing', 'sqlite', [], $db],
            'driver SQLite' => ['testing', 'mysql', ['driver' => 'sqlite'], $db],
            'driver MariaDB distinto al contrato' => ['testing', 'mysql', ['driver' => 'mariadb'], $db],
            'base vacia' => ['testing', 'mysql', ['database' => ''], ''],
            'base NULL' => ['testing', 'mysql', ['database' => null], $db],
            'nombre sin marca test' => ['testing', 'mysql', ['database' => 'kinesiosoft_copia'], 'kinesiosoft_copia'],
            'sin autorizacion' => ['testing', 'mysql', [], ''],
            'otra autorizada' => ['testing', 'mysql', [], 'kinesiosoft_otro_test'],
            'otra DB aunque tenga marcador coincidente' => ['testing', 'mysql', ['database' => 'kinesiosoft_otro_test'], 'kinesiosoft_otro_test'],
            'URL puede redirigir destino' => ['testing', 'mysql', ['url' => 'mysql://localhost/kinesiosoft_db'], $db],
            'replica' => ['testing', 'mysql', ['read' => ['host' => 'localhost']], $db],
            'override de escritura' => ['testing', 'mysql', ['write' => ['database' => 'kinesiosoft_db']], $db],
            'socket ambiguo' => ['testing', 'mysql', ['unix_socket' => '/mysql.sock'], $db],
            'motor MyISAM' => ['testing', 'mysql', ['engine' => 'MyISAM'], $db],
            'host ausente' => ['testing', 'mysql', ['host' => ''], $db],
            'puerto ausente' => ['testing', 'mysql', ['port' => ''], $db],
            'otro host' => ['testing', 'mysql', ['host' => 'localhost'], $db],
            'otro puerto' => ['testing', 'mysql', ['port' => '3307'], $db],
            'otro usuario' => ['testing', 'mysql', ['username' => 'otro'], $db],
        ];
    }

    public function test_accepts_only_explicit_matching_test_profile_without_opening_a_connection(): void
    {
        EntornoMariaDb::validarDestino('testing', 'mysql', self::configuracion(), EntornoMariaDb::DATABASE);
        $this->addToAssertionCount(1);
    }

    private static function configuracion(): array
    {
        return ['driver' => 'mysql', 'database' => EntornoMariaDb::DATABASE, 'host' => '127.0.0.1', 'port' => '3306', 'username' => 'root', 'engine' => 'InnoDB'];
    }

    #[DataProvider('servidoresInseguros')]
    public function test_rejects_unsafe_actual_server_metadata_before_schema_preparation(array $cambios, array $tablas): void
    {
        $this->expectException(RuntimeException::class);
        EntornoMariaDb::validarServidor(EntornoMariaDb::DATABASE, array_replace(self::servidor(), $cambios), $tablas);
    }

    public static function servidoresInseguros(): array
    {
        return [
            'database real de desarrollo' => [['db' => 'kinesiosoft_db'], []],
            'database real desconocida' => [['db' => null], []],
            'servidor MySQL distinto a MariaDB' => [['version' => '8.0.0'], []],
            'aislamiento diferente' => [['aislamiento' => 'READ-COMMITTED'], []],
            'transacciones no autocommit' => [['autocommit' => 0], []],
            'engine por defecto MyISAM' => [['engine' => 'MyISAM'], []],
            'tabla existente MyISAM' => [[], [(object) ['TABLE_NAME' => 'turnos', 'ENGINE' => 'MyISAM']]],
            'puerto real distinto' => [['puerto' => 3307], []],
            'usuario real distinto' => [['usuario' => 'otro@localhost'], []],
        ];
    }

    public function test_accepts_matching_actual_server_metadata_and_only_innodb_tables(): void
    {
        EntornoMariaDb::validarServidor(EntornoMariaDb::DATABASE, self::servidor(), [(object) ['TABLE_NAME' => 'turnos', 'ENGINE' => 'InnoDB']]);
        $this->addToAssertionCount(1);
    }

    private static function servidor(): array
    {
        return ['db' => EntornoMariaDb::DATABASE, 'version' => '10.4.32-MariaDB', 'aislamiento' => 'REPEATABLE-READ', 'autocommit' => 1, 'engine' => 'InnoDB', 'puerto' => 3306, 'usuario' => 'root@localhost'];
    }
}
