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
                'nombre' => '  Colegio   de Kinesiólogos   de Misiones  ',
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
            'nombre' => 'El nombre contiene caracteres no permitidos.',
            'sigla' => 'La sigla solo puede contener letras, números, espacios, puntos y guiones.',
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
            'nombre' => 'Debe ingresar el nombre del colegio emisor.',
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
            'sigla' => 'La sigla debe tener al menos 2 caracteres.',
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
            'jurisdiccion_id' => 'Debe seleccionar una jurisdicción.',
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
            'nombre' => 'Ya existe un colegio emisor con ese nombre.',
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
            'sigla' => 'Ya existe un colegio emisor con esa sigla.',
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

    public function test_update_normalizes_nombre_and_sigla_before_persisting(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio original',
            'sigla' => 'CO',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio), [
                'nombre' => '  Colegio   de Kinesiólogos   de Misiones  ',
                'sigla' => '  coki   mi  ',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(
            route('catalogos.colegios.index')
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKI MI',
            'jurisdiccion_id' => $jurisdiccion->id,
        ]);
    }

    public function test_update_rejects_invalid_nombre_and_sigla_characters(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio original',
            'sigla' => 'CO',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this
            ->from(route('catalogos.colegios.edit', $colegio))
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio), [
                'nombre' => 'Colegio / Misiones',
                'sigla' => 'COKI@MI',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(
            route('catalogos.colegios.edit', $colegio)
        );

        $response->assertSessionHasErrors([
            'nombre' => 'El nombre contiene caracteres no permitidos.',
            'sigla' => 'La sigla solo puede contener letras, números, espacios, puntos y guiones.',
        ]);

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio original',
            'sigla' => 'CO',
        ]);
    }

    public function test_update_rejects_sigla_shorter_than_two_characters(): void
    {
        $admin = $this->adminUser();

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio original',
            'sigla' => 'CO',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);

        $response = $this
            ->from(route('catalogos.colegios.edit', $colegio))
            ->actingAs($admin)
            ->put(route('catalogos.colegios.update', $colegio), [
                'nombre' => 'Colegio actualizado',
                'sigla' => 'C',
                'jurisdiccion_id' => $jurisdiccion->id,
            ]);

        $response->assertRedirect(
            route('catalogos.colegios.edit', $colegio)
        );

        $response->assertSessionHasErrors([
            'sigla' => 'La sigla debe tener al menos 2 caracteres.',
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
        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe un colegio emisor con ese nombre.',
        ]);
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
        $response->assertSessionHasErrors([
            'sigla' => 'Ya existe un colegio emisor con esa sigla.',
        ]);
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
                'jurisdiccion_id' => 'La jurisdicción seleccionada no existe, se encuentra inactiva o no puede utilizarse para esta operación.',
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
            ->assertSessionHasErrors([
                'jurisdiccion_id' => 'La jurisdicción seleccionada no existe, se encuentra inactiva o no puede utilizarse para esta operación.',
            ]);

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'nombre' => 'Colegio de Kinesiólogos de Misiones',
            'sigla' => 'COKIFIMI',
            'jurisdiccion_id' => $jurisdiccionActual->id,
        ]);
    }

    public function test_inactivar_inactivates_colegio(): void
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
            ->patch(route('catalogos.colegios.inactivar', $colegio));

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

    public function test_reactivar_activates_colegio(): void
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
            ->patch(route('catalogos.colegios.reactivar', $colegio));

        $response->assertRedirect(route('catalogos.colegios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => 1,
        ]);
    }

    public function test_repeated_state_requests_do_not_toggle_colegio_or_audit(): void
    {
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

        $this->actingAs($this->adminUser())
            ->patch(route('catalogos.colegios.inactivar', $colegio))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => false,
        ]);

        $colegio->update(['activo' => true]);

        $this->patch(route('catalogos.colegios.reactivar', $colegio))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_name_and_sigla_and_shows_empty_state(): void
    {
        $jurisdiccion = $this->jurisdiccionParaListado();
        $nombre = $this->colegioParaListado($jurisdiccion, 'Colegio Delta', 'CD', true);
        $sigla = $this->colegioParaListado($jurisdiccion, 'Colegio Norte', 'DELTA', false);
        $otro = $this->colegioParaListado($jurisdiccion, 'Colegio Sur', 'CS', true);

        $response = $this->actingAs($this->adminUser())
            ->get(route('catalogos.colegios.index', ['q' => 'Delta']));

        $response->assertOk()
            ->assertSee($nombre->nombre)
            ->assertSee($sigla->nombre)
            ->assertDontSee($otro->nombre)
            ->assertSee('Buscar por nombre o sigla')
            ->assertSee('value="Delta"', false);

        $this->get(route('catalogos.colegios.index', ['q' => 'NoExiste']))
            ->assertOk()
            ->assertSee('No se encontraron colegios emisores')
            ->assertDontSee('Crear primer colegio emisor');
    }

    public function test_index_filters_all_states_and_treats_unknown_state_as_all(): void
    {
        $jurisdiccion = $this->jurisdiccionParaListado();
        $activo = $this->colegioParaListado($jurisdiccion, 'Colegio Activo', 'CA', true);
        $inactivo = $this->colegioParaListado($jurisdiccion, 'Colegio Inactivo', 'CI', false);
        $this->actingAs($this->adminUser());

        $this->get(route('catalogos.colegios.index'))
            ->assertOk()->assertSee($activo->nombre)->assertSee($inactivo->nombre);

        foreach (['todos', 'desconocido'] as $estado) {
            $this->get(route('catalogos.colegios.index', ['estado' => $estado]))
                ->assertOk()->assertSee($activo->nombre)->assertSee($inactivo->nombre)
                ->assertSee('<option value="todos" selected>', false);
        }

        $this->get(route('catalogos.colegios.index', ['estado' => 'activos']))
            ->assertSee($activo->nombre)->assertDontSee($inactivo->nombre);
        $this->get(route('catalogos.colegios.index', ['estado' => 'inactivos']))
            ->assertSee($inactivo->nombre)->assertDontSee($activo->nombre);
    }

    public function test_index_groups_name_or_sigla_before_state_filter(): void
    {
        $jurisdiccion = $this->jurisdiccionParaListado();
        $activoPorNombre = $this->colegioParaListado($jurisdiccion, 'Colegio Delta', 'CD', true);
        $activoPorSigla = $this->colegioParaListado($jurisdiccion, 'Colegio Norte', 'DELTA', true);
        $inactivoPorNombre = $this->colegioParaListado($jurisdiccion, 'Delta Histórico', 'DH', false);
        $inactivoPorSigla = $this->colegioParaListado($jurisdiccion, 'Colegio Sur', 'DELTA-S', false);

        $response = $this->actingAs($this->adminUser())
            ->get(route('catalogos.colegios.index', ['q' => 'Delta', 'estado' => 'activos']));

        $response->assertOk()
            ->assertSee($activoPorNombre->nombre)
            ->assertSee($activoPorSigla->nombre)
            ->assertDontSee($inactivoPorNombre->nombre)
            ->assertDontSee($inactivoPorSigla->nombre);
    }

    public function test_index_paginates_ten_and_preserves_query_string(): void
    {
        $jurisdiccion = $this->jurisdiccionParaListado();
        for ($i = 1; $i <= 11; $i++) {
            $this->colegioParaListado($jurisdiccion, sprintf('Colegio Delta %02d', $i), 'CD'.$i, true);
        }

        $response = $this->actingAs($this->adminUser())
            ->get(route('catalogos.colegios.index', ['q' => 'Delta', 'estado' => 'activos']));

        $response->assertOk();
        $colegios = $response->viewData('colegios');
        $this->assertSame(10, $colegios->count());
        $this->assertSame(11, $colegios->total());
        $this->assertStringContainsString('q=Delta', $colegios->url(2));
        $this->assertStringContainsString('estado=activos', $colegios->url(2));
    }

    public function test_show_is_read_only_for_active_inactive_and_historical_colegios(): void
    {
        $jurisdiccion = $this->jurisdiccionParaListado();
        $activo = $this->colegioParaListado($jurisdiccion, 'Colegio Activo', 'CA', true);
        $inactivo = $this->colegioParaListado($jurisdiccion, 'Colegio Inactivo', 'CI', false);
        $historica = Jurisdiccion::create([
            'nombre' => 'Jurisdicción histórica', 'slug' => 'jurisdiccion-historica', 'activo' => false,
        ]);
        $historico = $this->colegioParaListado($historica, 'Colegio Histórico', 'CH', false);

        $this->actingAs($this->adminUser());
        foreach ([$activo, $inactivo, $historico] as $colegio) {
            $this->get(route('catalogos.colegios.show', $colegio))
                ->assertOk()
                ->assertSee('Consultar colegio emisor')
                ->assertSee($colegio->nombre)
                ->assertSee($colegio->sigla)
                ->assertSee($colegio->jurisdiccion->nombre)
                ->assertSee($colegio->activo ? 'Activo' : 'Inactivo');
            $this->assertDatabaseHas('colegios_emisores', [
                'id' => $colegio->id, 'jurisdiccion_id' => $colegio->jurisdiccion_id,
                'activo' => $colegio->activo,
            ]);
        }
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_superadmin_can_show_colegio_and_non_admin_cannot(): void
    {
        $colegio = $this->colegioParaListado($this->jurisdiccionParaListado(), 'Colegio Norte', 'CN', true);

        $this->actingAs($this->superadminUser())
            ->get(route('catalogos.colegios.show', $colegio))->assertOk();
        $this->actingAs($this->nonAdminUser())
            ->get(route('catalogos.colegios.show', $colegio))
            ->assertRedirect(route('perfil.completar'));
    }

    public function test_index_shows_desktop_mobile_and_actions_for_each_state(): void
    {
        $jurisdiccion = $this->jurisdiccionParaListado();
        $activo = $this->colegioParaListado($jurisdiccion, 'Colegio Activo', 'CA', true);
        $inactivo = $this->colegioParaListado($jurisdiccion, 'Colegio Inactivo', 'CI', false);

        $this->actingAs($this->adminUser())
            ->get(route('catalogos.colegios.index'))
            ->assertOk()
            ->assertSee('Nombre')
            ->assertSee('Sigla')
            ->assertSee('Jurisdicción')
            ->assertSee('Estado')
            ->assertSee('Acciones')
            ->assertSee('md:block')
            ->assertSee('md:hidden')
            ->assertSee('Limpiar filtros')
            ->assertSee('Jurisdicción: '.$jurisdiccion->nombre)
            ->assertSee($jurisdiccion->nombre)
            ->assertSee(route('catalogos.colegios.show', $activo))
            ->assertSee(route('catalogos.colegios.show', $inactivo))
            ->assertSee(route('catalogos.colegios.edit', $activo))
            ->assertSee(route('catalogos.colegios.edit', $inactivo))
            ->assertSee(route('catalogos.colegios.inactivar', $activo))
            ->assertSee(route('catalogos.colegios.reactivar', $inactivo))
            ->assertSee('js-confirm-action')
            ->assertSee('data-confirm-title="¿Inactivar colegio emisor?"', false)
            ->assertSee('data-confirm-title="¿Reactivar colegio emisor?"', false);
    }

    public function test_edit_offers_current_inactive_jurisdiction_and_only_active_alternatives(): void
    {
        $historica = Jurisdiccion::create([
            'nombre' => 'Jurisdicción histórica', 'slug' => 'historica', 'activo' => false,
        ]);
        $otraInactiva = Jurisdiccion::create([
            'nombre' => 'Jurisdicción cerrada', 'slug' => 'cerrada', 'activo' => false,
        ]);
        $activa = $this->jurisdiccionParaListado();
        $colegio = $this->colegioParaListado($historica, 'Colegio Histórico', 'CH', false);

        $this->actingAs($this->adminUser())
            ->get(route('catalogos.colegios.edit', $colegio))
            ->assertOk()
            ->assertSeeInOrder([$historica->nombre, '(Inactiva)'])
            ->assertSee('value="'.$historica->id.'"', false)
            ->assertSee($activa->nombre)
            ->assertDontSee($otraInactiva->nombre);
    }

    public function test_historical_colegio_can_switch_to_active_but_not_another_inactive_jurisdiction(): void
    {
        $historica = Jurisdiccion::create([
            'nombre' => 'Histórica', 'slug' => 'historica', 'activo' => false,
        ]);
        $otraInactiva = Jurisdiccion::create([
            'nombre' => 'Cerrada', 'slug' => 'cerrada', 'activo' => false,
        ]);
        $activa = $this->jurisdiccionParaListado();
        $colegio = $this->colegioParaListado($historica, 'Colegio Histórico', 'CH', false);
        $this->actingAs($this->adminUser());

        $this->from(route('catalogos.colegios.edit', $colegio))
            ->put(route('catalogos.colegios.update', $colegio), [
                'nombre' => 'Colegio Histórico', 'sigla' => 'CH',
                'jurisdiccion_id' => $otraInactiva->id,
            ])->assertSessionHasErrors('jurisdiccion_id');
        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id, 'jurisdiccion_id' => $historica->id,
        ]);

        $this->put(route('catalogos.colegios.update', $colegio), [
            'nombre' => 'Colegio Histórico', 'sigla' => 'CH',
            'jurisdiccion_id' => $activa->id,
        ])->assertRedirect(route('catalogos.colegios.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id, 'jurisdiccion_id' => $activa->id,
        ]);
    }

    public function test_reactivation_rejects_inactive_jurisdiction_with_error_and_no_audit(): void
    {
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Histórica', 'slug' => 'historica', 'activo' => false,
        ]);
        $colegio = $this->colegioParaListado($jurisdiccion, 'Colegio Histórico', 'CH', false);

        $this->actingAs($this->adminUser())
            ->patch(route('catalogos.colegios.reactivar', $colegio))
            ->assertRedirect(route('catalogos.colegios.index'))
            ->assertSessionHas('error', 'No se puede reactivar el colegio emisor porque su jurisdicción está inactiva.');

        $this->assertDatabaseHas('colegios_emisores', [
            'id' => $colegio->id, 'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function jurisdiccionParaListado(): Jurisdiccion
    {
        return Jurisdiccion::create([
            'nombre' => 'Misiones', 'slug' => 'misiones', 'activo' => true,
        ]);
    }

    private function colegioParaListado(Jurisdiccion $jurisdiccion, string $nombre, string $sigla, bool $activo): ColegioEmisor
    {
        return ColegioEmisor::create([
            'nombre' => $nombre, 'sigla' => $sigla,
            'jurisdiccion_id' => $jurisdiccion->id, 'activo' => $activo,
        ]);
    }
}
