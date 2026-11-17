<?php

namespace App\Services\Turnos;

use App\Models\DespachoOfertaAdelanto;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\Paciente;
use App\Models\Turno;
use Closure;
use Illuminate\Support\Facades\DB;

final class TransaccionOfertaAdelantoService
{
    /**
     * Protocolo K -> P -> T con las referencias inmutables de la oferta.
     * Permite cerrarla si el turno cambió de recursos sin tomar otro mutex.
     * El callback comprueba la asociación antes de modificar el turno.
     */
    public function ejecutar(int $id, Closure $operacion): mixed
    {
        $referencia = OfertaAdelanto::query()->useWritePdo()->findOrFail($id);

        return DB::transaction(function () use ($id, $referencia, $operacion): mixed {
            $profesional = Kinesiologa::query()->lockForUpdate()->findOrFail($referencia->kinesiologa_id);
            $paciente = Paciente::query()->lockForUpdate()->findOrFail($referencia->paciente_id);
            $turno = Turno::query()->lockForUpdate()->findOrFail($referencia->turno_id);
            $oferta = OfertaAdelanto::query()->lockForUpdate()->findOrFail($id);
            $despacho = DespachoOfertaAdelanto::query()->where('oferta_id', $id)->lockForUpdate()->first();
            if ((int) $turno->kinesiologa_id === (int) $profesional->id && (int) $turno->paciente_id === (int) $paciente->id) {
                $turno->setRelation('kinesiologa', $profesional);
                $turno->setRelation('paciente', $paciente);
            }

            return $operacion($oferta, $turno, $despacho, $profesional, $paciente);
        }, attempts: 1);
    }
}
