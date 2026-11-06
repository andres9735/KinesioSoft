<?php

namespace App\Http\Controllers;

use App\Services\Turnos\AccesoEnlaceAsistencia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EnlaceAsistenciaTurnoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $acceso = $request->attributes->get(AccesoEnlaceAsistencia::class);
        abort_unless($acceso instanceof AccesoEnlaceAsistencia, 500);
        $status = match ($acceso->estado) {
            AccesoEnlaceAsistencia::VALIDO => 200,
            AccesoEnlaceAsistencia::VENCIDO, AccesoEnlaceAsistencia::REVOCADO => 410,
            AccesoEnlaceAsistencia::PROGRAMACION_MODIFICADA, AccesoEnlaceAsistencia::ESTADO_INCOMPATIBLE => 409,
            default => 403,
        };

        return response()->view('turnos.asistencia.show', ['acceso' => $acceso], $status);
    }
}
