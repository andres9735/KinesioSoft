<?php

namespace Tests\Feature\Actions\Pacientes;

use App\Actions\Pacientes\CompletarPerfilClinicoInicialAction;
use App\Models\Auditoria;
use App\Models\DeclaracionSaludPaciente;
use App\Models\ItemDeclaracionSalud;
use App\Models\Paciente;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompletarPerfilClinicoInicialActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_initial_clinical_profile_and_creates_declaration_items_and_minimal_audit(): void
    {
        [$user, $paciente] = $this->crearContextoPaciente();

        $this->actingAs($user);

        $declaracion = app(
            CompletarPerfilClinicoInicialAction::class
        )->ejecutar(
            $user,
            $this->datosValidos()
        );

        $user->refresh();
        $paciente->refresh();
        $declaracion->refresh();

        $this->assertSame('43122015', $user->dni);
        $this->assertSame(
            '2001-02-06',
            $user->getRawOriginal('fecha_nacimiento')
        );
        $this->assertSame('masculino', $user->sexo);
        $this->assertSame('3758-409036', $user->telefono);

        $this->assertSame(
            'Av. Libertador 1234',
            $paciente->direccion
        );
        $this->assertSame(
            'María Gómez',
            $paciente->contacto_emergencia_nombre
        );
        $this->assertSame(
            '3764-112233',
            $paciente->contacto_emergencia_telefono
        );

        $this->assertSame(
            $paciente->id,
            $declaracion->paciente_id
        );
        $this->assertSame(
            'Mejorar movilidad y disminuir dolor lumbar',
            $declaracion->objetivo_salud
        );
        $this->assertSame('72.50', $declaracion->peso_kg);
        $this->assertSame('174.00', $declaracion->altura_cm);
        $this->assertSame(
            'Desarrollador de software',
            $declaracion->ocupacion
        );
        $this->assertSame(
            'MODERADO',
            $declaracion->nivel_actividad_fisica
        );
        $this->assertSame(
            'Caminatas y entrenamiento de fuerza tres veces por semana.',
            $declaracion->detalle_actividad_fisica
        );
        $this->assertSame(
            DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
            $declaracion->estado
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' => ItemDeclaracionSalud::TIPO_ANTECEDENTE,
                'descripcion' => 'Hipertensión arterial familiar',
            ]
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' => ItemDeclaracionSalud::TIPO_CIRUGIA,
                'descripcion' => 'Apendicectomía en 2015',
            ]
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' => ItemDeclaracionSalud::TIPO_ALERGIA,
                'descripcion' => 'Penicilina',
            ]
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' => ItemDeclaracionSalud::TIPO_MEDICACION,
                'descripcion' => 'Ibuprofeno según necesidad',
            ]
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            4
        );

        /*
         * UC-15 no crea todavía la Historia Clínica oficial.
         */
        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );

        $auditoria = Auditoria::query()->sole();

        $this->assertSame(
            'perfil_clinico_inicial_completado',
            $auditoria->evento
        );
        $this->assertSame(
            'Perfiles',
            $auditoria->modulo
        );
        $this->assertSame(
            $declaracion->getMorphClass(),
            $auditoria->auditable_type
        );
        $this->assertSame(
            $declaracion->id,
            $auditoria->auditable_id
        );

        $this->assertSame(
            [
                'estado' =>
                DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
            ],
            $auditoria->valores_nuevos
        );

        $this->assertSame(
            $user->id,
            $auditoria->actor_user_id
        );
        $this->assertSame(
            'Paciente Prueba',
            $auditoria->actor_nombre_snapshot
        );
        $this->assertSame(
            'paciente.prueba@example.com',
            $auditoria->actor_email_snapshot
        );

        /*
         * La auditoría registra el hecho administrativo,
         * pero no duplica información clínica sensible.
         */
        foreach (
            [
                'objetivo_salud',
                'peso_kg',
                'altura_cm',
                'ocupacion',
                'nivel_actividad_fisica',
                'detalle_actividad_fisica',
                'antecedentes',
                'cirugias',
                'alergias',
                'medicaciones',
            ] as $campoClinico
        ) {
            $this->assertArrayNotHasKey(
                $campoClinico,
                $auditoria->valores_nuevos ?? []
            );

            $this->assertArrayNotHasKey(
                $campoClinico,
                $auditoria->valores_anteriores ?? []
            );
        }
    }

    public function test_allows_categories_answered_no_without_creating_items(): void
    {
        [$user] = $this->crearContextoPaciente();

        $this->actingAs($user);

        $datos = $this->datosValidos([
            'antecedentes_respuesta' => 'no',
            'antecedentes' => [],
            'cirugias_respuesta' => 'no',
            'cirugias' => [],
            'alergias_respuesta' => 'no',
            'alergias' => [],
            'medicacion_respuesta' => 'no',
            'medicaciones' => [],
        ]);

        $declaracion = app(
            CompletarPerfilClinicoInicialAction::class
        )->ejecutar(
            $user,
            $datos
        );

        $this->assertDatabaseHas(
            'declaraciones_salud_paciente',
            [
                'id' => $declaracion->id,
                'estado' =>
                DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
            ]
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );

        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );
    }

    public function test_creates_items_only_for_categories_answered_yes(): void
    {
        [$user] = $this->crearContextoPaciente();

        $this->actingAs($user);

        $declaracion = app(
            CompletarPerfilClinicoInicialAction::class
        )->ejecutar(
            $user,
            $this->datosValidos([
                'antecedentes_respuesta' => 'si',
                'antecedentes' => [
                    'Hipertensión arterial familiar',
                ],

                'cirugias_respuesta' => 'no',
                'cirugias' => [],

                'alergias_respuesta' => 'no',
                'alergias' => [],

                'medicacion_respuesta' => 'si',
                'medicaciones' => [
                    'Ibuprofeno según necesidad',
                ],
            ])
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' =>
                    ItemDeclaracionSalud::TIPO_ANTECEDENTE,
                'descripcion' =>
                    'Hipertensión arterial familiar',
            ]
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' =>
                    ItemDeclaracionSalud::TIPO_MEDICACION,
                'descripcion' =>
                    'Ibuprofeno según necesidad',
            ]
        );

        $this->assertDatabaseMissing(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' =>
                    ItemDeclaracionSalud::TIPO_CIRUGIA,
            ]
        );

        $this->assertDatabaseMissing(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' =>
                    ItemDeclaracionSalud::TIPO_ALERGIA,
            ]
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            2
        );

        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );
    }

    public function test_rejects_second_pending_declaration_without_modifying_profile(): void
    {
        [$user, $paciente] = $this->crearContextoPaciente();

        DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id,
            'objetivo_salud' => 'Declaración previa',
            'peso_kg' => 70,
            'altura_cm' => 170,
            'ocupacion' => 'Empleado',
            'nivel_actividad_fisica' => 'SEDENTARIO',
            'detalle_actividad_fisica' => 'Sin actividad habitual.',
            'estado' =>
            DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
        ]);

        $this->actingAs($user);

        try {
            app(
                CompletarPerfilClinicoInicialAction::class
            )->ejecutar(
                $user,
                $this->datosValidos()
            );

            $this->fail(
                'Se esperaba una DomainException por declaración pendiente.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'Ya existe una declaración de salud pendiente de revisión profesional.',
                $exception->getMessage()
            );
        }

        $user->refresh();
        $paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull(
            $user->getRawOriginal('fecha_nacimiento')
        );
        $this->assertNull($user->sexo);
        $this->assertNull($user->telefono);

        $this->assertNull($paciente->direccion);
        $this->assertNull(
            $paciente->contacto_emergencia_nombre
        );
        $this->assertNull(
            $paciente->contacto_emergencia_telefono
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            1
        );
        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
        $this->assertDatabaseCount(
            'auditorias',
            0
        );
        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );
    }

    public function test_rolls_back_all_changes_when_audit_persistence_fails(): void
    {
        [$user, $paciente] = $this->crearContextoPaciente();

        $this->actingAs($user);

        /*
         * Los tests usan SQLite :memory:.
         * Este trigger fuerza un fallo exactamente al intentar
         * insertar la auditoría, después de que la Action haya
         * actualizado User/Paciente y creado declaración/items.
         */
        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_auditoria_insert
            BEFORE INSERT ON auditorias
            BEGIN
                SELECT RAISE(ABORT, 'forced audit failure');
            END
        SQL);

        try {
            app(
                CompletarPerfilClinicoInicialAction::class
            )->ejecutar(
                $user,
                $this->datosValidos()
            );

            $this->fail(
                'Se esperaba un error de persistencia de auditoría.'
            );
        } catch (QueryException) {
            $this->assertTrue(true);
        } finally {
            DB::statement(
                'DROP TRIGGER IF EXISTS fail_auditoria_insert'
            );
        }

        $user->refresh();
        $paciente->refresh();

        /*
         * La transacción debe haber restaurado el estado anterior.
         */
        $this->assertNull($user->dni);
        $this->assertNull(
            $user->getRawOriginal('fecha_nacimiento')
        );
        $this->assertNull($user->sexo);
        $this->assertNull($user->telefono);

        $this->assertNull($paciente->direccion);
        $this->assertNull(
            $paciente->contacto_emergencia_nombre
        );
        $this->assertNull(
            $paciente->contacto_emergencia_telefono
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );
        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
        $this->assertDatabaseCount(
            'auditorias',
            0
        );
        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );
    }

    /**
     * @return array{0: User, 1: Paciente}
     */
    private function crearContextoPaciente(): array
    {
        $user = User::factory()->create([
            'name' => 'Paciente',
            'apellido' => 'Prueba',
            'email' => 'paciente.prueba@example.com',
            'dni' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => null,
        ]);

        $paciente = Paciente::create([
            'user_id' => $user->id,
            'direccion' => null,
            'contacto_emergencia_nombre' => null,
            'contacto_emergencia_telefono' => null,
            'activo' => true,
        ]);

        return [$user, $paciente];
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function datosValidos(
        array $sobrescrituras = []
    ): array {
        return array_replace(
            [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',

                'direccion' => 'Av. Libertador 1234',
                'contacto_emergencia_nombre' => 'María Gómez',
                'contacto_emergencia_telefono' => '3764-112233',

                'objetivo_salud' =>
                'Mejorar movilidad y disminuir dolor lumbar',
                'peso_kg' => 72.5,
                'altura_cm' => 174,
                'ocupacion' => 'Desarrollador de software',
                'nivel_actividad_fisica' => 'MODERADO',
                'detalle_actividad_fisica' =>
                'Caminatas y entrenamiento de fuerza tres veces por semana.',

                'antecedentes_respuesta' => 'si',
                'antecedentes' => [
                    'Hipertensión arterial familiar',
                ],

                'cirugias_respuesta' => 'si',
                'cirugias' => [
                    'Apendicectomía en 2015',
                ],

                'alergias_respuesta' => 'si',
                'alergias' => [
                    'Penicilina',
                ],

                'medicacion_respuesta' => 'si',
                'medicaciones' => [
                    'Ibuprofeno según necesidad',
                ],
            ],
            $sobrescrituras
        );
    }
}
