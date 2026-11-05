<?php

namespace Tests\Feature\Services\Agenda;

use App\Enums\RoleEnum;
use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Agenda\AgendaAtencionQueryService;
use App\Services\Agenda\AgendaProfesionalQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendaProfesionalQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgendaProfesionalQueryService $service;

    private User $profesional;

    private Kinesiologa $propia;

    private Kinesiologa $ajena;

    private Paciente $paciente;

    private Turno $turno;

    private CarbonImmutable $fecha;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        // Desalinear users.id y kinesiologas.id para detectar confusión de identidades.
        User::factory()->create();
        $this->profesional = $this->usuario(RoleEnum::KINESIOLOGO);
        $this->propia = Kinesiologa::create(['user_id' => $this->profesional->id]);
        $this->ajena = Kinesiologa::create(['user_id' => $this->usuario(RoleEnum::KINESIOLOGO)->id]);
        $this->paciente = Paciente::create(['user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true]);
        $this->turno = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'inicio_at' => '2026-11-05 06:30:00', 'fin_at' => '2026-11-05 07:15:00',
        ]);
        Turno::factory()->for($this->paciente)->for($this->ajena)->create([
            'inicio_at' => '2026-11-05 06:30:00', 'fin_at' => '2026-11-05 07:00:00',
        ]);
        $this->fecha = CarbonImmutable::parse('2026-11-05', config('app.timezone'));
        $this->service = app(AgendaProfesionalQueryService::class);
    }

    public function test_rechaza_una_consulta_sin_identidad_autenticada_antes_de_leer_turnos(): void
    {
        $this->assertGuest('web');
        $this->assertRechazoSinDatos($this->propia->id);
    }

    #[DataProvider('rolesSinPermisoProfesional')]
    public function test_el_perfil_no_otorga_permiso_sin_el_rol_profesional(?RoleEnum $rol): void
    {
        $actor = $this->usuario($rol);
        $perfil = Kinesiologa::create(['user_id' => $actor->id]);
        $this->actingAs($actor, 'web');

        $this->assertRechazoSinDatos($perfil->id);
    }

    public static function rolesSinPermisoProfesional(): array
    {
        return [
            'sin rol' => [null],
            'paciente' => [RoleEnum::PACIENTE],
            'administrador' => [RoleEnum::ADMINISTRADOR],
            'superadmin' => [RoleEnum::SUPERADMIN],
        ];
    }

    public function test_el_rol_sin_relacion_profesional_persistida_no_otorga_acceso(): void
    {
        $actor = $this->usuario(RoleEnum::KINESIOLOGO);
        $actor->setRelation('kinesiologa', $this->propia);
        $this->actingAs($actor, 'web');

        $this->assertRechazoSinDatos($this->propia->id);
    }

    public function test_la_profesional_autenticada_consulta_solo_su_agenda_administrativa(): void
    {
        $this->actingAs($this->profesional, 'web');

        $jornada = $this->service->informacionDeJornada($this->propia->id, $this->fecha);
        $periodo = $this->service->informacionDePeriodo($this->propia->id, $this->fecha, $this->fecha);

        $this->assertSame([$this->turno->id], array_column($jornada, 'id'));
        $this->assertEquals($periodo, $jornada);
        $this->assertSame($this->propia->id, $jornada[0]['kinesiologa']['id']);
        $this->assertSame($this->paciente->id, $jornada[0]['paciente']['id']);
        $this->assertSame(['id', 'paciente', 'kinesiologa', 'tipo', 'estado', 'inicio_at', 'fin_at'], array_keys($jornada[0]));
    }

    #[DataProvider('identificadoresFueraDeAlcance')]
    public function test_identificadores_arbitrarios_no_amplian_el_ambito(string $caso): void
    {
        $this->actingAs($this->profesional, 'web');
        $id = match ($caso) {
            'ajena' => $this->ajena->id,
            'inexistente' => 999999,
            'negativo' => -1,
            'usuario' => $this->profesional->id,
        };

        $this->assertNotSame($this->propia->id, $id);
        $this->assertRechazoSinDatos($id);
    }

    public static function identificadoresFueraDeAlcance(): array
    {
        return [['ajena'], ['inexistente'], ['negativo'], ['usuario']];
    }

    public function test_manipular_la_relacion_precargada_no_concede_acceso_a_la_otra_profesional(): void
    {
        $this->profesional->setRelation('kinesiologa', $this->ajena);
        $this->actingAs($this->profesional, 'web');

        $this->assertRechazoSinDatos($this->ajena->id);
        $this->assertSame([$this->turno->id], array_column(
            $this->service->informacionDeJornada($this->propia->id, $this->fecha), 'id'
        ));
    }

    public function test_revalida_un_rol_revocado_aunque_el_guard_conserve_el_rol_cargado(): void
    {
        $roles = $this->profesional->roles;
        $this->profesional->removeRole(RoleEnum::KINESIOLOGO->value);
        $this->profesional->setRelation('roles', $roles);
        $this->actingAs($this->profesional, 'web');

        $this->assertRechazoSinDatos($this->propia->id);
    }

    public function test_revalida_la_relacion_profesional_persistida_ante_un_cambio_de_titular(): void
    {
        $this->profesional->load('kinesiologa');
        $this->actingAs($this->profesional, 'web');
        DB::table('kinesiologas')->where('id', $this->propia->id)->update([
            'user_id' => $this->usuario(RoleEnum::KINESIOLOGO)->id,
        ]);

        $this->assertRechazoSinDatos($this->propia->id);
    }

    public function test_un_objeto_user_no_persistido_no_equivale_a_identidad_autenticada_valida(): void
    {
        $actor = new User;
        $actor->id = $this->profesional->id;
        $this->actingAs($actor, 'web');

        $this->assertRechazoSinDatos($this->propia->id);
    }

    public function test_la_consulta_interna_conserva_su_independencia_de_la_sesion_para_uc29(): void
    {
        $this->assertGuest('web');
        $this->assertRechazoSinDatos($this->propia->id);

        $interno = app(AgendaAtencionQueryService::class)->informacionDePeriodo(
            $this->propia, $this->fecha, $this->fecha
        );

        $this->assertSame([$this->turno->id], array_column($interno, 'id'));
    }

    public function test_no_amplia_los_permisos_de_uc06_ni_los_de_configuracion_de_agenda(): void
    {
        $this->actingAs($this->profesional, 'web');
        $this->service->informacionDeJornada($this->propia->id, $this->fecha);

        foreach (['view', 'confirmar', 'cancelar'] as $capacidad) {
            $this->assertFalse(Gate::forUser($this->profesional)->allows($capacidad, $this->turno));
            $this->assertTrue(Gate::forUser($this->paciente->user)->allows($capacidad, $this->turno));
        }
        $configuracion = ConfiguracionAgenda::factory()->for($this->propia)->create();
        $this->assertTrue(Gate::forUser($this->profesional)->allows('view', $configuracion));
        $this->assertFalse(Gate::forUser($this->ajena->user)->allows('view', $configuracion));
    }

    private function usuario(?RoleEnum $rol): User
    {
        $user = User::factory()->create();
        if ($rol !== null) {
            $user->assignRole($rol->value);
        }

        return $user;
    }

    private function assertRechazoSinDatos(int $kinesiologaId): void
    {
        $antes = DB::table('turnos')->orderBy('id')->get()->toJson();
        $conexion = DB::connection();
        $conexion->enableQueryLog();
        $conexion->flushQueryLog();
        try {
            $this->service->informacionDePeriodo($kinesiologaId, $this->fecha, $this->fecha);
            $this->fail('La consulta debía ser rechazada.');
        } catch (AuthorizationException $error) {
            $this->assertSame('No tenés autorización para consultar esta agenda.', $error->getMessage());
        } finally {
            $consultas = $conexion->getQueryLog();
            $conexion->disableQueryLog();
            $conexion->flushQueryLog();
        }
        foreach ($consultas as $consulta) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($consulta['query']));
            $this->assertDoesNotMatchRegularExpression('/\b(turnos|pacientes|documentos_clinicos)\b/i', $consulta['query']);
        }
        $this->assertSame($antes, DB::table('turnos')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('auditorias', 0);
    }
}
