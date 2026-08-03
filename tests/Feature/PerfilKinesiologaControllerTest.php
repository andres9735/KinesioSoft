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

class PerfilKinesiologaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');
    }

    private function kinesiologaIncompleta(): User
    {
        $user = User::factory()->create([
            'name' => 'Silvia',
            'apellido' => 'Prueba',
            'email' => 'kinesio_' . uniqid() . '@example.com',
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::KINESIOLOGO->value);

        return $user;
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

        $especialidad1 = EspecialidadKinesiologia::create([
            'nombre' => 'Kinesiología Deportiva',
            'slug' => 'kinesiologia-deportiva',
            'activo' => true,
        ]);

        $especialidad2 = EspecialidadKinesiologia::create([
            'nombre' => 'Rehabilitación Respiratoria',
            'slug' => 'rehabilitacion-respiratoria',
            'activo' => true,
        ]);

        return compact(
            'titulo',
            'jurisdiccion1',
            'jurisdiccion2',
            'colegio1',
            'colegio2',
            'especialidad1',
            'especialidad2'
        );
    }

    private function kinesiologaCompleta(): User
    {
        $catalogos = $this->catalogos();

        $user = User::factory()->create([
            'name' => 'Silvia',
            'apellido' => 'Completa',
            'email' => 'silvia_' . uniqid() . '@example.com',
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
            'nro_matricula_profesional' => 'MP-1234',
            'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
            'colegio_emisor_id' => $catalogos['colegio1']->id,
            'activa' => true,
        ]);

        $kinesiologa->especialidades()->sync([$catalogos['especialidad1']->id]);

        return $user;
    }

    public function test_kinesiologa_can_view_completar_perfil_profesional_screen(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->actingAs($user)->get(route('perfil.kinesiologa.completar'));

        $response->assertOk();
        $response->assertSee($catalogos['titulo']->nombre);
        $response->assertSee($catalogos['jurisdiccion1']->nombre);
        $response->assertSee($catalogos['colegio1']->nombre);
        $response->assertSee($catalogos['especialidad1']->nombre);
    }

    public function test_kinesiologa_can_complete_profile_with_valid_data(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->actingAs($user)->post(route('perfil.kinesiologa.actualizar'), [
            'dni' => '30456321',
            'fecha_nacimiento' => '1993-08-09',
            'sexo' => 'femenino',
            'telefono' => '3758-451963',
            'titulo_profesional_id' => $catalogos['titulo']->id,
            'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
            'colegio_emisor_id' => $catalogos['colegio1']->id,
            'nro_matricula_profesional' => '0763',
            'especialidades' => [
                $catalogos['especialidad1']->id,
                $catalogos['especialidad2']->id,
            ],
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Perfil profesional completado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '30456321',
            'fecha_nacimiento' => '1993-08-09',
            'sexo' => 'femenino',
            'telefono' => '3758-451963',
        ]);

        $this->assertDatabaseHas('kinesiologas', [
            'user_id' => $user->id,
            'titulo_profesional_id' => $catalogos['titulo']->id,
        ]);

        $kinesiologa = Kinesiologa::where('user_id', $user->id)->first();

        $this->assertNotNull($kinesiologa);

        $this->assertDatabaseHas('matriculas_profesionales_kinesiologia', [
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => '0763',
            'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
            'colegio_emisor_id' => $catalogos['colegio1']->id,
            'activa' => 1,
        ]);

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $catalogos['especialidad1']->id,
        ]);

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $catalogos['especialidad2']->id,
        ]);
    }

    public function test_update_requires_dni(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.actualizar'), [
                'dni' => '',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo']->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' => $catalogos['colegio1']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [$catalogos['especialidad1']->id],
            ]);

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
        $response->assertSessionHasErrors('dni');
    }

    public function test_update_requires_titulo_profesional_id(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.actualizar'), [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => '',
                'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' => $catalogos['colegio1']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [$catalogos['especialidad1']->id],
            ]);

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
        $response->assertSessionHasErrors('titulo_profesional_id');
    }

    public function test_update_requires_jurisdiccion_id_when_user_has_no_matriculas(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.actualizar'), [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo']->id,
                'jurisdiccion_id' => '',
                'colegio_emisor_id' => $catalogos['colegio1']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [$catalogos['especialidad1']->id],
            ]);

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
        $response->assertSessionHasErrors('jurisdiccion_id');
    }

    public function test_update_requires_colegio_emisor_belonging_to_selected_jurisdiccion_when_user_has_no_matriculas(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.actualizar'), [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo']->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion2']->id,
                'colegio_emisor_id' => $catalogos['colegio1']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [$catalogos['especialidad1']->id],
            ]);

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
        $response->assertSessionHasErrors('colegio_emisor_id');
    }

    public function test_update_requires_at_least_one_especialidad(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.actualizar'), [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo']->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' => $catalogos['colegio1']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [],
            ]);

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
        $response->assertSessionHasErrors('especialidades');
    }

    public function test_update_rejects_duplicate_especialidades_in_request(): void
    {
        $user = $this->kinesiologaIncompleta();
        $catalogos = $this->catalogos();

        $response = $this->from(route('perfil.kinesiologa.completar'))
            ->actingAs($user)
            ->post(route('perfil.kinesiologa.actualizar'), [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo']->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion1']->id,
                'colegio_emisor_id' => $catalogos['colegio1']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [
                    $catalogos['especialidad1']->id,
                    $catalogos['especialidad1']->id,
                ],
            ]);

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
        $response->assertSessionHasErrors('especialidades.1');
    }

    public function test_kinesiologa_incompleta_is_redirected_to_completar_perfil_profesional_when_accessing_dashboard(): void
    {
        $user = $this->kinesiologaIncompleta();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('perfil.kinesiologa.completar'));
    }

    public function test_kinesiologa_completa_can_access_dashboard_without_redirection(): void
    {
        $user = $this->kinesiologaCompleta();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_existing_kinesiologa_can_update_basic_profile_data_without_modifying_existing_matricula(): void
    {
        $user = $this->kinesiologaCompleta();
        $titulo = TituloProfesional::firstOrFail();
        $especialidad1 = EspecialidadKinesiologia::where('slug', 'kinesiologia-deportiva')->firstOrFail();
        $especialidad2 = EspecialidadKinesiologia::where('slug', 'rehabilitacion-respiratoria')->firstOrFail();

        $matricula = $user->kinesiologa->matriculasProfesionales()->first();

        $response = $this->actingAs($user)->post(route('perfil.kinesiologa.actualizar'), [
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764999999',
            'titulo_profesional_id' => $titulo->id,
            'especialidades' => [
                $especialidad1->id,
                $especialidad2->id,
            ],
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'telefono' => '3764999999',
        ]);

        $this->assertDatabaseHas('matriculas_profesionales_kinesiologia', [
            'id' => $matricula->id,
            'nro_matricula_profesional' => 'MP-1234',
        ]);

        $this->assertSame(
            1,
            $user->kinesiologa->fresh()->matriculasProfesionales()->count()
        );

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $user->kinesiologa->id,
            'especialidad_id' => $especialidad1->id,
        ]);

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $user->kinesiologa->id,
            'especialidad_id' => $especialidad2->id,
        ]);
    }

    public function test_existing_kinesiologa_does_not_require_matricula_fields_in_profile_update(): void
    {
        $user = $this->kinesiologaCompleta();
        $titulo = TituloProfesional::firstOrFail();
        $especialidad1 = EspecialidadKinesiologia::where('slug', 'kinesiologia-deportiva')->firstOrFail();

        $response = $this->actingAs($user)->post(route('perfil.kinesiologa.actualizar'), [
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764555555',
            'titulo_profesional_id' => $titulo->id,
            'especialidades' => [$especialidad1->id],
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
    }

    public function test_existing_kinesiologa_profile_update_does_not_create_new_matriculas(): void
    {
        $user = $this->kinesiologaCompleta();
        $titulo = TituloProfesional::firstOrFail();
        $especialidad1 = EspecialidadKinesiologia::where('slug', 'kinesiologia-deportiva')->firstOrFail();

        $cantidadAntes = $user->kinesiologa->matriculasProfesionales()->count();

        $response = $this->actingAs($user)->post(route('perfil.kinesiologa.actualizar'), [
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764333333',
            'titulo_profesional_id' => $titulo->id,
            'especialidades' => [$especialidad1->id],
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();

        $cantidadDespues = $user->kinesiologa->fresh()->matriculasProfesionales()->count();

        $this->assertSame($cantidadAntes, $cantidadDespues);
    }

    public function test_existing_kinesiologa_sees_matriculas_management_message_in_profile_view(): void
    {
        $user = $this->kinesiologaCompleta();

        $response = $this->actingAs($user)->get(route('perfil.kinesiologa.completar'));

        $response->assertOk();
        $response->assertSee('Sus matrículas se administran desde el módulo específico.');
        $response->assertSee('Mis matrículas profesionales');
        $response->assertSee('Matrículas activas registradas');
    }

    public function test_existing_kinesiologa_does_not_see_initial_matricula_fields_in_profile_view(): void
    {
        $user = $this->kinesiologaCompleta();

        $response = $this->actingAs($user)->get(route('perfil.kinesiologa.completar'));

        $response->assertOk();
        $response->assertDontSee('Primera matrícula profesional');
        $response->assertDontSee('name="jurisdiccion_id"', false);
        $response->assertDontSee('name="colegio_emisor_id"', false);
        $response->assertDontSee('name="nro_matricula_profesional"', false);
    }
}
