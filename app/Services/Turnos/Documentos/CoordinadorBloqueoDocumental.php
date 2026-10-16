<?php

namespace App\Services\Turnos\Documentos;

interface CoordinadorBloqueoDocumental
{
    public function adquirir(string $ruta): BloqueoDocumental;
}
