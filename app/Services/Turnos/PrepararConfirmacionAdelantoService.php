<?php

namespace App\Services\Turnos;

use App\Models\DespachoConfirmacionAdelanto;
use App\Models\OfertaAdelanto;
use App\Models\Paciente;
use App\Models\SalidaOfertaAdelanto;
use App\Services\Agenda\RelojAplicacion;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Crypt;
use LogicException;

final class PrepararConfirmacionAdelantoService
{
    public function __construct(
        private readonly TransaccionOfertaAdelantoService $transacciones,
        private readonly RelojAplicacion $reloj,
        private readonly AuditoriaService $auditoria,
    ) {}

    /** Se anida en el traslado; también recupera aceptaciones anteriores sin despacho. */
    public function preparar(int $id): DespachoConfirmacionAdelanto
    {
        $referencia = SalidaOfertaAdelanto::query()->useWritePdo()->findOrFail($id);

        return $this->transacciones->ejecutar($referencia->oferta_id, function (OfertaAdelanto $oferta, $turno, $despachoOferta, $profesional, Paciente $paciente) use ($id): DespachoConfirmacionAdelanto {
            $salida = SalidaOfertaAdelanto::query()->lockForUpdate()->findOrFail($id);
            if ($salida->resultado !== OfertaAdelanto::ACEPTADA || $oferta->estado !== OfertaAdelanto::ACEPTADA
                || (int) $salida->oferta_id !== (int) $oferta->id) {
                throw new LogicException('Sólo una aceptación registrada genera confirmación.');
            }
            $existente = DespachoConfirmacionAdelanto::query()->where('salida_id', $id)->lockForUpdate()->first();
            if ($existente !== null) {
                return $existente;
            }
            $usuario = $paciente->user()->lockForUpdate()->firstOrFail();
            $nombre = $profesional->user()->useWritePdo()->firstOrFail();
            $datos = [
                'uso' => 'confirmacion-adelanto-v1', 'salida_id' => $id, 'oferta_id' => (int) $oferta->id,
                'paciente_id' => (int) $oferta->paciente_id, 'destinatario' => $usuario->email,
                'horario' => $oferta->inicio_at->format('d/m/Y H:i:s').'–'.$oferta->destino_fin_at->format('H:i:s'),
                'estado' => $salida->estado_turno_aceptado === 'PENDIENTE' ? 'Pendiente' : 'Confirmado',
                'profesional' => trim($nombre->name.' '.$nombre->apellido), 'zona' => config('app.timezone'),
            ];
            $despacho = DespachoConfirmacionAdelanto::query()->create([
                'salida_id' => $id, 'estado' => 'PENDIENTE',
                'material_cifrado' => Crypt::encryptString(json_encode($datos, JSON_THROW_ON_ERROR)),
                'preparado_at' => $this->reloj->ahora(),
            ]);
            $this->auditoria->registrar(
                evento: 'confirmacion_adelanto_preparada', modulo: 'Turnos', auditable: $despacho,
                valoresNuevos: ['salida_id' => $id, 'oferta_id' => $oferta->id],
                url: 'urn:kinesiosoft:confirmacion-adelanto', ipAddress: '', userAgent: 'Proceso interno',
            );

            return $despacho;
        });
    }
}
