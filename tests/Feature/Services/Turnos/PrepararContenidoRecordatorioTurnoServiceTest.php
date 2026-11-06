<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\EntregaEnlaceNoDisponibleException;
use App\Models\Auditoria;
use App\Models\RecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Turnos\EnlaceAsistenciaEmitido;
use App\Services\Turnos\PrepararContenidoRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\SobreEntregaEnlaceService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class PrepararContenidoRecordatorioTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;

    private Turno $turno;

    private PrepararContenidoRecordatorioTurnoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite en memoria sin PDO MySQL.');
        }
        // Aplicación/conexión propia, sin transacción envolvente: el callback del sobre
        // debe ejecutarse tras commit. No ejecutar downs de migraciones históricas.
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-NO-EXPONER',
        ])->fresh();
        $this->servicio = app(PrepararContenidoRecordatorioTurnoService::class);
        Mail::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function id(): int
    {
        return app(SeleccionarRecordatoriosTurnoService::class)->seleccionar()[0];
    }

    private function render(int $id): string
    {
        $referencias = $this->servicio->preparar($id);
        if ($referencias['sobre_id'] === null) {
            return $this->servicio->construir($id)->render();
        }

        return app(SobreEntregaEnlaceService::class)->conMaterialParaDespacho($referencias['sobre_id'],
            fn ($material, $reclamo) => $this->servicio->construir($id, $material, $reclamo)->render());
    }

    #[DataProvider('matriz')]
    public function test_plantilla_matriz_tipo_estado_plazos_y_privacidad(TipoTurnoEnum $tipo, string $estado, int $horas, bool $confirmar, bool $cancelar): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'tipo' => $tipo->value, 'estado' => $estado,
            'inicio_at' => CarbonImmutable::now()->addHours($horas)->format('Y-m-d H:i:s'),
            'fin_at' => CarbonImmutable::now()->addHours($horas)->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);
        $this->turno->kinesiologa->user->update(['name' => '<script>NO-EJECUTAR</script>', 'apellido' => 'Profesional']);
        $antes = $this->turno->fresh()->getAttributes();
        $html = $this->render($this->id());
        $this->assertSame($confirmar, str_contains($html, '>Confirmar asistencia<'));
        $this->assertSame($cancelar, str_contains($html, '>Cancelar turno<'));
        $this->assertSame($confirmar || $cancelar, str_contains($html, '?token='));
        $this->assertStringContainsString($tipo === TipoTurnoEnum::PRIMERA_CONSULTA ? 'Primera consulta' : 'Seguimiento', $html);
        $this->assertStringContainsString('Profesional', $html);
        $this->assertStringContainsString('&lt;script&gt;NO-EJECUTAR&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $html);
        $this->assertStringNotContainsString($this->turno->paciente->user->email, $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<form', $html);
        if (! $cancelar) {
            $this->assertStringContainsString('contactá al consultorio', $html);
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public static function matriz(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach ([['PENDIENTE', 12, true, true], ['PENDIENTE', 8, true, false],
                ['CONFIRMADO', 12, false, true], ['CONFIRMADO', 8, false, false]] as $caso) {
                $casos[] = [$tipo, ...$caso];
            }
        }

        return $casos;
    }

    #[DataProvider('bordes')]
    public function test_limites_microsegundos_de_cancelacion_y_confirmacion(string $inicio, bool $cancelar): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'inicio_at' => $inicio, 'fin_at' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('Y-m-d H:i:s.u'),
        ]);
        $html = $this->render($this->id());
        $this->assertStringContainsString('>Confirmar asistencia<', $html);
        $this->assertSame($cancelar, str_contains($html, '>Cancelar turno<'));
    }

    public static function bordes(): array
    {
        return [['2026-11-06 22:00:00.000001', true], ['2026-11-06 21:59:59.999999', false],
            ['2026-11-06 10:00:00.000001', false]];
    }

    public function test_preparacion_durable_idempotente_y_atomicidad_sobre_sin_descifrar(): void
    {
        $id = $this->id();
        $primera = $this->servicio->preparar($id);
        $auditorias = Auditoria::count();
        $this->assertSame($primera, $this->servicio->preparar($id));
        $this->assertSame($auditorias, Auditoria::count());
        $this->assertSame('DISPONIBLE', SobreEntregaEnlace::sole()->estado);
        $this->assertNull(SobreEntregaEnlace::sole()->reclamo_hash);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('sobres_entrega_enlace', 1);
        $this->assertStringNotContainsString('?token=', json_encode($primera));
        $this->assertStringNotContainsString('?token=', DB::table('recordatorios_turno')->get()->toJson());
        $this->assertStringNotContainsString('?token=', Auditoria::all()->toJson());
    }

    public function test_fallo_asociacion_revierte_nueva_emision_sobre_y_auditorias(): void
    {
        $id = $this->id();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'update "recordatorios_turno"')) {
                throw new RuntimeException('Fallo sintético');
            }
        });
        try {
            $this->servicio->preparar($id);
            $this->fail('La asociación debe ser atómica.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
            $this->assertDatabaseCount('sobres_entrega_enlace', 0);
            $this->assertDatabaseCount('auditorias', 1);
            $this->assertNull(RecordatorioTurno::findOrFail($id)->sobre_id);
        }
    }

    public function test_url_solo_en_contexto_de_sobre_reclamado_y_correo_no_serializable(): void
    {
        $id = $this->id();
        $referencias = $this->servicio->preparar($id);
        try {
            $this->servicio->construir($id);
            $this->fail('No admite consulta ordinaria de credenciales.');
        } catch (DomainException) {
            $this->assertSame('DISPONIBLE', SobreEntregaEnlace::sole()->estado);
        }
        app(SobreEntregaEnlaceService::class)->conMaterialParaDespacho($referencias['sobre_id'], function ($material, $reclamo) use ($id): void {
            $auditorias = Auditoria::count();
            $correo = $this->servicio->construir($id, $material, $reclamo);
            $url = $material->urlParaCorreo();
            parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
            foreach ([json_encode($correo), print_r($correo, true), Auditoria::all()->toJson(),
                DB::table('intentos_recordatorio_turno')->get()->toJson()] as $salida) {
                $this->assertStringNotContainsString($parametros['token'], $salida);
                $this->assertStringNotContainsString($url, $salida);
            }
            $html = $correo->render();
            $this->assertStringContainsString($url, $html);
            $this->assertSame($auditorias, Auditoria::count());
            $this->assertSame($this->turno->paciente->user->email, $correo->to[0]['address']);
            try {
                serialize($correo);
                $this->fail('No admite serializar credenciales en Jobs.');
            } catch (LogicException $error) {
                $this->assertStringNotContainsString($parametros['token'], $error->getMessage());
            }
        });
        // Renderizar no equivale a aceptación de transporte: permanece INCIERTO.
        $this->assertSame('INCIERTO', SobreEntregaEnlace::sole()->resultado);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 0);
    }

    #[DataProvider('cambios')]
    public function test_cambio_posterior_a_seleccion_impide_preparar(string $caso): void
    {
        $id = $this->id();
        if ($caso === 'email') {
            $this->turno->paciente->user->update(['email' => 'nuevo@example.test']);
        } elseif ($caso === 'inicio') {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-07 10:00:00', config('app.timezone')));
        } elseif ($caso === 'estado') {
            DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CANCELADO']);
        } else {
            DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00']);
            DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-07 10:00:00']);
        }
        $this->expectException(DomainException::class);
        $this->servicio->preparar($id);
    }

    public static function cambios(): array
    {
        return [['email'], ['inicio'], ['estado'], ['aba']];
    }

    public function test_confirmacion_posterior_respeta_acciones_actuales_sin_nuevo_enlace(): void
    {
        $id = $this->id();
        $this->servicio->preparar($id);
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'CONFIRMADO']);
        $html = $this->render($id);
        $this->assertStringNotContainsString('>Confirmar asistencia<', $html);
        $this->assertStringContainsString('>Cancelar turno<', $html);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public function test_enlace_historico_sin_sobre_no_se_reemite_silenciosamente(): void
    {
        $id = $this->id();
        $this->servicio->preparar($id);
        DB::table('recordatorios_turno')->where('id', $id)->update(['sobre_id' => null, 'enlace_id' => null]);
        DB::table('sobres_entrega_enlace')->delete();
        $this->expectException(DomainException::class);
        $this->servicio->preparar($id);
    }

    public function test_contexto_http_rechazado_sin_preparacion(): void
    {
        $id = $this->id();
        request()->setRouteResolver(fn () => new Route('GET', '/ajeno', fn () => ''));
        $this->expectException(DomainException::class);
        $this->servicio->preparar($id);
    }

    public function test_no_acepta_url_ajena_o_material_de_otra_programacion(): void
    {
        $id = $this->id();
        $ref = $this->servicio->preparar($id);
        $this->expectException(EntregaEnlaceNoDisponibleException::class);
        app(SobreEntregaEnlaceService::class)->conMaterialParaDespacho($ref['sobre_id'], function ($material, $reclamo) use ($id) {
            $alterado = new EnlaceAsistenciaEmitido($material->enlaceId, false,
                str_replace('consultorio.example.test', 'ajeno.example.test', $material->urlParaCorreo()), $material->sobreId);

            return $this->servicio->construir($id, $alterado, $reclamo);
        });
    }

    public function test_fecha_hora_locales_sin_exponer_datos_clinicos(): void
    {
        config(['app.timezone' => 'Pacific/Auckland']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', 'Pacific/Auckland')->utc());
        $html = $this->render($this->id());
        $this->assertStringContainsString('07/11/2026', $html);
        $this->assertStringContainsString('10:00–10:30', $html);
        $this->assertStringNotContainsString('CLINICO-NO-EXPONER', $html);
    }

    public function test_contenido_no_puede_salir_de_transaccion_exterior_sin_commit(): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update([
            'estado' => 'CONFIRMADO', 'inicio_at' => '2026-11-06 18:00:00', 'fin_at' => '2026-11-06 18:30:00',
        ]);
        $id = $this->id();
        $this->servicio->preparar($id);
        $this->expectException(DomainException::class);
        DB::transaction(fn () => $this->servicio->construir($id));
    }

    #[DataProvider('cambiosDespacho')]
    public function test_revalida_cambio_y_reclamo_al_construir_desde_sobre(string $caso): void
    {
        $id = $this->id();
        $referencia = $this->servicio->preparar($id);
        $this->expectException(EntregaEnlaceNoDisponibleException::class);
        app(SobreEntregaEnlaceService::class)->conMaterialParaDespacho($referencia['sobre_id'], function ($material, $reclamo) use ($id, $caso) {
            if ($caso === 'revocado') {
                DB::table('enlaces_asistencia_turno')->where('id', $material->enlaceId)->update(['revocado_at' => '2026-11-06 10:00:00']);
            } elseif ($caso === 'email') {
                $this->turno->paciente->user->update(['email' => 'nuevo@example.test']);
            } else {
                $reclamo = '00000000-0000-4000-8000-000000000000';
            }

            return $this->servicio->construir($id, $material, $reclamo);
        });
    }

    public static function cambiosDespacho(): array
    {
        return [['revocado'], ['email'], ['reclamo']];
    }
}
