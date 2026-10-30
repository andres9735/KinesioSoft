<?php

namespace Tests\Feature\Policies;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\DocumentoClinico;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Policies\DocumentoClinicoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentoClinicoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private DocumentoClinico $documento;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        $this->paciente = Paciente::create(['user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true]);
        $this->kinesiologa = $this->profesional();
        $this->documento = DocumentoClinico::factory()->for($this->paciente)->for($this->paciente->user, 'cargadoPor')->create();
        $turno = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA]);
        $this->documento->origenTurno()->create(['turno_id' => $turno->id]);
    }

    public function test_policy_is_discovered_and_patient_ownership_is_authoritative_through_gate(): void
    {
        $this->assertInstanceOf(DocumentoClinicoPolicy::class, Gate::getPolicyFor(DocumentoClinico::class));
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows('download', $this->documento));
        $this->assertFalse(Gate::forUser($this->usuario(RoleEnum::PACIENTE))->allows('download', $this->documento));
    }

    public function test_only_professional_of_origin_appointment_can_download_even_if_another_treated_same_patient(): void
    {
        $otra = $this->profesional();
        Turno::factory()->for($this->paciente)->for($otra)->create();
        $this->assertTrue(Gate::forUser($this->kinesiologa->user)->allows('download', $this->documento));
        $this->assertFalse(Gate::forUser($otra->user)->allows('download', $this->documento));
    }

    public function test_document_without_origin_appointment_is_denied_to_patient_and_professional(): void
    {
        $documento = DocumentoClinico::factory()->for($this->paciente)->for($this->paciente->user, 'cargadoPor')->create();
        $this->assertFalse(Gate::forUser($this->paciente->user)->allows('download', $documento));
        $this->assertFalse(Gate::forUser($this->kinesiologa->user)->allows('download', $documento));
    }

    #[DataProvider('origenesInconsistentes')]
    public function test_patient_download_requires_persisted_origin_and_consistent_first_consultation(string $caso): void
    {
        $otroPaciente = Paciente::create(['user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true]);
        $turno = Turno::factory()->for($caso === 'turno_ajeno' ? $otroPaciente : $this->paciente)->for($this->kinesiologa)->create([
            'tipo' => $caso === 'seguimiento' ? TipoTurnoEnum::SEGUIMIENTO : TipoTurnoEnum::PRIMERA_CONSULTA,
        ]);
        $documento = DocumentoClinico::factory()->for($this->paciente)->for($this->paciente->user, 'cargadoPor')->create([
            'origen' => match ($caso) {
                'interno' => 'CONSULTA_CLINICA',
                'desconocido' => 'DESCONOCIDO',
                default => DocumentoClinico::ORIGEN_SOLICITUD_TURNO,
            },
        ]);
        $documento->origenTurno()->create(['turno_id' => $turno->id]);
        $this->assertFalse(Gate::forUser($this->paciente->user)->allows('download', $documento));
        $documento->forceFill(['origen' => DocumentoClinico::ORIGEN_SOLICITUD_TURNO]);
        $documento->setRelation('origenTurno', $this->documento->origenTurno);
        $this->assertFalse(Gate::forUser($this->paciente->user)->allows('download', $documento));
    }

    public static function origenesInconsistentes(): array
    {
        return [['interno'], ['desconocido'], ['turno_ajeno'], ['seguimiento']];
    }

    #[DataProvider('estadosTurno')]
    public function test_legitimate_original_remains_downloadable_in_every_appointment_state(EstadoTurnoEnum $estado): void
    {
        $this->documento->origenTurno->turno->forceFill(['estado' => $estado])->save();
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows('download', $this->documento));
        $this->assertTrue(Gate::forUser($this->kinesiologa->user)->allows('download', $this->documento));
    }

    public static function estadosTurno(): array
    {
        return array_map(static fn (EstadoTurnoEnum $estado): array => [$estado], EstadoTurnoEnum::cases());
    }

    public function test_unsaved_resources_and_actors_cannot_borrow_persisted_ids(): void
    {
        $actor = new User;
        $actor->id = $this->paciente->user_id;
        $documento = new DocumentoClinico;
        $documento->id = $this->documento->id;
        $this->assertFalse(Gate::forUser($actor)->allows('download', $this->documento));
        $this->assertFalse(Gate::forUser($this->paciente->user)->allows('download', $documento));
    }

    #[DataProvider('administrativos')]
    public function test_administrative_role_never_grants_clinical_access_even_with_historical_profiles(RoleEnum $rol, string $historia): void
    {
        $actor = match ($historia) {
            'paciente' => $this->paciente->user,
            'kinesiologa' => $this->kinesiologa->user,
            default => $this->usuario($rol),
        };
        $actor->syncRoles([$rol->value]);
        $this->assertFalse(Gate::forUser($actor)->allows('download', $this->documento));
        if ($historia !== 'ninguna') {
            $relacion = $historia === 'paciente' ? 'paciente' : 'kinesiologa';
            $this->assertTrue($actor->{$relacion}()->exists());
        }
    }

    public static function administrativos(): array
    {
        $casos = [];
        foreach ([RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $rol) {
            foreach (['ninguna', 'paciente', 'kinesiologa'] as $historia) {
                $casos[$rol->name.' '.$historia] = [$rol, $historia];
            }
        }

        return $casos;
    }

    public function test_an_explicit_retained_clinical_role_still_requires_its_document_relation(): void
    {
        $actor = $this->paciente->user;
        $actor->assignRole(RoleEnum::ADMINISTRADOR->value);
        $this->assertTrue(Gate::forUser($actor)->allows('download', $this->documento));
        $otro = $this->usuario(RoleEnum::SUPERADMIN);
        $otro->assignRole(RoleEnum::PACIENTE->value);
        $this->assertFalse(Gate::forUser($otro)->allows('download', $this->documento));
    }

    public function test_cached_relations_cannot_spoof_document_ownership(): void
    {
        $ajeno = $this->usuario(RoleEnum::PACIENTE);
        $falso = Paciente::create(['user_id' => $ajeno->id, 'activo' => true]);
        $this->documento->forceFill(['paciente_id' => $falso->id]);
        $this->documento->setRelation('paciente', $falso);
        $this->assertFalse(Gate::forUser($ajeno)->allows('download', $this->documento));
        $this->assertTrue(Gate::forUser($this->paciente->user)->allows('download', $this->documento));
    }

    private function usuario(RoleEnum $rol): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol->value);

        return $user;
    }

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-policy'], ['nombre' => 'Título', 'activo' => true]);

        return Kinesiologa::create(['user_id' => $this->usuario(RoleEnum::KINESIOLOGO)->id, 'titulo_profesional_id' => $titulo->id]);
    }
}
