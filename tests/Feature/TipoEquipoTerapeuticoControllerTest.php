<?php

namespace Tests\Feature;

use App\Actions\Catalogos\ActualizarCategoriaClinicaAction;
use App\Actions\Catalogos\CambiarEstadoCategoriaClinicaAction;
use App\Enums\RoleEnum;
use App\Models\CategoriaClinica;
use App\Models\TipoEquipoTerapeutico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoEquipoTerapeuticoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function test_admin_and_superadmin_access_while_kinesiologo_and_paciente_are_blocked(): void
    {
        foreach ([RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get(route('catalogos.tipos-equipo.index'))->assertOk();
            $this->get(route('catalogos.tipos-equipo.create'))->assertOk();
        }

        foreach ([RoleEnum::KINESIOLOGO, RoleEnum::PACIENTE] as $role) {
            $this->actingAs($this->userWithRole($role));
            $destino = $role === RoleEnum::KINESIOLOGO
                ? route('perfil.kinesiologa.completar')
                : route('perfil.completar');

            $this->get(route('catalogos.tipos-equipo.index'))->assertRedirect($destino);
            $this->post(route('catalogos.tipos-equipo.store'), $this->payload(999))->assertRedirect($destino);
        }
    }

    public function test_empty_index_create_form_and_hub_navigation_have_expected_content(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.tipos-equipo.index'))
            ->assertOk()
            ->assertViewIs('catalogos.tipos-equipo-terapeutico.index')
            ->assertSee('No hay tipos de equipo terapéutico registrados')
            ->assertSee('Código')->assertSee('Nombre')
            ->assertSee('Categoría EQUIPAMIENTO')->assertSee('Estado')->assertSee('Acciones')
            ->assertSee('hidden overflow-x-auto md:block')->assertSee('md:hidden')
            ->assertSee('Limpiar filtros');

        $this->get(route('catalogos.index'))
            ->assertOk()
            ->assertSee(route('catalogos.tipos-equipo.index'))
            ->assertSee(route('catalogos.ejercicios.index'))
            ->assertSee('Tipos de equipo terapéutico')
            ->assertSee('Ejercicios domiciliarios');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_lists_both_states_represents_category_and_eager_loads_relation(): void
    {
        $categoria = $this->categoria('EQ-CAT', 'Aparatología');
        $activo = $this->tipoEquipo('US', 'Ultrasonido terapéutico', $categoria, true);
        $inactivo = $this->tipoEquipo('LASER', 'Láser terapéutico', $categoria, false);

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tipos-equipo.index'));

        $response->assertOk()->assertSee('US')->assertSee('Ultrasonido terapéutico')
            ->assertSee('LASER')->assertSee('Láser terapéutico')
            ->assertSee('EQ-CAT · Aparatología')
            ->assertSee('Categoría: EQ-CAT · Aparatología')
            ->assertSee(route('catalogos.tipos-equipo.inactivar', $activo))
            ->assertSee(route('catalogos.tipos-equipo.reactivar', $inactivo));

        foreach ($response->viewData('tiposEquipo') as $tipoEquipo) {
            $this->assertTrue($tipoEquipo->relationLoaded('categoriaClinica'));
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_index_searches_only_code_or_name_and_filters_state(): void
    {
        $categoria = $this->categoria('CAT', 'Categoría no buscable');
        $this->tipoEquipo('US-01', 'Ultrasonido', $categoria, true);
        $this->tipoEquipo('LAS-02', 'Láser', $categoria, false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->assertSame(['US-01'], $this->get(route('catalogos.tipos-equipo.index', ['q' => 'US-01']))
            ->viewData('tiposEquipo')->pluck('codigo')->all());
        $this->assertSame(['LAS-02'], $this->get(route('catalogos.tipos-equipo.index', ['q' => 'Láser']))
            ->viewData('tiposEquipo')->pluck('codigo')->all());
        $this->assertSame(0, $this->get(route('catalogos.tipos-equipo.index', ['q' => 'Categoría no buscable']))
            ->viewData('tiposEquipo')->total());

        foreach ([
            'todos' => ['LAS-02', 'US-01'],
            'activos' => ['US-01'],
            'inactivos' => ['LAS-02'],
        ] as $estado => $esperados) {
            $codigos = $this->get(route('catalogos.tipos-equipo.index', ['estado' => $estado]))
                ->viewData('tiposEquipo')->pluck('codigo')->sort()->values()->all();
            $this->assertSame($esperados, $codigos);
        }

        $this->assertSame(2, $this->get(route('catalogos.tipos-equipo.index', ['estado' => 'otro']))
            ->viewData('tiposEquipo')->total());
    }

    public function test_index_paginates_ten_and_keeps_query_parameters(): void
    {
        $categoria = $this->categoria('CAT', 'Equipamiento');

        for ($i = 1; $i <= 11; $i++) {
            $this->tipoEquipo(sprintf('EQ-%02d', $i), sprintf('Equipo %02d', $i), $categoria, true);
        }

        $tipos = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tipos-equipo.index', ['q' => 'Equipo', 'estado' => 'activos']))
            ->viewData('tiposEquipo');

        $this->assertSame(10, $tipos->count());
        $this->assertSame(11, $tipos->total());
        $this->assertStringContainsString('q=Equipo', $tipos->nextPageUrl());
        $this->assertStringContainsString('estado=activos', $tipos->nextPageUrl());
    }

    public function test_create_offers_only_active_equipment_categories_without_state_field(): void
    {
        $valida = $this->categoria('EQ-A', 'Equipamiento activo');
        $inactiva = $this->categoria('EQ-I', 'Equipamiento inactivo', 'EQUIPAMIENTO', false);
        $anatomica = $this->categoria('ANAT', 'Anatómica', 'ANATOMICA');
        $funcional = $this->categoria('FUNC', 'Funcional', 'FUNCIONAL');
        $terapeutica = $this->categoria('TER', 'Terapéutica', 'TERAPEUTICA');

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.tipos-equipo.create'));

        $response->assertOk()->assertViewIs('catalogos.tipos-equipo-terapeutico.create')
            ->assertSee('name="categoria_clinica_id"', false)
            ->assertDontSee('name="activo"', false);
        $this->assertSame([$valida->id], $response->viewData('categoriasDisponibles')->pluck('id')->all());
        $this->assertNotContains($inactiva->id, $response->viewData('categoriasDisponibles')->pluck('id')->all());
        $this->assertNotContains($anatomica->id, $response->viewData('categoriasDisponibles')->pluck('id')->all());
        $this->assertNotContains($funcional->id, $response->viewData('categoriasDisponibles')->pluck('id')->all());
        $this->assertNotContains($terapeutica->id, $response->viewData('categoriasDisponibles')->pluck('id')->all());
    }

    public function test_store_valid_record_forces_active_and_audits(): void
    {
        $categoria = $this->categoria('EQ-A', 'Equipamiento activo');

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->post(route('catalogos.tipos-equipo.store'), $this->payload($categoria->id, ['activo' => false]))
            ->assertRedirect(route('catalogos.tipos-equipo.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'codigo' => 'EQ-US', 'nombre' => 'Ultrasonido terapéutico',
            'categoria_clinica_id' => $categoria->id, 'activo' => true,
        ]);
        $this->assertDatabaseHas('auditorias', ['evento' => 'tipo_equipo_terapeutico_creado']);
    }

    public function test_store_rejects_three_wrong_types_and_inactive_equipment_category(): void
    {
        $mensajeEsperado = 'La categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.';
        $categorias = [
            $this->categoria('ANAT', 'Anatómica', 'ANATOMICA'),
            $this->categoria('FUNC', 'Funcional', 'FUNCIONAL'),
            $this->categoria('TER', 'Terapéutica', 'TERAPEUTICA'),
            $this->categoria('EQ-I', 'Equipamiento inactivo', 'EQUIPAMIENTO', false),
        ];
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        foreach ($categorias as $indice => $categoria) {
            $this->from(route('catalogos.tipos-equipo.create'))
                ->post(route('catalogos.tipos-equipo.store'), $this->payload($categoria->id, [
                    'codigo' => 'INVALIDO-'.$indice,
                ]))
                ->assertRedirect(route('catalogos.tipos-equipo.create'))
                ->assertSessionHasErrors([
                    'categoria_clinica_id' => $mensajeEsperado,
                ]);

            $this->assertNotContains(
                'validation.exists',
                session('errors')->get('categoria_clinica_id')
            );
        }

        $this->assertDatabaseCount('tipos_equipo_terapeutico', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_store_and_update_enforce_global_unique_code(): void
    {
        $categoria = $this->categoria('CAT', 'Equipamiento');
        $existente = $this->tipoEquipo('EQ-US', 'Existente', $categoria);
        $otro = $this->tipoEquipo('EQ-LAS', 'Otro', $categoria);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->post(route('catalogos.tipos-equipo.store'), $this->payload($categoria->id))
            ->assertSessionHasErrors('codigo');

        $this->put(route('catalogos.tipos-equipo.update', $existente), $this->payload($categoria->id, [
            'nombre' => 'Actualizado',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $existente->id, 'codigo' => 'EQ-US', 'nombre' => 'Actualizado',
        ]);

        $this->put(route('catalogos.tipos-equipo.update', $existente), $this->payload($categoria->id, [
            'codigo' => $otro->codigo,
        ]))->assertSessionHasErrors('codigo');
        $this->assertDatabaseHas('tipos_equipo_terapeutico', ['id' => $existente->id, 'codigo' => 'EQ-US']);
    }

    public function test_show_active_inactive_and_historical_association_is_read_only_without_audit(): void
    {
        $categoriaValida = $this->categoria('CAT-A', 'Aparatología activa');
        $categoriaHistorica = $this->categoria('CAT-H', 'Aparatología histórica');
        $activo = $this->tipoEquipo('EQ-A', 'Activo', $categoriaValida, true);
        $inactivo = $this->tipoEquipo('EQ-I', 'Inactivo', $categoriaHistorica, false);
        $categoriaHistorica->update(['tipo' => 'FUNCIONAL', 'activo' => false]);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.tipos-equipo.show', $activo))
            ->assertOk()->assertViewIs('catalogos.tipos-equipo-terapeutico.show')
            ->assertSee('EQ-A')->assertSee('CAT-A · Aparatología activa')->assertSee('EQUIPAMIENTO');
        $this->get(route('catalogos.tipos-equipo.show', $inactivo))
            ->assertOk()->assertSee('EQ-I')->assertSee('CAT-H · Aparatología histórica')
            ->assertSee('FUNCIONAL')->assertSee('Inactivo');

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_edit_active_and_inactive_offers_valid_candidates_and_preserves_historical_option(): void
    {
        $historica = $this->categoria('HIST', 'Categoría histórica', 'EQUIPAMIENTO', false);
        $valida = $this->categoria('VALIDA', 'Categoría válida');
        $incorrecta = $this->categoria('ANAT', 'Categoría anatómica', 'ANATOMICA');
        $tipoActivo = $this->tipoEquipo('EQ-A', 'Activo', $valida, true);
        $tipoInactivo = $this->tipoEquipo('EQ-I', 'Inactivo', $historica, false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->get(route('catalogos.tipos-equipo.edit', $tipoActivo))
            ->assertOk()->assertDontSee('name="activo"', false);

        $response = $this->get(route('catalogos.tipos-equipo.edit', $tipoInactivo));
        $response->assertOk()->assertViewIs('catalogos.tipos-equipo-terapeutico.edit')
            ->assertSee('Actual — histórica / no disponible para nuevas asociaciones')
            ->assertSee('HIST · Categoría histórica')
            ->assertSee('VALIDA · Categoría válida')
            ->assertDontSee('ANAT · Categoría anatómica');
        $this->assertSame([$valida->id], $response->viewData('categoriasDisponibles')->pluck('id')->all());
        $this->assertSame($historica->id, $response->viewData('categoriaHistoricaActual')->id);
        $this->assertNotSame($incorrecta->id, $response->viewData('categoriaHistoricaActual')->id);
    }

    public function test_update_changes_fields_and_preserves_active_or_inactive_state(): void
    {
        $categoria = $this->categoria('CAT', 'Equipamiento');
        $activo = $this->tipoEquipo('EQ-A', 'Activo', $categoria, true);
        $inactivo = $this->tipoEquipo('EQ-I', 'Inactivo', $categoria, false);
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->put(route('catalogos.tipos-equipo.update', $activo), $this->payload($categoria->id, [
            'codigo' => 'EQ-A', 'nombre' => 'Activo actualizado', 'activo' => false,
        ]))->assertSessionHasNoErrors();
        $this->put(route('catalogos.tipos-equipo.update', $inactivo), $this->payload($categoria->id, [
            'codigo' => 'EQ-I', 'nombre' => 'Inactivo actualizado', 'activo' => true,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $activo->id, 'nombre' => 'Activo actualizado', 'activo' => true,
        ]);
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $inactivo->id, 'nombre' => 'Inactivo actualizado', 'activo' => false,
        ]);
    }

    public function test_update_changes_to_valid_category_and_rejects_each_invalid_new_category(): void
    {
        $actual = $this->categoria('ACTUAL', 'Actual');
        $valida = $this->categoria('NUEVA', 'Nueva válida');
        $tipo = $this->tipoEquipo('EQ-US', 'Ultrasonido', $actual);
        $invalidas = [
            $this->categoria('INACT', 'Inactiva', 'EQUIPAMIENTO', false),
            $this->categoria('ANAT', 'Anatómica', 'ANATOMICA'),
            $this->categoria('FUNC', 'Funcional', 'FUNCIONAL'),
            $this->categoria('TER', 'Terapéutica', 'TERAPEUTICA'),
        ];
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->put(route('catalogos.tipos-equipo.update', $tipo), $this->payload($valida->id))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipo->id, 'categoria_clinica_id' => $valida->id,
        ]);

        foreach ($invalidas as $categoria) {
            $this->put(route('catalogos.tipos-equipo.update', $tipo), $this->payload($categoria->id))
                ->assertSessionHasErrors('categoria_clinica_id');
        }

        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipo->id, 'categoria_clinica_id' => $valida->id,
        ]);
    }

    public function test_update_rejects_active_anatomical_category_with_specific_message_and_preserves_valid_association(): void
    {
        $categoriaValida = $this->categoria('EQUIP', 'Equipamiento');
        $categoriaAnatomica = $this->categoria('ANAT', 'Anatómica', 'ANATOMICA');
        $tipo = $this->tipoEquipo('EQ-US', 'Ultrasonido', $categoriaValida);
        $mensajeEsperado = 'La nueva categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.';

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->from(route('catalogos.tipos-equipo.edit', $tipo))
            ->put(
                route('catalogos.tipos-equipo.update', $tipo),
                $this->payload($categoriaAnatomica->id)
            );

        $response
            ->assertRedirect(route('catalogos.tipos-equipo.edit', $tipo))
            ->assertSessionHasErrors([
                'categoria_clinica_id' => $mensajeEsperado,
            ])
            ->assertSessionMissing('success');

        $this->assertNotContains(
            'validation.exists',
            session('errors')->get('categoria_clinica_id')
        );
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipo->id,
            'categoria_clinica_id' => $categoriaValida->id,
            'activo' => true,
        ]);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_historical_association_can_be_kept_on_update_but_blocks_reactivation(): void
    {
        $categoria = $this->categoria('HIST', 'Histórica');
        $tipo = $this->tipoEquipo('EQ-H', 'Equipo histórico', $categoria, false);
        $mensajeEsperado = 'No se puede reactivar el tipo de equipo porque su categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.';

        app(ActualizarCategoriaClinicaAction::class)->ejecutar($categoria, [
            'codigo' => 'HIST', 'nombre' => 'Histórica', 'tipo' => 'FUNCIONAL',
            'categoria_padre_id' => null,
        ]);
        $auditoriasPrevias = $this->app['db']->table('auditorias')->count();

        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->put(route('catalogos.tipos-equipo.update', $tipo), $this->payload($categoria->id, [
                'codigo' => 'EQ-H2', 'nombre' => 'Equipo histórico revisado',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipo->id, 'codigo' => 'EQ-H2', 'nombre' => 'Equipo histórico revisado',
            'categoria_clinica_id' => $categoria->id, 'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', $auditoriasPrevias + 1);

        $this->patch(route('catalogos.tipos-equipo.reactivar', $tipo))
            ->assertRedirect(route('catalogos.tipos-equipo.index'))
            ->assertSessionHas('error', $mensajeEsperado)
            ->assertSessionMissing('success');
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipo->id,
            'categoria_clinica_id' => $categoria->id,
            'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', $auditoriasPrevias + 1);
    }

    public function test_inactivate_reactivate_and_blocked_reactivations_use_explicit_routes(): void
    {
        $categoria = $this->categoria('CAT', 'Equipamiento');
        $tipo = $this->tipoEquipo('EQ-US', 'Ultrasonido', $categoria, true);
        $mensajeEsperado = 'No se puede reactivar el tipo de equipo porque su categoría clínica debe estar activa y ser de tipo EQUIPAMIENTO.';
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR));

        $this->patch(route('catalogos.tipos-equipo.inactivar', $tipo))
            ->assertRedirect(route('catalogos.tipos-equipo.index'))->assertSessionHas('success');
        $this->patch(route('catalogos.tipos-equipo.inactivar', $tipo))->assertSessionHas('warning');
        $this->patch(route('catalogos.tipos-equipo.reactivar', $tipo))->assertSessionHas('success');
        $this->patch(route('catalogos.tipos-equipo.reactivar', $tipo))->assertSessionHas('warning');
        $this->assertDatabaseCount('auditorias', 2);

        $this->patch(route('catalogos.tipos-equipo.inactivar', $tipo))->assertSessionHas('success');
        app(CambiarEstadoCategoriaClinicaAction::class)->ejecutar($categoria, false);
        $auditoriasPrevias = $this->app['db']->table('auditorias')->count();

        $this->patch(route('catalogos.tipos-equipo.reactivar', $tipo))
            ->assertRedirect(route('catalogos.tipos-equipo.index'))
            ->assertSessionHas('error', $mensajeEsperado)
            ->assertSessionMissing('success');
        $this->assertDatabaseHas('tipos_equipo_terapeutico', [
            'id' => $tipo->id,
            'categoria_clinica_id' => $categoria->id,
            'activo' => false,
        ]);
        $this->assertDatabaseHas('categorias_clinicas', [
            'id' => $categoria->id,
            'tipo' => 'EQUIPAMIENTO',
            'activo' => false,
        ]);
        $this->assertDatabaseCount('auditorias', $auditoriasPrevias);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function categoria(
        string $codigo,
        string $nombre,
        string $tipo = 'EQUIPAMIENTO',
        bool $activo = true
    ): CategoriaClinica {
        return CategoriaClinica::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    private function tipoEquipo(
        string $codigo,
        string $nombre,
        CategoriaClinica $categoria,
        bool $activo = true
    ): TipoEquipoTerapeutico {
        return TipoEquipoTerapeutico::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => 'Descripción de prueba',
            'categoria_clinica_id' => $categoria->id,
            'activo' => $activo,
        ]);
    }

    private function payload(int $categoriaId, array $cambios = []): array
    {
        return array_replace([
            'codigo' => 'EQ-US',
            'nombre' => 'Ultrasonido terapéutico',
            'descripcion' => 'Aparatología de ultrasonido',
            'categoria_clinica_id' => $categoriaId,
        ], $cambios);
    }
}
