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

    public function test_store_normalizes_nombre_and_sigla_before_persisting(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' =>
                '  Colegio   de Kinesiólogos   de Misiones  ',
                'sigla' => '  coki   mi  ',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(
            route('catalogos.colegios.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKI MI',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => 1,
        ]);
    }

    public function test_store_rejects_invalid_nombre_and_sigla_characters(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this
            ->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Colegio / Misiones',
                'sigla' => 'COKI@MI',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(
            route('catalogos.colegios.create')
        );

        $response->assertSessionHasErrors([
            'nombre' =>
            'El nombre contiene caracteres no permitidos.',
            'sigla' =>
            'La sigla solo puede contener letras, números, espacios, puntos y guiones.',
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
        $response->assertSessionHasErrors([
            'nombre' =>
            'Debe ingresar el nombre del colegio emisor.',
        ]);
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
        $response->assertSessionHasErrors([
            'sigla' => 'Debe ingresar la sigla del colegio emisor.',
        ]);
    }

    public function test_store_rejects_sigla_shorter_than_two_characters(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $response = $this
            ->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Colegio de Kinesiólogos de Misiones',
                'sigla' => 'C',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(
            route('catalogos.colegios.create')
        );

        $response->assertSessionHasErrors([
            'sigla' =>
            'La sigla debe tener al menos 2 caracteres.',
        ]);
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
        $response->assertSessionHasErrors([
            'jurisdiccion_id' =>
            'Debe seleccionar una jurisdicción.',
        ]);
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
        $response->assertSessionHasErrors([
            'nombre' =>
            'Ya existe un colegio emisor con ese nombre.',
        ]);
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
        $response->assertSessionHasErrors([
            'sigla' =>
            'Ya existe un colegio emisor con esa sigla.',
        ]);
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

    public function test_create_form_does_not_show_inactive_jurisdictions(): void
    {
        $admin = $this->adminUser();

        $activa = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $inactiva = Jurisdiccion::create([
            'nombre' => 'Jurisdicción archivada',
            'slug' => 'jurisdiccion-archivada',
            'activo' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('catalogos.colegios.create'));

        $response->assertOk();
        $response->assertSee($activa->nombre);
        $response->assertDontSee($inactiva->nombre);
    }

    public function test_store_rejects_inactive_jurisdiction(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Jurisdicción inactiva',
            'slug' => 'jurisdiccion-inactiva',
            'activo' => false,
        ]);

        $response = $this
            ->from(route('catalogos.colegios.create'))
            ->actingAs($admin)
            ->post(route('catalogos.colegios.store'), [
                'nombre' => 'Colegio de jurisdicción inactiva',
                'sigla' => 'CJI',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response
            ->assertRedirect(route('catalogos.colegios.create'))
            ->assertSessionHasErrors([
                'jurisdiccion_id' =>
                'La jurisdicción seleccionada no existe, se encuentra inactiva o no puede utilizarse para esta operación.',
            ]);

        $this->assertDatabaseMissing('colegios_emisores', [
            'nombre' => 'Colegio de jurisdicción inactiva',
            'jurisdiccion_id' => $jurisdiccion->id,
        ]);
    }

    public function test_edit_form_shows_current_inactive_jurisdiction(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Jurisdicción histórica',
            'slug' => 'jurisdiccion-historica',
            'activo' => false,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio histórico',
            'sigla' => 'CH',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('catalogos.colegios.edit', $colegio));

        $response->assertOk();
        $response->assertSee($jurisdiccion->nombre);
    }

    public function test_update_rejects_switching_to_inactive_jurisdiction(): void
    {
        $admin = $this->adminUser();

        $jurisdiccionActual = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $jurisdiccionInactiva = Jurisdiccion::create([
            'nombre' => 'Jurisdicción destino inactiva',
            'slug' => 'jurisdiccion-destino-inactiva',
            'activo' => false,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccionActual->id,
            'activo' => true,
        ]);

        $response = $this
            ->from(route('catalogos.colegios.edit', $colegio))
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio), [
                'nombre' => 'Colegio trasladado',
                'sigla' => 'CT',
                'jurisdiccion_id' => $jurisdiccionInactiva->id,
            ]);

        $response
            ->assertRedirect(route('catalogos.colegios.edit', $colegio))
            ->assertSessionHasErrors('jurisdiccion_id');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccionActual->id,
        ]);
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

    public function test_update_allows_keeping_current_inactive_jurisdiction(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Jurisdicción histórica',
            'slug' => 'jurisdiccion-historica',
            'activo' => false,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio histórico',
            'sigla' => 'CH',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio), [
                'nombre' => 'Colegio histórico actualizado',
                'sigla' => 'CHA',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response
            ->assertRedirect(route('catalogos.colegios.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio histórico actualizado',
            'sigla' => 'CHA',
            'jurisdiccion_id' => $jurisdiccion->id,
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
