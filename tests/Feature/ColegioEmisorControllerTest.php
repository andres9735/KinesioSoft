<?php

namespace Tests\Feature;

use App\Models\ColegioEmisor;
use App\Models\Jurisdiccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ColegioEmisorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::findOrCreate('administrador', 'web');
        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('paciente', 'web');
        Role::findOrCreate('kinesiologo', 'web');
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole('administrador');

        return $user;
    }

    private function superadminUser(): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole('superadmin');

        return $user;
    }

    private function nonAdminUser(): User
    {
        $user = User::factory()->create([
            'activo' => true,
        ]);

        $user->assignRole('paciente');

        return $user;
    }

    public function test_admin_can_view_colegios_index(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('catalogos.colegios.index'));

        $response->assertOk();
        $response->assertSee('Colegios emisores');
    }

    public function test_superadmin_can_view_colegios_index(): void
    {
        $superadmin = $this->superadminUser();

        $response = $this->actingAs($superadmin)->get(route('catalogos.colegios.index'));

        $response->assertOk();
    }

    public function test_non_admin_user_cannot_access_colegios_index(): void
    {
        $user = $this->nonAdminUser();

        $response = $this->actingAs($user)->get(route('catalogos.colegios.index'));

        $response->assertRedirect(route('perfil.completar'));
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('catalogos.colegios.create'));

        $response->assertOk();
        $response->assertSee('Nuevo colegio emisor');
        $response->assertSee($jurisdiccion->nombre);
    }

    public function test_admin_can_store_valid_colegio_emisor(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('catalogos.colegios.store'), [
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'cokifimi',
            'jurisdiccion_id' => $jurisdiccion->id,
        ]);

        $response->assertRedirect(route('catalogos.colegios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => 1,
        ]);
    }

    public function test_store_requires_nombre(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => '',
                'sigla' => 'COKIFIMI',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(route('catalogos.colegios.create'));
        $response->assertSessionHasErrors('nombre');
    }

    public function test_store_requires_sigla(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => '',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(route('catalogos.colegios.create'));
        $response->assertSessionHasErrors('sigla');
    }

    public function test_store_requires_jurisdiccion(): void
    {
        $admin = $this->adminUser();

        $response = $this->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'COKIFIMI',
                'jurisdiccion_id' => '',
            ]);

        $response->assertRedirect(route('catalogos.colegios.create'));
        $response->assertSessionHasErrors('jurisdiccion_id');
    }

    public function test_store_rejects_duplicate_nombre(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'OTRASIGLA',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(route('catalogos.colegios.create'));
        $response->assertSessionHasErrors('nombre');
    }

    public function test_store_rejects_duplicate_sigla(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Otro colegio',
                'sigla' => 'COKIFIMI',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(route('catalogos.colegios.create'));
        $response->assertSessionHasErrors('sigla');
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = $this->adminUser();
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('catalogos.colegios.edit', $colegio));

        $response->assertOk();
        $response->assertSee('Editar colegio emisor');
        $response->assertSee('Colegio de Kinesiólogos de Misiones');
        $response->assertSee('COKIFIMI');
    }

    public function test_admin_can_update_colegio_emisor(): void
    {
        $admin = $this->adminUser();

        $jurisdiccionOriginal = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $jurisdiccionNueva = Jurisdiccion::create([
            'nombre' => 'Corrientes',
            'slug' => 'corrientes',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccionOriginal->id,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.colegios.update', $colegio), [
            'nombre' => 'Colegio Profesional de Kinesiología del Nordeste',
            'sigla' => 'CPKN',
            'jurisdiccion_id' => $jurisdiccionNueva->id,
        ]);

        $response->assertRedirect(route('catalogos.colegios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio Profesional de Kinesiología del Nordeste',
            'sigla' => 'CPKN',
            'jurisdiccion_id' => $jurisdiccionNueva->id,
        ]);
    }

    public function test_update_allows_same_nombre_and_sigla_for_same_record(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('catalogos.colegios.update', $colegio), [
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
        ]);

        $response->assertRedirect(route('catalogos.colegios.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_rejects_duplicate_nombre_from_another_record(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio1 = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $colegio2 = ColegioEmisor::create([
            'nombre' => 'Otro colegio',
            'sigla' => 'OTRO',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.colegios.edit', $colegio2))
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio2), [
                'nombre' => $colegio1->nombre,
                'sigla' => 'NUEVA',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(route('catalogos.colegios.edit', $colegio2));
        $response->assertSessionHasErrors('nombre');
    }

    public function test_update_rejects_duplicate_sigla_from_another_record(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio1 = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $colegio2 = ColegioEmisor::create([
            'nombre' => 'Otro colegio',
            'sigla' => 'OTRO',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->from(route('catalogos.colegios.edit', $colegio2))
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio2), [
                'nombre' => 'Nombre válido',
                'sigla' => $colegio1->sigla,
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(route('catalogos.colegios.edit', $colegio2));
        $response->assertSessionHasErrors('sigla');
    }

    public function test_toggle_activo_inactivates_colegio(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.colegios.toggle-activo', $colegio));

        $response->assertRedirect(route('catalogos.colegios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => 0,
        ]);
    }

    public function test_toggle_activo_activates_colegio(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('catalogos.colegios.toggle-activo', $colegio));

        $response->assertRedirect(route('catalogos.colegios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => 1,
        ]);
    }
}
