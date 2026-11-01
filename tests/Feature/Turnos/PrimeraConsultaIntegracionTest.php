<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\BloqueoAgenda;
use App\Models\DocumentoClinico;
use App\Models\DocumentoClinicoOrigenTurno;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\Agenda\AgendaQueryService;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\ReconciliadorDocumentosClinicosService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CoordinadorBloqueoDocumentalEnMemoria;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class PrimeraConsultaIntegracionTest extends TestCase
{
    use CreaPerfilesUc07;
    use DatabaseMigrations;

    private Paciente $paciente;

    private Kinesiologa $profesional;

    private string $uuid;

    private FilesystemAdapter $disco;

    private CoordinadorBloqueoDocumentalEnMemoria $coordinador;

    private array $temporales = [];

    public function runDatabaseMigrations(): void
    {
        // Commit físico de la Action; sin transacción envolvente ni down históricos ajenos.
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
        $this->paciente = $this->pacienteUc07();
        $this->profesional = $this->profesionalUc07();
        $this->uuid = (string) Str::uuid();
        $this->coordinador = new CoordinadorBloqueoDocumentalEnMemoria;
        app()->instance(CoordinadorBloqueoDocumental::class, $this->coordinador);
        $fake = Storage::fake('documentos_clinicos');
        $this->disco = Storage::build(array_replace(config('filesystems.disks.documentos_clinicos'), ['root' => $fake->path('')]));
        Storage::set('documentos_clinicos', $this->disco);
        $this->actingAs($this->paciente->user);
        $this->assertSame(0, DB::transactionLevel());
    }

    protected function tearDown(): void
    {
        Event::forget(TransactionCommitted::class);
        $this->assertSame([], $this->coordinador->activos);
        $this->disco->delete($this->disco->allFiles());
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('cantidades')]
    public function test_complete_http_journey_preserves_exact_snapshot_and_private_downloads(int $cantidad): void
    {
        $wizard = $this->get(route('turnos.solicitar.create'))->assertOk()->viewData('configuracionWizard');
        $this->uuid = $wizard['uuid'];
        $horarios = $this->getJson(route('turnos.solicitar.disponibilidad', [
            'desde' => '2026-10-05', 'hasta' => '2026-10-05',
        ]))->assertOk()->json('dias.0.horarios');
        $this->assertNotEmpty($horarios);
        $inicio = $horarios[0]['inicio_at'];
        $seleccion = $this->getJson(route('turnos.solicitar.profesionales-disponibles', [
            'inicio_at' => $inicio,
        ]))->assertOk()->json('profesionales.0');
        $this->assertSame($this->profesional->id, $seleccion['kinesiologa_id']);
        $archivos = array_map(fn ($i) => $this->archivo('Estudio '.$i.'.pdf', $i), range(1, max(1, $cantidad)));
        $archivos = $cantidad === 0 ? [] : $archivos;
        $adjuntos = array_map(fn ($archivo, $i) => $this->adjunto($archivo, '  Descripción original '.$i.'.  '), $archivos, array_keys($archivos));
        $respuesta = $this->post(route('turnos.solicitar.store'), $this->payload([
            'kinesiologa_id' => $seleccion['kinesiologa_id'], 'inicio_at' => $inicio, 'adjuntos' => $adjuntos,
        ]), ['Accept' => 'application/json'])->assertCreated();
        $turno = Turno::sole();
        $this->assertSame($turno->id, $respuesta->json('turno_id'));
        $this->assertFalse($respuesta->json('es_replay'));
        $this->assertSame($this->paciente->id, $turno->paciente_id);
        $this->assertSame($this->profesional->id, $turno->kinesiologa_id);
        $this->assertSame(TipoTurnoEnum::PRIMERA_CONSULTA, $turno->tipo);
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $turno->estado);
        $this->assertSame($inicio, $turno->inicio_at->toDateTimeString());
        $this->assertSame($seleccion['fin_at'], $turno->fin_at->toDateTimeString());
        $this->assertSame($this->payload()['motivo_solicitud'], $turno->motivo_solicitud);
        $this->conteos(1, $cantidad, 1 + $cantidad);
        $this->get($respuesta->json('redirect_url'))->assertOk()
            ->assertViewHas('turnoDestacadoId', $turno->id)->assertSee('Primera consulta')->assertSee('Pendiente');

        foreach (DocumentoClinico::orderBy('id')->get() as $i => $doc) {
            $contenido = file_get_contents($archivos[$i]->getRealPath());
            $this->assertSame($this->paciente->id, $doc->paciente_id);
            $this->assertSame($this->paciente->user_id, $doc->cargado_por_user_id);
            $this->assertSame(DocumentoClinico::ORIGEN_SOLICITUD_TURNO, $doc->origen);
            $this->assertSame(DocumentoClinico::ESTADO_VALIDACION_PENDIENTE, $doc->estado_validacion);
            $this->assertSame(DocumentoClinico::TIPO_SIN_CLASIFICAR, $doc->tipo_documento_clinico);
            $this->assertSame($archivos[$i]->getClientOriginalName(), $doc->nombre_original);
            $this->assertSame(trim($adjuntos[$i]['descripcion']), $doc->descripcion);
            $this->assertSame('application/pdf', $doc->mime_type);
            $this->assertSame(strlen($contenido), $doc->tamanio_bytes);
            $this->assertSame($contenido, $this->disco->get($doc->ruta_storage));
            $this->assertSame($turno->id, $doc->origenTurno->turno_id);
            $url = route('documentos-clinicos.download', $doc);
            $this->head($url)->assertStatus(405);
            $this->assertSame($i, Auditoria::where('evento', 'documento_clinico_descargado')->count());
            $descarga = $this->get($url)->assertOk()->assertDownload($doc->nombre_original)
                ->assertHeader('Content-Type', 'application/pdf')->assertStreamedContent($contenido);
            $this->assertStringContainsString('private', $descarga->headers->get('Cache-Control'));
            $this->assertStringNotContainsString($doc->ruta_storage, $descarga->headers->get('Content-Disposition'));
        }
        $this->assertCount($cantidad, DocumentoClinico::pluck('ruta_storage')->unique());
        $this->conteos(1, $cantidad, 1 + 2 * $cantidad);
        $this->auditoriaMinima();

        // La respuesta inicial ya fue consumida por HTTP; no se vuelven a enviar originales.
        $this->postJson(route('turnos.solicitar.store'), ['clave_idempotencia' => $this->uuid])
            ->assertOk()->assertExactJson([
                'turno_id' => $turno->id, 'es_replay' => true, 'redirect_url' => $respuesta->json('redirect_url'),
            ]);
        $this->conteos(1, $cantidad, 1 + 2 * $cantidad);
        $this->assertSame(array_map(static fn ($adjunto) => trim($adjunto['descripcion']), $adjuntos), DocumentoClinico::orderBy('id')->pluck('descripcion')->all());
    }

    public static function cantidades(): array
    {
        return ['sin adjuntos' => [0], 'cinco adjuntos' => [5]];
    }

    public function test_uc07_to_uc06_confirmation_cancellation_and_rebooking_preserve_shared_contracts(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $inicio = '2026-10-12 10:00:00';
        $datos = $this->payload(['inicio_at' => $inicio, 'adjuntos' => [$this->adjunto()]]);
        $this->post(route('turnos.solicitar.store'), $datos, ['Accept' => 'application/json'])->assertCreated();
        $turno = Turno::sole();
        $original = $turno->getAttributes();
        $documento = DocumentoClinico::sole();
        $originalDocumento = $documento->getAttributes();
        $contenido = $this->disco->get($documento->ruta_storage);
        $agenda = app(AgendaQueryService::class);
        $ocupacion = fn () => $agenda->turnosAfectadosPorIntervalo($this->profesional, $turno->inicio_at, $turno->fin_at)->pluck('id')->all();
        $disponibles = fn () => $this->getJson(route('turnos.solicitar.profesionales-disponibles', ['inicio_at' => $inicio]))->assertOk()->json('profesionales');
        $this->assertSame([$turno->id], $ocupacion());
        $this->assertSame([], $disponibles());
        $this->get(route('turnos.solicitar.create'))->assertOk()->assertViewHas('bloqueadoPorPrimeraConsulta', true);

        $detalle = $this->get(route('turnos.mis-turnos.show', $turno))->assertOk();
        $this->assertSame($original['motivo_solicitud'], $detalle->viewData('detalle')['motivo']);
        $this->assertSame($documento->descripcion, $detalle->viewData('detalle')['documentos']->sole()['descripcion']);
        $referencia = ['referencia_programacion' => $detalle->viewData('autogestion')['referencia']];
        $this->postJson(route('turnos.mis-turnos.confirmar', $turno), $referencia)
            ->assertOk()->assertJsonPath('es_replay', false);
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $turno->fresh()->estado);
        $this->assertSame([$turno->id], $ocupacion());
        $this->assertSame([], $disponibles());
        $this->get(route('turnos.solicitar.create'))->assertOk()->assertViewHas('bloqueadoPorPrimeraConsulta', true);

        $this->postJson(route('turnos.mis-turnos.cancelar', $turno), $referencia)
            ->assertOk()->assertJsonPath('es_replay', false);
        $this->postJson(route('turnos.mis-turnos.cancelar', $turno), $referencia)
            ->assertOk()->assertJsonPath('es_replay', true);
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $turno->fresh()->estado);
        $this->assertSame([], $ocupacion());
        $this->assertSame([$this->profesional->id], array_column($disponibles(), 'kinesiologa_id'));
        $this->get(route('turnos.mis-turnos.show', $turno))->assertOk()
            ->assertViewHas('detalle', fn ($d) => $d['seccion'] === 'historicos');
        foreach (['tipo', 'paciente_id', 'kinesiologa_id', 'inicio_at', 'fin_at', 'motivo_solicitud'] as $campo) {
            $this->assertSame($original[$campo], $turno->fresh()->getRawOriginal($campo));
        }
        $this->assertSame($originalDocumento, $documento->fresh()->getAttributes());
        $this->assertSame($turno->id, $documento->origenTurno->turno_id);
        $this->get(route('documentos-clinicos.download', $documento))->assertOk()->assertStreamedContent($contenido);
        $this->actingAs($this->pacienteUc07()->user)->get(route('documentos-clinicos.download', $documento))->assertForbidden();
        $this->actingAs($this->paciente->user);

        $wizard = $this->get(route('turnos.solicitar.create'))->assertOk()->viewData('configuracionWizard');
        $this->assertNotSame($this->uuid, $wizard['uuid']);
        $this->postJson(route('turnos.solicitar.store'), $this->payload([
            'clave_idempotencia' => $wizard['uuid'], 'inicio_at' => $inicio,
        ]))->assertCreated();
        $nuevo = Turno::query()->whereKeyNot($turno->id)->sole();
        $this->assertSame([$nuevo->id], $ocupacion());
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $turno->fresh()->estado);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $intencion = IntencionEvaluacionCupo::sole();
        $cancelacion = Auditoria::where('evento', 'turno_cancelado')->sole();
        $this->assertSame($cancelacion->id, $intencion->auditoria_cancelacion_id);
        $this->assertSame($turno->id, $intencion->turno_id);
        $this->assertNull($intencion->procesado_at);
        $this->assertSame(1, Auditoria::where('evento', 'turno_asistencia_confirmada')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_lost_response_then_uuid_only_replay_precedes_all_mutated_creation_conditions(): void
    {
        // Se envía el POST real y se descarta su respuesta, sin simular que no llegó al backend.
        $this->post(route('turnos.solicitar.store'), $this->payload(['adjuntos' => [$this->adjunto()]]),
            ['Accept' => 'application/json']);
        $turno = Turno::sole();
        $this->conteos(1, 1, 2);
        $this->profesional->user->update(['activo' => false]);
        BloqueoAgenda::factory()->for($this->profesional, 'kinesiologa')->create([
            'inicio_at' => $turno->inicio_at, 'fin_at' => $turno->fin_at,
        ]);
        // Antes de avanzar el reloj, su propio turno satisface D-01.
        $this->postJson(route('turnos.solicitar.store'), ['clave_idempotencia' => $this->uuid])
            ->assertOk()->assertJsonPath('turno_id', $turno->id)->assertJsonPath('es_replay', true);
        CarbonImmutable::setTestNow($turno->fin_at->addDay());
        $this->postJson(route('turnos.solicitar.store'), ['clave_idempotencia' => $this->uuid])
            ->assertOk()->assertJsonPath('turno_id', $turno->id)->assertJsonPath('es_replay', true);
        $this->conteos(1, 1, 2);
        $this->assertSame('Descripción original sintética.', DocumentoClinico::sole()->descripcion);
    }

    public function test_uncertain_commit_through_http_preserves_bytes_and_allows_safe_uuid_retry(): void
    {
        $pdo = DB::connection()->getPdo();
        Event::listen(TransactionCommitted::class, static function (): never {
            DB::connection()->setPdo(null);
            throw new RuntimeException('Commit sin respuesta verificable');
        });
        try {
            $this->post(route('turnos.solicitar.store'), $this->payload(['adjuntos' => [$this->adjunto()]]),
                ['Accept' => 'application/json'])->assertStatus(500)->assertJsonMissingPath('code')
                ->assertJsonMissingPath('turno_id')->assertDontSee('Commit sin respuesta verificable');
        } finally {
            DB::connection()->setPdo($pdo);
            Event::forget(TransactionCommitted::class);
        }
        $this->conteos(1, 1, 2);
        $doc = DocumentoClinico::sole();
        $contenido = $this->disco->get($doc->ruta_storage);
        $this->postJson(route('turnos.solicitar.store'), ['clave_idempotencia' => $this->uuid])
            ->assertOk()->assertJsonPath('es_replay', true)->assertJsonPath('turno_id', Turno::sole()->id);
        $this->conteos(1, 1, 2);
        $this->assertSame($contenido, $this->disco->get($doc->ruta_storage));
        $this->assertSame('Descripción original sintética.', $doc->fresh()->descripcion);

        // Antigüedad artificial: una referencia consolidada protege también el archivo antiguo.
        touch($this->disco->path($doc->ruta_storage), CarbonImmutable::getTestNow()->subHours(25)->getTimestamp());
        $resultado = app(ReconciliadorDocumentosClinicosService::class)->reconciliar(true);
        $this->assertTrue($resultado->exitoso());
        $this->assertSame(1, $resultado->conteos['referenciados']);
        $this->assertSame(0, $resultado->conteos['eliminados']);
        $this->assertSame($contenido, $this->disco->get($doc->ruta_storage));
    }

    public function test_confirmed_originals_and_association_cannot_be_rewritten(): void
    {
        $this->post(route('turnos.solicitar.store'), $this->payload(['adjuntos' => [$this->adjunto()]]),
            ['Accept' => 'application/json'])->assertCreated();
        $turno = Turno::sole();
        $doc = DocumentoClinico::sole();
        $origen = DocumentoClinicoOrigenTurno::sole();
        foreach ([
            [$turno, ['motivo_solicitud' => 'Otro motivo']],
            [$turno->fresh(), ['tipo' => TipoTurnoEnum::SEGUIMIENTO, 'motivo_solicitud' => 'Otro motivo']],
            [$doc, ['nombre_original' => 'otro.pdf']],
            [$doc->fresh(), ['descripcion' => 'Otra descripción']],
            [$doc->fresh(), ['ruta_storage' => 'originales/'.Str::uuid()]],
            [$doc->fresh(), ['origen' => 'OTRO']],
            [$origen, ['turno_id' => 999999]],
        ] as [$modelo, $cambios]) {
            $antes = $modelo->getRawOriginal();
            try {
                $modelo->update($cambios);
                $this->fail('El original confirmado debe rechazar modificaciones.');
            } catch (LogicException) {
                $this->assertSame($antes, $modelo->fresh()->getAttributes());
            }
        }
        $this->conteos(1, 1, 2);
    }

    public function test_document_from_real_flow_is_private_for_patient_professional_and_admin_roles(): void
    {
        $this->post(route('turnos.solicitar.store'), $this->payload(['adjuntos' => [$this->adjunto()]]),
            ['Accept' => 'application/json'])->assertCreated();
        $turno = Turno::sole();
        $doc = DocumentoClinico::sole();
        $url = route('documentos-clinicos.download', $doc);
        $otro = $this->pacienteUc07();
        $this->actingAs($otro->user)->get($url)->assertForbidden();
        $this->get(route('turnos.mis-turnos.index', ['turno' => $turno->id, 'paciente_id' => $this->paciente->id]))
            ->assertOk()->assertViewHas('turnoDestacadoId', null)
            ->assertViewHas('proximos', fn ($turnos) => $turnos->isEmpty());
        $this->postJson(route('turnos.solicitar.store'), ['clave_idempotencia' => $this->uuid])
            ->assertConflict()->assertJsonCount(1)->assertJsonMissingPath('code')->assertJsonMissingPath('turno_id')
            ->assertJsonMissingPath('redirect_url')->assertDontSee($this->payload()['motivo_solicitud']);
        $this->postJson(route('turnos.solicitar.store'), $this->payload([
            'clave_idempotencia' => (string) Str::uuid(), 'paciente_id' => $this->paciente->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('paciente_id');
        foreach ([
            $this->profesionalUc07()->user, $this->usuarioUc07(RoleEnum::ADMINISTRADOR),
            $this->usuarioUc07(RoleEnum::SUPERADMIN),
        ] as $actor) {
            $this->actingAs($actor)->get($url)->assertForbidden();
        }
        $this->assertSame(0, Auditoria::where('evento', 'documento_clinico_descargado')->count());
        $this->actingAs($this->profesional->user)->get($url)->assertOk()
            ->assertStreamedContent($this->disco->get($doc->ruta_storage));
        $this->conteos(1, 1, 3);
        $this->auditoriaMinima();
    }

    #[DataProvider('conflictos')]
    public function test_real_conflict_priority_never_writes_files_or_partial_aggregate(string $code): void
    {
        if ($code !== 'profesional_no_elegible') {
            Turno::factory()->for($code === 'primera_consulta_activa' ? $this->paciente : $this->pacienteUc07())
                ->for($this->profesional, 'kinesiologa')->create([
                    'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => EstadoTurnoEnum::PENDIENTE,
                    'inicio_at' => '2026-10-05 10:00:00', 'fin_at' => '2026-10-05 10:30:00',
                ]);
        }
        if ($code !== 'slot_no_disponible') {
            $this->profesional->user->update(['activo' => false]);
        }
        $this->post(route('turnos.solicitar.store'), $this->payload([
            'inicio_at' => $code === 'slot_no_disponible' ? '2026-10-05 10:00:00' : '2026-10-05 10:01:00',
            'adjuntos' => [$this->adjunto()],
        ]), ['Accept' => 'application/json'])->assertConflict()->assertJsonCount(2)->assertJsonPath('code', $code)
            ->assertJsonMissingPath('turno_id')->assertJsonMissingPath('redirect_url');
        $this->conteos($code === 'profesional_no_elegible' ? 0 : 1, 0, 0);
        $this->assertSame([], $this->coordinador->adquisiciones);
        $this->assertDatabaseMissing('turnos', ['clave_idempotencia' => $this->uuid]);
    }

    public static function conflictos(): array
    {
        return [['primera_consulta_activa'], ['profesional_no_elegible'], ['slot_no_disponible']];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_real_422_rejects_bad_input_without_side_effects_or_consuming_uuid(string $caso, array $campos): void
    {
        $payload = $this->payload();
        if ($caso === 'motivo') {
            $payload['motivo_solicitud'] = '';
        } elseif ($caso === 'estructura') {
            $payload['adjuntos'] = 'no-array';
        } elseif ($caso === 'cantidad') {
            $payload['adjuntos'] = array_map(fn ($i) => $this->adjunto($this->archivo('Estudio '.$i.'.pdf', $i)), range(1, 6));
        } elseif ($caso === 'mime') {
            $archivo = $this->archivo();
            file_put_contents($archivo->getRealPath(), 'Contenido de texto plano sin PDF');
            $payload['adjuntos'] = [$this->adjunto($archivo)];
        } else {
            // Se comprueba el rechazo real de toda la whitelist derivada, no sólo su ausencia en validated().
            foreach ($campos as $campo) {
                $payload[$campo] = 'MANIPULADO';
            }
        }
        $this->post(route('turnos.solicitar.store'), $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors($campos)->assertJsonMissingPath('code');
        $this->conteos(0, 0, 0);
        $this->assertSame([], $this->coordinador->adquisiciones);
        $this->postJson(route('turnos.solicitar.store'), $this->payload())->assertCreated();
        $this->conteos(1, 0, 1);
    }

    public static function entradasInvalidas(): array
    {
        return [
            ['motivo', ['motivo_solicitud']], ['estructura', ['adjuntos']],
            ['cantidad', ['adjuntos']], ['mime', ['adjuntos.0.archivo']],
            ['derivados', ['paciente_id', 'tipo', 'estado', 'fin_at', 'cargado_por_user_id',
                'origen', 'estado_validacion', 'tipo_documento_clinico', 'ruta_storage', 'mime_type', 'tamanio_bytes']],
        ];
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'clave_idempotencia' => $this->uuid, 'motivo_solicitud' => 'Motivo clínico original privado',
            'kinesiologa_id' => $this->profesional->id, 'inicio_at' => '2026-10-05 10:00:00',
        ], $cambios);
    }

    private function archivo(string $nombre = 'Estudio.pdf', int $indice = 0): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-integracion-');
        $this->temporales[] = $ruta;
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% fixture ".$indice."\n%%EOF\n");

        return new UploadedFile($ruta, $nombre, 'application/octet-stream', UPLOAD_ERR_OK, true);
    }

    private function adjunto(?UploadedFile $archivo = null, string $descripcion = 'Descripción original sintética.'): array
    {
        return ['archivo' => $archivo ?? $this->archivo(), 'descripcion' => $descripcion];
    }

    private function conteos(int $turnos, int $docs, int $auditorias): void
    {
        $this->assertDatabaseCount('turnos', $turnos);
        $this->assertDatabaseCount('documentos_clinicos', $docs);
        $this->assertDatabaseCount('documentos_clinicos_origen_turno', $docs);
        $this->assertSame($auditorias, Auditoria::where('modulo', 'Turnos')->count());
        $this->assertCount($docs, $this->disco->allFiles());
    }

    private function auditoriaMinima(): void
    {
        $permitidos = [
            'turno_primera_consulta_creado' => ['turno_id', 'paciente_id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at'],
            'documento_clinico_cargado' => ['documento_clinico_id', 'turno_id', 'paciente_id', 'origen', 'estado_validacion', 'tipo_documento_clinico', 'mime_type', 'tamanio_bytes'],
            'documento_clinico_descargado' => ['documento_clinico_id', 'paciente_id', 'origen', 'turno_id'],
        ];
        foreach (Auditoria::where('modulo', 'Turnos')->get() as $audit) {
            $this->assertNull($audit->valores_anteriores);
            $this->assertEqualsCanonicalizing($permitidos[$audit->evento], array_keys($audit->valores_nuevos));
            foreach ([$this->payload()['motivo_solicitud'], '%PDF-', $this->disco->path('')] as $privado) {
                $this->assertStringNotContainsString($privado, json_encode($audit->getAttributes()));
            }
        }
    }
}
