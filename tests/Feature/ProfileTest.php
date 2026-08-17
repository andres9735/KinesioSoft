<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_as_my_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSeeText('Mi cuenta')
            ->assertSeeText('Información de la cuenta')
            ->assertSeeText('Nombre')
            ->assertSeeText('Apellido')
            ->assertSeeText('Correo electrónico')
            ->assertSeeText('Cambiar contraseña')
            ->assertSeeText('Contraseña actual')
            ->assertSeeText('Nueva contraseña')
            ->assertSeeText('Confirmar nueva contraseña');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Ana María',
                'apellido' => 'González',
                'email' => 'ana.nueva@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame(
            'Ana María',
            $user->name
        );

        $this->assertSame(
            'González',
            $user->apellido
        );

        $this->assertSame(
            'ana.nueva@example.com',
            $user->email
        );

        $this->assertNull(
            $user->email_verified_at
        );
    }

    public function test_profile_update_requires_apellido(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Ana',
                'apellido' => '',
                'email' => 'ana@example.com',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('apellido');

        $this->assertSame(
            'Paciente',
            $user->refresh()->apellido
        );
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana',
            'apellido' => 'Paciente',
            'email' => 'ana@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Ana Actualizada',
                'apellido' => 'Paciente Actualizada',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull(
            $user->refresh()->email_verified_at
        );
    }

    public function test_profile_page_does_not_offer_account_deletion(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertDontSeeText('Eliminar cuenta')
            ->assertDontSeeText('Delete Account');
    }

    public function test_account_deletion_route_is_not_registered(): void
    {
        $this->assertFalse(
            Route::has('profile.destroy')
        );
    }

    public function test_delete_request_cannot_physically_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertStatus(405);

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }
}
