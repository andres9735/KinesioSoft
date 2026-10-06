<?php

namespace Tests\Unit\Services\Agenda;

use App\Services\Agenda\ValidadorMatrizAgenda;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ValidadorMatrizAgendaTest extends TestCase
{
    private ValidadorMatrizAgenda $validador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validador = new ValidadorMatrizAgenda;
    }

    public function test_rechaza_matriz_vacia(): void
    {
        $this->assertError(ValidadorMatrizAgenda::ERROR_MATRIZ_VACIA, []);
    }

    public function test_normaliza_una_franja_valida_en_formatos_h_i_y_h_i_s(): void
    {
        $this->assertSame([
            ['dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00'],
            ['dia_semana' => 2, 'hora_inicio' => '09:15:30', 'hora_fin' => '10:15:30'],
        ], $this->validador->validar([
            ['dia_semana' => '1', 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ['dia_semana' => 2, 'hora_inicio' => '09:15:30', 'hora_fin' => '10:15:30'],
        ], 45));
    }

    public function test_admite_varios_dias_y_multiples_franjas_contiguas_sin_maximo_artificial(): void
    {
        $franjas = [
            ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '09:00'],
            ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
            ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'],
            ['dia_semana' => 7, 'hora_inicio' => '08:30', 'hora_fin' => '09:30'],
        ];

        $this->assertCount(4, $this->validador->validar($franjas, 60));
    }

    public function test_rechaza_overlap_parcial_contenido_total_y_duplicados(): void
    {
        foreach ([
            [['08:00', '10:00'], ['09:59', '12:00']],
            [['08:00', '12:00'], ['09:00', '10:00']],
            [['08:00', '10:00'], ['08:00', '10:00']],
        ] as $intervalos) {
            $this->assertError(ValidadorMatrizAgenda::ERROR_SOLAPAMIENTO, [
                ['dia_semana' => 1, 'hora_inicio' => $intervalos[0][0], 'hora_fin' => $intervalos[0][1]],
                ['dia_semana' => 1, 'hora_inicio' => $intervalos[1][0], 'hora_fin' => $intervalos[1][1]],
            ]);
        }
    }

    public function test_rechaza_inicio_igual_o_posterior_al_fin(): void
    {
        foreach ([['10:00', '10:00'], ['10:01', '10:00']] as [$inicio, $fin]) {
            $this->assertError(ValidadorMatrizAgenda::ERROR_ORDEN_INVALIDO, [
                ['dia_semana' => 1, 'hora_inicio' => $inicio, 'hora_fin' => $fin],
            ]);
        }
    }

    public function test_rechaza_dia_y_horas_invalidas_sin_descartar_filas(): void
    {
        foreach ([0, 8, 'lunes', null] as $dia) {
            $this->assertError(ValidadorMatrizAgenda::ERROR_DIA_INVALIDO, [
                ['dia_semana' => $dia, 'hora_inicio' => '08:00', 'hora_fin' => '09:00'],
            ]);
        }

        foreach (['8:00', '24:00', '12:60', '12:00:60', '', null] as $hora) {
            $this->assertError(ValidadorMatrizAgenda::ERROR_HORA_INVALIDA, [
                ['dia_semana' => 1, 'hora_inicio' => $hora, 'hora_fin' => '13:00'],
            ]);
        }
    }

    public function test_valida_duracion_minima_sin_exigir_multiplo(): void
    {
        $exacta = [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:45']];
        $menor = [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:44']];
        $noMultiplo = [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:46']];

        $this->assertCount(1, $this->validador->validar($exacta, 45));
        $this->assertError(ValidadorMatrizAgenda::ERROR_DURACION_INSUFICIENTE, $menor, 45);
        $this->assertCount(1, $this->validador->validar($noMultiplo, 45));
    }

    public function test_ordena_salida_canonicamente_y_usa_id_como_desempate(): void
    {
        $resultado = $this->validador->validar([
            ['id' => 30, 'dia_semana' => 7, 'hora_inicio' => '16:00', 'hora_fin' => '17:00'],
            ['id' => 20, 'dia_semana' => 1, 'hora_inicio' => '12:00', 'hora_fin' => '13:00'],
            ['id' => 10, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '09:00'],
        ], 30);

        $this->assertSame([10, 20, 30], array_column($resultado, 'id'));
        $this->assertSame([1, 1, 7], array_column($resultado, 'dia_semana'));
    }

    public function test_exige_duracion_tecnica_positiva(): void
    {
        $this->assertError(ValidadorMatrizAgenda::ERROR_DURACION_INVALIDA, [
            ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '09:00'],
        ], 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $franjas
     */
    private function assertError(int $codigo, array $franjas, int $duracion = 30): void
    {
        try {
            $this->validador->validar($franjas, $duracion);
            $this->fail("Se esperaba el error de matriz {$codigo}.");
        } catch (InvalidArgumentException $exception) {
            $this->assertSame($codigo, $exception->getCode());
        }
    }
}
