<?php

namespace Tests\Feature\Services\Turnos;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\AccesoEnlaceRechazadoException;
use App\Exceptions\Turnos\CancelacionTurnoNoPermitidaException;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\Kinesiologa;
use App\Models\Turno;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\ResolverAsistenciaEnlaceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class ResolverAsistenciaEnlaceServiceTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Turno $turno;

    private EnlaceAsistenciaTurno $enlace;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        $this->ahora('2026-11-06 07:00:00');
        $this->app->instance('request', Request::create('https://consultorio.example.test/turnos/asistencia/decidir', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Prueba UC28']));
        $this->turno = Turno::factory()->for($this->profesionalUc07())->for($this->pacienteUc07())->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'inicio_at' => '2026-11-08 10:00:00', 'fin_at' => '2026-11-08 10:30:00',
            'motivo_solicitud' => 'Original clínico preservado',
        ])->fresh();
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        $this->enlace = EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId);
        parse_str(parse_url($emitido->urlParaCorreo(), PHP_URL_QUERY), $query);
        $this->token = $query['token'];
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.retrieved: '.Kinesiologa::class);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cookie_cifrada_acotada_sin_original_y_recibo_no_autoriza_decisiones(): void
    {
        [$datos, $cookie] = $this->autorizacion();
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/turnos/asistencia', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertStringNotContainsString($this->token, Crypt::decryptString($cookie->getValue()));
        $this->assertSame($datos, app(AutorizacionNavegadorEnlaceService::class)->leer($cookie->getValue()));
        $recibo = app(AutorizacionNavegadorEnlaceService::class)->resultado($this->enlace->id, ['accion' => 'confirmar', 'replay' => false]);
        $this->expectException(AccesoEnlaceRechazadoException::class);
        app(AutorizacionNavegadorEnlaceService::class)->leer($recibo->getValue());
    }

    #[DataProvider('cancelaciones')]
    public function test_cancelacion_real_uc06_y_conservacion_de_atributos(string $tipo, string $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo, 'estado' => $estado]);
        // Emitir sobre la revisión realmente vigente, sin reutilizar una autorización obsoleta.
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->turno->id);
        $this->enlace = EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId);
        $antes = $this->turno->fresh()->getAttributes();
        $this->ahora('2026-11-07 22:00:00');
        $resultado = $this->resolver('cancelar');
        $this->assertFalse($resultado['replay']);
        $actual = $this->turno->fresh();
        $this->assertSame('CANCELADO', $actual->estado->value);
        $this->assertSame(array_diff_key($antes, ['estado' => true, 'updated_at' => true]), array_diff_key($actual->getAttributes(), ['estado' => true, 'updated_at' => true]));
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $auditoria = Auditoria::where('evento', 'turno_cancelado')->sole();
        $this->assertSame('ENLACE_SEGURO', $auditoria->valores_nuevos['canal']);
        $this->assertSame($this->turno->paciente->user_id, $auditoria->actor_user_id);
        $this->assertSame('127.0.0.1', $auditoria->ip_address);
        $this->assertStringNotContainsString($this->token, $auditoria->toJson());
    }

    public static function cancelaciones(): array
    {
        return [['PRIMERA_CONSULTA', 'PENDIENTE'], ['PRIMERA_CONSULTA', 'CONFIRMADO'], ['SEGUIMIENTO', 'PENDIENTE'], ['SEGUIMIENTO', 'CONFIRMADO']];
    }

    public function test_confirmar_replay_y_cancelar_con_misma_autorizacion_sin_duplicar(): void
    {
        [$datos] = $this->autorizacion();
        $servicio = app(ResolverAsistenciaEnlaceService::class);
        $this->assertFalse($servicio->ejecutar($datos, 'confirmar')['replay']);
        $this->assertTrue($servicio->ejecutar($datos, 'confirmar')['replay']);
        $this->assertSame('CONFIRMADO', $this->turno->fresh()->estado->value);
        $this->assertDatabaseCount('auditorias', 2);
        $this->assertFalse($servicio->ejecutar($datos, 'cancelar')['replay']);
        try {
            $servicio->ejecutar($datos, 'cancelar');
            $this->fail('Cancelar invalida la autorización anterior.');
        } catch (AccesoEnlaceRechazadoException) {
            $this->assertDatabaseCount('auditorias', 3);
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        }
    }

    public function test_no_cancela_un_microsegundo_despues_del_limite(): void
    {
        $this->ahora('2026-11-07 22:00:00.000001');
        $this->expectException(CancelacionTurnoNoPermitidaException::class);
        $this->resolver('cancelar');
    }

    #[DataProvider('invalidaciones')]
    public function test_autorizacion_anterior_no_supera_cambios_posteriores(string $caso): void
    {
        [$datos] = $this->autorizacion();
        switch ($caso) {
            case 'revocado': $this->enlace->forceFill(['revocado_at' => CarbonImmutable::now(config('app.timezone'))])->save();
                break;
            case 'aba':
                DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-09 10:00:00']);
                DB::table('turnos')->where('id', $this->turno->id)->update(['inicio_at' => '2026-11-08 10:00:00']);
                break;
            case 'inicio': $this->ahora('2026-11-08 10:00:00');
                break;
            case 'cookie_vencida': $this->ahora('2026-11-06 07:15:00');
                break;
            default: DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => $caso]);
        }
        $antes = $this->turno->fresh()->getAttributes();
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'confirmar');
            $this->fail('La autorización anterior debe rechazarse.');
        } catch (AccesoEnlaceRechazadoException) {
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
            $this->assertDatabaseCount('auditorias', 1);
        }
    }

    public static function invalidaciones(): array
    {
        return [['revocado'], ['aba'], ['inicio'], ['cookie_vencida'], ['CANCELADO'], ['EN_ATENCION'], ['ATENDIDO'], ['AUSENTE']];
    }

    public function test_revalida_cambio_antes_del_lock_y_rollback_si_falla_la_auditoria(): void
    {
        [$datos] = $this->autorizacion();
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function (): void {
            DB::table('enlaces_asistencia_turno')->where('id', $this->enlace->id)->update(['revocado_at' => '2026-11-06 07:00:00']);
        });
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'confirmar');
            $this->fail('La revocación posterior al GET debe prevalecer.');
        } catch (AccesoEnlaceRechazadoException) {
            $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
            $this->assertDatabaseCount('auditorias', 1);
        }
        Event::forget('eloquent.retrieved: '.Kinesiologa::class);
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'insert into "auditorias"')) {
                throw new RuntimeException('Fallo controlado');
            }
        });
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'cancelar');
            $this->fail('Debe revertirse la operación completa.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo controlado', $e->getMessage());
            $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
            $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public function test_cookie_manipulada_no_se_descifra(): void
    {
        [, $cookie] = $this->autorizacion();
        $valor = $cookie->getValue();
        $valor[10] = $valor[10] === 'a' ? 'b' : 'a';
        $this->expectException(AccesoEnlaceRechazadoException::class);
        app(AutorizacionNavegadorEnlaceService::class)->leer($valor);
    }

    public function test_revocacion_durante_action_anidada_revierte_transicion_y_auditoria(): void
    {
        [$datos] = $this->autorizacion();
        $lecturas = 0;
        Event::listen('eloquent.retrieved: '.Kinesiologa::class, function () use (&$lecturas): void {
            // Simulación en la misma conexión; no certifica una carrera real MariaDB.
            if (++$lecturas === 2) {
                DB::table('enlaces_asistencia_turno')->where('id', $this->enlace->id)->update(['revocado_at' => '2026-11-06 07:00:00']);
            }
        });
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'confirmar');
            $this->fail('La autorización debe conservarse hasta consolidar la decisión.');
        } catch (AccesoEnlaceRechazadoException $error) {
            $this->assertSame('revocado', $error->motivo);
            $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
            $this->assertDatabaseCount('auditorias', 1);
        }
    }

    public function test_vencimiento_durante_action_anidada_revierte_todos_los_efectos(): void
    {
        [$datos] = $this->autorizacion();
        DB::connection()->beforeExecuting(function (string $sql): void {
            if (str_starts_with($sql, 'insert into "auditorias"')) {
                $this->ahora('2026-11-06 07:15:00');
            }
        });
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'cancelar');
            $this->fail('El vencimiento debe impedir efectos consolidados.');
        } catch (AccesoEnlaceRechazadoException $error) {
            $this->assertSame('vencido', $error->motivo);
            $this->assertSame('PENDIENTE', $this->turno->fresh()->estado->value);
            $this->assertSame(1, EnlaceAsistenciaTurno::versionActualDe($this->turno));
            $this->assertDatabaseCount('auditorias', 1);
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public function test_auditoria_confirmacion_previa_desde_portal_y_cancelacion_por_enlace(): void
    {
        [$datos] = $this->autorizacion();
        $actor = $this->turno->paciente->user;
        app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($actor, $this->turno);
        $servicio = app(ResolverAsistenciaEnlaceService::class);
        $this->assertTrue($servicio->ejecutar($datos, 'confirmar')['replay']);
        $this->assertFalse($servicio->ejecutar($datos, 'cancelar')['replay']);
        $this->assertSame('CANCELADO', $this->turno->fresh()->estado->value);
        $this->assertSame('MIS_TURNOS', Auditoria::where('evento', 'turno_asistencia_confirmada')->sole()->valores_nuevos['canal']);
        $auditoria = Auditoria::where('evento', 'turno_cancelado')->sole();
        $this->assertSame('ENLACE_SEGURO', $auditoria->valores_nuevos['canal']);
        foreach (['turno_id', 'paciente_id', 'kinesiologa_id', 'tipo', 'inicio_at', 'fin_at', 'estado', 'actor_user_id', 'ocurrido_at'] as $campo) {
            $this->assertArrayHasKey($campo, $auditoria->valores_nuevos);
        }
        $this->assertSame('CONFIRMADO', $auditoria->valores_anteriores['estado']);
        $this->assertSame('CANCELADO', $auditoria->valores_nuevos['estado']);
        $this->assertDatabaseCount('auditorias', 3);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public function test_auditoria_cancelacion_previa_desde_portal_no_es_sobrescrita_por_enlace(): void
    {
        [$datos] = $this->autorizacion();
        app(CancelarTurnoAction::class)->ejecutar($this->turno->paciente->user, $this->turno);
        $antes = $this->turno->fresh()->getAttributes();
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, 'confirmar');
            $this->fail('La cancelación consolidada desde el portal debe prevalecer.');
        } catch (AccesoEnlaceRechazadoException) {
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
            $this->assertSame('MIS_TURNOS', Auditoria::where('evento', 'turno_cancelado')->sole()->valores_nuevos['canal']);
            $this->assertDatabaseCount('auditorias', 2);
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        }
    }

    private function autorizacion(): array
    {
        return app(AutorizacionNavegadorEnlaceService::class)->crear($this->enlace);
    }

    private function resolver(string $accion): array
    {
        [$datos] = $this->autorizacion();

        return app(ResolverAsistenciaEnlaceService::class)->ejecutar($datos, $accion);
    }

    private function ahora(string $fecha): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($fecha, config('app.timezone')));
    }
}
