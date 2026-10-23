<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\ConsultarDisponibilidadPrimeraConsultaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultarDisponibilidadPrimeraConsultaRequestTest extends TestCase
{
    use RefreshDatabase;

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
        return array_map(fn ($campo) => [$campo], ['kinesiologa_id', 'paciente_id', 'fin_at', 'estado', 'tipo', 'slot', 'motivo_solicitud', 'adjuntos', 'foo']);
    }

    public function test_window_limit_consumes_configuration_instead_of_a_fixed_31(): void
    {
        config(['turnos.disponibilidad.max_dias_por_consulta' => 2]);
        $this->validar($this->payload(['hasta' => '2026-10-06']));
        $this->assertError($this->payload(['hasta' => '2026-10-07']), 'hasta');
    }

    public function test_window_validation_needs_no_professional_and_executes_no_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request = $this->validar($this->payload());
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], $consultas);
        $this->assertSame($this->payload(), $request->validated());
        $this->assertArrayNotHasKey('kinesiologa_id', $request->validated());
    }

    #[DataProvider('profesionalesObsoletas')]
    public function test_professional_parameter_is_rejected_instead_of_enabling_a_v1_mode(mixed $valor): void
    {
        $this->assertError($this->payload(['kinesiologa_id' => $valor]), 'kinesiologa_id');
    }

    public static function profesionalesObsoletas(): array
    {
        return [[1], ['1'], [[1, 2]]];
    }

    public function test_window_counts_calendar_dates_across_a_daylight_saving_transition(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $valida = ['desde' => '2026-03-01', 'hasta' => '2026-03-31'];
        $this->assertSame($valida, $this->validar($valida)->validated());
        $this->assertError(['desde' => '2026-03-01', 'hasta' => '2026-04-01'], 'hasta');
    }

    public function test_csrf_metadata_is_allowed_but_is_not_validated_as_functional_input(): void
    {
        $request = $this->validar($this->payload(['_token' => 'csrf']));
        $this->assertTrue($request->authorize());
        $this->assertArrayNotHasKey('_token', $request->validated());
    }

    private function payload(array $cambios = []): array
    {
        return array_replace(['desde' => '2026-10-05', 'hasta' => '2026-10-07'], $cambios);
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
