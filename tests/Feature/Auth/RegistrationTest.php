<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleEnum;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Andrés',
            'apellido' => 'Schupiak',
            'email' => 'andres@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'andres@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Andrés', $user->name);
        $this->assertSame('Schupiak', $user->apellido);
        $this->assertTrue($user->hasRole(RoleEnum::PACIENTE->value));

        $paciente = Paciente::where('user_id', $user->id)->first();

        $this->assertNotNull($paciente);
        $this->assertSame($user->id, $paciente->user_id);
        $this->assertMatchesRegularExpression('/^HC-\d{6}$/', $paciente->nro_historia_clinica);
    }

    public function test_registration_creates_paciente_profile_for_user(): void
    {
        $this->post('/register', [
            'name' => 'María',
            'apellido' => 'Gómez',
            'email' => 'maria@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'maria@example.com')->first();

        $this->assertNotNull($user);
        $this->assertDatabaseHas('pacientes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_registration_generates_historia_clinica_with_expected_format(): void
    {
        $this->post('/register', [
            'name' => 'Lucas',
            'apellido' => 'Fernández',
            'email' => 'lucas@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'lucas@example.com')->first();
        $paciente = Paciente::where('user_id', $user->id)->first();

        $this->assertNotNull($paciente);
        $this->assertMatchesRegularExpression('/^HC-\d{6}$/', $paciente->nro_historia_clinica);
    }

    public function test_registration_requires_apellido(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Andrés',
            'apellido' => '',
            'email' => 'andres@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('apellido');
        $this->assertGuest();
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'andres@example.com',
        ]);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Andrés',
            'apellido' => 'Schupiak',
            'email' => 'andres@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_requires_password_confirmation_to_match(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Andrés',
            'apellido' => 'Schupiak',
            'email' => 'andres@example.com',
            'password' => 'password',
            'password_confirmation' => 'otra-password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
