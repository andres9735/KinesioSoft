<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Models\Auditoria;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FixturesAutogestionTurnos;
use Tests\TestCase;

class MisTurnosAutogestionTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Turno $turno;

    private CarbonImmutable $ahora;

    private FixturesAutogestionTurnos $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahora = CarbonImmutable::parse('2026-10-30 10:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->ahora);
        $this->fixtures = new FixturesAutogestionTurnos;
        $this->paciente = $this->fixtures->paciente();
        $this->turno = $this->fixtures->turno($this->paciente, $this->fixtures->profesional());
        $this->actingAs($this->paciente->user);
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.created: '.Auditoria::class);
        Event::forget('eloquent.retrieved: '.Kinesiologa::class);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function referencia(?Turno $turno = null): string
    {
        return $this->get(route('turnos.mis-turnos.show', $turno ?? $this->turno))->assertOk()->viewData('autogestion')['referencia'];
    }

    private function url(string $operacion): string
    {
        return route('turnos.mis-turnos.'.$operacion, $this->turno);
    }

    public static function tiposYOperaciones(): array
    {
        $casos = [];
        foreach (['PRIMERA_CONSULTA', 'SEGUIMIENTO'] as $tipo) {
            foreach (['confirmar', 'cancelar'] as $operacion) {
                $casos[$tipo.' '.$operacion] = [$tipo, $operacion];
            }
        }

        return $casos;
    }

    #[DataProvider('tiposYOperaciones')]
    public function test_original_actions_are_reached_and_replay_has_no_duplicate_effects(string $tipo, string $operacion): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo]);
        $antes = $this->turno->fresh()->getAttributes();
        $datos = ['referencia_programacion' => $this->referencia()];
        $response = $this->postJson($this->url($operacion), $datos)->assertOk()->assertJsonPath('es_replay', false);
        $this->assertSame(['code', 'es_replay', 'message'], array_keys($response->json()));
        $actual = $this->turno->fresh();
        $this->assertSame($operacion === 'cancelar' ? EstadoTurnoEnum::CANCELADO : EstadoTurnoEnum::CONFIRMADO, $actual->estado);
        $this->get(route('turnos.mis-turnos.show', $actual))->assertOk()->assertViewHas('detalle', fn ($d) => $d['seccion'] === ($operacion === 'cancelar' ? 'historicos' : 'proximos'));
        foreach (['tipo', 'paciente_id', 'kinesiologa_id', 'inicio_at', 'fin_at', 'motivo_solicitud'] as $campo) {
            $this->assertSame($antes[$campo], $actual->getRawOriginal($campo));
        }
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', $operacion === 'cancelar' ? 1 : 0);
        $auditoria = Auditoria::sole();
        $this->assertSame('MIS_TURNOS', $auditoria->valores_nuevos['canal']);
        $this->assertSame($this->paciente->user_id, $auditoria->actor_user_id);
        $this->assertSame($operacion === 'cancelar' ? 'turno_cancelado' : 'turno_asistencia_confirmada', $auditoria->evento);
        $ocupados = app(AgendaQueryService::class)->turnosAfectadosPorIntervalo($actual->kinesiologa, $actual->inicio_at, $actual->fin_at)->pluck('id')->all();
        $this->assertSame($operacion === 'cancelar' ? [] : [$actual->id], $ocupados);
        $consolidado = $actual->getAttributes();
        CarbonImmutable::setTestNow($actual->inicio_at->addDay());
        $this->postJson($this->url($operacion), $datos)->assertOk()->assertJsonPath('es_replay', true);
        $this->assertSame($consolidado, $actual->fresh()->getAttributes());
        $this->assertSame($auditoria->getAttributes(), Auditoria::sole()->getAttributes());
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', $operacion === 'cancelar' ? 1 : 0);
        $this->get(route('turnos.mis-turnos.show', $actual))->assertOk()->assertViewHas('detalle', fn ($d) => $d['seccion'] === 'historicos');
    }

    public static function bordes(): array
    {
        return [
            ['confirmar', 1, 200], ['confirmar', 0, 422], ['confirmar', -1, 422],
            ['cancelar', 43201, 200], ['cancelar', 43200, 200], ['cancelar', 43199, 422],
        ];
    }

    #[DataProvider('bordes')]
    public function test_time_is_revalidated_after_the_dialog_was_opened(string $operacion, int $segundos, int $status): void
    {
        $referencia = $this->referencia();
        CarbonImmutable::setTestNow($this->turno->inicio_at->subSeconds($segundos));
        $antes = $this->turno->fresh()->getAttributes();
        $this->postJson($this->url($operacion), ['referencia_programacion' => $referencia])->assertStatus($status);
        $this->assertDatabaseCount('auditorias', $status === 200 ? 1 : 0);
        if ($status !== 200) {
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
            $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        }
    }

    public static function estados(): array
    {
        $casos = [];
        foreach (['PRIMERA_CONSULTA', 'SEGUIMIENTO'] as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                $casos[$tipo.' '.$estado->value] = [$tipo, $estado];
            }
        }

        return $casos;
    }

    #[DataProvider('estados')]
    public function test_buttons_and_post_restrictions_cover_every_type_and_state(string $tipo, EstadoTurnoEnum $estado): void
    {
        DB::table('turnos')->where('id', $this->turno->id)->update(['tipo' => $tipo, 'estado' => $estado->value]);
        $response = $this->get(route('turnos.mis-turnos.show', $this->turno))->assertOk();
        $d = $response->viewData('detalle');
        $this->assertSame($estado === EstadoTurnoEnum::PENDIENTE, $d['puedeConfirmar']);
        $this->assertSame(in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true), $d['puedeCancelar']);
        $this->assertSame($d['puedeConfirmar'], str_contains($response->getContent(), '>Confirmar asistencia</button>'));
        $this->assertSame($d['puedeCancelar'], str_contains($response->getContent(), '>Cancelar turno</button>'));
        if (! in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
            $ref = $response->viewData('autogestion')['referencia'];
            $antes = $this->turno->fresh()->getAttributes();
            $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertUnprocessable();
            $this->postJson($this->url('cancelar'), ['referencia_programacion' => $ref])->assertStatus($estado === EstadoTurnoEnum::CANCELADO ? 200 : 422);
            $this->assertSame($antes, $this->turno->fresh()->getAttributes());
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    public function test_missing_fabricated_tampered_foreign_and_wrong_purpose_references_are_rejected(): void
    {
        $ref = $this->referencia();
        $otro = $this->fixtures->turno($this->paciente, $this->fixtures->profesional());
        $otroRef = $this->referencia($otro);
        $valores = [null, [], 'inventada', substr($ref, 0, -4).'AAAA', $otroRef, Crypt::encryptString('{}')];
        foreach ($valores as $valor) {
            foreach (['confirmar', 'cancelar'] as $op) {
                $this->postJson($this->url($op), ['referencia_programacion' => $valor])->assertUnprocessable()->assertJsonValidationErrors('referencia_programacion');
            }
        }
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_policy_runs_before_reference_validation_and_client_authority_is_rejected(): void
    {
        $ref = $this->referencia();
        $otroPaciente = $this->fixtures->paciente();
        $ajeno = $this->fixtures->turno($otroPaciente, $this->fixtures->profesional());
        foreach (['confirmar', 'cancelar'] as $op) {
            $this->postJson(route('turnos.mis-turnos.'.$op, $ajeno), [])->assertForbidden();
            foreach (['paciente_id', 'actor_id', 'canal', 'estado', 'inicio_at'] as $campo) {
                $this->postJson($this->url($op), ['referencia_programacion' => $ref, $campo => 'manipulado'])->assertUnprocessable()->assertJsonValidationErrors($campo);
            }
        }
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $ajeno->fresh()->estado);
    }

    public function test_http_authentication_verification_profile_and_active_account_remain_required(): void
    {
        $ref = $this->referencia();
        $this->app['auth']->forgetGuards();
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertUnauthorized();
        $actor = $this->paciente->user;
        $actor->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($actor)->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertForbidden();
        $actor->forceFill(['email_verified_at' => now(), 'activo' => false])->save();
        $this->actingAs($actor)->postJson($this->url('cancelar'), ['referencia_programacion' => $ref])->assertRedirect(route('login'));
        $actor->forceFill(['activo' => true, 'telefono' => null])->save();
        $this->actingAs($actor)->postJson($this->url('cancelar'), ['referencia_programacion' => $ref])->assertRedirect(route('perfil.completar'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reference_cannot_be_reused_by_a_new_owner_or_on_missing_or_invalid_resources(): void
    {
        $ref = $this->referencia();
        $nuevo = $this->fixtures->paciente();
        DB::table('turnos')->where('id', $this->turno->id)->update(['paciente_id' => $nuevo->id]);
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertForbidden();
        $this->actingAs($nuevo->user)->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertUnprocessable();
        foreach (['999999', 'abc', '-1'] as $id) {
            $this->postJson('/turnos/mis-turnos/'.$id.'/confirmar-asistencia', ['referencia_programacion' => $ref])->assertNotFound();
        }
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_state_changed_since_get_is_rejected_and_updated_detail_hides_actions(): void
    {
        $ref = $this->referencia();
        DB::table('turnos')->where('id', $this->turno->id)->update(['estado' => 'EN_ATENCION']);
        foreach (['confirmar', 'cancelar'] as $op) {
            $this->postJson($this->url($op), ['referencia_programacion' => $ref])->assertUnprocessable()->assertJsonPath('code', 'estado_no_permitido');
        }
        $this->get(route('turnos.mis-turnos.show', $this->turno))->assertOk()->assertViewHas('detalle', fn ($d) => ! $d['puedeConfirmar'] && ! $d['puedeCancelar']);
        $this->assertSame(EstadoTurnoEnum::EN_ATENCION, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function programaciones(): array
    {
        return [['inicio_at'], ['fin_at'], ['kinesiologa_id'], ['tipo']];
    }

    #[DataProvider('programaciones')]
    public function test_programming_changed_since_get_conflicts_without_mutation(string $campo): void
    {
        $referencia = $this->referencia();
        $valor = match ($campo) {
            'inicio_at' => $this->turno->inicio_at->addHour(),
            'fin_at' => $this->turno->fin_at->addHour(),
            'kinesiologa_id' => $this->fixtures->profesional()->id,
            'tipo' => 'SEGUIMIENTO',
        };
        DB::table('turnos')->where('id', $this->turno->id)->update([$campo => $valor]);
        $antes = $this->turno->fresh()->getAttributes();
        foreach (['confirmar', 'cancelar'] as $op) {
            $this->postJson($this->url($op), ['referencia_programacion' => $referencia])->assertConflict()->assertJsonPath('code', 'programacion_modificada');
        }
        $this->assertSame($antes, $this->turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $this->referencia()])->assertOk();
    }

    public function test_change_after_reference_check_is_still_detected_by_original_transactional_action(): void
    {
        $ref = $this->referencia();
        $cambio = false;
        Kinesiologa::retrieved(function () use (&$cambio): void {
            if (! $cambio) {
                $cambio = true;
                DB::table('turnos')->where('id', $this->turno->id)->update(['fin_at' => $this->turno->fin_at->addMinutes(5)]);
            }
        });
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertConflict();
        $this->assertTrue($cambio);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_optional_reason_is_stored_only_in_cancellation_audit_and_is_not_echoed(): void
    {
        $ref = $this->referencia();
        $motivoOriginal = $this->turno->motivo_solicitud;
        $this->postJson($this->url('cancelar'), ['referencia_programacion' => $ref, 'motivo' => '  Motivo administrativo privado  '])
            ->assertOk()->assertDontSee('administrativo privado');
        $this->assertSame('Motivo administrativo privado', Auditoria::sole()->valores_nuevos['motivo_cancelacion']);
        $this->assertSame($motivoOriginal, $this->turno->fresh()->motivo_solicitud);
    }

    public static function motivosVacios(): array
    {
        return [[null], [''], ['   ']];
    }

    #[DataProvider('motivosVacios')]
    public function test_empty_reason_is_optional_and_does_not_add_an_audit_reason(?string $motivo): void
    {
        $this->postJson($this->url('cancelar'), ['referencia_programacion' => $this->referencia(), 'motivo' => $motivo])->assertOk();
        $this->assertArrayNotHasKey('motivo_cancelacion', Auditoria::sole()->valores_nuevos);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
    }

    public function test_reason_must_be_text_and_is_not_accepted_for_confirmation(): void
    {
        $ref = $this->referencia();
        $this->postJson($this->url('cancelar'), ['referencia_programacion' => $ref, 'motivo' => ['invalido']])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref, 'motivo' => 'no permitido'])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_audit_failure_rolls_back_and_returns_no_internal_exception_data(): void
    {
        $ref = $this->referencia();
        Event::listen('eloquent.created: '.Auditoria::class, fn () => throw new RuntimeException('DETALLE_INTERNO_FIXTURE'));
        $this->postJson($this->url('cancelar'), ['referencia_programacion' => $ref])->assertStatus(500)
            ->assertJsonPath('code', 'error_tecnico')->assertDontSee('DETALLE_INTERNO_FIXTURE')->assertDontSee('trace');
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 0);
    }

    public function test_real_csrf_middleware_rejects_missing_token_and_accepts_session_token(): void
    {
        $ref = $this->referencia();
        $this->app->bind(ValidateCsrfToken::class, CsrfMisTurnosEstricto::class);
        $this->withSession(['_token' => 'csrf-fixture-local']);
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref])->assertStatus(419);
        $this->assertDatabaseCount('auditorias', 0);
        $this->postJson($this->url('confirmar'), ['referencia_programacion' => $ref], ['X-CSRF-TOKEN' => 'csrf-fixture-local'])->assertOk();
    }

    public function test_context_is_allowlisted_and_action_forms_never_use_generic_auto_submit_handler(): void
    {
        $response = $this->get(route('turnos.mis-turnos.show', [
            'turno' => $this->turno, 'profesional' => 'Fixture', 'proximos_page' => '2',
            'historicos_page' => '3', 'seccion' => 'proximos', 'redirect_url' => 'https://otro.invalid',
        ]))->assertOk();
        $url = $response->viewData('autogestion')['urlDetalle'];
        parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
        $this->assertSame(['seccion' => 'proximos', 'profesional' => 'Fixture', 'proximos_page' => '2', 'historicos_page' => '3'], $parametros);
        $this->assertStringNotContainsString('otro.invalid', $url);
        $response->assertSee('Procesando la operación')->assertSee('aria-live="polite"', false)->assertSee('type="button"', false);
    }
}

class CsrfMisTurnosEstricto extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
