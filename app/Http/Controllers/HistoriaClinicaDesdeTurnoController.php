<?php

namespace App\Http\Controllers;

use App\Services\HistoriaClinica\ConsultarExpedienteDesdeTurnoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HistoriaClinicaDesdeTurnoController extends Controller
{
    public function __invoke(Request $request, string $turno, ConsultarExpedienteDesdeTurnoService $consulta): Response
    {
        try {
            $expediente = $consulta->consultar($request->user('web'), (int) $turno);
        } catch (QueryException) {
            return response()->view('historia-clinica.error', ['turnoId' => $turno], 503)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->view('historia-clinica.desde-turno', $expediente)
            ->header('Cache-Control', 'private, no-store');
    }
}
