<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
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

        Paciente::create([
            'user_id' => $user->id,
            'activo' => true,
        ]);

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

        $paciente = Paciente::create([
            'user_id' => $user->id,
            'direccion' => 'Av. Mitre 1234',
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3764123457',
            'activo' => true,
        ]);

        DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id,
            'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 72.50,
            'altura_cm' => 175.00,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' =>
            'Caminatas tres veces por semana.',
            'estado' => 'PENDIENTE_REVISION',
        ]);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function datosPerfilClinicoValido(
        array $sobrescrituras = []
    ): array {
        return array_replace(
            [
                'dni' => '43122015',
                'fecha_nacimiento' => '2001-02-06',
                'sexo' => 'masculino',
                'telefono' => '3758-409036',

                'direccion' => 'Av. Mitre 1234',
                'contacto_emergencia_nombre' => 'María Pérez',
                'contacto_emergencia_telefono' => '3764123456',

                'objetivo_salud' =>
                'Mejorar movilidad y reducir molestias.',
                'peso_kg' => 72.50,
                'altura_cm' => 175.00,
                'ocupacion' => 'Docente',
                'nivel_actividad_fisica' => 'MODERADO',
                'detalle_actividad_fisica' =>
                'Caminatas tres veces por semana.',

                'antecedentes_respuesta' => 'si',
                'antecedentes' => [
                    'Hipertensión controlada',
                ],

                'cirugias_respuesta' => 'no',
                'cirugias' => [],

                'alergias_respuesta' => 'no',
                'alergias' => [],

                'medicacion_respuesta' => 'si',
                'medicaciones' => [
                    'Losartán',
                ],
            ],
            $sobrescrituras
        );
    }

    private function assertSoloErrorDe(
        TestResponse $response,
        string $campo
    ): void {
        $response->assertSessionHasErrors([$campo]);

        $errores = session('errors');

        $this->assertNotNull($errores);

        $this->assertSame(
            [$campo],
            array_keys(
                $errores
                    ->getBag('default')
                    ->getMessages()
            )
        );
    }

    public function test_paciente_can_view_completar_perfil_screen(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this->actingAs($user)->get(route('perfil.completar'));

        $response->assertOk();
    }

    public function test_step_1_validation_accepts_valid_data_without_persisting(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                [
                    'dni' => '43122015',
                    'fecha_nacimiento' => '2001-02-06',
                    'sexo' => 'masculino',
                    'telefono' => '3758-409036',
                    'direccion' => 'Av. Mitre 1234',
                    'contacto_emergencia_nombre' => 'María Pérez',
                    'contacto_emergencia_telefono' => '3764123456',
                ]
            );

        $response->assertNoContent();

        $user->refresh();
        $user->paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull($user->fecha_nacimiento);
        $this->assertNull($user->sexo);
        $this->assertNull($user->telefono);

        $this->assertNull($user->paciente->direccion);
        $this->assertNull(
            $user->paciente->contacto_emergencia_nombre
        );
        $this->assertNull(
            $user->paciente->contacto_emergencia_telefono
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public function test_step_1_validation_rejects_invalid_data(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                [
                    'dni' => '',
                    'fecha_nacimiento' => now()
                        ->addDay()
                        ->format('Y-m-d'),
                    'sexo' => 'invalido',
                    'telefono' => 'telefono!',
                    'direccion' => '',
                    'contacto_emergencia_nombre' => '',
                    'contacto_emergencia_telefono' => 'telefono!',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dni',
                'fecha_nacimiento',
                'sexo',
                'telefono',
                'direccion',
                'contacto_emergencia_nombre',
                'contacto_emergencia_telefono',
            ]);
    }

    public function test_step_validation_returns_readable_spanish_message(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-1'),
                $this->datosPerfilClinicoValido([
                    'dni' => '',
                ])
            );

        $response->assertUnprocessable();

        $this->assertSame(
            'El campo DNI es obligatorio.',
            $response->json('errors.dni.0')
        );

        $this->assertStringNotContainsString(
            'validation.',
            json_encode(
                $response->json('errors'),
                JSON_THROW_ON_ERROR
            )
        );
    }

    public function test_step_2_validation_accepts_valid_data_without_persisting(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                [
                    'objetivo_salud' =>
                    'Mejorar movilidad y reducir molestias.',
                    'peso_kg' => 72.50,
                    'altura_cm' => 175.00,
                    'ocupacion' => 'Docente',
                    'nivel_actividad_fisica' => 'MODERADO',
                    'detalle_actividad_fisica' =>
                    'Caminatas tres veces por semana.',

                    'antecedentes_respuesta' => 'si',
                    'antecedentes' => [
                        'Hipertensión controlada',
                    ],

                    'cirugias_respuesta' => 'no',
                    'cirugias' => [],

                    'alergias_respuesta' => 'no',
                    'alergias' => [],

                    'medicacion_respuesta' => 'si',
                    'medicaciones' => [
                        'Losartán',
                    ],
                ]
            );

        $response->assertNoContent();

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );

        $user->refresh();
        $user->paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull($user->paciente->direccion);
    }

    public function test_step_2_validation_requires_detail_when_answer_is_yes(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                [
                    'objetivo_salud' => 'Mejorar movilidad',
                    'peso_kg' => 72.50,
                    'altura_cm' => 175.00,
                    'ocupacion' => 'Docente',
                    'nivel_actividad_fisica' => 'MODERADO',
                    'detalle_actividad_fisica' =>
                    'Caminatas tres veces por semana.',

                    'antecedentes_respuesta' => 'si',
                    'antecedentes' => [
                        '   ',
                    ],

                    'cirugias_respuesta' => 'no',
                    'cirugias' => [],

                    'alergias_respuesta' => 'no',
                    'alergias' => [],

                    'medicacion_respuesta' => 'no',
                    'medicaciones' => [],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'antecedentes',
            ]);

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );
    }

    public function test_step_2_validation_rejects_details_when_answer_is_no(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route('perfil.validar-paso-2'),
                [
                    'objetivo_salud' => 'Mejorar movilidad',
                    'peso_kg' => 72.50,
                    'altura_cm' => 175.00,
                    'ocupacion' => 'Docente',
                    'nivel_actividad_fisica' => 'MODERADO',
                    'detalle_actividad_fisica' =>
                    'Caminatas tres veces por semana.',

                    'antecedentes_respuesta' => 'no',
                    'antecedentes' => [
                        'Hipertensión',
                    ],

                    'cirugias_respuesta' => 'no',
                    'cirugias' => [],

                    'alergias_respuesta' => 'no',
                    'alergias' => [],

                    'medicacion_respuesta' => 'no',
                    'medicaciones' => [],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'antecedentes',
            ]);

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public function test_paciente_can_complete_initial_clinical_profile_with_valid_data(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido()
            );

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas(
            'success',
            'Perfil clínico inicial completado correctamente.'
        );
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
        ]);

        $paciente = Paciente::query()
            ->where('user_id', $user->id)
            ->sole();

        $this->assertSame(
            'Av. Mitre 1234',
            $paciente->direccion
        );

        $this->assertSame(
            'María Pérez',
            $paciente->contacto_emergencia_nombre
        );

        $this->assertSame(
            '3764123456',
            $paciente->contacto_emergencia_telefono
        );

        $declaracion = DeclaracionSaludPaciente::query()
            ->where('paciente_id', $paciente->id)
            ->sole();

        $this->assertSame(
            DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
            $declaracion->estado
        );

        $this->assertSame(
            'Mejorar movilidad y reducir molestias.',
            $declaracion->objetivo_salud
        );

        $this->assertSame(
            '72.50',
            $declaracion->peso_kg
        );

        $this->assertSame(
            '175.00',
            $declaracion->altura_cm
        );

        $this->assertSame(
            'Docente',
            $declaracion->ocupacion
        );

        $this->assertSame(
            'MODERADO',
            $declaracion->nivel_actividad_fisica
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' => 'ANTECEDENTE',
                'descripcion' => 'Hipertensión controlada',
            ]
        );

        $this->assertDatabaseHas(
            'items_declaracion_salud',
            [
                'declaracion_salud_id' => $declaracion->id,
                'tipo' => 'MEDICACION',
                'descripcion' => 'Losartán',
            ]
        );

        $this->assertDatabaseCount(
            'historias_clinicas',
            0
        );
    }

    public function test_pending_declaration_redirects_back_with_input_and_warning(): void
    {
        $user = $this->pacienteIncompleto();
        $paciente = $user->paciente;

        DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id,
            'objetivo_salud' => 'Declaración previa',
            'peso_kg' => 70,
            'altura_cm' => 170,
            'ocupacion' => 'Empleado',
            'nivel_actividad_fisica' => 'SEDENTARIO',
            'detalle_actividad_fisica' =>
            'Sin actividad física habitual.',
            'estado' =>
            DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
        ]);

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido()
            );

        $response->assertRedirect(
            route('perfil.completar')
        );

        $response->assertSessionHas(
            'warning',
            'Ya existe una declaración de salud pendiente de revisión profesional.'
        );

        $response->assertSessionHasInput(
            'dni',
            '43122015'
        );

        $user->refresh();
        $paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull($user->fecha_nacimiento);
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

    public function test_persistence_failure_redirects_back_with_input_and_friendly_error(): void
    {
        $user = $this->pacienteIncompleto();

        /*
         * El fallo se produce al insertar la auditoría, cuando la
         * transacción ya actualizó User/Paciente y creó declaración/items.
         * Todo debe revertirse.
         */
        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_uc15_auditoria_insert
            BEFORE INSERT ON auditorias
            BEGIN
                SELECT RAISE(ABORT, 'forced audit failure');
            END
        SQL);

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido()
            );

        DB::statement(
            'DROP TRIGGER IF EXISTS fail_uc15_auditoria_insert'
        );

        $response->assertRedirect(
            route('perfil.completar')
        );

        $response->assertSessionHas(
            'error',
            'No se pudo completar el perfil clínico inicial por un '
                . 'inconveniente técnico. Los cambios no fueron '
                . 'aplicados. Intente nuevamente.'
        );

        $response->assertSessionHasInput(
            'dni',
            '43122015'
        );

        $user->refresh();
        $user->paciente->refresh();

        $this->assertNull($user->dni);
        $this->assertNull($user->fecha_nacimiento);
        $this->assertNull($user->sexo);
        $this->assertNull($user->telefono);

        $this->assertNull(
            $user->paciente->direccion
        );

        $this->assertNull(
            $user->paciente->contacto_emergencia_nombre
        );

        $this->assertNull(
            $user->paciente->contacto_emergencia_telefono
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

    public function test_update_requires_dni(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'dni' => '',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'dni'
        );
    }

    public function test_update_requires_fecha_nacimiento(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'fecha_nacimiento' => '',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'fecha_nacimiento'
        );
    }

    public function test_update_requires_fecha_nacimiento_before_today(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'fecha_nacimiento' => now()
                        ->addDay()
                        ->format('Y-m-d'),
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'fecha_nacimiento'
        );
    }

    public function test_update_requires_valid_sexo(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'sexo' => 'valor_invalido',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'sexo'
        );
    }

    public function test_update_requires_telefono(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'telefono' => '',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'telefono'
        );
    }

    public function test_update_rejects_invalid_telefono_format(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'telefono' => 'telefono-invalido!',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'telefono'
        );
    }

    public function test_update_requires_unique_dni_except_current_user(): void
    {
        $existingUser = User::factory()->create([
            'dni' => '43122015',
            'activo' => true,
        ]);

        $existingUser->assignRole(
            RoleEnum::PACIENTE->value
        );

        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'dni' => '43122015',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            'dni'
        );
    }

    #[DataProvider('camposRequeridosRestantesUc15')]
    public function test_final_update_requires_remaining_uc15_fields(
        string $campo
    ): void {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    $campo => '',
                ])
            );

        $response->assertRedirect(route('perfil.completar'));

        $this->assertSoloErrorDe(
            $response,
            $campo
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public static function camposRequeridosRestantesUc15(): array
    {
        return [
            'direccion' => [
                'direccion',
            ],
            'contacto emergencia nombre' => [
                'contacto_emergencia_nombre',
            ],
            'contacto emergencia telefono' => [
                'contacto_emergencia_telefono',
            ],
            'objetivo salud' => [
                'objetivo_salud',
            ],
            'peso' => [
                'peso_kg',
            ],
            'altura' => [
                'altura_cm',
            ],
            'ocupacion' => [
                'ocupacion',
            ],
            'nivel actividad fisica' => [
                'nivel_actividad_fisica',
            ],
            'detalle actividad fisica' => [
                'detalle_actividad_fisica',
            ],
            'respuesta antecedentes' => [
                'antecedentes_respuesta',
            ],
            'respuesta cirugias' => [
                'cirugias_respuesta',
            ],
            'respuesta alergias' => [
                'alergias_respuesta',
            ],
            'respuesta medicacion' => [
                'medicacion_respuesta',
            ],
        ];
    }


    #[DataProvider('valoresInvalidosAdicionalesUc15')]
    public function test_final_update_rejects_additional_invalid_values(
        array $sobrescrituras,
        string $campo
    ): void {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido(
                    $sobrescrituras
                )
            );

        $response->assertRedirect(
            route('perfil.completar')
        );

        $this->assertSoloErrorDe(
            $response,
            $campo
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public static function valoresInvalidosAdicionalesUc15(): array
    {
        return [
            'telefono emergencia formato invalido' => [
                [
                    'contacto_emergencia_telefono' =>
                    'telefono-invalido!',
                ],
                'contacto_emergencia_telefono',
            ],

            'peso igual a cero' => [
                [
                    'peso_kg' => 0,
                ],
                'peso_kg',
            ],

            'peso inferior al minimo' => [
                [
                    'peso_kg' => 0.50,
                ],
                'peso_kg',
            ],

            'peso superior al maximo' => [
                [
                    'peso_kg' => 1000,
                ],
                'peso_kg',
            ],

            'altura igual a cero' => [
                [
                    'altura_cm' => 0,
                ],
                'altura_cm',
            ],

            'altura inferior al minimo' => [
                [
                    'altura_cm' => 17,
                ],
                'altura_cm',
            ],

            'altura superior al maximo' => [
                [
                    'altura_cm' => 1000,
                ],
                'altura_cm',
            ],

            'nivel actividad fuera del dominio' => [
                [
                    'nivel_actividad_fisica' => 'INTENSO',
                ],
                'nivel_actividad_fisica',
            ],

            'respuesta antecedentes fuera del dominio' => [
                [
                    'antecedentes_respuesta' => 'quizas',
                ],
                'antecedentes_respuesta',
            ],

            'respuesta cirugias fuera del dominio' => [
                [
                    'cirugias_respuesta' => 'quizas',
                ],
                'cirugias_respuesta',
            ],

            'respuesta alergias fuera del dominio' => [
                [
                    'alergias_respuesta' => 'quizas',
                ],
                'alergias_respuesta',
            ],

            'respuesta medicacion fuera del dominio' => [
                [
                    'medicacion_respuesta' => 'quizas',
                ],
                'medicacion_respuesta',
            ],
        ];
    }

    #[DataProvider('coherenciaItemsClinicosUc15')]
    public function test_final_update_enforces_clinical_answer_item_coherence(
        array $sobrescrituras,
        string $campo
    ): void {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido(
                    $sobrescrituras
                )
            );

        $response->assertRedirect(
            route('perfil.completar')
        );

        $this->assertSoloErrorDe(
            $response,
            $campo
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public static function coherenciaItemsClinicosUc15(): array
    {
        return [
            'antecedentes si sin detalle' => [
                [
                    'antecedentes_respuesta' => 'si',
                    'antecedentes' => ['   '],
                ],
                'antecedentes',
            ],

            'cirugias si sin detalle' => [
                [
                    'cirugias_respuesta' => 'si',
                    'cirugias' => ['   '],
                ],
                'cirugias',
            ],

            'alergias si sin detalle' => [
                [
                    'alergias_respuesta' => 'si',
                    'alergias' => ['   '],
                ],
                'alergias',
            ],

            'medicacion si sin detalle' => [
                [
                    'medicacion_respuesta' => 'si',
                    'medicaciones' => ['   '],
                ],
                'medicaciones',
            ],

            'antecedentes no con detalle' => [
                [
                    'antecedentes_respuesta' => 'no',
                    'antecedentes' => [
                        'Hipertensión',
                    ],
                ],
                'antecedentes',
            ],

            'cirugias no con detalle' => [
                [
                    'cirugias_respuesta' => 'no',
                    'cirugias' => [
                        'Apendicectomía',
                    ],
                ],
                'cirugias',
            ],

            'alergias no con detalle' => [
                [
                    'alergias_respuesta' => 'no',
                    'alergias' => [
                        'Penicilina',
                    ],
                ],
                'alergias',
            ],

            'medicacion no con detalle' => [
                [
                    'medicacion_respuesta' => 'no',
                    'medicaciones' => [
                        'Losartán',
                    ],
                ],
                'medicaciones',
            ],
        ];
    }

    public function test_final_update_rejects_invalid_clinical_array_structure(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'antecedentes_respuesta' => 'si',
                    'antecedentes' => 'No es un array',
                ])
            );

        $response->assertRedirect(
            route('perfil.completar')
        );

        $this->assertSoloErrorDe(
            $response,
            'antecedentes'
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
    }

    public function test_final_update_rejects_non_string_clinical_item_without_semantic_duplicate_error(): void
    {
        $user = $this->pacienteIncompleto();

        $response = $this
            ->from(route('perfil.completar'))
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'antecedentes_respuesta' => 'si',
                    'antecedentes' => [
                        123,
                    ],
                ])
            );

        $response->assertRedirect(
            route('perfil.completar')
        );

        /*
         * Debe existir únicamente el error estructural del elemento,
         * no un segundo error semántico sobre "antecedentes".
         */
        $this->assertSoloErrorDe(
            $response,
            'antecedentes.0'
        );

        $this->assertDatabaseCount(
            'declaraciones_salud_paciente',
            0
        );

        $this->assertDatabaseCount(
            'items_declaracion_salud',
            0
        );
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

    public function test_paciente_completo_can_view_read_only_clinical_profile(): void
    {
        $user = $this->pacienteCompleto();

        $response = $this
            ->actingAs($user)
            ->get(route('perfil.completar'));

        $response
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('30111222')
            ->assertSee('Av. Mitre 1234')
            ->assertSee('Mejorar movilidad')
            ->assertSee('72.50')
            ->assertSee('175.00')
            ->assertSee('Docente')
            ->assertSee('Moderado')
            ->assertSee('Pendiente de revisión');

        $response->assertDontSee(
            'Confirmar y finalizar'
        );

        $response->assertDontSee(
            'perfil-paciente-form',
            false
        );
    }

    public function test_paciente_can_keep_own_dni_when_completing_initial_clinical_profile(): void
    {
        $user = $this->pacienteIncompleto();

        /*
         * El DNI ya pertenece al propio usuario, pero su perfil
         * clínico continúa incompleto y todavía no tiene declaración.
         */
        $user->update([
            'dni' => '30111222',
        ]);

        $user->refresh();

        $response = $this
            ->actingAs($user)
            ->post(
                route('perfil.actualizar'),
                $this->datosPerfilClinicoValido([
                    'dni' => '30111222',
                ])
            );

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'dni' => '30111222',
        ]);

        $this->assertDatabaseHas(
            'declaraciones_salud_paciente',
            [
                'paciente_id' => $user->paciente->id,
                'estado' =>
                DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
            ]
        );
    }
}
