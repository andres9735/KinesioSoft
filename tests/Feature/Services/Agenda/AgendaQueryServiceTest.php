<?php

namespace Tests\Feature\Services\Agenda;

use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AgendaQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgendaQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AgendaQueryService;
    }

    public function test_resuelve_aplicabilidad_inclusiva_huecos_y_vigencia_abierta(): void
    {
        $kinesiologa = $this->kinesiologa();
        $cerrada = $this->configuracion($kinesiologa, '2026-10-05', '2026-10-10');
        $abierta = $this->configuracion($kinesiologa, '2026-10-15');

        $this->assertNull($this->aplicable($kinesiologa, '2026-10-04'));
        $this->assertTrue($this->aplicable($kinesiologa, '2026-10-05')->is($cerrada));
        $this->assertTrue($this->aplicable($kinesiologa, '2026-10-07')->is($cerrada));
        $this->assertTrue($this->aplicable($kinesiologa, '2026-10-10')->is($cerrada));
        $this->assertNull($this->aplicable($kinesiologa, '2026-10-11'));
        $this->assertTrue($this->aplicable($kinesiologa, '2099-12-31')->is($abierta));
    }

    public function test_aplicable_excluye_anuladas_y_otras_kinesiologas(): void
    {
        $propia = $this->kinesiologa();
        $ajena = $this->kinesiologa();
        $this->configuracion($propia, '2026-10-01', null, '2026-09-30 10:00:00');
        $this->configuracion($ajena, '2026-10-01');

        $this->assertNull($this->aplicable($propia, '2026-10-05'));
    }

    public function test_aplicable_no_oculta_corrupcion_con_dos_resultados(): void
    {
        $kinesiologa = $this->kinesiologa();
        $this->configuracion($kinesiologa, '2026-10-01', '2026-10-20');
        $this->configuracion($kinesiologa, '2026-10-05', '2026-10-15');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Existe más de una configuración de agenda aplicable');

        $this->aplicable($kinesiologa, '2026-10-10');
    }

    public function test_aplicable_carga_franjas_en_orden_canonico(): void
    {
        $kinesiologa = $this->kinesiologa();
        $configuracion = $this->configuracion($kinesiologa, '2026-10-01');
        $franjaTarde = $this->franja($configuracion, 2, '16:00:00', '17:00:00');
        $franjaManana = $this->franja($configuracion, 1, '08:00:00', '09:00:00');
        $franjaMediodia = $this->franja($configuracion, 1, '12:00:00', '13:00:00');

        $resultado = $this->aplicable($kinesiologa, '2026-10-05');

        $this->assertSame(
            [$franjaManana->id, $franjaMediodia->id, $franjaTarde->id],
            $resultado->franjasHorarias->modelKeys()
        );
    }

    public function test_futuras_historicas_y_proxima_excluyen_anuladas_y_son_deterministas(): void
    {
        $kinesiologa = $this->kinesiologa();
        $hoy = CarbonImmutable::parse('2026-10-05');
        $this->configuracion($kinesiologa, '2026-09-01', '2026-10-04');
        $historicaReciente = $this->configuracion($kinesiologa, '2026-09-15', '2026-10-04');
        $this->configuracion($kinesiologa, '2026-09-01', '2026-09-30', '2026-09-01 10:00:00');
        $this->configuracion($kinesiologa, '2026-10-01');
        $primeraEmpatada = $this->configuracion($kinesiologa, '2026-10-10', '2026-10-12');
        $segundaEmpatada = $this->configuracion($kinesiologa, '2026-10-10', '2026-10-14');
        $this->configuracion($kinesiologa, '2026-10-06', '2026-10-09', '2026-10-01 10:00:00');

        $futuras = $this->service->futurasEn($kinesiologa, $hoy)->get();
        $historicas = $this->service->historicasEn($kinesiologa, $hoy)->get();

        $this->assertSame([$primeraEmpatada->id, $segundaEmpatada->id], $futuras->modelKeys());
        $this->assertSame($historicaReciente->id, $historicas->first()->id);
        $this->assertTrue($this->service->proximaEn($kinesiologa, $hoy)->is($primeraEmpatada));
    }

    public function test_vecinos_eligen_los_mas_cercanos_e_ignoran_anuladas_ajenas_y_id_excluido(): void
    {
        $kinesiologa = $this->kinesiologa();
        $ajena = $this->kinesiologa();
        $anteriorLejana = $this->configuracion($kinesiologa, '2026-09-01', '2026-09-10');
        $anteriorCercana = $this->configuracion($kinesiologa, '2026-09-20', '2026-09-30');
        $posteriorCercana = $this->configuracion($kinesiologa, '2026-10-10', '2026-10-20');
        $posteriorLejana = $this->configuracion($kinesiologa, '2026-11-01');
        $this->configuracion($kinesiologa, '2026-10-04', '2026-10-04', '2026-10-01 10:00:00');
        $this->configuracion($ajena, '2026-10-04');
        $objetivo = CarbonImmutable::parse('2026-10-05');

        $this->assertTrue($this->service->predecesoraDe($kinesiologa, $objetivo)->is($anteriorCercana));
        $this->assertTrue($this->service->sucesoraDe($kinesiologa, $objetivo)->is($posteriorCercana));
        $this->assertTrue(
            $this->service->predecesoraDe($kinesiologa, $objetivo, $anteriorCercana->id)
                ->is($anteriorLejana)
        );
        $this->assertTrue(
            $this->service->sucesoraDe($kinesiologa, $objetivo, $posteriorCercana->id)
                ->is($posteriorLejana)
        );
        $this->assertNull($this->service->predecesoraDe($kinesiologa, CarbonImmutable::parse('2026-08-01')));
        $this->assertNull($this->service->sucesoraDe($kinesiologa, CarbonImmutable::parse('2026-12-01')));
    }

    public function test_overlap_de_configuraciones_es_inclusivo_y_maneja_fines_abiertos(): void
    {
        $kinesiologa = $this->kinesiologa();
        $this->configuracion($kinesiologa, '2026-10-01', '2026-10-10');

        $casos = [
            ['2026-10-11', '2026-10-20', false],
            ['2026-10-10', '2026-10-20', true],
            ['2026-10-05', '2026-10-06', true],
            ['2026-09-01', '2026-11-30', true],
            ['2026-10-11', null, false],
            ['2026-10-10', null, true],
        ];

        foreach ($casos as [$desde, $hasta, $esperado]) {
            $query = $this->service->configuracionesSuperpuestasCon(
                $kinesiologa,
                CarbonImmutable::parse($desde),
                $hasta === null ? null : CarbonImmutable::parse($hasta)
            );

            $this->assertSame($esperado, $query->exists(), "Caso {$desde} - {$hasta}");
        }

        $otra = $this->kinesiologa();
        $this->configuracion($otra, '2026-10-01');
        $this->assertFalse($this->service->configuracionesSuperpuestasCon(
            $otra,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-30')
        )->exists());
    }

    public function test_overlap_con_configuracion_abierta_excluye_anuladas_ajenas_y_propia(): void
    {
        $kinesiologa = $this->kinesiologa();
        $ajena = $this->kinesiologa();
        $abierta = $this->configuracion($kinesiologa, '2026-10-01');
        $this->configuracion($kinesiologa, '2026-11-01', null, '2026-10-01 10:00:00');
        $this->configuracion($ajena, '2026-10-01');
        $desde = CarbonImmutable::parse('2026-10-20');

        $this->assertTrue(
            $this->service->configuracionesSuperpuestasCon($kinesiologa, $desde, null)->exists()
        );
        $this->assertFalse(
            $this->service
                ->configuracionesSuperpuestasCon($kinesiologa, $desde, null, $abierta->id)
                ->exists()
        );
    }

    public function test_overlap_de_bloqueos_es_semiabierto_y_acotado_a_la_kinesiologa(): void
    {
        $kinesiologa = $this->kinesiologa();
        $ajena = $this->kinesiologa();
        $existente = $this->bloqueo($kinesiologa, '2026-10-05 08:00:00', '2026-10-05 10:00:00');
        $this->bloqueo(
            $kinesiologa,
            '2026-10-05 07:00:00',
            '2026-10-05 12:00:00',
            '2026-10-01 10:00:00'
        );
        $this->bloqueo($ajena, '2026-10-05 08:00:00', '2026-10-05 10:00:00');

        $casos = [
            ['2026-10-05 10:00:00', '2026-10-05 12:00:00', false],
            ['2026-10-05 09:59:00', '2026-10-05 12:00:00', true],
            ['2026-10-05 07:00:00', '2026-10-05 08:00:00', false],
            ['2026-10-05 07:00:00', '2026-10-05 08:01:00', true],
        ];

        foreach ($casos as [$inicio, $fin, $esperado]) {
            $query = $this->service->bloqueosSuperpuestosCon(
                $kinesiologa,
                CarbonImmutable::parse($inicio),
                CarbonImmutable::parse($fin)
            );

            $this->assertSame($esperado, $query->exists(), "Caso {$inicio} - {$fin}");
        }

        $this->assertFalse($this->service->bloqueosSuperpuestosCon(
            $kinesiologa,
            CarbonImmutable::parse('2026-10-05 09:00:00'),
            CarbonImmutable::parse('2026-10-05 09:30:00'),
            $existente->id
        )->exists());
    }

    public function test_filtros_temporales_de_bloqueos_reciben_instante_explicito(): void
    {
        $kinesiologa = $this->kinesiologa();
        $ahora = CarbonImmutable::parse('2026-10-05 10:00:00');
        $pasado = $this->bloqueo($kinesiologa, '2026-10-05 08:00:00', '2026-10-05 10:00:00');
        $enCurso = $this->bloqueo($kinesiologa, '2026-10-05 09:00:00', '2026-10-05 11:00:00');
        $proximo = $this->bloqueo($kinesiologa, '2026-10-05 10:00:01', '2026-10-05 12:00:00');
        $anulado = $this->bloqueo(
            $kinesiologa,
            '2026-10-06 08:00:00',
            '2026-10-06 10:00:00',
            '2026-10-01 10:00:00'
        );

        $this->assertSame([$proximo->id], $this->service->bloqueosProximosEn($kinesiologa, $ahora)->pluck('id')->all());
        $this->assertSame([$enCurso->id], $this->service->bloqueosEnCursoEn($kinesiologa, $ahora)->pluck('id')->all());
        $this->assertSame([$pasado->id], $this->service->bloqueosPasadosEn($kinesiologa, $ahora)->pluck('id')->all());
        $this->assertSame([$anulado->id], $this->service->bloqueosAnulados($kinesiologa)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$pasado->id, $enCurso->id, $proximo->id],
            $this->service->bloqueosNoAnulados($kinesiologa)->pluck('id')->all()
        );
    }

    private function aplicable(Kinesiologa $kinesiologa, string $fecha): ?ConfiguracionAgenda
    {
        return $this->service->configuracionAplicableEn(
            $kinesiologa,
            CarbonImmutable::parse($fecha)
        );
    }

    private function kinesiologa(): Kinesiologa
    {
        return Kinesiologa::create([
            'user_id' => User::factory()->create()->id,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
        ]);
    }

    private function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null,
        ?string $anuladoAt = null
    ): ConfiguracionAgenda {
        return ConfiguracionAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'vigencia_desde' => $desde,
                'vigencia_hasta' => $hasta,
                'anulado_at' => $anuladoAt,
                'motivo_anulacion' => $anuladoAt === null ? null : 'Anulación de prueba',
            ]);
    }

    private function franja(
        ConfiguracionAgenda $configuracion,
        int $dia,
        string $inicio,
        string $fin
    ): FranjaHorariaAgenda {
        return FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create([
                'dia_semana' => $dia,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
            ]);
    }

    private function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        ?string $anuladoAt = null
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'inicio_at' => $inicio,
                'fin_at' => $fin,
                'anulado_at' => $anuladoAt,
                'motivo_anulacion' => $anuladoAt === null ? null : 'Anulación de prueba',
            ]);
    }
}
