<?php

namespace Tests\Feature\Services\Turnos;

use App\Models\Auditoria;
use App\Models\DocumentoClinico;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use App\Services\Turnos\AlmacenamientoDocumentosClinicosService;
use App\Services\Turnos\Documentos\CoordinadorBloqueoDocumental;
use App\Services\Turnos\GestionDocumentoClinicoService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CoordinadorBloqueoDocumentalEnMemoria;
use Tests\TestCase;

class GestionDocumentoClinicoTest extends TestCase
{
    use RefreshDatabase;

    private CoordinadorBloqueoDocumentalEnMemoria $coordinador;

    private FilesystemAdapter $disco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinador = new CoordinadorBloqueoDocumentalEnMemoria;
        $this->app->instance(CoordinadorBloqueoDocumental::class, $this->coordinador);
        $this->disco = Storage::fake('documentos_clinicos');
    }

    protected function tearDown(): void
    {
        try {
            $this->assertSame([], $this->coordinador->activos);
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('lotes')]
    public function test_pipeline_persists_each_original_description_with_its_file_and_turno(array $descripciones, bool $plano): void
    {
        [$actor, $paciente, $turno] = $this->contexto();
        $adjuntos = [];
        $archivos = [];
        foreach ($descripciones as $indice => $descripcion) {
            $archivos[$indice] = $this->pdf($indice);
            $adjuntos[$indice] = $plano ? $archivos[$indice] : ['archivo' => $archivos[$indice], 'descripcion' => $descripcion];
        }
        $operacion = app(AlmacenamientoDocumentosClinicosService::class)->almacenar($adjuntos);
        $metadata = $operacion->documentos();

        try {
            $documentos = DB::transaction(function () use ($actor, $paciente, $turno, $metadata): array {
                $documentos = [];
                foreach ($metadata as $indice => $atributos) {
                    $documentos[$indice] = app(GestionDocumentoClinicoService::class)->registrarOrigenTurno($actor, $paciente, $turno, $atributos);
                }

                return $documentos;
            });

            $this->assertSame(array_keys($descripciones), array_keys($metadata));
            foreach ($documentos as $indice => $documento) {
                $documento = $documento->fresh();
                $archivo = $archivos[$indice];
                $this->assertSame($descripciones[$indice], $metadata[$indice]['descripcion']);
                $this->assertSame($descripciones[$indice], $documento->descripcion);
                $this->assertSame($archivo->getClientOriginalName(), $documento->nombre_original);
                $this->assertSame('application/pdf', $documento->mime_type);
                $this->assertSame($archivo->getSize(), $documento->tamanio_bytes);
                $this->assertSame(hash_file('sha256', $archivo->getPathname()), hash('sha256', $this->disco->get($documento->ruta_storage)));
                $this->assertSame($paciente->id, $documento->paciente_id);
                $this->assertSame($actor->id, $documento->cargado_por_user_id);
                $this->assertSame(DocumentoClinico::ORIGEN_SOLICITUD_TURNO, $documento->origen);
                $this->assertSame(DocumentoClinico::ESTADO_VALIDACION_PENDIENTE, $documento->estado_validacion);
                $this->assertSame(DocumentoClinico::TIPO_SIN_CLASIFICAR, $documento->tipo_documento_clinico);
                $this->assertSame($turno->id, $documento->origenTurno->turno_id);
                $this->assertMatchesRegularExpression('/\Aoriginales\/[a-f0-9-]{36}\z/', $documento->ruta_storage);
                $auditoria = Auditoria::where('auditable_id', $documento->id)->where('evento', 'documento_clinico_cargado')->sole();
                $this->assertArrayNotHasKey('descripcion', $auditoria->valores_nuevos);
            }
            $this->assertDatabaseCount('documentos_clinicos', count($descripciones));
            $this->assertDatabaseCount('documentos_clinicos_origen_turno', count($descripciones));
            $this->assertCount(count($descripciones), $this->disco->allFiles());
        } finally {
            $operacion->conservarYLiberar();
        }
    }

    public static function lotes(): array
    {
        return [
            'un adjunto' => [[2 => 'Adjunto sintético A.'], false],
            'dispersos y Unicode' => [[2 => 'Adjunto sintético A ../privado', 7 => 'Adjunto sintético B 日本語', 11 => str_repeat('á', 500)], false],
            'NULL explícito' => [[7 => null], false],
            'caller plano existente' => [[3 => null], true],
        ];
    }

    public function test_registration_failure_rolls_back_documents_and_allows_existing_compensation(): void
    {
        [$actor, $paciente, $turno] = $this->contexto();
        $this->disco->put('previo', 'archivo ajeno');
        $operacion = app(AlmacenamientoDocumentosClinicosService::class)->almacenar([
            2 => ['archivo' => $this->pdf(2), 'descripcion' => 'Adjunto sintético A.'],
            7 => ['archivo' => $this->pdf(7), 'descripcion' => 'Adjunto sintético B.'],
        ]);
        $original = new RuntimeException('Fallo de persistencia documental de prueba.');
        Auditoria::creating(static function () use ($original): never {
            throw $original;
        });
        $servicio = app(GestionDocumentoClinicoService::class);

        try {
            DB::transaction(function () use ($servicio, $actor, $paciente, $turno, $operacion): void {
                foreach ($operacion->documentos() as $metadata) {
                    $servicio->registrarOrigenTurno($actor, $paciente, $turno, $metadata);
                }
            });
            $this->fail('Debe fallar la persistencia documental.');
        } catch (RuntimeException $error) {
            $this->assertSame($original, $error);
        } finally {
            $operacion->compensarYLiberar();
        }

        $this->assertDatabaseCount('documentos_clinicos', 0);
        $this->assertDatabaseCount('documentos_clinicos_origen_turno', 0);
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseHas('turnos', ['id' => $turno->id]);
        $this->assertSame(['previo'], $this->disco->allFiles());
        $this->assertSame('archivo ajeno', $this->disco->get('previo'));
        $this->assertCount(2, $this->coordinador->liberaciones);
    }

    private function contexto(): array
    {
        $actor = User::factory()->create();
        $paciente = Paciente::create(['user_id' => $actor->id, 'activo' => true]);
        $kinesiologa = Kinesiologa::create(['user_id' => User::factory()->create()->id]);
        $turno = Turno::factory()->for($paciente)->for($kinesiologa)->create();

        return [$actor, $paciente, $turno];
    }

    private function pdf(int $indice): UploadedFile
    {
        return UploadedFile::fake()->createWithContent("adjunto-{$indice}.pdf", "%PDF-1.4\n% Adjunto sintético {$indice}\n%%EOF\n");
    }
}
