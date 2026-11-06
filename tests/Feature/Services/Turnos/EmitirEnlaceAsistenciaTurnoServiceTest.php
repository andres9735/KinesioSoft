<?php

namespace Tests\Feature\Services\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Turno;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class EmitirEnlaceAsistenciaTurnoServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 07:00:00', config('app.timezone')));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
            'motivo_solicitud' => 'Información clínica que no debe exponerse',
        ])->fresh();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('tiposYEstados')]
    public function test_emite_sin_sesion_token_opaco_y_solo_hash_persistido(TipoTurnoEnum $tipo, EstadoTurnoEnum $estado): void
    {
        $turno = Turno::factory()->for($this->turno->paciente)->for($this->turno->kinesiologa)->create([
            'tipo' => $tipo, 'estado' => $estado, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
        ])->fresh();
        $antes = $turno->getAttributes();
        $this->assertGuest();
        $resultado = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($turno->id);
        $this->assertTrue($resultado->nuevaEmision);
        $url = $resultado->urlParaCorreo();
        parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
        $token = $parametros['token'];
        $this->assertTrue(preg_match('/\A[0-9a-f]{64}\z/', $token) === 1);
        $this->assertSame(['token'], array_keys($parametros));
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('/turnos/asistencia', parse_url($url, PHP_URL_PATH));
        $enlace = EnlaceAsistenciaTurno::findOrFail($resultado->enlaceId);
        $this->assertTrue($enlace->coincideConToken($token));
        $this->assertTrue($enlace->vigentePara($turno, CarbonImmutable::now(config('app.timezone'))));
        $this->assertFalse(str_contains(DB::table('enlaces_asistencia_turno')->get()->toJson(), $token));
        $this->assertFalse(str_contains(DB::table('auditorias')->get()->toJson(), $token));
        $this->assertFalse(str_contains($url, 'Información'));
        $this->assertSame($antes, $turno->fresh()->getAttributes());
    }

    public static function tiposYEstados(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach ([EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO] as $estado) {
                $casos[] = [$tipo, $estado];
            }
        }

        return $casos;
    }

    public function test_repeticion_del_ciclo_no_genera_token_url_ni_auditoria_adicional(): void
    {
        $servicio = app(EmitirEnlaceAsistenciaTurnoService::class);
        $primero = $servicio->emitir($this->turno->id);
        $antes = DB::table('enlaces_asistencia_turno')->get()->toJson();
        $segundo = $servicio->emitir($this->turno->id);
        $this->assertSame($primero->enlaceId, $segundo->enlaceId);
        $this->assertFalse($segundo->nuevaEmision);
        $this->assertNull($segundo->urlParaCorreo());
        $this->assertSame($antes, DB::table('enlaces_asistencia_turno')->get()->toJson());
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_confirmacion_conserva_credencial_y_vigencia_no_es_fija_de_24_horas(): void
    {
        $resultado = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        $this->turno->forceFill(['estado' => EstadoTurnoEnum::CONFIRMADO])->save();
        $enlace = EnlaceAsistenciaTurno::findOrFail($resultado->enlaceId);
        $this->assertTrue($enlace->vigentePara($this->turno->fresh(), CarbonImmutable::parse('2026-11-07 22:00:00', config('app.timezone'))));
        $repetido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        $this->assertSame($resultado->enlaceId, $repetido->enlaceId);
        $this->assertNull($enlace->revocado_at);
    }

    public function test_nueva_programacion_tiene_otro_ciclo_y_la_anterior_no_revive_al_volver(): void
    {
        $servicio = app(EmitirEnlaceAsistenciaTurnoService::class);
        $uno = $servicio->emitir($this->turno->id);
        $this->turno->forceFill(['inicio_at' => '2026-11-09 10:00:00', 'fin_at' => '2026-11-09 10:30:00'])->save();
        $dos = $servicio->emitir($this->turno->id);
        $this->turno->forceFill(['inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00'])->save();
        $tres = $servicio->emitir($this->turno->id);
        $this->assertCount(3, array_unique([$uno->enlaceId, $dos->enlaceId, $tres->enlaceId]));
        $this->assertCount(3, EnlaceAsistenciaTurno::all()->pluck('ciclo_emision')->unique());
        $this->assertFalse(EnlaceAsistenciaTurno::find($uno->enlaceId)->vigentePara($this->turno->fresh(), CarbonImmutable::now(config('app.timezone'))));
        $this->assertFalse(EnlaceAsistenciaTurno::find($dos->enlaceId)->vigentePara($this->turno->fresh(), CarbonImmutable::now(config('app.timezone'))));
        $this->assertTrue(EnlaceAsistenciaTurno::find($tres->enlaceId)->vigentePara($this->turno->fresh(), CarbonImmutable::now(config('app.timezone'))));
    }

    public function test_revocado_no_se_reemite_silenciosamente(): void
    {
        $servicio = app(EmitirEnlaceAsistenciaTurnoService::class);
        $emitido = $servicio->emitir($this->turno->id);
        EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId)->forceFill(['revocado_at' => CarbonImmutable::now(config('app.timezone'))])->save();
        $this->expectException(DomainException::class);
        $servicio->emitir($this->turno->id);
    }

    #[DataProvider('estadosIncompatibles')]
    public function test_no_emite_para_estados_incompatibles(EstadoTurnoEnum $estado): void
    {
        $this->turno->forceFill(['estado' => $estado])->save();
        try {
            app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
            $this->fail('La emisión debía rechazarse.');
        } catch (DomainException) {
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public static function estadosIncompatibles(): array
    {
        return array_map(fn ($e) => [$e], [EstadoTurnoEnum::CANCELADO, EstadoTurnoEnum::EN_ATENCION, EstadoTurnoEnum::ATENDIDO, EstadoTurnoEnum::AUSENTE]);
    }

    public function test_no_emite_al_alcanzar_inicio_y_revalida_despues_de_los_locks(): void
    {
        // El reloj se adelanta al observar el lock del turno; se evalúa después.
        $id = $this->turno->id;
        $cambiar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$cambiar): void {
            if ($cambiar && str_starts_with($sql, 'select * from "turnos"')) {
                $cambiar = false;
                CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08 10:00:00', config('app.timezone')));
            }
        });
        try {
            app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($id);
            $this->fail('El turno ya comenzó.');
        } catch (DomainException) {
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
        }
    }

    public function test_fallo_de_auditoria_revierte_emision_y_no_deja_credencial_parcial(): void
    {
        $fallar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fallar): void {
            if ($fallar && str_starts_with($sql, 'insert into "auditorias"')) {
                throw new RuntimeException('Fallo controlado de auditoría');
            }
        });
        try {
            app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
            $this->fail('Debía propagarse el fallo.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
            $this->assertDatabaseCount('auditorias', 0);
        }
        $fallar = false;
        $this->assertTrue(app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id)->nuevaEmision);
    }

    public function test_url_transitoria_no_aparece_en_json_debug_serializacion_auditoria_ni_query_log(): void
    {
        $this->app->instance('request', Request::create('https://otro.example.test/credencial-que-no-debe-auditarse'));
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $resultado = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
            $queries = json_encode(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        parse_str(parse_url($resultado->urlParaCorreo(), PHP_URL_QUERY), $parametros);
        $token = $parametros['token'];
        $this->assertFalse(str_contains(json_encode($resultado), $token));
        $this->assertFalse(str_contains(print_r($resultado->__debugInfo(), true), $token));
        $this->assertFalse(str_contains($queries, $token));
        $auditoria = Auditoria::sole();
        $this->assertSame('urn:kinesiosoft:emision-enlace-asistencia', $auditoria->url);
        $this->assertFalse(str_contains($auditoria->toJson(), $token));
        $this->assertFalse(str_contains($auditoria->toJson(), 'credencial-que-no-debe-auditarse'));
        $this->expectException(LogicException::class);
        serialize($resultado);
    }

    #[DataProvider('basesInseguras')]
    public function test_base_insegura_no_emite_y_no_se_usa_host_de_request(string $base): void
    {
        config(['app.url' => $base]);
        try {
            app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
            $this->fail('La URL base no es segura.');
        } catch (LogicException) {
            $this->assertDatabaseCount('enlaces_asistencia_turno', 0);
        }
    }

    public static function basesInseguras(): array
    {
        return [['http://consultorio.example.test'], ['https://usuario:clave@example.test'], ['https://example.test/?token=x'], ['https://example.test/#x']];
    }

    public function test_no_envia_correos_encola_trabajos_ni_registra_rutas_publicas(): void
    {
        Mail::fake();
        Queue::fake();
        $rutasAntes = array_map(fn ($r) => $r->uri(), Route::getRoutes()->getRoutes());
        app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
        $this->assertSame($rutasAntes, array_map(fn ($r) => $r->uri(), Route::getRoutes()->getRoutes()));
        // Desde 6.25 existe sólo la consulta GET; emitir no altera las rutas.
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('turnos.asistencia.show')->methods());
    }

    #[DataProvider('tiposYEstados')]
    public function test_correctiva_cancelacion_uc06_con_enlace_preserva_atributos_y_no_rehabilita_credencial(TipoTurnoEnum $tipo, EstadoTurnoEnum $estado): void
    {
        $turno = Turno::factory()->for($this->turno->paciente)->for($this->turno->kinesiologa)->create([
            'tipo' => $tipo, 'estado' => $estado, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
            'motivo_solicitud' => 'Original que debe conservarse',
        ])->fresh();
        $actor = $turno->paciente->user;
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($turno->id);
        $enlace = EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId);
        $hash = $enlace->token_hash;
        $ahora = CarbonImmutable::now(config('app.timezone'));
        if ($estado === EstadoTurnoEnum::PENDIENTE) {
            $this->assertFalse(app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($actor, $turno)->esReplay);
            $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($turno));
            $this->assertTrue($enlace->fresh()->vigentePara($turno->fresh(), $ahora));
        }
        $turno->refresh();
        $antes = $turno->getAttributes();
        $otroAntes = $this->turno->fresh()->getAttributes();
        // Límite inclusivo de UC–06: exactamente doce horas corridas.
        CarbonImmutable::setTestNow(CarbonImmutable::parse($turno->getRawOriginal('inicio_at'), config('app.timezone'))->subHours(12));
        $this->assertFalse(app(CancelarTurnoAction::class)->ejecutar($actor, $turno)->esReplay);
        $turno->refresh();
        $this->assertSame(array_diff_key($antes, ['estado' => true, 'updated_at' => true]), array_diff_key($turno->getAttributes(), ['estado' => true, 'updated_at' => true]));
        $this->assertSame($otroAntes, $this->turno->fresh()->getAttributes());
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $turno->estado);
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($turno));
        $this->assertFalse($enlace->fresh()->vigentePara($turno, CarbonImmutable::now(config('app.timezone'))));
        $auditorias = Auditoria::count();
        $this->assertTrue(app(CancelarTurnoAction::class)->ejecutar($actor, $turno)->esReplay);
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($turno));
        $this->assertSame($auditorias, Auditoria::count());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        DB::table('turnos')->where('id', $turno->id)->update(['estado' => $estado->value]);
        $this->assertFalse($enlace->fresh()->vigentePara($turno->fresh(), CarbonImmutable::now(config('app.timezone'))));
        $this->assertSame($hash, $enlace->fresh()->token_hash);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
    }

    public function test_correctiva_fallo_de_cancelacion_revierte_revision_y_conserva_enlace_y_turno(): void
    {
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        $enlace = EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId);
        $antes = $this->turno->getAttributes();
        $fallar = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fallar): void {
            if ($fallar && str_starts_with($sql, 'insert into "intenciones_evaluacion_cupo"')) {
                throw new RuntimeException('Fallo controlado de intención');
            }
        });
        try {
            app(CancelarTurnoAction::class)->ejecutar($this->turno->paciente->user, $this->turno);
            $this->fail('La cancelación no debe consolidarse parcialmente.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fallo controlado de intención', $error->getMessage());
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
            $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
            $this->assertTrue($enlace->fresh()->vigentePara($this->turno->fresh(), CarbonImmutable::now(config('app.timezone'))));
            $this->assertDatabaseCount('auditorias', 1);
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
            $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        }
        $fallar = false;
        app(CancelarTurnoAction::class)->ejecutar($this->turno->paciente->user, $this->turno->fresh());
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($this->turno));
        $this->assertFalse($enlace->fresh()->vigentePara($this->turno->fresh(), CarbonImmutable::now(config('app.timezone'))));
    }
}
