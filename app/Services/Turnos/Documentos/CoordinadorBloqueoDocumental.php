<?php

namespace App\Services\Turnos\Documentos;

interface CoordinadorBloqueoDocumental
{
    public function adquirir(string $ruta): BloqueoDocumental;

    /** Devuelve null exclusivamente si el lock está ocupado; los errores se propagan. */
    public function intentarAdquirir(string $ruta): ?BloqueoDocumental;
}
