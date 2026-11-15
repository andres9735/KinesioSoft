<?php

namespace App\Http\Controllers;

use App\Services\Turnos\ConsultarAlertasRecordatorioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AlertasRecordatorioController extends Controller
{
    public function __invoke(Request $request, ConsultarAlertasRecordatorioService $alertas): Response
    {
        return response()->view('turnos.recordatorios.alertas', [
            'alertas' => $alertas->listar($request->user()),
            'mensajes' => ConsultarAlertasRecordatorioService::MENSAJES,
        ])->header('Cache-Control', 'no-store, private');
    }
}
