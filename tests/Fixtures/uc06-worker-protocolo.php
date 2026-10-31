<?php

/** Worker sintético sin Laravel ni bases de datos: verifica sólo el transporte del harness. */
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Tests\Integration\MySql\Support\Barrera;

$entrada = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if ($entrada['modo'] === 'terminar') {
    exit(3);
}
$barrera = new Barrera($entrada['directorio']);
$nombre = $entrada['worker'];
$barrera->marcar($nombre.'-listo', ['pid' => getmypid()]);
$barrera->esperar($nombre.'-continuar');
echo json_encode(['status' => 'ok', 'pid' => getmypid()]), PHP_EOL;
