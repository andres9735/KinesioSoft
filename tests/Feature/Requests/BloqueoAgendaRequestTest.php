<?php

namespace Tests\Feature\Requests;

use App\Enums\RoleEnum;
use App\Http\Requests\Agenda\AnularBloqueoAgendaRequest;
use App\Http\Requests\Agenda\RevisarBloqueoAgendaRequest;
use App\Http\Requests\Agenda\StoreBloqueoAgendaRequest;
use App\Models\BloqueoAgenda;
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

class BloqueoAgendaRequestTest extends TestCase
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
            '/_tests/requests/agenda/bloqueos/revisar',
            fn (RevisarBloqueoAgendaRequest $request) => response()->json($request->validated())
        );
        Route::middleware('web')->post(
            '/_tests/requests/agenda/bloqueos',
            fn (StoreBloqueoAgendaRequest $request) => response()->json($request->validated())
        );
        Route::middleware('web')->patch(
            '/_tests/requests/agenda/bloqueos/{bloqueo}/anular',
            fn (AnularBloqueoAgendaRequest $request, BloqueoAgenda $bloqueo) => response()->json($request->validated())
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_review_y_store_delegan_create_a_la_policy(): void
    {
        $kinesiologo = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        $this->review($this->payload(), $kinesiologo)->assertOk();
        $this->store($this->payload(['snapshot' => 'token-opaco']), $kinesiologo)->assertOk();

        foreach ([RoleEnum::PACIENTE, RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $actor = $this->usuarioConRol($role);
            $this->review($this->payload(), $actor)->assertForbidden();
            $this->store($this->payload(['snapshot' => 'token-opaco']), $actor)->assertForbidden();
        }
    }

    #[DataProvider('intervalos')]
    public function test_review_valida_intervalo_y_regla_temporal(
        string $inicio,
        string $fin,
        bool $valido
    ): void {
        $response = $this->review(
            $this->payload(['inicio_at' => $inicio, 'fin_at' => $fin]),
            $this->usuarioConRol(RoleEnum::KINESIOLOGO)
        );

        if ($valido) {
            $response->assertOk();
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('fin_at');
        }
    }

    public static function intervalos(): array
    {
        return [
            'futuro' => ['2026-10-07 10:00:00', '2026-10-07 12:00:00', true],
            'en curso' => ['2026-10-06 10:00:00', '2026-10-06 13:00:00', true],
            'iguales' => ['2026-10-06 13:00:00', '2026-10-06 13:00:00', false],
            'inicio posterior' => ['2026-10-06 14:00:00', '2026-10-06 13:00:00', false],
            'fin igual a ahora' => ['2026-10-06 10:00:00', '2026-10-06 12:00:00', false],
            'fin pasado' => ['2026-10-06 10:00:00', '2026-10-06 11:59:59', false],
        ];
    }

    #[DataProvider('motivos')]
    public function test_review_valida_y_normaliza_motivo(string $motivo, bool $valido): void
    {
        $response = $this->review(
            $this->payload(['motivo' => $motivo]),
            $this->usuarioConRol(RoleEnum::KINESIOLOGO)
        );

        if ($valido) {
            $response->assertOk();
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('motivo');
        }
    }

    public static function motivos(): array
    {
        return [
            'vacío' => ['', false],
            'espacios' => ['   ', false],
            'máximo' => [str_repeat('a', 500), true],
            'excedido' => [str_repeat('a', 501), false],
        ];
    }

    public function test_review_devuelve_candidato_canonico_y_solo_campos_permitidos(): void
    {
        $this->review($this->payload([
            'inicio_at' => ' 2026-10-07 10:00 ',
            'fin_at' => ' 2026-10-07 12:00 ',
            'motivo' => '  Congreso  ',
            'desconocido' => 'ignorar',
        ]), $this->usuarioConRol(RoleEnum::KINESIOLOGO))
            ->assertOk()
            ->assertExactJson([
                'inicio_at' => '2026-10-07 10:00:00',
                'fin_at' => '2026-10-07 12:00:00',
                'motivo' => 'Congreso',
            ]);
    }

    public function test_store_exige_snapshot_no_vacio_y_repite_reglas_del_candidato(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        foreach ([null, '', '   '] as $snapshot) {
            $payload = $this->payload(['snapshot' => $snapshot]);
            if ($snapshot === null) {
                unset($payload['snapshot']);
            }
            $this->store($payload, $actor)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('snapshot');
        }

        $this->store($this->payload([
            'fin_at' => '2026-10-06 12:00:00',
            'snapshot' => 'token-opaco',
        ]), $actor)->assertUnprocessable()->assertJsonValidationErrors('fin_at');
    }

    public function test_store_rechaza_campos_internos_y_no_los_incluye_en_validated(): void
    {
        $actor = $this->usuarioConRol(RoleEnum::KINESIOLOGO);

        foreach (['kinesiologa_id', 'anulado_at', 'motivo_anulacion', 'estado', 'tipo'] as $campo) {
            $this->store($this->payload([
                'snapshot' => 'token-opaco',
                $campo => 'valor-no-confiable',
            ]), $actor)->assertUnprocessable()->assertJsonValidationErrors($campo);
        }

        $this->store($this->payload([
            'snapshot' => ' token-opaco ',
            'desconocido' => 'ignorar',
        ]), $actor)
            ->assertOk()
            ->assertJsonMissing(['desconocido' => 'ignorar'])
            ->assertJsonPath('snapshot', 'token-opaco');
    }

    public function test_anular_delega_policy_sobre_route_binding_sin_restriccion_temporal(): void
    {
        $propietaria = $this->kinesiologaConPerfil('propietaria');
        $otra = $this->kinesiologaConPerfil('otra');
        $futuro = $this->bloqueo($propietaria->kinesiologa, '2026-10-07 10:00:00', '2026-10-07 12:00:00');
        $enCurso = $this->bloqueo($propietaria->kinesiologa, '2026-10-06 10:00:00', '2026-10-06 13:00:00');
        $pasado = $this->bloqueo($propietaria->kinesiologa, '2026-10-05 10:00:00', '2026-10-05 12:00:00');
        $ajeno = $this->bloqueo($otra->kinesiologa, '2026-10-07 10:00:00', '2026-10-07 12:00:00');
        $anulado = $this->bloqueo(
            $propietaria->kinesiologa,
            '2026-10-07 13:00:00',
            '2026-10-07 14:00:00',
            '2026-10-06 09:00:00'
        );

        foreach ([$futuro, $enCurso, $pasado] as $bloqueo) {
            $this->anular($bloqueo, $this->payloadAnulacion(), $propietaria)->assertOk();
        }

        $this->anular($ajeno, $this->payloadAnulacion(), $propietaria)->assertForbidden();
        $this->anular($anulado, $this->payloadAnulacion(), $propietaria)->assertForbidden();
    }

    public function test_anular_sin_target_deniega_de_forma_segura(): void
    {
        $request = AnularBloqueoAgendaRequest::create('/_tests/sin-bloqueo', 'PATCH');
        $request->setUserResolver(fn () => $this->usuarioConRol(RoleEnum::KINESIOLOGO));

        $this->assertFalse($request->authorize());
    }

    public function test_anular_valida_motivo_snapshot_y_campos_prohibidos(): void
    {
        $actor = $this->kinesiologaConPerfil('validacion');
        $bloqueo = $this->bloqueo($actor->kinesiologa, '2026-10-07 10:00:00', '2026-10-07 12:00:00');

        foreach ([
            ['motivo_anulacion' => '', 'snapshot' => 'token', 'campo' => 'motivo_anulacion'],
            ['motivo_anulacion' => '   ', 'snapshot' => 'token', 'campo' => 'motivo_anulacion'],
            ['motivo_anulacion' => str_repeat('a', 501), 'snapshot' => 'token', 'campo' => 'motivo_anulacion'],
            ['motivo_anulacion' => 'Corrección', 'snapshot' => '', 'campo' => 'snapshot'],
            ['motivo_anulacion' => 'Corrección', 'snapshot' => '   ', 'campo' => 'snapshot'],
        ] as $caso) {
            $this->anular($bloqueo, [
                'motivo_anulacion' => $caso['motivo_anulacion'],
                'snapshot' => $caso['snapshot'],
            ], $actor)->assertUnprocessable()->assertJsonValidationErrors($caso['campo']);
        }

        $this->anular($bloqueo, $this->payloadAnulacion([
            'motivo_anulacion' => str_repeat('a', 500),
        ]), $actor)->assertOk();

        $this->anular($bloqueo, $this->payloadAnulacion(['anulado_at' => '2026-10-06 12:00:00']), $actor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anulado_at');
    }

    private function review(array $payload, User $actor): TestResponse
    {
        return $this->actingAs($actor)->postJson('/_tests/requests/agenda/bloqueos/revisar', $payload);
    }

    private function store(array $payload, User $actor): TestResponse
    {
        return $this->actingAs($actor)->postJson('/_tests/requests/agenda/bloqueos', $payload);
    }

    private function anular(BloqueoAgenda $bloqueo, array $payload, User $actor): TestResponse
    {
        return $this->actingAs($actor)->patchJson(
            "/_tests/requests/agenda/bloqueos/{$bloqueo->getKey()}/anular",
            $payload
        );
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'inicio_at' => '2026-10-07 10:00:00',
            'fin_at' => '2026-10-07 12:00:00',
            'motivo' => 'Congreso',
        ], $cambios);
    }

    private function payloadAnulacion(array $cambios = []): array
    {
        return array_replace([
            'motivo_anulacion' => 'Corrección de carga',
            'snapshot' => 'token-opaco',
        ], $cambios);
    }

    private function usuarioConRol(RoleEnum $role, string $prefijo = 'usuario'): User
    {
        $user = User::factory()->create([
            'email' => $prefijo.'_'.uniqid().'@example.com',
            'activo' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function kinesiologaConPerfil(string $prefijo): User
    {
        $user = $this->usuarioConRol(RoleEnum::KINESIOLOGO, $prefijo);
        Kinesiologa::create(['user_id' => $user->id]);

        return $user->fresh('kinesiologa');
    }

    private function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        ?string $anuladoAt = null
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()->for($kinesiologa)->create([
            'inicio_at' => $inicio,
            'fin_at' => $fin,
            'anulado_at' => $anuladoAt,
            'motivo_anulacion' => $anuladoAt === null ? null : 'Motivo anterior',
        ]);
    }
}
