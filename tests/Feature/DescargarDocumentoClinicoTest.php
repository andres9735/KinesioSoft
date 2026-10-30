<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\DeclaracionSaludPaciente;
use App\Models\DocumentoClinico;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\Localidad;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DescargarDocumentoClinicoTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENIDO = "%PDF-1.4\nFixture privado\0\n%%EOF\n";

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private Turno $turno;

    private DocumentoClinico $documento;

    private FilesystemAdapter $disco;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        $this->disco = Storage::fake('documentos_clinicos');
        $this->paciente = $this->pacienteCompleto();
        $this->kinesiologa = $this->profesionalCompleta();
        $this->turno = Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => 'Motivo original privado']);
        $this->documento = $this->documento();
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.Auditoria::class);
        parent::tearDown();
    }

    #[DataProvider('actoresClinicos')]
    public function test_owner_and_assigned_professional_receive_exact_private_bytes_and_minimal_access_audit(string $actor): void
    {
        $user = $actor === 'paciente' ? $this->paciente->user : $this->kinesiologa->user;
        $antes = $this->documento->fresh()->getAttributes();
        $origen = $this->documento->origenTurno->getAttributes();
        $response = $this->actingAs($user)->get($this->url());
        $response->assertOk()->assertStreamed()->assertDownload('Estudio original.pdf');
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Length', (string) strlen(self::CONTENIDO));
        $response->assertHeaderMissing('Location');
        $response->assertStreamedContent(self::CONTENIDO);
        $headers = implode("\n", array_map(fn (array $valores): string => implode('; ', $valores), $response->headers->all()));
        foreach ([$this->documento->ruta_storage, 'storage/app/private', $this->disco->path('')] as $privado) {
            $this->assertStringNotContainsString($privado, $headers);
            $this->assertStringNotContainsString($privado, $response->streamedContent());
        }
        $auditoria = Auditoria::sole();
        $this->assertSame('documento_clinico_descargado', $auditoria->evento);
        $this->assertSame('Turnos', $auditoria->modulo);
        $this->assertSame($user->id, $auditoria->actor_user_id);
        $this->assertSame($this->documento->getMorphClass(), $auditoria->auditable_type);
        $this->assertSame($this->documento->id, $auditoria->auditable_id);
        $this->assertNull($auditoria->valores_anteriores);
        $this->assertSame([
            'documento_clinico_id' => $this->documento->id,
            'paciente_id' => $this->paciente->id,
            'origen' => $this->documento->origen,
            'turno_id' => $this->turno->id,
        ], $auditoria->valores_nuevos);
        foreach (['nombre_original', 'ruta_storage', 'motivo_solicitud', 'clave_idempotencia', 'tamanio_bytes', 'contenido'] as $campo) {
            $this->assertArrayNotHasKey($campo, $auditoria->valores_nuevos);
        }
        $this->assertSame($antes, $this->documento->fresh()->getAttributes());
        $this->assertSame($origen, $this->documento->origenTurno()->firstOrFail()->getAttributes());
    }

    public static function actoresClinicos(): array
    {
        return [['paciente'], ['kinesiologa']];
    }

    #[DataProvider('actoresDenegados')]
    public function test_unrelated_and_administrative_actors_are_denied_before_storage_even_when_file_is_missing(string $actor): void
    {
        $user = match ($actor) {
            'paciente' => $this->pacienteCompleto()->user,
            'kinesiologa' => $this->profesionalCompleta()->user,
            'admin historico' => $this->paciente->user,
            'superadmin historica' => $this->kinesiologa->user,
            default => $this->usuario($actor === 'admin' ? RoleEnum::ADMINISTRADOR : RoleEnum::SUPERADMIN),
        };
        if ($actor === 'kinesiologa') {
            Turno::factory()->for($this->paciente)->for($user->kinesiologa)->create();
        }
        if ($actor === 'admin historico' || $actor === 'superadmin historica') {
            $user->syncRoles([$actor === 'admin historico' ? RoleEnum::ADMINISTRADOR->value : RoleEnum::SUPERADMIN->value]);
        }
        $this->disco->delete($this->documento->ruta_storage);
        Storage::shouldReceive('disk')->never();
        $this->actingAs($user)->get($this->url())->assertForbidden();
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function actoresDenegados(): array
    {
        return [['paciente'], ['kinesiologa'], ['admin'], ['superadmin'], ['admin historico'], ['superadmin historica']];
    }

    public function test_guest_is_redirected_to_login_without_storage_or_audit(): void
    {
        Storage::shouldReceive('disk')->never();
        $this->get($this->url())->assertRedirect(route('login'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('actoresClinicos')]
    public function test_unverified_clinical_actors_are_redirected_by_existing_middleware(string $actor): void
    {
        $user = $actor === 'paciente' ? $this->paciente->user : $this->kinesiologa->user;
        $user->forceFill(['email_verified_at' => null])->save();
        Storage::shouldReceive('disk')->never();
        $this->actingAs($user)->get($this->url())->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('actoresClinicos')]
    public function test_incomplete_profiles_follow_existing_profile_redirects(string $actor): void
    {
        $user = $actor === 'paciente' ? $this->paciente->user : $this->kinesiologa->user;
        $user->update(['telefono' => null]);
        Storage::shouldReceive('disk')->never();
        $destino = $actor === 'paciente' ? 'perfil.completar' : 'perfil.kinesiologa.completar';
        $this->actingAs($user->fresh())->get($this->url())->assertRedirect(route($destino));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_unknown_document_and_missing_file_return_not_found_without_auditing_or_deleting_rows(): void
    {
        $this->actingAs($this->paciente->user)->get(route('documentos-clinicos.download', ['documento' => 999999]))->assertNotFound();
        $this->disco->delete($this->documento->ruta_storage);
        $this->get($this->url())->assertNotFound();
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertDatabaseCount('documentos_clinicos', 1);
    }

    #[DataProvider('fallasApertura')]
    public function test_unopenable_stream_is_not_found_and_never_audited(bool $excepcion): void
    {
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $llamada = $adapter->shouldReceive('readStream')->once()->with($this->documento->ruta_storage);
        $excepcion ? $llamada->andThrow(UnableToReadFile::fromLocation($this->documento->ruta_storage)) : $llamada->andReturn(null);
        $adapter->shouldReceive('url')->never();
        Storage::set('documentos_clinicos', $adapter);
        $this->actingAs($this->paciente->user)->get($this->url())->assertNotFound();
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function fallasApertura(): array
    {
        return [[false], [true]];
    }

    public function test_unexpected_storage_failure_propagates_and_is_not_disguised_as_missing_file(): void
    {
        $this->withoutExceptionHandling();
        $fallo = new RuntimeException('Falla técnica de storage');
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('readStream')->once()->andThrow($fallo);
        Storage::set('documentos_clinicos', $adapter);
        try {
            $this->actingAs($this->paciente->user)->get($this->url());
            $this->fail('El error técnico debe propagarse.');
        } catch (RuntimeException $error) {
            $this->assertSame($fallo, $error);
        }
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_a_single_stream_is_open_before_audit_and_is_closed_after_response_consumes_it(): void
    {
        $stream = $this->stream();
        Event::listen('eloquent.creating: '.Auditoria::class, function () use ($stream): void {
            $this->assertTrue(is_resource($stream));
            $this->assertSame(0, ftell($stream));
        });
        $response = $this->actingAs($this->paciente->user)->get($this->url());
        $this->assertTrue(is_resource($stream));
        $response->assertOk()->assertStreamedContent(self::CONTENIDO);
        $this->assertFalse(is_resource($stream));
        $this->assertDatabaseCount('auditorias', 1);
    }

    public function test_audit_failure_closes_open_stream_propagates_and_never_serves_unaudited_content(): void
    {
        $this->withoutExceptionHandling();
        $stream = $this->stream();
        $antes = $this->documento->fresh()->getAttributes();
        $fallo = new RuntimeException('Falla controlada de auditoría');
        Event::listen('eloquent.creating: '.Auditoria::class, function () use ($stream, $fallo): never {
            $this->assertTrue(is_resource($stream));
            throw $fallo;
        });
        try {
            $this->actingAs($this->paciente->user)->get($this->url());
            $this->fail('La descarga no puede continuar sin auditoría.');
        } catch (RuntimeException $error) {
            $this->assertSame($fallo, $error);
        }
        $this->assertFalse(is_resource($stream));
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertSame($antes, $this->documento->fresh()->getAttributes());
    }

    public function test_response_creation_failure_also_closes_the_stream_before_audit(): void
    {
        $this->withoutExceptionHandling();
        $stream = $this->stream();
        $fallo = new RuntimeException('Falla al construir respuesta');
        $factory = Mockery::mock(ResponseFactory::class);
        $factory->shouldReceive('streamDownload')->once()->andThrow($fallo);
        $this->app->instance(ResponseFactory::class, $factory);
        try {
            $this->actingAs($this->paciente->user)->get($this->url());
            $this->fail('Debe propagarse la falla de respuesta.');
        } catch (RuntimeException $error) {
            $this->assertSame($fallo, $error);
        }
        $this->assertFalse(is_resource($stream));
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('nombresProblematicos')]
    public function test_problematic_original_names_cannot_inject_headers_or_expose_internal_paths(string $nombre): void
    {
        $documento = $this->documento(['nombre_original' => $nombre]);
        $response = $this->actingAs($this->paciente->user)->get($this->url($documento));
        $response->assertOk()->assertDownload()->assertHeaderMissing('X-Inyectado');
        $header = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $header);
        $this->assertStringNotContainsString("\r", $header);
        $this->assertStringNotContainsString("\n", $header);
        $this->assertStringNotContainsString($documento->ruta_storage, $header);
        if (str_contains($nombre, 'José')) {
            $this->assertStringContainsString(rawurlencode($nombre), $header);
        }
        $response->assertStreamedContent(self::CONTENIDO);
        $this->assertSame($nombre, $documento->fresh()->nombre_original);
    }

    public static function nombresProblematicos(): array
    {
        return [['Estudio José "original" 100%.pdf'], ["informe.pdf\r\nX-Inyectado: si"], ['../../privado/estudio.pdf'], ['C:\\privado\\estudio.pdf']];
    }

    #[DataProvider('rutasCorruptas')]
    public function test_corrupt_persisted_paths_are_unavailable_without_resolving_storage(string $ruta): void
    {
        $documento = $this->documento(['ruta_storage' => $ruta], false);
        Storage::shouldReceive('disk')->never();
        $this->actingAs($this->paciente->user)->get($this->url($documento))->assertNotFound();
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function rutasCorruptas(): array
    {
        return [['../secreto'], ['/originales/11111111-1111-4111-8111-111111111111'], ['originales/../secreto'], ['originales\\secreto'], ['otro/11111111-1111-4111-8111-111111111111'], ['originales/11111111-1111-4111-8111-111111111111.pdf']];
    }

    public function test_query_parameters_never_choose_the_file_and_each_valid_get_audits_once(): void
    {
        $url = $this->url().'?path=../secreto&paciente_id=999&turno_id=999';
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->paciente->user)->get($url)->assertOk()->assertStreamedContent(self::CONTENIDO);
        }
        $this->assertSame(2, Auditoria::where('evento', 'documento_clinico_descargado')->count());
    }

    #[DataProvider('documentosNoAutorizados')]
    public function test_direct_url_rejects_documents_outside_patient_contract_before_opening_private_storage(string $caso): void
    {
        $otroPaciente = $this->pacienteCompleto();
        $documento = $this->documento([
            'paciente_id' => $caso === 'documento_ajeno' ? $otroPaciente->id : $this->paciente->id,
            'origen' => match ($caso) {
                'interno' => 'CONSULTA_CLINICA',
                'desconocido' => 'DESCONOCIDO',
                default => DocumentoClinico::ORIGEN_SOLICITUD_TURNO,
            },
            'descripcion' => 'DESCRIPCION NO AUTORIZADA',
        ], true, false);
        if ($caso !== 'sin_asociacion') {
            $pacienteTurno = in_array($caso, ['turno_ajeno', 'documento_ajeno'], true) ? $otroPaciente : $this->paciente;
            $turno = Turno::factory()->for($pacienteTurno)->for($this->kinesiologa)->create([
                'tipo' => $caso === 'seguimiento' ? TipoTurnoEnum::SEGUIMIENTO : TipoTurnoEnum::PRIMERA_CONSULTA,
            ]);
            $documento->origenTurno()->create(['turno_id' => $turno->id]);
        }
        $antes = $documento->fresh()->getAttributes();
        $this->disco->assertExists($documento->ruta_storage);
        Storage::shouldReceive('disk')->never();
        $url = $this->url($documento).'?'.http_build_query([
            'paciente_id' => $this->paciente->id, 'turno_id' => $this->turno->id,
            'documento' => $this->documento->id, 'origen' => DocumentoClinico::ORIGEN_SOLICITUD_TURNO,
        ]);
        $this->actingAs($this->paciente->user)->withHeader('Referer', route('turnos.mis-turnos.show', $this->turno))->get($url)
            ->assertForbidden()->assertHeaderMissing('Content-Disposition')->assertHeaderMissing('Location')
            ->assertDontSee($documento->nombre_original)->assertDontSee($documento->descripcion)
            ->assertDontSee($documento->ruta_storage, false)->assertDontSee(self::CONTENIDO, false);
        if ($caso === 'sin_asociacion') {
            $this->actingAs($this->kinesiologa->user)->get($this->url($documento))->assertForbidden();
        }
        $this->assertSame($antes, $documento->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function documentosNoAutorizados(): array
    {
        return [['documento_ajeno'], ['interno'], ['desconocido'], ['sin_asociacion'], ['turno_ajeno'], ['seguimiento']];
    }

    public function test_inactive_patient_cannot_download_even_an_authorized_original(): void
    {
        $this->paciente->user->forceFill(['activo' => false])->save();
        Storage::shouldReceive('disk')->never();
        $this->actingAs($this->paciente->user)->get($this->url())->assertRedirect(route('login'));
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_implicit_head_does_not_open_or_audit_download(): void
    {
        Storage::shouldReceive('disk')->never();
        $this->actingAs($this->paciente->user)->head($this->url())->assertStatus(405);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function url(?DocumentoClinico $documento = null): string
    {
        return route('documentos-clinicos.download', ['documento' => ($documento ?? $this->documento)->id]);
    }

    private function documento(array $cambios = [], bool $escribir = true, bool $origen = true): DocumentoClinico
    {
        $documento = DocumentoClinico::factory()->for($this->paciente)->for($this->paciente->user, 'cargadoPor')->create(array_replace([
            'ruta_storage' => 'originales/'.Str::uuid(), 'nombre_original' => 'Estudio original.pdf',
            'mime_type' => 'application/pdf', 'tamanio_bytes' => strlen(self::CONTENIDO),
        ], $cambios));
        if ($origen) {
            $documento->origenTurno()->create(['turno_id' => $this->turno->id]);
        }
        if ($escribir) {
            $this->disco->put($documento->ruta_storage, self::CONTENIDO);
        }

        return $documento;
    }

    private function stream(): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, self::CONTENIDO);
        rewind($stream);
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('readStream')->once()->with($this->documento->ruta_storage)->andReturn($stream);
        $adapter->shouldReceive('url')->never();
        Storage::set('documentos_clinicos', $adapter);

        return $stream;
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

    private function profesionalCompleta(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-download'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-download'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-download'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'DESC'], ['nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $user = $this->usuario(RoleEnum::KINESIOLOGO);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create(['kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id, 'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa;
    }
}
