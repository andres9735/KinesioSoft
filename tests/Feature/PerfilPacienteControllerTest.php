<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerfilPacienteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    private function pacienteIncompleto(): User
    {
        $user = User::factory()->create([
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        return $user;
    }

    private function pacienteCompleto(): User
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
            'activo' => true,
        ]);

        $user->assignRole(RoleEnum::PACIENTE->value);

        return $user;
    }

    public function test_paciente_can_view_completar_perfil_screen(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->get(route('perfil.completar'));

        $response->assertOk();
    }

    public function test_paciente_can_update_profile_with_valid_data(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->post(route('perfil.actualizar'), [
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Perfil completado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
        ]);
    }

    public function test_update_requires_dni(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('dni');
    }

    public function test_update_requires_fecha_nacimiento(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_update_requires_fecha_nacimiento_before_today(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => now()->addDay()->format('Y-m-d'),
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_update_requires_valid_sexo(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'valor_invalido',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('sexo');
    }

    public function test_update_requires_telefono(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('telefono');
    }

    public function test_update_rejects_invalid_telefono_format(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => 'telefono-invalido!',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('telefono');
    }

    public function test_update_requires_unique_dni_except_current_user(): void
    {
        $existingUser = User::factory()->create([
            'dni' => '43122015',
            'activo' => true,
        ]);
        $existingUser->assignRole(RoleEnum::PACIENTE->value);

        $user = $this->pacienteIncompleto();

        $response = $this->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(route('perfil.actualizar'), [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',
            ]);

        $response->assertRedirect(route('perfil.completar'));
        $response->assertSessionHasErrors('dni');
    }

    public function test_paciente_incompleto_is_redirected_to_completar_perfil_when_accessing_dashboard(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_paciente_completo_can_access_dashboard_without_redirection(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_paciente_can_keep_same_dni_when_updating_own_profile(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this->actingAs($user)->post(route('perfil.actualizar'), [
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'masculino',
            'telefono' => '3764123456',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
    }
}
