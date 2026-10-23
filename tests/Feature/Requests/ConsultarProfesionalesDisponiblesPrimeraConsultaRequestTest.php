<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\ConsultarProfesionalesDisponiblesPrimeraConsultaRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultarProfesionalesDisponiblesPrimeraConsultaRequestTest extends TestCase
{
    #[DataProvider('iniciosValidos')]
    public function test_accepts_exact_datetime_without_evaluating_clock_grid_or_booking_horizon(string $inicio): void
    {
        $payload = ['inicio_at' => $inicio];
        $request = $this->validar($payload);

        $this->assertSame($payload, $request->validated());
        $this->assertSame($payload, $request->safe()->all());
    }

    public static function iniciosValidos(): array
    {
        return [
            'canónico' => ['2026-10-05 10:00:00'],
            'pasado' => ['2020-01-01 09:00:00'],
            'fuera de grilla' => ['2026-10-05 10:01:17'],
            'futuro lejano' => ['2099-12-31 23:59:59'],
        ];
    }

    #[DataProvider('iniciosInvalidos')]
    public function test_rejects_missing_or_malformed_datetime(array $payload): void
    {
        $this->assertError($payload, 'inicio_at');
    }

    public static function iniciosInvalidos(): array
    {
        return [
            'ausente' => [[]],
            'NULL' => [['inicio_at' => null]],
            'vacío' => [['inicio_at' => '']],
            'array' => [['inicio_at' => ['2026-10-05 10:00:00']]],
            'número' => [['inicio_at' => 20261005]],
            'booleano' => [['inicio_at' => false]],
            'sin hora' => [['inicio_at' => '2026-10-05']],
            'sin segundos' => [['inicio_at' => '2026-10-05 10:00']],
            'ISO UTC' => [['inicio_at' => '2026-10-05T10:00:00Z']],
            'offset' => [['inicio_at' => '2026-10-05 10:00:00-03:00']],
            'microsegundos' => [['inicio_at' => '2026-10-05 10:00:00.000001']],
            'fecha imposible' => [['inicio_at' => '2026-02-30 10:00:00']],
            'hora imposible' => [['inicio_at' => '2026-10-05 24:00:00']],
            'fecha no padded' => [['inicio_at' => '2026-1-5 10:00:00']],
        ];
    }

    #[DataProvider('camposProhibidos')]
    public function test_rejects_extra_or_derived_fields_even_when_null(string $campo): void
    {
        $this->assertError(['inicio_at' => '2026-10-05 10:00:00', $campo => null], $campo);
    }

    public static function camposProhibidos(): array
    {
        return [
            'kinesiologa_id' => ['kinesiologa_id'],
            'paciente_id' => ['paciente_id'],
            'fin_at' => ['fin_at'],
            'duracion_turno_minutos' => ['duracion_turno_minutos'],
            ...array_map(static fn (string $campo): array => [$campo], [
                'fecha', 'profesionales', 'motivo_solicitud', 'adjuntos', 'clave_idempotencia', 'desde', 'hasta', 'foo',
            ]),
        ];
    }

    public function test_validation_executes_no_queries_and_accepts_only_nonfunctional_csrf_metadata(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $request = $this->validar(['inicio_at' => '2026-10-05 10:00:00', '_token' => 'csrf']);
            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertTrue($request->authorize());
        $this->assertSame(['inicio_at' => '2026-10-05 10:00:00'], $request->validated());
        $this->assertSame([], $consultas);
    }

    private function validar(array $payload): ConsultarProfesionalesDisponiblesPrimeraConsultaRequest
    {
        $request = ConsultarProfesionalesDisponiblesPrimeraConsultaRequest::create('/turnos/solicitar/profesionales-disponibles', 'GET', $payload);
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
