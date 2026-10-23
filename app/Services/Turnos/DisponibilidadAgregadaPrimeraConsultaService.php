<?php

namespace App\Services\Turnos;

use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class DisponibilidadAgregadaPrimeraConsultaService
{
    public function __construct(
        private readonly ElegibilidadKinesiologaService $elegibilidad,
        private readonly DisponibilidadPrimeraConsultaService $disponibilidad,
    ) {}

    /**
     * Fechas civiles inclusivas, con el límite técnico del motor individual.
     * Los intervalos conservan su timezone canónica y no reservan agenda.
     *
     * @return list<array{
     *     fecha: string,
     *     horarios: list<array{
     *         inicio_at: CarbonImmutable,
     *         profesionales: list<array{kinesiologa_id: int, fin_at: CarbonImmutable}>
     *     }>
     * }>
     */
    public function consultarDisponibilidad(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $porFecha = [];

        // El ID define un orden estable, sin ranking clínico ni datos personales.
        foreach ($this->elegibilidad->listarElegibles()->sortBy('id') as $kinesiologa) {
            try {
                $slots = $this->disponibilidad->consultarDisponibilidad($kinesiologa, $desde, $hasta);
            } catch (ProfesionalNoElegibleException) {
                // El motor relee elegibilidad: pudo perderse después de enumerar candidatas.
                continue;
            }

            foreach ($slots as $slot) {
                $inicio = $slot['inicio_at'];
                $fecha = $inicio->toDateString();
                $hora = $inicio->format('H:i:s.u');
                $porFecha[$fecha][$hora] ??= ['inicio_at' => $inicio, 'profesionales' => []];
                $porFecha[$fecha][$hora]['profesionales'][] = [
                    'kinesiologa_id' => (int) $kinesiologa->getKey(),
                    'fin_at' => $slot['fin_at'],
                ];
            }
        }

        ksort($porFecha, SORT_STRING);
        $dias = [];

        foreach ($porFecha as $fecha => $horarios) {
            ksort($horarios, SORT_STRING);
            $dias[] = ['fecha' => $fecha, 'horarios' => array_values($horarios)];
        }

        return $dias;
    }
}
