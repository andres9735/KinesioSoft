<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\ResultadoGeneracionAgendaEnum;
use App\Models\Paciente;
use App\Models\PreparacionAgendaDiaria;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\GenerarAgendaDiariaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class GeneracionAgendaDiariaRendimientoTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('volumenes')]
    public function test_preparacion_por_profesional_bajo_cota_local_con_datos_sinteticos(int $cantidadProfesionales): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 07:00:00', config('app.timezone')));
        // Preparación de datos fuera de la medición; treinta citas de 30 minutos,
        // desde las 06:00, por profesional. Es un ensayo sintético de volumen.
        for ($i = 0; $i < $cantidadProfesionales; $i++) {
            $profesional = $this->profesionalUc07();
            for ($j = 0; $j < 30; $j++) {
                $paciente = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
                $inicio = CarbonImmutable::parse('2026-11-05 06:00:00', config('app.timezone'))->addMinutes($j * 30);
                Turno::factory()->for($profesional)->for($paciente)->create([
                    'inicio_at' => $inicio, 'fin_at' => $inicio->addMinutes(30),
                ]);
            }
        }
        $inicios = [];
        $medidas = [];
        PreparacionAgendaDiaria::creating(function ($preparacion) use (&$inicios): void {
            $inicios[$preparacion->kinesiologa_id] = [hrtime(true), count(DB::getQueryLog())];
        });
        PreparacionAgendaDiaria::updated(function ($preparacion) use (&$inicios, &$medidas): void {
            if ($preparacion->resultado === ResultadoGeneracionAgendaEnum::COMPLETADA) {
                [$inicio, $queries] = $inicios[$preparacion->kinesiologa_id];
                $medidas[] = [
                    'segundos' => (hrtime(true) - $inicio) / 1e9,
                    'consultas' => count(DB::getQueryLog()) - $queries,
                    'turnos' => $preparacion->total_turnos,
                ];
            }
        });
        DB::enableQueryLog();
        DB::flushQueryLog();
        memory_reset_peak_usage();
        $memoriaBase = memory_get_usage(true);
        $inicioGlobal = hrtime(true);
        try {
            $resultado = app(GenerarAgendaDiariaService::class)->ejecutar();
            $duracionGlobal = (hrtime(true) - $inicioGlobal) / 1e9;
            $consultas = DB::getQueryLog();
            $pico = memory_get_peak_usage(true);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertSame(ResultadoGeneracionAgendaEnum::COMPLETADA, $resultado->resultado);
        $this->assertCount($cantidadProfesionales, $medidas);
        foreach ($medidas as $medida) {
            $this->assertLessThanOrEqual(60.0, $medida['segundos']);
            $this->assertSame(30, $medida['turnos']);
        }
        $this->assertCount(1, array_unique(array_column($medidas, 'consultas')));
        $lecturasTurnos = array_filter($consultas, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "turnos"'));
        $this->assertCount($cantidadProfesionales, $lecturasTurnos);
        foreach ($lecturasTurnos as $lectura) {
            $this->assertStringNotContainsString('for update', strtolower($lectura['query']));
        }
        fwrite(STDOUT, "\nUC29_RENDIMIENTO ".json_encode([
            'profesionales' => $cantidadProfesionales, 'turnos' => 30 * $cantidadProfesionales,
            'segundos_total' => round($duracionGlobal, 6),
            'segundos_prof_min' => round(min(array_column($medidas, 'segundos')), 6),
            'segundos_prof_max' => round(max(array_column($medidas, 'segundos')), 6),
            'consultas_total' => count($consultas), 'consultas_prof' => $medidas[0]['consultas'],
            'pico_heap_mib' => $pico / 1048576, 'incremento_heap_mib' => ($pico - $memoriaBase) / 1048576,
        ], JSON_UNESCAPED_UNICODE)."\n");
    }

    public static function volumenes(): array
    {
        return ['30 turnos' => [1], '150 turnos' => [5], '600 turnos' => [20]];
    }
}
