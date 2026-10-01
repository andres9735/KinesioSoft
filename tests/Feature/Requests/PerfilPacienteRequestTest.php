<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\PerfilPaciente\CompletarPerfilPacienteRequest;
use App\Http\Requests\PerfilPaciente\ValidarPaso1PerfilPacienteRequest;
use App\Http\Requests\PerfilPaciente\ValidarPaso2PerfilPacienteRequest;
use App\Models\Localidad;
use App\Models\Provincia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerfilPacienteRequestTest extends TestCase
{
    use RefreshDatabase;

    private Provincia $provincia;

    private Localidad $localidad;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post(
            '/_tests/requests/perfil-paciente/paso-1',
            fn (ValidarPaso1PerfilPacienteRequest $request) => response()->json($request->validated())
        );

        Route::middleware('web')->post(
            '/_tests/requests/perfil-paciente/completar',
            fn (CompletarPerfilPacienteRequest $request) => response()->json($request->validated())
        );

        Route::middleware('web')->post(
            '/_tests/requests/perfil-paciente/paso-2',
            fn (ValidarPaso2PerfilPacienteRequest $request) => response()->json($request->validated())
        );

        $this->provincia = Provincia::create([
            'georef_id' => '54',
            'nombre' => 'Misiones',
        ]);

        $this->localidad = Localidad::create([
            'georef_id' => '54098010',
            'provincia_id' => $this->provincia->id,
            'nombre' => 'Posadas',
            'departamento_nombre' => 'Capital',
        ]);
    }

    #[DataProvider('camposRespuestaSalud')]
    public function test_step_2_requires_each_explicit_health_answer(
        string $campo
    ): void {
        $payload = $this->payloadPaso2();
        unset($payload[$campo]);

        $this->validarPaso2($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);
    }

    public function test_step_2_accepts_four_explicit_no_answers(): void
    {
        $this->validarPaso2($this->payloadPaso2())
            ->assertOk()
            ->assertJsonPath('antecedentes_respuesta', 'no')
            ->assertJsonPath('cirugias_respuesta', 'no')
            ->assertJsonPath('alergias_respuesta', 'no')
            ->assertJsonPath('medicacion_respuesta', 'no');
    }

    public function test_step_2_accepts_explicit_yes_and_no_combination(): void
    {
        $this->validarPaso2($this->payloadPaso2([
            'antecedentes_respuesta' => 'si',
            'antecedentes' => ['Hipertensión controlada'],
            'alergias_respuesta' => 'si',
            'alergias' => ['Penicilina'],
        ]))
            ->assertOk()
            ->assertJsonPath('antecedentes_respuesta', 'si')
            ->assertJsonPath('cirugias_respuesta', 'no')
            ->assertJsonPath('alergias_respuesta', 'si')
            ->assertJsonPath('medicacion_respuesta', 'no');
    }

    #[DataProvider('camposRespuestaSalud')]
    public function test_final_request_requires_each_explicit_health_answer(
        string $campo
    ): void {
        $payload = array_merge($this->payload(), $this->payloadPaso2());
        unset($payload[$campo]);

        $this->postJson(
            '/_tests/requests/perfil-paciente/completar',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);
    }

    public static function camposRespuestaSalud(): array
    {
        return [
            'antecedentes' => ['antecedentes_respuesta'],
            'cirugías' => ['cirugias_respuesta'],
            'alergias' => ['alergias_respuesta'],
            'medicación' => ['medicacion_respuesta'],
        ];
    }

    public function test_accepts_complete_structured_address_with_manual_street(): void
    {
        $response = $this->validar($this->payload([
            'calle' => '  Pasaje Los Lapachos  ',
            'altura_domicilio' => '  1234 A  ',
            'piso_departamento' => '  2 B  ',
            'referencia_domicilio' => '  Frente a la plaza  ',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('provincia_id', $this->provincia->id)
            ->assertJsonPath('localidad_id', $this->localidad->id)
            ->assertJsonPath('calle', 'Pasaje Los Lapachos')
            ->assertJsonPath('altura_domicilio', '1234 A')
            ->assertJsonPath('piso_departamento', '2 B')
            ->assertJsonPath(
                'referencia_domicilio',
                'Frente a la plaza'
            );

        $this->assertDatabaseCount('calles', 0);
        $this->assertFalse(
            Schema::hasColumn('pacientes', 'provincia_id')
        );
        $this->assertFalse(
            Schema::hasColumn('pacientes', 'calle_id')
        );
    }

    public function test_structured_address_validation_does_not_require_legacy_direccion(): void
    {
        $payload = $this->payload();

        $this->assertArrayNotHasKey('direccion', $payload);

        $response = $this->validar($payload)->assertOk();

        $this->assertArrayNotHasKey(
            'direccion',
            $response->json()
        );
    }

    public function test_final_request_accepts_complete_payload_without_legacy_direccion(): void
    {
        $payload = array_replace($this->payload(), [
            'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 72.5,
            'altura_cm' => 174,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas semanales',
            'antecedentes_respuesta' => 'no',
            'antecedentes' => [],
            'cirugias_respuesta' => 'no',
            'cirugias' => [],
            'alergias_respuesta' => 'no',
            'alergias' => [],
            'medicacion_respuesta' => 'no',
            'medicaciones' => [],
        ]);

        $this->assertArrayNotHasKey('direccion', $payload);

        $response = $this->postJson(
            '/_tests/requests/perfil-paciente/completar',
            $payload
        );

        $response
            ->assertOk()
            ->assertJsonPath('provincia_id', $this->provincia->id)
            ->assertJsonPath('localidad_id', $this->localidad->id)
            ->assertJsonPath('calle', 'Pasaje Los Lapachos')
            ->assertJsonPath('altura_domicilio', '1234 A');

        $this->assertArrayNotHasKey('direccion', $response->json());
    }

    public function test_legacy_direccion_does_not_replace_structured_address(): void
    {
        $payload = $this->payload([
            'direccion' => 'Av. Mitre 1234',
        ]);

        unset(
            $payload['provincia_id'],
            $payload['localidad_id'],
            $payload['calle'],
            $payload['altura_domicilio']
        );

        $this->validar($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'provincia_id',
                'localidad_id',
                'calle',
                'altura_domicilio',
            ]);
    }

    public function test_rejects_missing_provincia(): void
    {
        $payload = $this->payload();
        unset($payload['provincia_id']);

        $this->validar($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provincia_id');
    }

    #[DataProvider('provinciasInvalidas')]
    public function test_rejects_invalid_provincia(mixed $provinciaId): void
    {
        $this->validar($this->payload([
            'provincia_id' => $provinciaId,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provincia_id');
    }

    public static function provinciasInvalidas(): array
    {
        return [
            'no integer' => ['Misiones'],
            'nonexistent' => [999999],
        ];
    }

    public function test_rejects_missing_localidad(): void
    {
        $payload = $this->payload();
        unset($payload['localidad_id']);

        $this->validar($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('localidad_id');
    }

    #[DataProvider('localidadesInvalidas')]
    public function test_rejects_invalid_localidad(mixed $localidadId): void
    {
        $this->validar($this->payload([
            'localidad_id' => $localidadId,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('localidad_id');
    }

    public static function localidadesInvalidas(): array
    {
        return [
            'no integer' => ['Posadas'],
            'nonexistent' => [999999],
        ];
    }

    public function test_rejects_localidad_from_another_provincia(): void
    {
        $otraProvincia = Provincia::create([
            'georef_id' => '06',
            'nombre' => 'Buenos Aires',
        ]);

        $otraLocalidad = Localidad::create([
            'georef_id' => '06441020',
            'provincia_id' => $otraProvincia->id,
            'nombre' => 'La Plata',
            'departamento_nombre' => 'La Plata',
        ]);

        $this->validar($this->payload([
            'localidad_id' => $otraLocalidad->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('localidad_id');
    }

    public function test_rejects_missing_calle(): void
    {
        $payload = $this->payload();
        unset($payload['calle']);

        $this->validar($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('calle');
    }

    #[DataProvider('callesInvalidas')]
    public function test_rejects_invalid_calle(mixed $calle): void
    {
        $this->validar($this->payload([
            'calle' => $calle,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('calle');
    }

    public static function callesInvalidas(): array
    {
        return [
            'only whitespace' => ['   '],
            'not string' => [['Avenida Mitre']],
            'length 151' => [str_repeat('a', 151)],
        ];
    }

    public function test_accepts_calle_with_length_150(): void
    {
        $calle = str_repeat('a', 150);

        $this->validar($this->payload([
            'calle' => $calle,
        ]))
            ->assertOk()
            ->assertJsonPath('calle', $calle);
    }

    public function test_rejects_missing_altura_domicilio(): void
    {
        $payload = $this->payload();
        unset($payload['altura_domicilio']);

        $this->validar($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('altura_domicilio');
    }

    #[DataProvider('alturasInvalidas')]
    public function test_rejects_invalid_altura_domicilio(
        mixed $altura
    ): void {
        $this->validar($this->payload([
            'altura_domicilio' => $altura,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('altura_domicilio');
    }

    public static function alturasInvalidas(): array
    {
        return [
            'only whitespace' => ['   '],
            'not string' => [['1234']],
            'length 31' => [str_repeat('a', 31)],
        ];
    }

    #[DataProvider('alturasValidas')]
    public function test_accepts_non_numeric_altura_domicilio(
        string $altura
    ): void {
        $this->validar($this->payload([
            'altura_domicilio' => "  {$altura}  ",
        ]))
            ->assertOk()
            ->assertJsonPath('altura_domicilio', $altura);
    }

    public static function alturasValidas(): array
    {
        return [
            'numeric text' => ['1234'],
            'alphanumeric' => ['1234 A'],
            'without number' => ['S/N'],
            'length 30' => [str_repeat('a', 30)],
        ];
    }

    public function test_optional_address_fields_may_be_absent(): void
    {
        $payload = $this->payload();
        unset(
            $payload['piso_departamento'],
            $payload['referencia_domicilio']
        );

        $response = $this->validar($payload)->assertOk();

        $this->assertArrayNotHasKey(
            'piso_departamento',
            $response->json()
        );
        $this->assertArrayNotHasKey(
            'referencia_domicilio',
            $response->json()
        );
    }

    public function test_optional_address_fields_may_be_null(): void
    {
        $this->validar($this->payload([
            'piso_departamento' => null,
            'referencia_domicilio' => null,
        ]))
            ->assertOk()
            ->assertJsonPath('piso_departamento', null)
            ->assertJsonPath('referencia_domicilio', null);
    }

    public function test_optional_address_fields_trim_and_normalize_whitespace_to_null(): void
    {
        $this->validar($this->payload([
            'piso_departamento' => '   ',
            'referencia_domicilio' => '   ',
        ]))
            ->assertOk()
            ->assertJsonPath('piso_departamento', null)
            ->assertJsonPath('referencia_domicilio', null);
    }

    public function test_optional_address_fields_accept_boundary_lengths(): void
    {
        $piso = str_repeat('a', 50);
        $referencia = str_repeat('a', 500);

        $this->validar($this->payload([
            'piso_departamento' => $piso,
            'referencia_domicilio' => $referencia,
        ]))
            ->assertOk()
            ->assertJsonPath('piso_departamento', $piso)
            ->assertJsonPath('referencia_domicilio', $referencia);
    }

    #[DataProvider('opcionalesInvalidos')]
    public function test_rejects_invalid_optional_address_field(
        string $campo,
        mixed $valor
    ): void {
        $this->validar($this->payload([
            $campo => $valor,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);
    }

    public static function opcionalesInvalidos(): array
    {
        return [
            'piso length 51' => [
                'piso_departamento',
                str_repeat('a', 51),
            ],
            'piso not string' => [
                'piso_departamento',
                ['2 B'],
            ],
            'referencia length 501' => [
                'referencia_domicilio',
                str_repeat('a', 501),
            ],
            'referencia not string' => [
                'referencia_domicilio',
                ['Frente a la plaza'],
            ],
        ];
    }

    private function validar(array $payload): TestResponse
    {
        return $this->postJson(
            '/_tests/requests/perfil-paciente/paso-1',
            $payload
        );
    }

    private function validarPaso2(array $payload): TestResponse
    {
        return $this->postJson(
            '/_tests/requests/perfil-paciente/paso-2',
            $payload
        );
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function payloadPaso2(array $cambios = []): array
    {
        return array_replace([
            'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 72.5,
            'altura_cm' => 174,
            'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas semanales',
            'antecedentes_respuesta' => 'no',
            'antecedentes' => [],
            'cirugias_respuesta' => 'no',
            'cirugias' => [],
            'alergias_respuesta' => 'no',
            'alergias' => [],
            'medicacion_respuesta' => 'no',
            'medicaciones' => [],
        ], $cambios);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function payload(array $cambios = []): array
    {
        return array_replace([
            'dni' => '43122015',
            'fecha_nacimiento' => '2001-02-06',
            'sexo' => 'masculino',
            'telefono' => '3758-409036',
            'provincia_id' => $this->provincia->id,
            'localidad_id' => $this->localidad->id,
            'calle' => 'Pasaje Los Lapachos',
            'altura_domicilio' => '1234 A',
            'piso_departamento' => '2 B',
            'referencia_domicilio' => 'Frente a la plaza',
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3764123456',
        ], $cambios);
    }
}
