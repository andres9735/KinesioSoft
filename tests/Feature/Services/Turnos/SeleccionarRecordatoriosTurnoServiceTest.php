<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\EstadoRecordatorioTurnoEnum as Estado;
use App\Enums\EstadoTurnoEnum;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\RecordatorioTurno;
use App\Models\Turno;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\RegistrarRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class SeleccionarRecordatoriosTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite en memoria sin PDO MySQL.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        Mail::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function seleccionar(): array
    {
        return app(SeleccionarRecordatoriosTurnoService::class)->seleccionar();
    }

    #[DataProvider('tiposEstados')]
    public function test_tipos_y_seis_estados_canonicos(TipoTurnoEnum $tipo, EstadoTurnoEnum $estado, bool $elegible): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo->value, 'estado' => $estado->value]);
        $antes = $this->turno->fresh()->getAttributes();
        $ids = $this->seleccionar();
        $this->assertCount($elegible ? 1 : 0, $ids);
        $this->assertDatabaseCount('recordatorios_turno', $elegible ? 1 : 0);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public static function tiposEstados(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                $casos[$tipo->value.'-'.$estado->value] = [$tipo, $estado,
                    in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)];
            }
        }

        return $casos;
    }

    #[DataProvider('limites')]
    public function test_24_horas_y_futuro_estricto_sin_anticipacion_minima(string $inicio, bool $elegible): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'inicio_at' => $inicio, 'fin_at' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('Y-m-d H:i:s.u'),
        ]);
        $this->assertCount($elegible ? 1 : 0, $this->seleccionar());
    }

    public static function limites(): array
    {
        return [
            ['2026-11-07 10:00:00.000001', false], ['2026-11-07 10:00:00.000000', true],
            ['2026-11-07 09:59:59.999999', true], ['2026-11-06 18:00:00', true],
            ['2026-11-06 10:00:00.000001', true], ['2026-11-06 10:00:00.000000', false],
            ['2026-11-06 09:59:59.999999', false],
        ];
    }

    public function test_repeticion_y_confirmacion_no_duplican_y_no_usar_bandera_historica(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['recordatorio_enviado_at' => '2026-11-01 10:00:00']);
        $ids = $this->seleccionar();
        $cantidad = Auditoria::count();
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CONFIRMADO']);
        $this->assertSame($ids, $this->seleccionar());
        $this->assertSame($cantidad, Auditoria::count());
        $this->assertDatabaseCount('recordatorios_turno', 1);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
    }

    public function test_recupera_pendientes_de_ciclos_anteriores_sin_restringir_created_at(): void
    {
        $registro = app(RegistrarRecordatorioTurnoService::class)->registrar($this->turno->id);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 09:50:00', config('app.timezone')));
        $this->assertSame([$registro->id], $this->seleccionar());
        $this->assertDatabaseCount('recordatorios_turno', 1);
    }

    public function test_reprogramacion_y_aba_no_reactivan_programacion_procesada(): void
    {
        $registro = RecordatorioTurno::findOrFail($this->seleccionar()[0]);
        $servicio = app(RegistrarRecordatorioTurnoService::class);
        $intento = $servicio->iniciarIntento($registro->id, (string) Str::uuid());
        $servicio->registrarResultado($intento->id, Resultado::ACEPTADO);
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
        ]);
        $this->assertSame([], $this->seleccionar());
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
        ]);
        $actual = RecordatorioTurno::findOrFail($this->seleccionar()[0]);
        $this->assertNotSame($registro->id, $actual->id);
        $this->assertGreaterThan($registro->version_programacion, $actual->version_programacion);
        $this->assertFalse($registro->correspondeA($this->turno->fresh()));
        $this->assertSame(Estado::PROCESADO, $registro->fresh()->estado);
    }

    #[DataProvider('sinCorreo')]
    public function test_correo_no_utilizable_incidente_idempotente_y_recuperacion(string $correo): void
    {
        $usuario = $this->turno->paciente->user;
        $usuario->update(['email' => $correo]);
        $antes = $this->turno->getAttributes();
        $this->assertSame([], $this->seleccionar());
        $registro = RecordatorioTurno::sole();
        $this->assertNull($registro->destinatario);
        $this->assertSame('CORREO_NO_UTILIZABLE', $registro->incidencia);
        $cantidad = Auditoria::count();
        $this->assertSame([], $this->seleccionar());
        $this->assertSame($cantidad, Auditoria::count());
        $usuario->update(['email' => 'vigente@example.test']);
        $this->assertSame([$registro->id], $this->seleccionar());
        $this->assertSame('vigente@example.test', $registro->fresh()->destinatario);
        $this->assertSame(Estado::PENDIENTE, $registro->fresh()->estado);
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
    }

    public static function sinCorreo(): array
    {
        return [[''], ['invalido'], ['destino@example.test '], ["destino@example.test\r\nBcc:ajeno@example.test"]];
    }

    public function test_destinatario_vigente_sin_cache_ni_reemitir_tras_cambio_email(): void
    {
        $id = $this->seleccionar()[0];
        $this->turno->paciente->user->update(['email' => 'nuevo@example.test']);
        $this->assertSame([$id], $this->seleccionar());
        $this->assertSame('nuevo@example.test', RecordatorioTurno::findOrFail($id)->destinatario);
        $this->assertStringNotContainsString('nuevo@example.test', Auditoria::all()->toJson());
        $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
    }

    #[DataProvider('resultadosExcluidos')]
    public function test_no_reselecciona_intentos_conocidos_fallidos_o_inciertos(Resultado $resultado): void
    {
        $id = $this->seleccionar()[0];
        $servicio = app(RegistrarRecordatorioTurnoService::class);
        $intento = $servicio->iniciarIntento($id, (string) Str::uuid());
        $servicio->registrarResultado($intento->id, $resultado);
        $cantidad = Auditoria::count();
        $this->assertSame([], $this->seleccionar());
        $this->assertSame($cantidad, Auditoria::count());
        $this->assertDatabaseCount('recordatorios_turno', 1);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        // La recuperación/reintento corresponde al consumidor posterior del mismo ID.
    }

    public static function resultadosExcluidos(): array
    {
        return [[Resultado::ACEPTADO], [Resultado::FALLO_RECUPERABLE],
            [Resultado::FALLO_DEFINITIVO], [Resultado::INCIERTO]];
    }

    #[DataProvider('zonas')]
    public function test_zona_local_y_24_horas_transcurridas_tambien_en_cambio_dst(string $zona, string $ahora): void
    {
        config(['app.timezone' => $zona]);
        $instante = CarbonImmutable::parse($ahora, $zona);
        CarbonImmutable::setTestNow($instante->utc());
        $inicio = $instante->utc()->addHours(24)->setTimezone($zona);
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'inicio_at' => $inicio->format('Y-m-d H:i:s'), 'fin_at' => $inicio->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);
        $registro = RecordatorioTurno::findOrFail($this->seleccionar()[0]);
        $this->assertSame($zona, $registro->zona_horaria);
        $this->assertSame([], array_diff([$registro->id], $this->seleccionar()));
    }

    public static function zonas(): array
    {
        return [['Pacific/Auckland', '2026-11-06 10:00:00'], ['America/New_York', '2026-03-07 10:00:00'],
            ['America/New_York', '2026-10-31 10:00:00']];
    }

    #[DataProvider('acciones')]
    public function test_elegibilidad_no_redefine_acciones_uc06(string $estado, int $horas, bool $confirmar, bool $cancelar): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'estado' => $estado, 'inicio_at' => CarbonImmutable::now()->addHours($horas)->format('Y-m-d H:i:s'),
            'fin_at' => CarbonImmutable::now()->addHours($horas)->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);
        $this->assertCount(1, $this->seleccionar());
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        parse_str(parse_url($emitido->urlParaCorreo(), PHP_URL_QUERY), $parametros);
        $acceso = app(ValidarEnlaceAsistenciaTurnoService::class)->validar($parametros['token']);
        $this->assertSame($confirmar, $acceso->puedeConfirmar);
        $this->assertSame($cancelar, $acceso->puedeCancelar);
    }

    public static function acciones(): array
    {
        return [['PENDIENTE', 12, true, true], ['PENDIENTE', 8, true, false],
            ['CONFIRMADO', 12, false, true], ['CONFIRMADO', 8, false, false]];
    }

    public function test_revalidacion_despues_del_lock_excluye_inicio_alcanzado(): void
    {
        // Avanzar el reloj tras la consulta de candidatos, antes de entrar a los locks.
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'select "turnos"."id"')) {
                CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 10:00:00', config('app.timezone')));
            }
        });
        $this->assertSame([], $this->seleccionar());
        $this->assertDatabaseCount('recordatorios_turno', 0);
    }

    #[DataProvider('entregasNoDisponibles')]
    public function test_no_reselecciona_referencia_revocada_vencida_o_incierta(string $caso): void
    {
        $id = $this->seleccionar()[0];
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        app(RegistrarRecordatorioTurnoService::class)->asociarSobre($id, $emitido->sobreId);
        if ($caso === 'revocado') {
            DB::table('enlaces_asistencia_turno')->where('id', $emitido->enlaceId)->update(['revocado_at' => '2026-11-06 10:00:00']);
        } elseif ($caso === 'vencido') {
            DB::table('sobres_entrega_enlace')->where('id', $emitido->sobreId)->update(['vence_at' => '2026-11-06 10:00:00']);
        } else {
            DB::table('sobres_entrega_enlace')->where('id', $emitido->sobreId)->update(['estado' => 'INCIERTO']);
        }
        $this->assertSame([], $this->seleccionar());
        $registro = RecordatorioTurno::findOrFail($id);
        $this->assertSame('ENLACE_NO_DISPONIBLE', $registro->incidencia);
        $this->assertSame($emitido->sobreId, (int) $registro->sobre_id);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
    }

    public static function entregasNoDisponibles(): array
    {
        return [['revocado'], ['vencido'], ['incierto']];
    }
}
