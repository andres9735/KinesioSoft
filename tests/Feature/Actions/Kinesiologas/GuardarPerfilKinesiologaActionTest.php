<?php

namespace Tests\Feature\Actions\Kinesiologas;

use App\Actions\Kinesiologas\GuardarPerfilKinesiologaAction;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardarPerfilKinesiologaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_initial_professional_profile_atomically_and_audits_creation(): void
    {
        $catalogos = $this->catalogos();

        $user = User::factory()->create([
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
        ]);

        $kinesiologa = app(GuardarPerfilKinesiologaAction::class)->ejecutar(
            $user,
            [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo1']->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion']->id,
                'colegio_emisor_id' => $catalogos['colegio']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [
                    $catalogos['especialidad1']->id,
                    $catalogos['especialidad2']->id,
                ],
            ]
        );

        $this->assertTrue($kinesiologa->exists);
        $this->assertSame($user->id, $kinesiologa->user_id);

        $this->assertSame(
            $catalogos['titulo1']->id,
            $kinesiologa->titulo_profesional_id
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '30456321',
            'fecha_nacimiento' => '1993-08-09',
            'sexo' => 'femenino',
            'telefono' => '3758-451963',
        ]);

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'kinesiologa_id' => $kinesiologa->id,
                'nro_matricula_profesional' => '0763',
                'jurisdiccion_id' => $catalogos['jurisdiccion']->id,
                'colegio_emisor_id' => $catalogos['colegio']->id,
                'activa' => true,
            ]
        );

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $catalogos['especialidad1']->id,
        ]);

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $catalogos['especialidad2']->id,
        ]);

        $auditoria = Auditoria::query()
            ->where('evento', 'perfil_kinesiologa_creado')
            ->sole();

        $this->assertSame('Perfiles', $auditoria->modulo);
        $this->assertSame(
            $kinesiologa->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame($kinesiologa->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);

        $this->assertSame(
            [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo1']->id,
                'especialidades' => [
                    [
                        'id' => $catalogos['especialidad1']->id,
                        'nombre' => $catalogos['especialidad1']->nombre,
                    ],
                    [
                        'id' => $catalogos['especialidad2']->id,
                        'nombre' => $catalogos['especialidad2']->nombre,
                    ],
                ],
            ],
            $auditoria->valores_nuevos
        );
    }

    public function test_updates_existing_profile_without_creating_another_matricula_and_audits_changes(): void
    {
        $catalogos = $this->catalogos();

        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764123456',
        ]);

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $catalogos['titulo1']->id,
            'consultorio_id' => null,
        ]);

        $matricula = MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => 'MP-1234',
            'jurisdiccion_id' => $catalogos['jurisdiccion']->id,
            'colegio_emisor_id' => $catalogos['colegio']->id,
            'activa' => true,
        ]);

        $kinesiologa
            ->especialidades()
            ->sync([$catalogos['especialidad1']->id]);

        $actualizada = app(GuardarPerfilKinesiologaAction::class)->ejecutar(
            $user,
            [
                'dni' => '30999888',
                'fecha_nacimiento' => '1990-01-01',
                'sexo' => 'femenino',
                'telefono' => '3764999999',
                'titulo_profesional_id' => $catalogos['titulo2']->id,
                'especialidades' => [
                    $catalogos['especialidad2']->id,
                ],
            ]
        );

        $actualizada->refresh();

        $this->assertSame($kinesiologa->id, $actualizada->id);

        $this->assertSame(
            $catalogos['titulo2']->id,
            $actualizada->titulo_profesional_id
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '30999888',
            'telefono' => '3764999999',
        ]);

        $this->assertDatabaseHas(
            'matriculas_profesionales_kinesiologia',
            [
                'id' => $matricula->id,
                'kinesiologa_id' => $kinesiologa->id,
                'nro_matricula_profesional' => 'MP-1234',
            ]
        );

        $this->assertSame(
            1,
            MatriculaProfesionalKinesiologia::query()
                ->where('kinesiologa_id', $kinesiologa->id)
                ->count()
        );

        $this->assertDatabaseMissing('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $catalogos['especialidad1']->id,
        ]);

        $this->assertDatabaseHas('kinesiologa_especialidad', [
            'kinesiologa_id' => $kinesiologa->id,
            'especialidad_id' => $catalogos['especialidad2']->id,
        ]);

        $auditorias = Auditoria::query()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $auditorias);

        $this->assertSame(
            'perfil_kinesiologa_actualizado',
            $auditorias[0]->evento
        );

        $this->assertSame(
            [
                'dni' => '30111222',
                'telefono' => '3764123456',
                'titulo_profesional_id' => $catalogos['titulo1']->id,
            ],
            $auditorias[0]->valores_anteriores
        );

        $this->assertSame(
            [
                'dni' => '30999888',
                'telefono' => '3764999999',
                'titulo_profesional_id' => $catalogos['titulo2']->id,
            ],
            $auditorias[0]->valores_nuevos
        );

        $this->assertSame(
            'especialidades_kinesiologa_actualizadas',
            $auditorias[1]->evento
        );

        $this->assertSame(
            [
                'especialidades' => [
                    [
                        'id' => $catalogos['especialidad1']->id,
                        'nombre' => $catalogos['especialidad1']->nombre,
                    ],
                ],
            ],
            $auditorias[1]->valores_anteriores
        );

        $this->assertSame(
            [
                'especialidades' => [
                    [
                        'id' => $catalogos['especialidad2']->id,
                        'nombre' => $catalogos['especialidad2']->nombre,
                    ],
                ],
            ],
            $auditorias[1]->valores_nuevos
        );
    }

    public function test_only_specialty_change_generates_only_specialty_audit(): void
    {
        $catalogos = $this->catalogos();

        [$user, $kinesiologa] = $this->perfilExistente($catalogos);

        $kinesiologa
            ->especialidades()
            ->sync([$catalogos['especialidad1']->id]);

        app(GuardarPerfilKinesiologaAction::class)->ejecutar(
            $user,
            [
                'dni' => $user->dni,
                'fecha_nacimiento' => $user->getRawOriginal(
                    'fecha_nacimiento'
                ),
                'sexo' => $user->sexo,
                'telefono' => $user->telefono,
                'titulo_profesional_id' => $kinesiologa->titulo_profesional_id,
                'especialidades' => [
                    $catalogos['especialidad2']->id,
                ],
            ]
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'especialidades_kinesiologa_actualizadas',
            $auditoria->evento
        );
    }

    public function test_does_not_audit_when_existing_profile_has_no_changes(): void
    {
        $catalogos = $this->catalogos();

        [$user, $kinesiologa] = $this->perfilExistente($catalogos);

        $kinesiologa
            ->especialidades()
            ->sync([
                $catalogos['especialidad1']->id,
                $catalogos['especialidad2']->id,
            ]);

        app(GuardarPerfilKinesiologaAction::class)->ejecutar(
            $user,
            [
                'dni' => $user->dni,
                'fecha_nacimiento' => $user->getRawOriginal(
                    'fecha_nacimiento'
                ),
                'sexo' => $user->sexo,
                'telefono' => $user->telefono,
                'titulo_profesional_id' => $kinesiologa->titulo_profesional_id,
                'especialidades' => [
                    $catalogos['especialidad2']->id,
                    $catalogos['especialidad1']->id,
                ],
            ]
        );

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_uses_authenticated_kinesiologa_as_audit_actor(): void
    {
        $catalogos = $this->catalogos();

        $user = User::factory()->create([
            'name' => 'Laura',
            'apellido' => 'Profesional',
            'email' => 'laura.profesional@example.com',
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
        ]);

        $this->actingAs($user);

        app(GuardarPerfilKinesiologaAction::class)->ejecutar(
            $user,
            [
                'dni' => '30456321',
                'fecha_nacimiento' => '1993-08-09',
                'sexo' => 'femenino',
                'telefono' => '3758-451963',
                'titulo_profesional_id' => $catalogos['titulo1']->id,
                'jurisdiccion_id' => $catalogos['jurisdiccion']->id,
                'colegio_emisor_id' => $catalogos['colegio']->id,
                'nro_matricula_profesional' => '0763',
                'especialidades' => [
                    $catalogos['especialidad1']->id,
                ],
            ]
        );

        $auditoria = Auditoria::query()
            ->where('evento', 'perfil_kinesiologa_creado')
            ->sole();

        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame(
            'Laura Profesional',
            $auditoria->actor_nombre
        );
        $this->assertSame(
            'laura.profesional@example.com',
            $auditoria->actor_email
        );
    }

    public function test_rolls_back_user_update_and_audit_when_professional_profile_persistence_fails(): void
    {
        $catalogos = $this->catalogos();

        $user = User::factory()->create([
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
        ]);

        try {
            app(GuardarPerfilKinesiologaAction::class)->ejecutar(
                $user,
                [
                    'dni' => '30456321',
                    'fecha_nacimiento' => '1993-08-09',
                    'sexo' => 'femenino',
                    'telefono' => '3758-451963',
                    'titulo_profesional_id' => $catalogos['titulo1']->id,
                    'jurisdiccion_id' => $catalogos['jurisdiccion']->id,

                    // Se fuerza un error de FK al crear la matrícula.
                    'colegio_emisor_id' => 999999,

                    'nro_matricula_profesional' => '0763',
                    'especialidades' => [
                        $catalogos['especialidad1']->id,
                    ],
                ]
            );

            $this->fail(
                'Se esperaba una QueryException al usar un colegio emisor inexistente.'
            );
        } catch (QueryException) {
            $user->refresh();

            $this->assertNull($user->dni);
            $this->assertNull($user->fecha_nacimiento);
            $this->assertNull($user->sexo);
            $this->assertNull($user->telefono);

            $this->assertDatabaseMissing('kinesiologas', [
                'user_id' => $user->id,
            ]);

            $this->assertDatabaseMissing(
                'matriculas_profesionales_kinesiologia',
                [
                    'nro_matricula_profesional' => '0763',
                ]
            );

            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    /**
     * @return array{0: User, 1: Kinesiologa}
     */
    private function perfilExistente(array $catalogos): array
    {
        $user = User::factory()->create([
            'dni' => '30111222',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764123456',
        ]);

        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $catalogos['titulo1']->id,
            'consultorio_id' => null,
        ]);

        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => 'MP-1234',
            'jurisdiccion_id' => $catalogos['jurisdiccion']->id,
            'colegio_emisor_id' => $catalogos['colegio']->id,
            'activa' => true,
        ]);

        return [$user, $kinesiologa];
    }

    /**
     * @return array{
     *     titulo1: TituloProfesional,
     *     titulo2: TituloProfesional,
     *     jurisdiccion: Jurisdiccion,
     *     colegio: ColegioEmisor,
     *     especialidad1: EspecialidadKinesiologia,
     *     especialidad2: EspecialidadKinesiologia
     * }
     */
    private function catalogos(): array
    {
        $titulo1 = TituloProfesional::create([
            'nombre' => 'Kinesiólogo',
            'slug' => 'kinesiologo',
            'activo' => true,
        ]);

        $titulo2 = TituloProfesional::create([
            'nombre' => 'Licenciado en Kinesiología',
            'slug' => 'licenciado-en-kinesiologia',
            'activo' => true,
        ]);

        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones',
            'activo' => true,
        ]);

        $colegio = ColegioEmisor::create([
            'nombre' =>
            'Colegio de Kinesiólogos y Fisioterapeutas de Misiones',
            'sigla' => 'CKFM',
            'jurisdiccion_id' => $jurisdiccion->id,
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
            'titulo1',
            'titulo2',
            'jurisdiccion',
            'colegio',
            'especialidad1',
            'especialidad2'
        );
    }
}
