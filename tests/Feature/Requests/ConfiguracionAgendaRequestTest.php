<?php

namespace Tests\Feature\Requests;

use App\Enums\RoleEnum;
use App\Http\Requests\Agenda\AnularConfiguracionAgendaRequest;
use App\Http\Requests\Agenda\StoreCambioDuracionAgendaRequest;
use App\Http\Requests\Agenda\StoreConfiguracionAgendaRequest;
use App\Http\Requests\Agenda\UpdateConfiguracionAgendaRequest;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfiguracionAgendaRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        CarbonImmutable::setTestNow(CarbonImmutable::create(
            2026,
            10,
            6,
            12,
            0,
            0,
            config('app.timezone')
        ));

        Route::middleware('web')->post(
            '/_tests/requests/agenda/configuraciones',
            fn (StoreConfiguracionAgendaRequest $request) => response()->json($request->validated())
        );

        Route::middleware('web')->put(
            '/_tests/requests/agenda/configuraciones/{configuracion}',
            fn (UpdateConfiguracionAgendaRequest $request, ConfiguracionAgenda $configuracion) => response()->json($request->validated())
        );

        Route::middleware('web')->post(
            '/_tests/requests/agenda/duracion',
            fn (StoreCambioDuracionAgendaRequest $request) => response()->json($request->validated())
        );

        Route::middleware('web')->patch(
            '/_tests/requests/agenda/configuraciones/{configuracion}/anular',
            fn (AnularConfiguracionAgendaRequest $request, ConfiguracionAgenda $configuracion) => response()->json($request->validated())
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_store_delega_la_autorizacion_de_clase_a_la_policy(): void
    {
        $this->respuestaStore($this->payloadConfiguracion(), $this->usuarioConRol(RoleEnum::KINESIOLOGO))
            ->assertOk();

        foreach ([RoleEnum::PACIENTE, RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $this->respuestaStore($this->payloadConfiguracion(), $this->usuarioConRol($role))
                ->assertForbidden();
        }
    }

    #[DataProvider('fechasInicioStore')]
    public function test_store_usa_el_reloj_para_validar_ayer_hoy_y_manana(
        string $fecha,
        bool $valida
    ): void {
        $response = $this->respuestaStore(
            $this->payloadConfiguracion(['vigencia_desde' => $fecha]),
            $this->usuarioConRol(RoleEnum::KINESIOLOGO)
        );

        $valida
            ? $response->assertOk()
            : $response->assertUnprocessable()->assertJsonValidationErrors('vigencia_desde');
    }

    public static function fechasInicioStore(): array
    {
        return [
            'ayer' => ['2026-10-05', false],
            'hoy' => ['2026-10-06', true],
            'mañana' => ['2026-10-07', true],
        ];
    }

    public function test_store_admite_fin_nulo_e_inclusivo_y_rechaza_fin_anterior(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->respuestaStore($this->payloadConfiguracion(['vigencia_hasta' => null]), $actor)
            ->assertOk()
            ->assertJsonPath('vigencia_hasta', null);

        $this->respuestaStore($this->payloadConfiguracion([
            'vigencia_desde' => '2026-10-07',
            'vigencia_hasta' => '2026-10-07',
        ]), $actor)->assertOk();

        $this->respuestaStore($this->payloadConfiguracion([
            'vigencia_desde' => '2026-10-07',
            'vigencia_hasta' => '2026-10-06',
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vigencia_hasta');
    }

    #[DataProvider('duraciones')]
    public function test_store_valida_rango_entero_y_multiplo_de_cinco(
        mixed $duracion,
        bool $valida
    ): void {
        $response = $this->respuestaStore(
            $this->payloadConfiguracion(['duracion_turno_minutos' => $duracion]),
            $this->usuarioConRol(RoleEnum::KINESIOLOGO)
        );

        $valida
            ? $response->assertOk()
            : $response->assertUnprocessable()->assertJsonValidationErrors('duracion_turno_minutos');
    }

    public static function duraciones(): array
    {
        return [
            'mínimo' => [15, true],
            'default UX' => [45, true],
            'máximo' => [180, true],
            'menor' => [14, false],
            'mayor' => [181, false],
            'no múltiplo' => [17, false],
            'no entero' => ['cuarenta y cinco', false],
        ];
    }

    public function test_store_acepta_contiguas_y_convierte_la_matriz_en_payload_canonico(): void
    {
        $response = $this->respuestaStore($this->payloadConfiguracion([
            'franjas' => [
                ['dia_semana' => ' 2 ', 'hora_inicio' => ' 10:00 ', 'hora_fin' => ' 11:00 '],
                ['dia_semana' => '1', 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
                ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '09:00'],
            ],
        ]), $this->usuarioConRol(RoleEnum::KINESIOLOGO));

        $response
            ->assertOk()
            ->assertJsonPath('franjas.0.dia_semana', 1)
            ->assertJsonPath('franjas.0.hora_inicio', '08:00:00')
            ->assertJsonPath('franjas.1.hora_inicio', '09:00:00')
            ->assertJsonPath('franjas.2.dia_semana', 2);
    }

    public function test_store_convierte_fallos_del_validador_de_matriz_en_errores_laravel(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        foreach ([
            [],
            [
                ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00'],
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '11:00'],
            ],
            [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:44']],
        ] as $franjas) {
            $this->respuestaStore($this->payloadConfiguracion(['franjas' => $franjas]), $actor)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('franjas');
        }
    }

    public function test_store_informa_dia_y_hora_estructuralmente_invalidos(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->respuestaStore($this->payloadConfiguracion([
            'franjas' => [['dia_semana' => 8, 'hora_inicio' => '08:00', 'hora_fin' => '09:00']],
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('franjas.0.dia_semana');

        $this->respuestaStore($this->payloadConfiguracion([
            'franjas' => [['dia_semana' => 1, 'hora_inicio' => '8:00', 'hora_fin' => '09:00']],
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('franjas.0.hora_inicio');
    }

    public function test_normalizacion_descarta_solo_filas_completamente_vacias(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->respuestaStore($this->payloadConfiguracion([
            'franjas' => [
                ['dia_semana' => null, 'hora_inicio' => '  ', 'hora_fin' => null],
                ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ],
        ]), $actor)
            ->assertOk()
            ->assertJsonCount(1, 'franjas');

        foreach ([
            [['dia_semana' => 1, 'hora_inicio' => null, 'hora_fin' => null]],
            [['dia_semana' => null, 'hora_inicio' => '08:00', 'hora_fin' => null]],
        ] as $franjas) {
            $this->respuestaStore($this->payloadConfiguracion(['franjas' => $franjas]), $actor)
                ->assertUnprocessable();
        }
    }

    public function test_store_rechaza_campos_internos_y_no_devuelve_campos_desconocidos(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->respuestaStore($this->payloadConfiguracion([
            'kinesiologa_id' => 999,
            'anulado_at' => '2026-10-06 12:00:00',
            'estado' => 'VIGENTE',
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kinesiologa_id', 'anulado_at', 'estado']);

        $response = $this->respuestaStore($this->payloadConfiguracion([
            'campo_desconocido' => 'no pertenece al contrato',
        ]), $actor)->assertOk();

        $this->assertArrayNotHasKey('campo_desconocido', $response->json());
    }

    public function test_update_delega_autorizacion_de_instancia_a_la_policy(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $payload = $this->payloadConfiguracion([
            'vigencia_desde' => '2026-10-07',
            'snapshot' => 'token-opaco',
        ]);

        $this->respuestaUpdate($this->configuracion($propietaria->kinesiologa, '2026-10-07'), $payload, $propietaria)
            ->assertOk()
            ->assertJsonPath('snapshot', 'token-opaco');
        $this->respuestaUpdate($this->configuracion($otra->kinesiologa, '2026-10-07'), $payload, $propietaria)
            ->assertForbidden();
        $this->respuestaUpdate($this->configuracion($propietaria->kinesiologa, '2026-10-06'), $payload, $propietaria)
            ->assertForbidden();
        $this->respuestaUpdate($this->configuracion(
            $propietaria->kinesiologa,
            '2026-10-07',
            null,
            '2026-10-05 10:00:00'
        ), $payload, $propietaria)->assertForbidden();
    }

    public function test_update_sin_recurso_de_ruta_deniega_de_forma_segura(): void
    {
        $request = UpdateConfiguracionAgendaRequest::create('/_tests/sin-recurso', 'PUT');
        $request->setUserResolver(fn () => $this->usuarioConRol(RoleEnum::KINESIOLOGO));

        $this->assertFalse($request->authorize());
    }

    #[DataProvider('fechasInicioUpdate')]
    public function test_update_exige_inicio_estrictamente_futuro(string $fecha, bool $valida): void
    {
        $actor = $this->kinesiologaConPerfil('update');
        $configuracion = $this->configuracion($actor->kinesiologa, '2026-10-10');
        $response = $this->respuestaUpdate($configuracion, $this->payloadConfiguracion([
            'vigencia_desde' => $fecha,
            'snapshot' => 'token-opaco',
        ]), $actor);

        $valida
            ? $response->assertOk()
            : $response->assertUnprocessable()->assertJsonValidationErrors('vigencia_desde');
    }

    public static function fechasInicioUpdate(): array
    {
        return [
            'pasado' => ['2026-10-05', false],
            'hoy' => ['2026-10-06', false],
            'mañana' => ['2026-10-07', true],
        ];
    }

    public function test_update_exige_snapshot_opaco_y_aplica_reglas_comunes(): void
    {
        $actor = $this->kinesiologaConPerfil('update-reglas');
        $configuracion = $this->configuracion($actor->kinesiologa, '2026-10-10');

        foreach ([null, '   '] as $snapshot) {
            $payload = $this->payloadConfiguracion([
                'vigencia_desde' => '2026-10-07',
                'snapshot' => $snapshot,
            ]);

            if ($snapshot === null) {
                unset($payload['snapshot']);
            }

            $this->respuestaUpdate($configuracion, $payload, $actor)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('snapshot');
        }

        $this->respuestaUpdate($configuracion, $this->payloadConfiguracion([
            'vigencia_desde' => '2026-10-07',
            'vigencia_hasta' => '2026-10-06',
            'duracion_turno_minutos' => 17,
            'franjas' => [],
            'snapshot' => 'token-opaco',
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'vigencia_hasta',
                'duracion_turno_minutos',
                'franjas',
            ]);
    }

    public function test_cambio_duracion_delega_ability_de_clase_y_deniega_roles_ajenos(): void
    {
        $payload = $this->payloadCambioDuracion();

        $this->respuestaCambioDuracion($payload, $this->usuarioConRol(RoleEnum::KINESIOLOGO))
            ->assertOk()
            ->assertJsonPath('snapshot', 'token-opaco');

        foreach ([RoleEnum::PACIENTE, RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $this->respuestaCambioDuracion($payload, $this->usuarioConRol($role))
                ->assertForbidden();
        }
    }

    public function test_cambio_duracion_exige_fecha_futura_snapshot_y_matriz_compatible(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->respuestaCambioDuracion($this->payloadCambioDuracion([
            'vigencia_desde' => '2026-10-06',
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vigencia_desde');

        $this->respuestaCambioDuracion($this->payloadCambioDuracion([
            'duracion_turno_minutos' => 181,
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duracion_turno_minutos');

        $this->respuestaCambioDuracion($this->payloadCambioDuracion([
            'duracion_turno_minutos' => 17,
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duracion_turno_minutos');

        $this->respuestaCambioDuracion($this->payloadCambioDuracion([
            'duracion_turno_minutos' => 60,
            'franjas' => [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '08:45']],
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('franjas');

        $payloadSinSnapshot = $this->payloadCambioDuracion();
        unset($payloadSinSnapshot['snapshot']);

        $this->respuestaCambioDuracion($payloadSinSnapshot, $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('snapshot');

        $this->respuestaCambioDuracion($this->payloadCambioDuracion([
            'snapshot' => '   ',
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('snapshot');
    }

    public function test_anular_delega_autorizacion_de_instancia_a_la_policy(): void
    {
        $propietaria = $this->kinesiologaConPerfil('anular-propia');
        $otra = $this->kinesiologaConPerfil('anular-ajena');
        $payload = $this->payloadAnulacion();

        $this->respuestaAnular($this->configuracion($propietaria->kinesiologa, '2026-10-07'), $payload, $propietaria)
            ->assertOk()
            ->assertJsonPath('snapshot', 'token-opaco');
        $this->respuestaAnular($this->configuracion($otra->kinesiologa, '2026-10-07'), $payload, $propietaria)
            ->assertForbidden();
        $this->respuestaAnular($this->configuracion($propietaria->kinesiologa, '2026-10-06'), $payload, $propietaria)
            ->assertForbidden();
        $this->respuestaAnular($this->configuracion($propietaria->kinesiologa, '2026-10-01'), $payload, $propietaria)
            ->assertForbidden();
        $this->respuestaAnular($this->configuracion($propietaria->kinesiologa, '2026-09-01', '2026-10-05'), $payload, $propietaria)
            ->assertForbidden();
        $this->respuestaAnular($this->configuracion(
            $propietaria->kinesiologa,
            '2026-10-07',
            null,
            '2026-10-05 10:00:00'
        ), $payload, $propietaria)->assertForbidden();
    }

    public function test_anular_sin_recurso_de_ruta_deniega_de_forma_segura(): void
    {
        $request = AnularConfiguracionAgendaRequest::create('/_tests/sin-recurso', 'PATCH');
        $request->setUserResolver(fn () => $this->usuarioConRol(RoleEnum::KINESIOLOGO));

        $this->assertFalse($request->authorize());
    }

    public function test_anular_valida_motivo_snapshot_y_campos_internos(): void
    {
        $actor = $this->kinesiologaConPerfil('anular-validacion');
        $configuracion = $this->configuracion($actor->kinesiologa, '2026-10-07');

        foreach ([
            ['motivo_anulacion' => null, 'snapshot' => 'token-opaco', 'campo' => 'motivo_anulacion'],
            ['motivo_anulacion' => '   ', 'snapshot' => 'token-opaco', 'campo' => 'motivo_anulacion'],
            ['motivo_anulacion' => str_repeat('a', 501), 'snapshot' => 'token-opaco', 'campo' => 'motivo_anulacion'],
            ['motivo_anulacion' => 'Corrección', 'snapshot' => null, 'campo' => 'snapshot'],
            ['motivo_anulacion' => 'Corrección', 'snapshot' => '   ', 'campo' => 'snapshot'],
        ] as $caso) {
            $payload = [
                'motivo_anulacion' => $caso['motivo_anulacion'],
                'snapshot' => $caso['snapshot'],
            ];

            if ($caso['motivo_anulacion'] === null) {
                unset($payload['motivo_anulacion']);
            }

            if ($caso['snapshot'] === null) {
                unset($payload['snapshot']);
            }

            $this->respuestaAnular($configuracion, $payload, $actor)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($caso['campo']);
        }

        $this->respuestaAnular($configuracion, $this->payloadAnulacion([
            'motivo_anulacion' => str_repeat('a', 500),
        ]), $actor)->assertOk();

        $this->respuestaAnular($configuracion, $this->payloadAnulacion([
            'motivo_anulacion' => '  Corrección de agenda  ',
        ]), $actor)
            ->assertOk()
            ->assertJsonPath('motivo_anulacion', 'Corrección de agenda');

        $this->respuestaAnular($configuracion, $this->payloadAnulacion([
            'anulado_at' => '2026-10-06 12:00:00',
        ]), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anulado_at');
    }

    private function respuestaStore(array $payload, User $actor): TestResponse
    {
        return $this->actingAs($actor)->postJson(
            '/_tests/requests/agenda/configuraciones',
            $payload
        );
    }

    private function respuestaUpdate(
        ConfiguracionAgenda $configuracion,
        array $payload,
        User $actor
    ): TestResponse {
        return $this->actingAs($actor)->putJson(
            "/_tests/requests/agenda/configuraciones/{$configuracion->getKey()}",
            $payload
        );
    }

    private function respuestaCambioDuracion(array $payload, User $actor): TestResponse
    {
        return $this->actingAs($actor)->postJson(
            '/_tests/requests/agenda/duracion',
            $payload
        );
    }

    private function respuestaAnular(
        ConfiguracionAgenda $configuracion,
        array $payload,
        User $actor
    ): TestResponse {
        return $this->actingAs($actor)->patchJson(
            "/_tests/requests/agenda/configuraciones/{$configuracion->getKey()}/anular",
            $payload
        );
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function payloadConfiguracion(array $cambios = []): array
    {
        return array_replace([
            'vigencia_desde' => '2026-10-06',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => [
                ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ],
        ], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function payloadCambioDuracion(array $cambios = []): array
    {
        return array_replace([
            'vigencia_desde' => '2026-10-07',
            'duracion_turno_minutos' => 60,
            'franjas' => [
                ['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ],
            'snapshot' => 'token-opaco',
        ], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function payloadAnulacion(array $cambios = []): array
    {
        return array_replace([
            'motivo_anulacion' => 'Cambio de disponibilidad futura',
            'snapshot' => 'token-opaco',
        ], $cambios);
    }

    private function usuarioConRol(RoleEnum $role, string $emailPrefix = 'usuario'): User
    {
        $user = User::factory()->create([
            'email' => $emailPrefix.'_'.uniqid().'@example.com',
            'activo' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function kinesiologaConPerfil(string $emailPrefix): User
    {
        $user = $this->usuarioConRol(RoleEnum::KINESIOLOGO, $emailPrefix);

        Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => null,
            'consultorio_id' => null,
        ]);

        return $user->fresh('kinesiologa');
    }

    private function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null,
        ?string $anuladoAt = null
    ): ConfiguracionAgenda {
        $configuracion = ConfiguracionAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'vigencia_desde' => $desde,
                'vigencia_hasta' => $hasta,
                'anulado_at' => $anuladoAt,
                'motivo_anulacion' => $anuladoAt === null ? null : 'Anulación de prueba',
            ]);

        FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create();

        return $configuracion;
    }
}
