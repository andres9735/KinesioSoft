<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InactiveCatalogValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
    }

    private function catalogos(): array
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $jurisdiccion1 = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $jurisdiccion2 = Jurisdiccion::create([
            'nombre' => 'Corrientes',
            'slug' => 'corrientes',
            'activo' => true,
        ]);

        $colegio1 = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'CKM',
            'jurisdiccion_id' => $jurisdiccion1->id,
            'activo' => true,
        ]);

        $colegio2 = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Corrientes',
            'sigla' => 'CKC',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'activo' => true,
        ]);

        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        return compact(
            'titulo',
            'jurisdiccion1',
            'jurisdiccion2',
            'colegio1',
            'colegio2',
            'especialidad'
        );
    }

    private function kinesiologaIncompleta(): User
    {
        $user = User::factory()->create([
            'name' => 'Silvia',
            'apellido' => 'Incompleta',
            'email' => 'incompleta_' . uniqid() . '@example.com',
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        return $user;
    }

    private function kinesiologaCompleta(array $catalogos): User
    {
        $user = User::factory()->create([
            'name' => 'Silvia',
            'apellido' => 'Completa',
            'email' => 'completa_' . uniqid() . '@example.com',
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764123456',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $catalogos['titulo']->id,
            'consultorio_id' => null,
        ]);

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => 'MP-1001',
            'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
            'colegio_emisor_id' => $catalogos['colegio1']->id,
            'activa' => true,
        ]);

        $kinesiologa->especialidades()->sync([
            $catalogos['especialidad']->id,
        ]);

        return $user;
    }

    private function datosPerfil(array $catalogos): array
    {
        return [
            'dni' => '30456321',
            'fecha_nacimiento' => '1993-08-09',
            'sexo' => 'femenino',
            'telefono' => '3758-451963',
            'titulo_profesional_id' => $catalogos['titulo']->id,
            'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
            'colegio_emisor_id' => $catalogos['colegio1']->id,
            'nro_matricula_profesional' => 'MP-2001',
            'especialidades' => [
                $catalogos['especialidad']->id,
            ],
        ];
    }

    public function test_initial_profile_rejects_inactive_professional_title(): void
    {
        $catalogos = $this->catalogos();
        $catalogos['titulo']->update(['activo' => false]);

        $user = $this->kinesiologaIncompleta();

        $response = $this
            ->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.kinesiologa.actualizar'),
                $this->datosPerfil($catalogos)
            );

        $response
            ->assertRedirect(route('perfil.kinesiologa.completar'))
            ->assertSessionHasErrors('titulo_profesional_id');

        $this->assertDatabaseMissing('kinesiologas', [
            'user_id' => $user->id,
        ]);
    }

    public function test_initial_profile_rejects_inactive_jurisdiction(): void
    {
        $catalogos = $this->catalogos();
        $catalogos['jurisdiccion1']->update(['activo' => false]);

        $user = $this->kinesiologaIncompleta();

        $response = $this
            ->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.kinesiologa.actualizar'),
                $this->datosPerfil($catalogos)
            );

        $response
            ->assertRedirect(route('perfil.kinesiologa.completar'))
            ->assertSessionHasErrors('jurisdiccion_id');

        $this->assertDatabaseMissing('kinesiologas', [
            'user_id' => $user->id,
        ]);
    }

    public function test_initial_profile_rejects_inactive_issuing_college(): void
    {
        $catalogos = $this->catalogos();
        $catalogos['colegio1']->update(['activo' => false]);

        $user = $this->kinesiologaIncompleta();

        $response = $this
            ->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.kinesiologa.actualizar'),
                $this->datosPerfil($catalogos)
            );

        $response
            ->assertRedirect(route('perfil.kinesiologa.completar'))
            ->assertSessionHasErrors('colegio_emisor_id');

        $this->assertDatabaseMissing('kinesiologas', [
            'user_id' => $user->id,
        ]);
    }

    public function test_initial_profile_rejects_inactive_specialty(): void
    {
        $catalogos = $this->catalogos();
        $catalogos['especialidad']->update(['activo' => false]);

        $user = $this->kinesiologaIncompleta();

        $response = $this
            ->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.kinesiologa.actualizar'),
                $this->datosPerfil($catalogos)
            );

        $response
            ->assertRedirect(route('perfil.kinesiologa.completar'))
            ->assertSessionHasErrors('especialidades.0');

        $this->assertDatabaseMissing('kinesiologas', [
            'user_id' => $user->id,
        ]);
    }

    public function test_license_store_rejects_inactive_jurisdiction(): void
    {
        $catalogos = $this->catalogos();
        $user = $this->kinesiologaCompleta($catalogos);

        $catalogos['jurisdiccion2']->update(['activo' => false]);

        $response = $this
            ->from(route('perfil.kinesiologa.matriculas.create'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $catalogos['jurisdiccion2']->id,
                'colegio_emisor_id' => $catalogos['colegio2']->id,
                'nro_matricula_profesional' => 'MP-2002',
            ]);

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.create')
            )
            ->assertSessionHasErrors('jurisdiccion_id');

        $this->assertDatabaseMissing(
            'matriculas_profesionales_kinesiologia',
            [
                'kinesiologa_id' => $user->kinesiologa->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion2']->id,
            ]
        );
    }

    public function test_license_store_rejects_inactive_issuing_college(): void
    {
        $catalogos = $this->catalogos();
        $user = $this->kinesiologaCompleta($catalogos);

        $catalogos['colegio2']->update(['activo' => false]);

        $response = $this
            ->from(route('perfil.kinesiologa.matriculas.create'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $catalogos['jurisdiccion2']->id,
                'colegio_emisor_id' => $catalogos['colegio2']->id,
                'nro_matricula_profesional' => 'MP-2002',
            ]);

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.create')
            )
            ->assertSessionHasErrors('colegio_emisor_id');

        $this->assertDatabaseMissing(
            'matriculas_profesionales_kinesiologia',
            [
                'kinesiologa_id' => $user->kinesiologa->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion2']->id,
            ]
        );
    }

    public function test_license_update_rejects_changing_to_inactive_jurisdiction(): void
    {
        $catalogos = $this->catalogos();
        $user = $this->kinesiologaCompleta($catalogos);

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $catalogos['jurisdiccion2']->update(['activo' => false]);

        $response = $this
            ->from(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            )
            ->actingAs($user)
            ->put(
                route(
                    'perfil.kinesiologa.matriculas.update',
                    $matricula
                ),
                [
                    'jurisdiccion_id' =>
                    $catalogos['jurisdiccion2']->id,
                    'colegio_emisor_id' =>
                    $catalogos['colegio2']->id,
                    'nro_matricula_profesional' => 'MP-2003',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            )
            ->assertSessionHasErrors('jurisdiccion_id');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'jurisdiccion_id' =>
                $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' =>
                $catalogos['colegio1']->id,
                'nro_matricula_profesional' => 'MP-1001',
            ]
        );
    }

    public function test_license_update_rejects_changing_to_inactive_issuing_college(): void
    {
        $catalogos = $this->catalogos();
        $user = $this->kinesiologaCompleta($catalogos);

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $catalogos['colegio2']->update(['activo' => false]);

        $response = $this
            ->from(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            )
            ->actingAs($user)
            ->put(
                route(
                    'perfil.kinesiologa.matriculas.update',
                    $matricula
                ),
                [
                    'jurisdiccion_id' =>
                    $catalogos['jurisdiccion2']->id,
                    'colegio_emisor_id' =>
                    $catalogos['colegio2']->id,
                    'nro_matricula_profesional' => 'MP-2003',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            )
            ->assertSessionHasErrors('colegio_emisor_id');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'jurisdiccion_id' =>
                $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' =>
                $catalogos['colegio1']->id,
                'nro_matricula_profesional' => 'MP-1001',
            ]
        );
    }

    public function test_license_update_allows_keeping_current_inactive_catalogs(): void
    {
        $catalogos = $this->catalogos();
        $user = $this->kinesiologaCompleta($catalogos);

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $catalogos['jurisdiccion1']->update(['activo' => false]);
        $catalogos['colegio1']->update(['activo' => false]);

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'perfil.kinesiologa.matriculas.update',
                    $matricula
                ),
                [
                    'jurisdiccion_id' =>
                    $catalogos['jurisdiccion1']->id,
                    'colegio_emisor_id' =>
                    $catalogos['colegio1']->id,
                    'nro_matricula_profesional' => 'MP-1999',
                ]
            );

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.index')
            )
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'jurisdiccion_id' =>
                $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' =>
                $catalogos['colegio1']->id,
                'nro_matricula_profesional' => 'MP-1999',
            ]
        );
    }
}
