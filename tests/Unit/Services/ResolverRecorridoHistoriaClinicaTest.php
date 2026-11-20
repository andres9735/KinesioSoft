<?php

namespace Tests\Unit\Services;

use App\Services\HistoriaClinica\ResolverRecorridoHistoriaClinica;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResolverRecorridoHistoriaClinicaTest extends TestCase
{
    #[DataProvider('situacionesDeLaCuartaDecision')]
    public function test_resuelve_el_recorrido_sin_confundir_declaracion_con_datos_de_base(
        ?bool $activa,
        bool $pendiente,
        bool $revisada,
        ?bool $baseDisponible,
        string $esperado,
    ): void {
        $this->assertSame($esperado, (new ResolverRecorridoHistoriaClinica)->resolver(
            $activa, $pendiente, $revisada, $baseDisponible,
        ));
    }

    public static function situacionesDeLaCuartaDecision(): array
    {
        return [
            'sin expediente ni declaración' => [null, false, false, null, 'apertura_a'],
            'sin expediente con declaración pendiente' => [null, true, false, null, 'revision_c'],
            'revisada sin expediente detiene apertura' => [null, false, true, null, 'declaracion_revisada_sin_historia'],
            'pendiente no oculta una revisada huérfana' => [null, true, true, null, 'declaracion_revisada_sin_historia'],
            'activa con base disponible' => [true, false, false, true, 'expediente_disponible'],
            'pendiente no bloquea expediente disponible' => [true, true, false, true, 'expediente_disponible'],
            'histórica con base consultable' => [true, false, true, true, 'expediente_disponible'],
            'solo cabecera requiere completar mismo expediente' => [true, false, false, false, 'completar_mismo_expediente'],
            'cabecera y pendiente requieren ambas reglas' => [true, true, false, false, 'completar_mismo_expediente'],
            'disponibilidad desconocida no habilita atención' => [true, false, false, null, 'verificar_datos_base'],
            'pendiente no acredita disponibilidad' => [true, true, false, null, 'verificar_datos_base'],
            'revisada no acredita disponibilidad' => [true, false, true, null, 'verificar_datos_base'],
            'inactiva sin declaración' => [false, false, false, null, 'historia_inactiva'],
            'inactiva con pendiente' => [false, true, false, true, 'historia_inactiva'],
            'inactiva con revisada' => [false, false, true, true, 'historia_inactiva'],
        ];
    }
}
