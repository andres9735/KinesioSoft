<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\ConsultarDisponibilidadPrimeraConsultaRequest;
use App\Models\Kinesiologa;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultarDisponibilidadPrimeraConsultaRequestTest extends TestCase
{
    use RefreshDatabase;

    private Kinesiologa $kinesiologa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kinesiologa = Kinesiologa::create([
            'user_id' => User::factory()->create(['activo' => false])->id,
            'titulo_profesional_id' => TituloProfesional::create(['nombre' => 'Título', 'slug' => 'titulo-request', 'activo' => true])->id,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        parent::tearDown();
    }

    #[DataProvider('ventanasValidas')]
    public function test_validates_inclusive_windows_without_booking_horizon_or_past_restriction(string $desde, string $hasta): void
    {
        $payload = $this->payload(['desde' => $desde, 'hasta' => $hasta]);
        $request = $this->validar($payload);
        $this->assertSame($payload, $request->validated());
        $this->assertSame($payload, $request->safe()->all());
    }

    public static function ventanasValidas(): array
    {
        return [
            'una fecha' => ['2026-10-05', '2026-10-05'],
            '31 fechas' => ['2026-10-01', '2026-10-31'],
            'ventana lejana' => ['2056-07-01', '2056-07-07'],
            'ventana pasada' => ['2020-01-01', '2020-01-03'],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rejects_missing_or_malformed_input_and_oversized_window(array $cambios, string $campo): void
    {
        $this->assertError($this->payload($cambios), $campo);
    }

    public static function entradasInvalidas(): array
    {
        return [
            'profesional requerida' => [['kinesiologa_id' => null], 'kinesiologa_id'],
            'profesional no entera' => [['kinesiologa_id' => 'abc'], 'kinesiologa_id'],
            'profesional inexistente' => [['kinesiologa_id' => 999999], 'kinesiologa_id'],
            'desde requerido' => [['desde' => null], 'desde'],
            'hasta requerido' => [['hasta' => null], 'hasta'],
            'desde formato' => [['desde' => '05/10/2026'], 'desde'],
            'hasta formato' => [['hasta' => '2026-10-05T00:00:00'], 'hasta'],
            'fecha imposible' => [['desde' => '2026-02-30'], 'desde'],
            'fecha no padded' => [['desde' => '2026-1-1'], 'desde'],
            'fecha array' => [['hasta' => []], 'hasta'],
            'orden inverso' => [['hasta' => '2026-10-04'], 'hasta'],
            '32 fechas' => [['desde' => '2026-10-01', 'hasta' => '2026-11-01'], 'hasta'],
        ];
    }

    #[DataProvider('camposProhibidos')]
    public function test_rejects_unknown_and_derived_fields_even_when_null(string $campo): void
    {
        $this->assertError($this->payload([$campo => null]), $campo);
    }

    public static function camposProhibidos(): array
    {
        return array_map(fn ($campo) => [$campo], ['paciente_id', 'fin_at', 'estado', 'tipo', 'slot', 'motivo_solicitud', 'adjuntos', 'foo']);
    }

    public function test_window_limit_consumes_configuration_instead_of_a_fixed_31(): void
    {
        config(['turnos.disponibilidad.max_dias_por_consulta' => 2]);
        $this->validar($this->payload(['hasta' => '2026-10-06']));
        $this->assertError($this->payload(['hasta' => '2026-10-07']), 'hasta');
    }

    public function test_only_checks_structural_professional_existence_without_evaluating_eligibility_or_agenda(): void
    {
        DB::enableQueryLog();
        $this->validar($this->payload());
        $consultas = DB::getQueryLog();
        $this->assertCount(1, $consultas);
        $this->assertStringContainsString('"kinesiologas"', $consultas[0]['query']);
        $this->assertStringNotContainsString('activo', $consultas[0]['query']);
        $this->assertFalse($this->kinesiologa->user->activo);
    }

    public function test_csrf_metadata_is_allowed_but_is_not_validated_as_functional_input(): void
    {
        $request = $this->validar($this->payload(['_token' => 'csrf']));
        $this->assertTrue($request->authorize());
        $this->assertArrayNotHasKey('_token', $request->validated());
    }

    private function payload(array $cambios = []): array
    {
        return array_replace(['kinesiologa_id' => $this->kinesiologa->id, 'desde' => '2026-10-05', 'hasta' => '2026-10-07'], $cambios);
    }

    private function validar(array $payload): ConsultarDisponibilidadPrimeraConsultaRequest
    {
        $request = ConsultarDisponibilidadPrimeraConsultaRequest::create('/_tests/requests/disponibilidad', 'GET', $payload);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request;
    }

    private function assertError(array $payload, string $campo): void
    {
        try {
            $this->validar($payload);
            $this->fail('Debe rechazarse la entrada inválida.');
        } catch (ValidationException $error) {
            $this->assertSame(422, $error->status);
            $this->assertArrayHasKey($campo, $error->errors());
        }
    }
}
