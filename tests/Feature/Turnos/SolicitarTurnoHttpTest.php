<?php

namespace Tests\Feature\Turnos;

use App\Enums\DiaSemanaEnum;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ClaveIdempotenciaIncompatibleException;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\ResultadoConfirmacionInciertoException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\BloqueoAgenda;
use App\Models\ColegioEmisor;
use App\Models\ConfiguracionAgenda;
use App\Models\DeclaracionSaludPaciente;
use App\Models\DocumentoClinico;
use App\Models\EspecialidadKinesiologia;
use App\Models\FranjaHorariaAgenda;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\Localidad;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CoordinadorBloqueoDocumentalEnMemoria;
use Tests\TestCase;

class SolicitarTurnoHttpTest extends TestCase
{
    // El caso multipart necesita un commit físico, sin transacción envolvente del test.
    use DatabaseMigrations;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private ConfiguracionAgenda $configuracion;

    private string $uuid;

    private FilesystemAdapter $disco;

    private CoordinadorBloqueoDocumentalEnMemoria $coordinador;

    private array $temporales = [];

    public function runDatabaseMigrations(): void
    {
        // Mismo patrón SQLite efímero de AtomicidadPrimeraConsultaTest; evita down históricos ajenos.
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        $this->paciente = $this->pacienteCompleto();
        $this->kinesiologa = $this->profesional();
        $this->configuracion = ConfiguracionAgenda::factory()->for($this->kinesiologa)->create([
            'vigencia_desde' => '2026-10-01', 'vigencia_hasta' => null, 'duracion_turno_minutos' => 30,
        ]);
        FranjaHorariaAgenda::factory()->for($this->configuracion, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00',
        ]);
        $this->uuid = (string) Str::uuid();
        $this->coordinador = new CoordinadorBloqueoDocumentalEnMemoria;
        $this->app->instance(CoordinadorBloqueoDocumental::class, $this->coordinador);
        $fake = Storage::fake('documentos_clinicos');
        $this->disco = Storage::build(array_replace(config('filesystems.disks.documentos_clinicos'), ['root' => $fake->path('')]));
        Storage::set('documentos_clinicos', $this->disco);
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.Turno::class);
        Event::forget('eloquent.retrieved: '.ConfiguracionAgenda::class);
        Event::forget(TransactionCommitted::class);
        $this->assertSame([], $this->coordinador->activos);
        // Sólo el disco fake configurado para este test, nunca el storage clínico de desarrollo.
        $this->disco->delete($this->disco->allFiles());
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_availability_uses_real_engine_and_exposes_only_ordered_dates_and_starts(): void
    {
        $this->turno(['inicio_at' => '2026-10-05 10:30:00', 'fin_at' => '2026-10-05 11:00:00']);
        BloqueoAgenda::factory()->for($this->kinesiologa)->create([
            'inicio_at' => '2026-10-05 11:00:00', 'fin_at' => '2026-10-05 11:30:00', 'motivo' => 'MOTIVO BLOQUEO PRIVADO',
        ]);
        $response = $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl());
        $response->assertOk()->assertExactJson([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
            'dias' => [['fecha' => '2026-10-05', 'horarios' => [
                ['inicio_at' => '2026-10-05 10:00:00'],
                ['inicio_at' => '2026-10-05 11:30:00'],
            ]]],
        ])->assertDontSee('MOTIVO BLOQUEO PRIVADO')->assertDontSee($this->paciente->user->email);
    }

    public function test_valid_empty_availability_is_200_json_even_without_accept_header(): void
    {
        $this->actingAs($this->paciente->user)->get($this->disponibilidadUrl(['desde' => '2026-10-06', 'hasta' => '2026-10-06']))
            ->assertOk()->assertExactJson(['desde' => '2026-10-06', 'hasta' => '2026-10-06', 'dias' => []]);
    }

    public function test_same_day_availability_excludes_started_slots_without_controller_time_rules(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 10:30:00', config('app.timezone')));
        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl())->assertOk()->assertExactJson([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
            'dias' => [['fecha' => '2026-10-05', 'horarios' => [
                ['inicio_at' => '2026-10-05 11:00:00'],
                ['inicio_at' => '2026-10-05 11:30:00'],
            ]]],
        ]);
    }

    #[DataProvider('disponibilidadInvalida')]
    public function test_availability_validation_returns_standard_field_errors(array $cambios, string $campo): void
    {
        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl($cambios))
            ->assertUnprocessable()->assertJsonValidationErrors($campo)->assertJsonMissingPath('code');
    }

    public static function disponibilidadInvalida(): array
    {
        return [
            [['kinesiologa_id' => 1], 'kinesiologa_id'],
            [['desde' => null], 'desde'], [['hasta' => null], 'hasta'],
            [['desde' => '05/10/2026'], 'desde'], [['hasta' => 'no-fecha'], 'hasta'],
            [['hasta' => '2026-10-04'], 'hasta'], [['hasta' => '2026-11-05'], 'hasta'],
            [['paciente_id' => 999], 'paciente_id'],
        ];
    }

    #[DataProvider('ventanasValidas')]
    public function test_31_inclusive_dates_and_distant_dates_are_valid(string $desde, string $hasta): void
    {
        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl(compact('desde', 'hasta')))
            ->assertOk()->assertJsonPath('desde', $desde)->assertJsonPath('hasta', $hasta)->assertJsonStructure(['dias']);
    }

    public static function ventanasValidas(): array
    {
        return [['2026-10-05', '2026-11-04'], ['2030-10-07', '2030-10-07']];
    }

    public function test_no_eligible_professionals_is_a_successful_empty_window_not_a_conflict(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $response = $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl());
        $response->assertOk()->assertExactJson(['desde' => '2026-10-05', 'hasta' => '2026-10-05', 'dias' => []]);
    }

    public function test_shared_start_with_different_durations_exposes_neither_finishes_nor_professionals(): void
    {
        $this->configuracion->franjasHorarias()->update(['hora_fin' => '10:45:00']);
        $otra = $this->profesional();
        $this->agendaDe($otra, 45, '10:00:00', '10:45:00');

        $response = $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl());

        $response->assertOk()->assertExactJson([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
            'dias' => [['fecha' => '2026-10-05', 'horarios' => [['inicio_at' => '2026-10-05 10:00:00']]]],
        ])->assertDontSee('10:30:00')->assertDontSee('10:45:00')
            ->assertDontSee($this->kinesiologa->user->email)->assertDontSee($otra->user->email);
    }

    public function test_distinct_professional_grids_are_united_in_order_without_duplicate_starts(): void
    {
        $this->agendaDe($this->profesional(), 45, '10:00:00', '12:15:00');

        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl())->assertOk()->assertExactJson([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
            'dias' => [['fecha' => '2026-10-05', 'horarios' => [
                ['inicio_at' => '2026-10-05 10:00:00'],
                ['inicio_at' => '2026-10-05 10:30:00'],
                ['inicio_at' => '2026-10-05 10:45:00'],
                ['inicio_at' => '2026-10-05 11:00:00'],
                ['inicio_at' => '2026-10-05 11:30:00'],
            ]]],
        ]);
    }

    public function test_response_identifies_its_window_and_omits_empty_and_unqueried_dates(): void
    {
        FranjaHorariaAgenda::factory()->for($this->configuracion, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '09:00:00', 'hora_fin' => '09:30:00',
        ]);
        $this->configuracion->franjasHorarias()->where('dia_semana', DiaSemanaEnum::LUNES->value)->update(['hora_fin' => '10:30:00']);
        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl(['hasta' => '2026-10-08']))
            ->assertOk()->assertExactJson([
                'desde' => '2026-10-05', 'hasta' => '2026-10-08',
                'dias' => [
                    ['fecha' => '2026-10-05', 'horarios' => [['inicio_at' => '2026-10-05 10:00:00']]],
                    ['fecha' => '2026-10-07', 'horarios' => [['inicio_at' => '2026-10-07 09:00:00']]],
                ],
            ]);
        $this->getJson($this->disponibilidadUrl(['desde' => '2026-10-06', 'hasta' => '2026-10-07']))
            ->assertOk()->assertExactJson([
                'desde' => '2026-10-06', 'hasta' => '2026-10-07',
                'dias' => [['fecha' => '2026-10-07', 'horarios' => [['inicio_at' => '2026-10-07 09:00:00']]]],
            ]);
    }

    public function test_start_disappears_only_when_no_eligible_professional_still_contributes_it(): void
    {
        $this->configuracion->franjasHorarias()->update(['hora_fin' => '10:30:00']);
        $otra = $this->profesional();
        $this->agendaDe($otra, 30, '10:00:00', '10:30:00');
        $this->turno();
        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl())->assertOk()->assertExactJson([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
            'dias' => [['fecha' => '2026-10-05', 'horarios' => [['inicio_at' => '2026-10-05 10:00:00']]]],
        ]);
        $this->turno(['kinesiologa_id' => $otra->id]);
        $this->getJson($this->disponibilidadUrl())->assertOk()
            ->assertExactJson(['desde' => '2026-10-05', 'hasta' => '2026-10-05', 'dias' => []]);
    }

    public function test_projection_uses_only_eligible_professionals_and_does_not_restore_excluded_starts(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->agendaDe($this->profesional(), 30, '14:00:00', '14:30:00');

        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl())->assertOk()->assertExactJson([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
            'dias' => [['fecha' => '2026-10-05', 'horarios' => [['inicio_at' => '2026-10-05 14:00:00']]]],
        ]);
    }

    public function test_http_window_limit_is_enforced_even_when_no_professional_is_eligible(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);

        $this->actingAs($this->paciente->user)->getJson($this->disponibilidadUrl(['hasta' => '2026-11-05']))
            ->assertUnprocessable()->assertJsonValidationErrors('hasta')->assertJsonMissingPath('dias');
    }

    public function test_creation_returns_201_only_after_commit_with_a_small_whitelist_and_localizable_destination(): void
    {
        $committed = false;
        Event::listen(TransactionCommitted::class, function () use (&$committed): void {
            $committed = true;
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertDatabaseCount('turnos', 1);
        });
        $response = $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload())->assertCreated();
        $this->assertTrue($committed);
        $turno = Turno::sole();
        $response->assertExactJson($this->success($turno, false))->assertHeaderMissing('Location');
        $this->assertSame($this->paciente->id, $turno->paciente_id);
        $this->assertSame(TipoTurnoEnum::PRIMERA_CONSULTA, $turno->tipo);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $turno->estado);
        $this->assertSame('2026-10-05 10:30:00', $turno->fin_at->toDateTimeString());
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->get($response->json('redirect_url'))->assertOk()->assertViewHas('turnoDestacadoId', $turno->id);
    }

    public function test_real_valid_multipart_flows_through_request_action_and_document_persistence(): void
    {
        $file = $this->archivo('Estudio privado.pdf', "%PDF-1.7\nDocumento de prueba\n%%EOF");
        $response = $this->actingAs($this->paciente->user)->post($this->storeUrl(), $this->payload([
            'adjuntos' => [['archivo' => $file, 'descripcion' => 'Documento sintético para la regresión HTTP.']],
        ]), ['Accept' => 'application/json']);
        $response->assertCreated()->assertExactJson($this->success(Turno::sole(), false));
        $doc = DocumentoClinico::sole();
        $this->assertSame($this->paciente->id, $doc->paciente_id);
        $this->assertSame($this->paciente->user_id, $doc->cargado_por_user_id);
        $this->assertSame('application/pdf', $doc->mime_type);
        $this->assertSame('Documento sintético para la regresión HTTP.', $doc->descripcion);
        $this->assertSame(file_get_contents($file->getRealPath()), $this->disco->get($doc->ruta_storage));
        $this->assertDatabaseHas('documentos_clinicos_origen_turno', ['documento_clinico_id' => $doc->id, 'turno_id' => $response->json('turno_id')]);
        $this->assertDatabaseCount('auditorias', 2);
        $this->assertSame([], $this->coordinador->activos);
        $this->postJson($this->storeUrl(), ['clave_idempotencia' => $this->uuid])->assertOk()
            ->assertExactJson($this->success(Turno::sole(), true));
        $this->assertDatabaseCount('documentos_clinicos', 1);
        $this->assertDatabaseCount('auditorias', 2);
        $this->assertCount(1, $this->disco->allFiles());
    }

    #[DataProvider('payloadsReplay')]
    public function test_legitimate_replay_ignores_creation_fields_and_current_conditions(bool $soloUuid): void
    {
        $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload())->assertCreated();
        $turno = Turno::sole();
        $this->kinesiologa->user->update(['activo' => false]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 09:00:00', config('app.timezone')));
        $payload = $soloUuid ? ['clave_idempotencia' => $this->uuid] : $this->payload([
            'kinesiologa_id' => $this->profesional()->id, 'inicio_at' => '2020-01-01 01:00:00', 'motivo_solicitud' => 'Otro motivo ignorado',
            'adjuntos' => array_fill(0, 6, $this->archivo('actual.txt', 'contenido inválido')),
        ]);
        $this->post($this->storeUrl(), $payload, ['Accept' => 'application/json'])->assertOk()->assertExactJson($this->success($turno, true));
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 1);
        $this->assertDatabaseCount('documentos_clinicos', 0);
        $this->assertSame([], $this->disco->allFiles());
        $this->assertSame('Motivo clínico original privado', $turno->fresh()->motivo_solicitud);
    }

    public static function payloadsReplay(): array
    {
        return [[true], [false]];
    }

    public function test_replay_precedes_d01_even_when_its_own_future_turn_is_active(): void
    {
        $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload())->assertCreated();
        $this->postJson($this->storeUrl(), ['clave_idempotencia' => $this->uuid])->assertOk()->assertExactJson($this->success(Turno::sole(), true));
    }

    #[DataProvider('confirmacionInvalida')]
    public function test_creation_validation_preserves_422_field_errors(array $cambios, string $campo): void
    {
        $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload($cambios))
            ->assertUnprocessable()->assertJsonValidationErrors($campo)->assertJsonMissingPath('code');
        $this->assertDatabaseCount('turnos', 0);
    }

    public static function confirmacionInvalida(): array
    {
        return [
            [['clave_idempotencia' => 'inválida'], 'clave_idempotencia'],
            [['motivo_solicitud' => null], 'motivo_solicitud'],
            [['paciente_id' => 999], 'paciente_id'], [['fin_at' => '2026-10-05 12:00:00'], 'fin_at'],
        ];
    }

    public function test_invalid_real_file_is_a_field_validation_error(): void
    {
        $this->actingAs($this->paciente->user)->post($this->storeUrl(), $this->payload([
            'adjuntos' => [['archivo' => $this->archivo('falso.pdf', 'no es PDF'), 'descripcion' => 'Archivo sintético inválido.']],
        ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('adjuntos.0.archivo');
        $this->assertDatabaseCount('turnos', 0);
    }

    public function test_d01_has_priority_over_ineligible_professional_and_unavailable_slot(): void
    {
        $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA]);
        $this->kinesiologa->user->update(['activo' => false]);
        $response = $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload(['inicio_at' => '2026-10-01 01:00:00']));
        $this->assertConflict($response, 'primera_consulta_activa');
        $this->assertDatabaseCount('turnos', 1);
    }

    public function test_professional_has_priority_over_invalid_slot(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $response = $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload(['inicio_at' => '2026-10-01 01:00:00']));
        $this->assertConflict($response, 'profesional_no_elegible');
        $this->assertDatabaseCount('turnos', 0);
    }

    #[DataProvider('slotsInvalidos')]
    public function test_slot_conflict_never_reveals_its_cause(string $inicio): void
    {
        $response = $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload(['inicio_at' => $inicio]));
        $this->assertConflict($response, 'slot_no_disponible');
        $this->assertDatabaseCount('turnos', 0);
    }

    public static function slotsInvalidos(): array
    {
        return [['2026-10-05 09:00:00'], ['2026-10-05 10:01:00'], ['2026-10-06 10:00:00']];
    }

    #[DataProvider('conflictosTipados')]
    public function test_mapping_depends_on_exception_class_and_never_on_the_message(string $clase, ?string $code): void
    {
        // Inyección acotada en persistencia para aislar transporte; Request y Action siguen siendo reales.
        Event::listen('eloquent.creating: '.Turno::class, static function () use ($clase): void {
            throw new $clase('MENSAJE ALTERADO PRIVADO sin relación con el conflicto');
        });
        $response = $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), $this->payload());
        if ($code !== null) {
            $this->assertConflict($response, $code);
        } else {
            $response->assertConflict()->assertJsonStructure(['message'])->assertJsonCount(1)->assertJsonMissingPath('code');
        }
        $response->assertDontSee('MENSAJE ALTERADO PRIVADO');
        $this->assertDatabaseCount('turnos', 0);
    }

    public static function conflictosTipados(): array
    {
        return [
            [PrimeraConsultaActivaException::class, 'primera_consulta_activa'],
            [ProfesionalNoElegibleException::class, 'profesional_no_elegible'],
            [SlotNoDisponibleException::class, 'slot_no_disponible'],
            [ClaveIdempotenciaIncompatibleException::class, null],
        ];
    }

    #[DataProvider('contextosIncompatibles')]
    public function test_incompatible_uuid_from_form_request_is_private_and_has_no_fourth_functional_code(bool $ajeno): void
    {
        $turno = $this->turno([
            'tipo' => $ajeno ? TipoTurnoEnum::PRIMERA_CONSULTA : TipoTurnoEnum::SEGUIMIENTO,
            'paciente_id' => $ajeno ? $this->pacienteCompleto()->id : $this->paciente->id,
            'clave_idempotencia' => $this->uuid, 'motivo_solicitud' => 'SECRETO UUID AJENA',
        ]);
        $response = $this->actingAs($this->paciente->user)->postJson($this->storeUrl(), ['clave_idempotencia' => $this->uuid]);
        $response->assertConflict()->assertJsonStructure(['message'])->assertJsonCount(1)->assertJsonMissingPath('code')
            ->assertJsonMissingPath('turno_id')->assertDontSee('SECRETO UUID AJENA')->assertDontSee($this->uuid)
            ->assertDontSee($turno->inicio_at->toDateTimeString())->assertDontSee($this->kinesiologa->user->name);
        $this->assertDatabaseCount('turnos', 1);
    }

    public static function contextosIncompatibles(): array
    {
        return [[true], [false]];
    }

    public function test_incompatible_uuid_renderer_does_not_handle_other_routes(): void
    {
        Route::get('/_testing/clave-incompatible', static function (): void {
            throw new ClaveIdempotenciaIncompatibleException('Error fuera de la ruta autorizada');
        })->name('testing.clave-incompatible');
        $this->getJson('/_testing/clave-incompatible')->assertStatus(500)->assertJsonMissingPath('code');
    }

    #[DataProvider('erroresTecnicos')]
    public function test_internal_errors_keep_technical_http_status_on_both_endpoints(bool $store, string $tipo): void
    {
        $error = match ($tipo) {
            'sql' => new QueryException('sqlite', 'select dato_interno', [], new RuntimeException('SQL PRIVADO')),
            'incierto' => new ResultadoConfirmacionInciertoException(new RuntimeException('INCERTEZA PRIVADA')),
            default => new LogicException('INTEGRIDAD PRIVADA'),
        };
        Event::listen('eloquent.retrieved: '.ConfiguracionAgenda::class, static function () use ($error): void {
            throw $error;
        });
        $this->actingAs($this->paciente->user);
        $response = $store ? $this->postJson($this->storeUrl(), $this->payload()) : $this->getJson($this->disponibilidadUrl());
        $response->assertStatus(500)->assertJsonMissingPath('code');
        $this->assertDatabaseCount('turnos', 0);
    }

    public static function erroresTecnicos(): array
    {
        return [[true, 'sql'], [false, 'sql'], [true, 'incierto'], [false, 'incierto'], [true, 'integridad'], [false, 'integridad']];
    }

    #[DataProvider('contextosIncompatibles')]
    public function test_real_agenda_integrity_failure_is_not_a_slot_conflict(bool $store): void
    {
        ConfiguracionAgenda::factory()->for($this->kinesiologa)->create(['vigencia_desde' => '2026-10-01', 'vigencia_hasta' => null]);
        $this->actingAs($this->paciente->user);
        $response = $store ? $this->postJson($this->storeUrl(), $this->payload()) : $this->getJson($this->disponibilidadUrl());
        $response->assertStatus(500)->assertJsonMissingPath('code');
        $this->assertDatabaseCount('turnos', 0);
    }

    #[DataProvider('middlewareCasos')]
    public function test_real_http_middleware_blocks_invalid_actors_on_both_routes(bool $store, string $actor): void
    {
        if ($actor !== 'guest') {
            $user = match ($actor) {
                'kinesiologo' => $this->kinesiologa->user,
                'administrador' => $this->usuario(RoleEnum::ADMINISTRADOR),
                'superadmin' => $this->usuario(RoleEnum::SUPERADMIN),
                'incompleto' => $this->usuario(RoleEnum::PACIENTE),
                default => $this->paciente->user,
            };
            if ($actor === 'no_verificado') {
                $user->forceFill(['email_verified_at' => null])->save();
            }
            $this->actingAs($user);
        }
        $response = $store ? $this->postJson($this->storeUrl(), $this->payload()) : $this->getJson($this->disponibilidadUrl());
        if ($actor === 'incompleto') {
            $response->assertRedirect(route('perfil.completar'));
        } else {
            $response->assertStatus($actor === 'guest' ? 401 : 403);
        }
        $this->assertDatabaseCount('turnos', 0);
    }

    public static function middlewareCasos(): array
    {
        $casos = [];
        foreach ([false, true] as $store) {
            foreach (['guest', 'no_verificado', 'incompleto', 'kinesiologo', 'administrador', 'superadmin'] as $actor) {
                $casos[] = [$store, $actor];
            }
        }

        return $casos;
    }

    private function assertConflict($response, string $code): void
    {
        $response->assertConflict()->assertJsonPath('code', $code)->assertJsonStructure(['code', 'message'])->assertJsonCount(2);
    }

    private function archivo(string $nombre, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-http-');
        $this->temporales[] = $ruta;
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, 'application/octet-stream', UPLOAD_ERR_OK, true);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'clave_idempotencia' => $this->uuid, 'motivo_solicitud' => 'Motivo clínico original privado',
            'kinesiologa_id' => $this->kinesiologa->id, 'inicio_at' => '2026-10-05 10:00:00',
        ], $cambios);
    }

    private function storeUrl(): string
    {
        return route('turnos.solicitar.store');
    }

    private function disponibilidadUrl(array $cambios = []): string
    {
        return route('turnos.solicitar.disponibilidad', array_replace([
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
        ], $cambios));
    }

    private function success(Turno $turno, bool $replay): array
    {
        return ['turno_id' => $turno->id, 'es_replay' => $replay, 'redirect_url' => route('turnos.mis-turnos.index', ['turno' => $turno->id])];
    }

    private function turno(array $cambios = []): Turno
    {
        return Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create(array_replace([
            'inicio_at' => '2026-10-05 10:00:00', 'fin_at' => '2026-10-05 10:30:00',
        ], $cambios));
    }

    private function usuario(RoleEnum $rol): User
    {
        $user = User::factory()->create([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true,
        ]);
        $user->assignRole($rol->value);

        return $user;
    }

    private function pacienteCompleto(): Paciente
    {
        $provincia = Provincia::firstOrCreate(['georef_id' => '54'], ['nombre' => 'Misiones']);
        $localidad = Localidad::firstOrCreate(['georef_id' => '54098010'], ['provincia_id' => $provincia->id, 'nombre' => 'Posadas', 'departamento_nombre' => 'Capital']);
        $paciente = Paciente::create([
            'user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true,
            'localidad_id' => $localidad->id, 'calle' => 'Mitre', 'altura_domicilio' => '123',
            'contacto_emergencia_nombre' => 'Contacto', 'contacto_emergencia_telefono' => '3764123457',
        ]);
        DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id, 'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 70, 'altura_cm' => 170, 'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Caminatas',
            'estado' => DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
        ]);

        return $paciente;
    }

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-http'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-http'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-http'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'HTTP'], ['nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $user = $this->usuario(RoleEnum::KINESIOLOGO);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create(['kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id, 'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa;
    }

    private function agendaDe(Kinesiologa $profesional, int $duracion, string $inicio, string $fin): void
    {
        $configuracion = ConfiguracionAgenda::factory()->for($profesional)->create([
            'vigencia_desde' => '2026-10-01', 'vigencia_hasta' => null, 'duracion_turno_minutos' => $duracion,
        ]);
        FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create([
            'dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => $inicio, 'hora_fin' => $fin,
        ]);
    }
}
