<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Tests\Integration\AdelantosMariaDb09\Support\Entorno639;

require dirname(__DIR__, 3).'/vendor/autoload.php';

// FUTURO: necesita autorización de migraciones y base NUEVA previamente creada.
// Nunca crea bases/usuarios, reinicia esquemas ni continúa un DDL parcialmente aplicado.
try {
    $app = Entorno639::aplicacion('migraciones');
    $c = $app['db']->connection();
    Entorno639::verificar($c, 'migraciones', true);
    $tablas = $c->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    if ($tablas !== [] || $c->select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()') !== []) {
        throw new RuntimeException('639: el esquema debe estar vacío; no se limpiará ni reutilizará.');
    }
    $kernel = $app->make(Kernel::class);
    if ($kernel->call('migrate', ['--database' => 'mysql', '--force' => true]) !== 0) {
        throw new RuntimeException('639: migración fallida; conservar el estado parcial.');
    }
    $c->getSchemaBuilder()->create('certificacion_adelantos_09', function (Blueprint $t): void {
        $t->engine = 'InnoDB';
        $t->unsignedInteger('id')->primary();
        $t->char('run', 32);
    });
    $c->table('certificacion_adelantos_09')->insert(['id' => 1, 'run' => Entorno639::perfil('migraciones')['RUN']]);
    echo "639: esquema preparado; evidencia y datos se conservan.\n";
} catch (Throwable) {
    // Nunca volcar SQL, argumentos, configuración ni cadenas de causas.
    fwrite(STDERR, "639: preparación rechazada o fallida; sin limpieza automática.\n");
    exit(2);
}
