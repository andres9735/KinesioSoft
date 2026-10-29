<?php

namespace Tests\Feature\Policies;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\DocumentoClinico;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Policies\DocumentoClinicoPolicy;
use App\Policies\TurnoPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TurnoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Paciente $otroPaciente;

    private Kinesiologa $kinesiologa;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 12:00:00', config('app.timezone')));
        // Desalinear deliberadamente users.id y pacientes.id.
        $this->usuario(RoleEnum::ADMINISTRADOR);
        $this->paciente = Paciente::create(['user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true]);
        $this->otroPaciente = Paciente::create(['user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true]);
        $this->kinesiologa = Kinesiologa::create(['user_id' => $this->usuario(RoleEnum::KINESIOLOGO)->id]);
        $this->turno = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create([
            'inicio_at' => CarbonImmutable::now()->addDay(), 'fin_at' => CarbonImmutable::now()->addDay()->addMinutes(45),
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_laravel_discovers_the_turno_policy_without_explicit_registration(): void
    {
        $this->assertInstanceOf(TurnoPolicy::class, Gate::getPolicyFor(Turno::class));
        $this->assertInstanceOf(DocumentoClinicoPolicy::class, Gate::getPolicyFor(DocumentoClinico::class));
        $this->assertNotSame($this->paciente->id, $this->paciente->user_id);
        $this->assertTrue(Gate::forUser($this->paciente->user)->authorize('view', $this->turno)->allowed());
    }

    #[DataProvider('capacidades')]
    public function test_each_resource_contract_requires_the_persisted_owner_and_rejects_guests(string $capacidad): void
    {
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows($capacidad, $this->turno));
        $this->assertFalse(Gate::forUser($this->otroPaciente->user)->allows($capacidad, $this->turno));
        $this->assertFalse(Gate::forUser(null)->allows($capacidad, $this->turno));
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function capacidades(): array
    {
        return [['view'], ['confirmar'], ['cancelar']];
    }

    #[DataProvider('turnosConsultables')]
    public function test_read_access_is_independent_of_type_state_and_start_instant(TipoTurnoEnum $tipo, EstadoTurnoEnum $estado, int $segundos): void
    {
        $inicio = CarbonImmutable::now()->addSeconds($segundos);
        $this->turno->forceFill(['tipo' => $tipo, 'estado' => $estado, 'inicio_at' => $inicio, 'fin_at' => $inicio->addMinutes(45)])->save();
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows('view', $this->turno));
        $this->assertFalse(Gate::forUser($this->otroPaciente->user)->allows('view', $this->turno));
        $this->assertSame($estado, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function turnosConsultables(): array
    {
        $casos = [];
        foreach (TipoTurnoEnum::cases() as $tipo) {
            foreach (EstadoTurnoEnum::cases() as $estado) {
                foreach ([-1, 0, 1] as $segundos) {
                    $casos[$tipo->name.'_'.$estado->name.'_'.$segundos] = [$tipo, $estado, $segundos];
                }
            }
        }

        return $casos;
    }

    #[DataProvider('capacidades')]
    public function test_patient_role_without_a_persisted_profile_cannot_use_resource_contracts(string $capacidad): void
    {
        $sinPerfil = $this->usuario(RoleEnum::PACIENTE);
        // Ni relaciones cargadas artificialmente ni un id de paciente igual al id del usuario autorizan.
        $sinPerfil->setRelation('paciente', $this->paciente);
        $this->assertFalse(Gate::forUser($sinPerfil)->allows($capacidad, $this->turno));
        $this->assertSame($this->otroPaciente->id, $this->paciente->user_id);
        $turnoAjeno = Turno::factory()->for($this->otroPaciente)->for($this->kinesiologa)->create();
        $this->assertFalse(Gate::forUser($this->paciente->user)->allows($capacidad, $turnoAjeno));
        $this->assertFalse(Gate::forUser($this->otroPaciente->user)->allows($capacidad, $this->turno));
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_roles_do_not_grant_access_even_with_an_owned_historical_patient_profile(RoleEnum $rol): void
    {
        $actor = $this->paciente->user;
        $actor->syncRoles([$rol->value]);
        $this->assertTrue($actor->paciente()->exists());
        foreach (['view', 'confirmar', 'cancelar'] as $capacidad) {
            $this->assertFalse(Gate::forUser($actor)->allows($capacidad, $this->turno));
        }
    }

    public static function rolesSinAcceso(): array
    {
        return [[RoleEnum::KINESIOLOGO], [RoleEnum::ADMINISTRADOR], [RoleEnum::SUPERADMIN]];
    }

    public function test_assigned_professional_is_not_authorized_as_the_patient(): void
    {
        foreach (['view', 'confirmar', 'cancelar'] as $capacidad) {
            $this->assertFalse(Gate::forUser($this->kinesiologa->user)->allows($capacidad, $this->turno));
        }
    }

    public function test_retaining_an_explicit_patient_role_does_not_bypass_ownership(): void
    {
        $propietario = $this->paciente->user;
        $propietario->assignRole(RoleEnum::ADMINISTRADOR->value);
        $ajeno = $this->otroPaciente->user;
        $ajeno->assignRole(RoleEnum::SUPERADMIN->value);
        $this->assertTrue(Gate::forUser($propietario)->allows('view', $this->turno));
        $this->assertFalse(Gate::forUser($ajeno)->allows('view', $this->turno));
    }

    #[DataProvider('capacidades')]
    public function test_cached_relations_and_unsaved_patient_ids_cannot_spoof_persisted_ownership(string $capacidad): void
    {
        $ajeno = $this->otroPaciente->user;
        $ajeno->setRelation('paciente', $this->paciente);
        $this->turno->setRelation('paciente', $this->otroPaciente);
        $this->turno->paciente_id = $this->otroPaciente->id;
        $this->assertFalse(Gate::forUser($ajeno)->allows($capacidad, $this->turno));
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows($capacidad, $this->turno));
        $this->assertSame($this->paciente->id, $this->turno->fresh()->paciente_id);
    }

    #[DataProvider('capacidades')]
    public function test_persisted_ownership_is_rechecked_even_when_the_turno_instance_is_stale(string $capacidad): void
    {
        $this->turno->load('paciente');
        Turno::whereKey($this->turno->id)->update(['paciente_id' => $this->otroPaciente->id]);
        $this->assertFalse(Gate::forUser($this->paciente->user)->allows($capacidad, $this->turno));
        $this->assertTrue(Gate::forUser($this->otroPaciente->user)->allows($capacidad, $this->turno));
    }

    public function test_absent_and_unpersisted_resources_and_users_are_denied(): void
    {
        $sinPersistir = $this->turno->replicate();
        $sinPersistir->id = $this->turno->id;
        $usuarioFalso = new User;
        $usuarioFalso->id = $this->paciente->user_id;
        $usuarioFalso->setRelation('roles', $this->paciente->user->roles);
        $inexistente = clone $this->turno;
        $inexistente->id = 999999;
        foreach (['view', 'confirmar', 'cancelar'] as $capacidad) {
            $this->assertFalse(Gate::forUser($this->paciente->user)->allows($capacidad, $sinPersistir));
            $this->assertFalse(Gate::forUser($usuarioFalso)->allows($capacidad, $this->turno));
            $this->assertFalse(Gate::forUser($this->paciente->user)->allows($capacidad, $inexistente));
        }
    }

    public function test_resource_contracts_do_not_execute_or_approve_a_business_transition(): void
    {
        $this->turno->forceFill(['estado' => EstadoTurnoEnum::CANCELADO, 'inicio_at' => CarbonImmutable::now()->subDay()])->save();
        // Sólo se verifica el contrato de actor/recurso; no existe una Action ni una ruta de transición en 6.07.
        foreach (['confirmar', 'cancelar'] as $capacidad) {
            $this->assertTrue(Gate::forUser($this->paciente->user)->allows($capacidad, $this->turno));
            $this->assertFalse(Gate::forUser($this->otroPaciente->user)->allows($capacidad, $this->turno));
        }
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $this->turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_turno_authorization_does_not_grant_document_access_or_generic_modification(): void
    {
        $documentoAjeno = DocumentoClinico::factory()->for($this->otroPaciente)->for($this->otroPaciente->user, 'cargadoPor')->create();
        $actor = Gate::forUser($this->paciente->user);
        $this->assertTrue($actor->allows('view', $this->turno));
        $this->assertFalse($actor->allows('download', $documentoAjeno));
        foreach (['create', 'update', 'delete', 'forceDelete', 'download'] as $capacidad) {
            $this->assertFalse($actor->allows($capacidad, $this->turno));
        }
    }

    public function test_authorization_ignores_list_filters_pages_and_client_owner_ids(): void
    {
        $this->app->make('request')->query->replace([
            'paciente_id' => $this->otroPaciente->id, 'profesional' => 'SIN_COINCIDENCIAS',
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CANCELADO', 'proximos_page' => '999', 'turno' => 999999,
        ]);
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows('view', $this->turno));
        $this->assertFalse(Gate::forUser($this->otroPaciente->user)->allows('view', $this->turno));
    }

    public function test_owner_check_uses_one_exists_query_without_loading_clinical_or_patient_data(): void
    {
        $actor = $this->paciente->user->load('roles');
        Gate::forUser($actor)->allows('view', $this->turno);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $autorizado = Gate::forUser($actor)->allows('view', $this->turno);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertTrue($autorizado);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('exists', $queries[0]['query']);
        $this->assertStringNotContainsString('motivo_solicitud', $queries[0]['query']);
        $this->assertStringNotContainsString('documentos_clinicos', $queries[0]['query']);
        $this->assertFalse($this->turno->relationLoaded('paciente'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_denial_through_gate_raises_a_generic_authorization_error(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');
        Gate::forUser($this->otroPaciente->user)->authorize('view', $this->turno);
    }

    private function usuario(RoleEnum $rol): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol->value);

        return $user;
    }
}
