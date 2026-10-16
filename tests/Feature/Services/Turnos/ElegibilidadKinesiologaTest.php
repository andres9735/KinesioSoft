<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\RoleEnum;
use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ElegibilidadKinesiologaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
    }

    public function test_complete_active_verified_professional_is_eligible_without_any_agenda(): void
    {
        $kinesiologa = $this->profesionalCompleta();

        $this->assertTrue($kinesiologa->user->perfilCompleto());
        $this->assertTrue($kinesiologa->user->puedeAtenderComoKinesiologo());
        $this->assertTrue(app(ElegibilidadKinesiologaService::class)->esElegible($kinesiologa));
        $this->assertDatabaseCount('configuraciones_agenda', 0);
        $this->assertDatabaseCount('franjas_horarias_agenda', 0);
    }

    #[DataProvider('rolesIncorrectos')]
    public function test_other_roles_are_not_eligible_even_with_a_complete_professional_relation(RoleEnum $rol): void
    {
        $kinesiologa = $this->profesionalCompleta();
        $kinesiologa->user->syncRoles([$rol->value]);
        $user = $kinesiologa->user->fresh();

        $this->assertTrue($user->tienePerfilProfesionalKinesiologiaCompleto());
        $this->assertFalse($user->puedeAtenderComoKinesiologo());
        $this->assertFalse(app(ElegibilidadKinesiologaService::class)->esElegible($kinesiologa->fresh()));
    }

    /**
     * @return array<string, array{RoleEnum}>
     */
    public static function rolesIncorrectos(): array
    {
        return [
            'paciente' => [RoleEnum::PACIENTE],
            'administrador' => [RoleEnum::ADMINISTRADOR],
            'superadmin' => [RoleEnum::SUPERADMIN],
        ];
    }

    #[DataProvider('condicionesFaltantes')]
    public function test_each_missing_canonical_condition_prevents_eligibility(string $condicion): void
    {
        $kinesiologa = $this->profesionalCompleta();
        $this->quitarCondicion($kinesiologa, $condicion);

        $this->assertFalse($kinesiologa->user->fresh()->puedeAtenderComoKinesiologo());
        $this->assertFalse(app(ElegibilidadKinesiologaService::class)->esElegible($kinesiologa->fresh()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function condicionesFaltantes(): array
    {
        return [
            'cuenta inactiva' => ['activo'],
            'correo no verificado' => ['email_verified_at'],
            'DNI' => ['dni'],
            'fecha de nacimiento' => ['fecha_nacimiento'],
            'sexo' => ['sexo'],
            'teléfono' => ['telefono'],
            'título' => ['titulo'],
            'sin matrículas' => ['matricula'],
            'sólo matrícula inactiva' => ['matricula_inactiva'],
            'sin especialidades' => ['especialidad'],
        ];
    }

    public function test_user_without_a_professional_profile_is_not_eligible(): void
    {
        $user = $this->usuarioCompleto();

        $this->assertFalse($user->tienePerfilProfesionalKinesiologiaCompleto());
        $this->assertFalse($user->puedeAtenderComoKinesiologo());
    }

    public function test_one_active_license_is_sufficient_among_multiple_licenses(): void
    {
        $kinesiologa = $this->profesionalCompleta();
        $kinesiologa->matriculasProfesionales()->firstOrFail()->update(['activa' => false]);
        $jurisdiccion = Jurisdiccion::create(['nombre' => 'Segunda', 'slug' => 'segunda', 'activo' => true]);
        $colegio = ColegioEmisor::create([
            'nombre' => 'Segundo colegio', 'sigla' => 'SEG',
            'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true,
        ]);
        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'SEG-123',
            'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id,
            'activa' => true,
        ]);

        $this->assertTrue($kinesiologa->user->puedeAtenderComoKinesiologo());
        $this->assertTrue($kinesiologa->user->fresh()->load($this->relacionesDeEvaluacion())->puedeAtenderComoKinesiologo());
    }

    #[DataProvider('catalogosHistoricos')]
    public function test_inactive_historical_catalogs_do_not_add_eligibility_requirements(string $catalogo): void
    {
        $kinesiologa = $this->profesionalCompleta();
        $registro = $catalogo === 'titulo'
            ? $kinesiologa->tituloProfesional
            : $kinesiologa->especialidades()->firstOrFail();
        $registro->update(['activo' => false]);
        $user = $kinesiologa->user->fresh();

        $this->assertTrue($user->perfilCompleto());
        $this->assertTrue($user->puedeAtenderComoKinesiologo());
        $this->assertTrue(app(ElegibilidadKinesiologaService::class)->esElegible($kinesiologa->fresh()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function catalogosHistoricos(): array
    {
        return ['título' => ['titulo'], 'especialidad' => ['especialidad']];
    }

    #[DataProvider('estadosDeEvaluacion')]
    public function test_normal_and_preloaded_evaluations_agree_without_redundant_queries(string $condicion, bool $esperado): void
    {
        if ($condicion === 'sin_perfil') {
            $user = $this->usuarioCompleto();
        } else {
            $kinesiologa = $this->profesionalCompleta();
            if ($condicion !== 'valida') {
                $this->quitarCondicion($kinesiologa, $condicion);
            }
            $user = $kinesiologa->user->fresh();
        }
        $normal = $user->puedeAtenderComoKinesiologo();
        $precargado = $user->fresh()->load($this->relacionesDeEvaluacion());

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $resultado = $precargado->puedeAtenderComoKinesiologo();
            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame($esperado, $normal);
        $this->assertSame($normal, $resultado);
        $this->assertSame([], $consultas);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function estadosDeEvaluacion(): array
    {
        return [
            'válida' => ['valida', true],
            'cuenta inactiva' => ['activo', false],
            'correo no verificado' => ['email_verified_at', false],
            'datos personales incompletos' => ['dni', false],
            'sin perfil' => ['sin_perfil', false],
            'sin título' => ['titulo', false],
            'sin matrículas' => ['matricula', false],
            'sólo matrícula inactiva' => ['matricula_inactiva', false],
            'sin especialidades' => ['especialidad', false],
        ];
    }

    public function test_normal_evaluation_does_not_memoize_a_boolean_after_license_changes(): void
    {
        $kinesiologa = $this->profesionalCompleta();
        $user = $kinesiologa->user;
        $matricula = $kinesiologa->matriculasProfesionales()->firstOrFail();

        $this->assertTrue($user->puedeAtenderComoKinesiologo());
        $matricula->update(['activa' => false]);
        $this->assertFalse($user->puedeAtenderComoKinesiologo());
        $matricula->update(['activa' => true]);
        $this->assertTrue($user->puedeAtenderComoKinesiologo());
    }

    public function test_list_and_individual_evaluation_use_the_same_user_predicate(): void
    {
        $valida = $this->profesionalCompleta();
        $historica = $this->profesionalCompleta();
        $historica->tituloProfesional->update(['activo' => false]);
        $historica->especialidades()->firstOrFail()->update(['activo' => false]);
        $inactiva = $this->profesionalCompleta();
        $this->quitarCondicion($inactiva, 'activo');
        $incompleta = $this->profesionalCompleta();
        $this->quitarCondicion($incompleta, 'especialidad');
        $sinVerificar = $this->profesionalCompleta();
        $this->quitarCondicion($sinVerificar, 'email_verified_at');
        $rolIncorrecto = $this->profesionalCompleta();
        $rolIncorrecto->user->syncRoles([RoleEnum::ADMINISTRADOR->value]);
        $service = app(ElegibilidadKinesiologaService::class);
        $listado = $service->listarElegibles();

        $this->assertEqualsCanonicalizing([$valida->id, $historica->id], $listado->modelKeys());
        foreach ([$valida, $historica, $inactiva, $incompleta, $sinVerificar, $rolIncorrecto] as $kinesiologa) {
            $actual = $kinesiologa->fresh();
            $esperado = $actual->user->puedeAtenderComoKinesiologo();
            $this->assertSame($esperado, $service->esElegible($actual));
            $this->assertSame($esperado, $listado->contains($kinesiologa));
        }
        foreach ($listado as $kinesiologa) {
            $this->assertInstanceOf(Kinesiologa::class, $kinesiologa);
        }
    }

    public function test_listing_uses_a_constant_number_of_queries_for_multiple_professionals(): void
    {
        $this->profesionalCompleta();
        $service = app(ElegibilidadKinesiologaService::class);
        $cantidades = [];

        foreach ([1, 4] as $cantidad) {
            if ($cantidad === 4) {
                for ($i = 0; $i < 3; $i++) {
                    $this->profesionalCompleta();
                }
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $listado = $service->listarElegibles();
                $cantidades[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }
            $this->assertCount($cantidad, $listado);
        }

        $this->assertGreaterThan(0, $cantidades[0]);
        $this->assertSame($cantidades[0], $cantidades[1]);
    }

    /**
     * @return list<string>
     */
    private function relacionesDeEvaluacion(): array
    {
        return ['roles', 'kinesiologa.matriculasProfesionales', 'kinesiologa.especialidades'];
    }

    private function quitarCondicion(Kinesiologa $kinesiologa, string $condicion): void
    {
        match ($condicion) {
            'activo' => $kinesiologa->user->update(['activo' => false]),
            'email_verified_at' => $kinesiologa->user->forceFill(['email_verified_at' => null])->save(),
            'dni', 'fecha_nacimiento', 'sexo', 'telefono' => $kinesiologa->user->update([$condicion => null]),
            'titulo' => $kinesiologa->update(['titulo_profesional_id' => null]),
            'matricula' => $kinesiologa->matriculasProfesionales()->delete(),
            'matricula_inactiva' => $kinesiologa->matriculasProfesionales()->firstOrFail()->update(['activa' => false]),
            'especialidad' => $kinesiologa->especialidades()->detach(),
        };
    }

    private function usuarioCompleto(): User
    {
        $user = User::factory()->create([
            'dni' => fake()->unique()->numerify('########'),
            'fecha_nacimiento' => '1990-01-01', 'sexo' => 'femenino',
            'telefono' => '3764123456', 'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        return $user;
    }

    private function profesionalCompleta(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-test'], ['nombre' => 'Título test', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-test'], ['nombre' => 'Especialidad test', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-test'], ['nombre' => 'Jurisdicción test', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'TEST'], [
            'nombre' => 'Colegio test', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true,
        ]);
        $user = $this->usuarioCompleto();
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id,
            'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true,
        ]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa->fresh();
    }
}
