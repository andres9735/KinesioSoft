<?php

use Tests\Integration\AdelantosMariaDb09\Support\Entorno639;

require dirname(__DIR__, 3).'/vendor/autoload.php';

// Sin marcadores explícitos se rechaza antes de descubrir tests o abrir conexiones.
Entorno639::perfil();
ini_set('zend.exception_ignore_args', '1');
