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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MisMatriculasProfesionalesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
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
            'nombre' => 'Colegio de Kinesiólogos y Fisioterapeutas de Misiones',
            'sigla' => 'CKFM',
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

    private function kinesiologaCompleta(
        string $emailPrefix = 'kinesio',
        string $nroMatricula = '0755',
        string $jurisdiccionSlug = 'misiones',
        string $colegioSigla = 'CKFM'
    ): User {
        if (TituloProfesional::count() === 0) {
            $this->catalogos();
        }

        $titulo = TituloProfesional::where('slug', 'kinesiologo')
            ->firstOrFail();

        $jurisdiccion = Jurisdiccion::where('slug', $jurisdiccionSlug)
            ->firstOrFail();

        $colegio = ColegioEmisor::where('sigla', $colegioSigla)
            ->firstOrFail();

        $especialidad = EspecialidadKinesiologia::where(
            'slug',
            'kinesiologia-deportiva'
        )->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Silvia',
            'apellido' => 'Prueba',
            'email' => $emailPrefix . '_' . uniqid() . '@example.com',
            'dni' => (string) random_int(30000000, 39999999),
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764' . random_int(100000, 999999),
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
            'consultorio_id' => null,
        ]);

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => $nroMatricula,
            'jurisdiccion_id' => $jurisdiccion->id,
            'colegio_emisor_id' => $colegio->id,
            'activa' => true,
        ]);

        $kinesiologa->especialidades()->sync([
            $especialidad->id,
        ]);

        return $user;
    }

    private function pacienteCompleto(): User
    {
        $user = User::factory()->create([
            'name' => 'Paciente',
            'apellido' => 'Prueba',
            'email' => 'paciente_' . uniqid() . '@example.com',
            'dni' => '40111222',
            'fecha_nacimiento' => '1995-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764000000',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        return $user;
    }

    public function test_kinesiologa_can_view_matriculas_index(): void
    {
        $user = $this->kinesiologaCompleta();

        $response = $this
            ->actingAs($user)
            ->get(route('perfil.kinesiologa.matriculas.index'));

        $response->assertOk();
        $response->assertSee('Mis matrículas profesionales');
        $response->assertSee('0755');
    }

    public function test_non_kinesiologa_cannot_access_matriculas_index(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this
            ->actingAs($user)
            ->get(route('perfil.kinesiologa.matriculas.index'));

        $response->assertForbidden();
    }

    public function test_non_kinesiologa_cannot_store_matricula(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => 999,
                'colegio_emisor_id' => 999,
                'nro_matricula_profesional' => '0955',
            ]);

        $response->assertForbidden();
    }

    public function test_kinesiologa_can_view_create_form(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion1 = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $colegio1 = ColegioEmisor::where('sigla', 'CKFM')
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->get(route('perfil.kinesiologa.matriculas.create'));

        $response->assertOk();
        $response->assertSee('Nueva matrícula profesional');
        $response->assertSee($jurisdiccion1->nombre);
        $response->assertSee($colegio1->nombre);
    }

    public function test_kinesiologa_can_store_valid_matricula_for_new_jurisdiccion(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $jurisdiccion2->id,
                'colegio_emisor_id' => $colegio2->id,
                'nro_matricula_profesional' => '0955',
            ]);

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'kinesiologa_id' => $user->kinesiologa->id,
                'jurisdiccion_id' => $jurisdiccion2->id,
                'colegio_emisor_id' => $colegio2->id,
                'nro_matricula_profesional' => '0955',
                'activa' => 1,
            ]
        );
    }

    public function test_store_rejects_second_matricula_for_same_jurisdiccion(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion1 = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $colegio1 = ColegioEmisor::where('sigla', 'CKFM')
            ->firstOrFail();

        $response = $this
            ->from(route('perfil.kinesiologa.matriculas.create'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $jurisdiccion1->id,
                'colegio_emisor_id' => $colegio1->id,
                'nro_matricula_profesional' => '0955',
            ]);

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.create')
        );

        $response->assertSessionHasErrors('jurisdiccion_id');
    }

    public function test_store_rejects_colegio_from_other_jurisdiccion(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion1 = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $response = $this
            ->from(route('perfil.kinesiologa.matriculas.create'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $jurisdiccion1->id,
                'colegio_emisor_id' => $colegio2->id,
                'nro_matricula_profesional' => '0955',
            ]);

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.create')
        );

        $response->assertSessionHasErrors('colegio_emisor_id');
    }

    public function test_store_rejects_license_number_already_used_in_same_jurisdiction(): void
    {
        $this->kinesiologaCompleta(
            'titular',
            '0755',
            'misiones',
            'CKFM'
        );

        $user = $this->kinesiologaCompleta(
            'solicitante',
            '0855',
            'corrientes',
            'CKC'
        );

        $jurisdiccion = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $colegio = ColegioEmisor::where('sigla', 'CKFM')
            ->firstOrFail();

        $response = $this
            ->from(route('perfil.kinesiologa.matriculas.create'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $jurisdiccion->id,
                'colegio_emisor_id' => $colegio->id,
                'nro_matricula_profesional' => '0755',
            ]);

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.create')
            )
            ->assertSessionHasErrors(
                'nro_matricula_profesional'
            );

        $this->assertDatabaseMissing(
            'matriculas_profesionales_kinesiologia',
            [
                'kinesiologa_id' => $user->kinesiologa->id,
                'jurisdiccion_id' => $jurisdiccion->id,
            ]
        );
    }

    public function test_store_allows_same_license_number_in_different_jurisdiction(): void
    {
        $user = $this->kinesiologaCompleta(
            'kinesio',
            '0755',
            'misiones',
            'CKFM'
        );

        $jurisdiccion = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.matriculas.store'), [
                'jurisdiccion_id' => $jurisdiccion->id,
                'colegio_emisor_id' => $colegio->id,
                'nro_matricula_profesional' => '0755',
            ]);

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.index')
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'kinesiologa_id' => $user->kinesiologa->id,
                'jurisdiccion_id' => $jurisdiccion->id,
                'nro_matricula_profesional' => '0755',
            ]
        );
    }

    public function test_kinesiologa_can_view_edit_form_for_own_matricula(): void
    {
        $user = $this->kinesiologaCompleta();

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            );

        $response->assertOk();
        $response->assertSee('Editar matrícula profesional');
        $response->assertSee($matricula->nro_matricula_profesional);
    }

    public function test_kinesiologa_cannot_view_edit_form_for_foreign_matricula(): void
    {
        $user = $this->kinesiologaCompleta(
            'k1',
            '0755'
        );

        $other = $this->kinesiologaCompleta(
            'k2',
            '0855'
        );

        $foreignMatricula = $other->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $foreignMatricula
                )
            );

        $response->assertForbidden();
    }

    public function test_kinesiologa_cannot_update_foreign_matricula(): void
    {
        $user = $this->kinesiologaCompleta(
            'propietaria',
            '0755'
        );

        $other = $this->kinesiologaCompleta(
            'otra',
            '0855'
        );

        $foreignMatricula = $other->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'perfil.kinesiologa.matriculas.update',
                    $foreignMatricula
                ),
                [
                    /*
                    * Los datos son deliberadamente inválidos para
                    * comprobar que la autorización se evalúa antes
                    * que la validación.
                    */
                    'jurisdiccion_id' => 999,
                    'colegio_emisor_id' => 999,
                    'nro_matricula_profesional' => '',
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $foreignMatricula->id,
                'kinesiologa_id' => $other->kinesiologa->id,
                'nro_matricula_profesional' => '0855',
            ]
        );
    }

    public function test_kinesiologa_can_update_own_matricula(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $user->kinesiologa->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'colegio_emisor_id' => $colegio2->id,
            'activa' => true,
        ]);

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->where('jurisdiccion_id', $jurisdiccion2->id)
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'perfil.kinesiologa.matriculas.update',
                    $matricula
                ),
                [
                    'nro_matricula_profesional' => '0999',
                ]
            );

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'nro_matricula_profesional' => '0999',
            ]
        );
    }

    public function test_update_rejects_changing_jurisdiccion_even_when_destination_is_free(): void
    {
        $user = $this->kinesiologaCompleta();

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

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
                    'jurisdiccion_id' => $jurisdiccion2->id,
                    'nro_matricula_profesional' => '0999',
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
                'jurisdiccion_id' => $matricula->jurisdiccion_id,
                'colegio_emisor_id' => $matricula->colegio_emisor_id,
                'nro_matricula_profesional' => '0755',
            ]
        );
    }

    public function test_update_rejects_changing_colegio_within_same_jurisdiccion(): void
    {
        $user = $this->kinesiologaCompleta();

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $jurisdiccion = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $otroColegio = ColegioEmisor::create([
            'nombre' => 'Otro Colegio Profesional de Misiones',
            'sigla' => 'OCPM',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

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
                    'colegio_emisor_id' => $otroColegio->id,
                    'nro_matricula_profesional' => '0999',
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
                'colegio_emisor_id' => $matricula->colegio_emisor_id,
                'nro_matricula_profesional' => '0755',
            ]
        );
    }

    public function test_update_rejects_license_number_used_by_another_kinesiologist_in_same_jurisdiction(): void
    {
        $this->kinesiologaCompleta(
            'titular',
            '0755',
            'misiones',
            'CKFM'
        );

        $user = $this->kinesiologaCompleta(
            'editora',
            '0855',
            'misiones',
            'CKFM'
        );

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $jurisdiccion = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $colegio = ColegioEmisor::where('sigla', 'CKFM')
            ->firstOrFail();

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
                    'jurisdiccion_id' => $jurisdiccion->id,
                    'colegio_emisor_id' => $colegio->id,
                    'nro_matricula_profesional' => '0755',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            )
            ->assertSessionHasErrors(
                'nro_matricula_profesional'
            );

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'nro_matricula_profesional' => '0855',
            ]
        );
    }

    public function test_update_uses_persisted_jurisdiccion_for_license_number_uniqueness(): void
    {
        $this->kinesiologaCompleta(
            'titular',
            '0755',
            'misiones',
            'CKFM'
        );

        $user = $this->kinesiologaCompleta(
            'editora',
            '0855',
            'misiones',
            'CKFM'
        );

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

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
                    /*
                     * Se intenta falsear la jurisdicción para que el
                     * número 0755 parezca libre en Corrientes.
                     */
                    'jurisdiccion_id' => $jurisdiccion2->id,
                    'nro_matricula_profesional' => '0755',
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'perfil.kinesiologa.matriculas.edit',
                    $matricula
                )
            )
            ->assertSessionHasErrors([
                'jurisdiccion_id',
                'nro_matricula_profesional',
            ]);

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'jurisdiccion_id' => $matricula->jurisdiccion_id,
                'nro_matricula_profesional' => '0855',
            ]
        );
    }

    public function test_update_allows_keeping_own_license_number(): void
    {
        $user = $this->kinesiologaCompleta(
            'propietaria',
            '0755',
            'misiones',
            'CKFM'
        );

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->put(
                route(
                    'perfil.kinesiologa.matriculas.update',
                    $matricula
                ),
                [
                    'nro_matricula_profesional' => '0755',
                ]
            );

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.index')
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'nro_matricula_profesional' => '0755',
            ]
        );
    }

    public function test_database_rejects_duplicate_license_number_in_same_jurisdiction(): void
    {
        $this->kinesiologaCompleta(
            'titular',
            '0755',
            'misiones',
            'CKFM'
        );

        $other = $this->kinesiologaCompleta(
            'otra',
            '0855',
            'corrientes',
            'CKC'
        );

        $jurisdiccion = Jurisdiccion::where('slug', 'misiones')
            ->firstOrFail();

        $colegio = ColegioEmisor::where('sigla', 'CKFM')
            ->firstOrFail();

        $this->expectException(QueryException::class);

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $other->kinesiologa->id,
            'nro_matricula_profesional' => '0755',
            'jurisdiccion_id' => $jurisdiccion->id,
            'colegio_emisor_id' => $colegio->id,
            'activa' => true,
        ]);
    }

    public function test_cannot_inactivate_last_active_matricula(): void
    {
        $user = $this->kinesiologaCompleta();

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'perfil.kinesiologa.matriculas.toggle-activa',
                    $matricula
                ),
                [
                    'activar' => false,
                ]
            );

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.index')
        );

        $response->assertSessionHas(
            'warning',
            'No puede inactivar su última matrícula activa.'
        );

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'activa' => 1,
            ]
        );
    }

    public function test_can_inactivate_matricula_if_there_is_another_active(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $segunda = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $user->kinesiologa->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'colegio_emisor_id' => $colegio2->id,
            'activa' => true,
        ]);

        $primera = $user->kinesiologa
            ->matriculasProfesionales()
            ->where('nro_matricula_profesional', '0755')
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'perfil.kinesiologa.matriculas.toggle-activa',
                    $primera
                ),
                [
                    'activar' => false,
                ]
            );

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $primera->id,
                'activa' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $segunda->id,
                'activa' => 1,
            ]
        );
    }

    public function test_can_reactivate_inactive_matricula(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $segunda = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $user->kinesiologa->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'colegio_emisor_id' => $colegio2->id,
            'activa' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'perfil.kinesiologa.matriculas.toggle-activa',
                    $segunda
                ),
                [
                    'activar' => true,
                ]
            );

        $response->assertRedirect(
            route('perfil.kinesiologa.matriculas.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $segunda->id,
                'activa' => 1,
            ]
        );
    }

    public function test_cannot_reactivate_matricula_when_jurisdiccion_is_inactive(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $matricula = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $user->kinesiologa->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'colegio_emisor_id' => $colegio2->id,
            'activa' => false,
        ]);

        $jurisdiccion2->update([
            'activo' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'perfil.kinesiologa.matriculas.toggle-activa',
                    $matricula
                ),
                [
                    'activar' => true,
                ]
            );

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.index')
            )
            ->assertSessionHas(
                'warning',
                'No puede reactivar la matrícula profesional mientras su jurisdicción o colegio emisor se encuentren inactivos.'
            );

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'activa' => false,
            ]
        );
    }

    public function test_cannot_reactivate_matricula_when_colegio_is_inactive(): void
    {
        $user = $this->kinesiologaCompleta();

        $jurisdiccion2 = Jurisdiccion::where('slug', 'corrientes')
            ->firstOrFail();

        $colegio2 = ColegioEmisor::where('sigla', 'CKC')
            ->firstOrFail();

        $matricula = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $user->kinesiologa->id,
            'nro_matricula_profesional' => '0955',
            'jurisdiccion_id' => $jurisdiccion2->id,
            'colegio_emisor_id' => $colegio2->id,
            'activa' => false,
        ]);

        $colegio2->update([
            'activo' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'perfil.kinesiologa.matriculas.toggle-activa',
                    $matricula
                ),
                [
                    'activar' => true,
                ]
            );

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.index')
            )
            ->assertSessionHas(
                'warning',
                'No puede reactivar la matrícula profesional mientras su jurisdicción o colegio emisor se encuentren inactivos.'
            );

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'activa' => false,
            ]
        );
    }

    public function test_change_state_requires_explicit_requested_state(): void
    {
        $user = $this->kinesiologaCompleta();

        $matricula = $user->kinesiologa
            ->matriculasProfesionales()
            ->firstOrFail();

        $response = $this
            ->from(route('perfil.kinesiologa.matriculas.index'))
            ->actingAs($user)
            ->patch(
                route(
                    'perfil.kinesiologa.matriculas.toggle-activa',
                    $matricula
                )
            );

        $response
            ->assertRedirect(
                route('perfil.kinesiologa.matriculas.index')
            )
            ->assertSessionHasErrors('activar');

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'activa' => true,
            ]
        );
    }
}
