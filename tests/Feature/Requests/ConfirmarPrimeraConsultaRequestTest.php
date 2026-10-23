<?php

namespace Tests\Feature\Requests;

use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Enums\EstadoTurnoEnum;
use App\Enums\TipoTurnoEnum;
use App\Exceptions\Turnos\ClaveIdempotenciaIncompatibleException;
use App\Http\Requests\ConfirmarPrimeraConsultaRequest;
use App\Models\DocumentoClinico;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfirmarPrimeraConsultaRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private string $uuid;

    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
        $this->actor = User::factory()->create();
        $this->paciente = Paciente::create(['user_id' => $this->actor->id, 'activo' => true]);
        $this->kinesiologa = Kinesiologa::create([
            'user_id' => User::factory()->create()->id,
            'titulo_profesional_id' => TituloProfesional::create(['nombre' => 'Título', 'slug' => 'titulo-request', 'activo' => true])->id,
        ]);
        $this->uuid = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        CarbonImmutable::setTestNow();
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        parent::tearDown();
    }

    #[DataProvider('uuidInvalidas')]
    public function test_phase_one_requires_a_string_uuid_and_does_not_query_creation_or_replay(mixed $uuid): void
    {
        DB::enableQueryLog();
        $errores = $this->errores(['clave_idempotencia' => $uuid]);
        $this->assertSame(['clave_idempotencia'], array_keys($errores));
        $this->assertSame([], DB::getQueryLog());
    }

    public static function uuidInvalidas(): array
    {
        return [[null], [''], ['no-uuid'], [123], [[]]];
    }

    #[DataProvider('camposProhibidos')]
    public function test_whitelist_rejects_derived_and_unknown_fields_before_replay_or_creation(string $campo, bool $replay): void
    {
        if ($replay) {
            $this->turno();
        }
        DB::enableQueryLog();
        $errores = $this->errores(['clave_idempotencia' => $this->uuid, $campo => null]);
        $this->assertSame([$campo], array_keys($errores));
        $this->assertSame([], DB::getQueryLog());
    }

    public static function camposProhibidos(): array
    {
        $casos = [];
        foreach (['paciente_id', 'tipo', 'estado', 'fin_at', 'cargado_por_user_id', 'origen', 'estado_validacion', 'tipo_documento_clinico', 'ruta_storage', 'mime_type', 'tamanio_bytes', 'recordatorio_enviado_at', 'duracion', 'foo', 'archivos', 'descripciones'] as $campo) {
            foreach ([false, true] as $replay) {
                $casos[$campo.($replay ? ' replay' : ' nueva')] = [$campo, $replay];
            }
        }

        return $casos;
    }

    #[DataProvider('retries')]
    public function test_replay_needs_only_uuid_and_never_validates_changed_creation_data_or_current_files(string $caso): void
    {
        $turno = $this->turno();
        $this->kinesiologa->user->update(['activo' => false]);
        if ($caso === 'slot pasado') {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 09:00:00', config('app.timezone')));
        }
        if (in_array($caso, ['sin originales', 'descripción histórica NULL'], true)) {
            $documento = DocumentoClinico::factory()->for($this->paciente)->for($this->actor, 'cargadoPor')->create();
            $documento->origenTurno()->create(['turno_id' => $turno->id]);
            $this->assertNull($documento->fresh()->descripcion);
        }
        $payload = ['clave_idempotencia' => strtoupper($this->uuid)];
        $archivos = [];
        switch ($caso) {
            case 'profesional distinto':
                $payload['kinesiologa_id'] = 999999;
                break;
            case 'slot distinto':
                $payload['inicio_at'] = 'no es una fecha';
                break;
            case 'motivo distinto':
                $payload['motivo_solicitud'] = ['no es un string'];
                break;
            case 'adjuntos distintos':
                $archivos = [$this->pdf()];
                break;
            case 'seis adjuntos':
                $archivos = array_map(fn () => $this->pdf(), range(1, 6));
                break;
            case 'mime inválido':
                $archivos = [$this->archivo('texto falso', 'falso.pdf')];
                break;
            case 'tamaño excedido':
                $archivos = [$this->pdf(10 * 1024 * 1024 + 1)];
                break;
            case 'upload corrupto':
                $archivo = $this->pdf();
                $archivos = [new UploadedFile($archivo->getPathname(), 'parcial.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true)];
                break;
            case 'no UploadedFile':
                $payload['adjuntos'] = ['/ruta/controlada/por/cliente'];
                break;
            case 'colección inválida':
                $payload['adjuntos'] = 'no es una colección';
                break;
            case 'sin inspeccionar':
                $archivo = $this->createMock(UploadedFile::class);
                foreach (['isValid', 'getSize', 'getMimeType'] as $metodo) {
                    $archivo->expects($this->never())->method($metodo);
                }
                $payload['adjuntos'] = [['archivo' => $archivo]];
                break;
            case 'descripción ausente':
                $payload['adjuntos'] = [['archivo' => $this->pdf()]];
                break;
            case 'descripción inválida':
                $payload['adjuntos'] = [['archivo' => $this->pdf(), 'descripcion' => ['no es texto']]];
                break;
            case 'descripción extensa':
                $payload['adjuntos'] = [['archivo' => $this->pdf(), 'descripcion' => str_repeat('á', 501)]];
                break;
        }
        DB::enableQueryLog();
        $request = $this->validar($payload, $this->adjuntos($archivos));
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(['clave_idempotencia' => strtoupper($this->uuid)], $request->validated());
        $this->assertSame($request->validated(), $request->safe()->all());
        $this->assertCount(2, $consultas);
        $this->assertStringContainsString('"pacientes"', $consultas[0]['query']);
        $this->assertStringContainsString('"clave_idempotencia" = ?', $consultas[1]['query']);
        $this->assertStringNotContainsString('estado', $consultas[1]['query']);
        $this->assertSame([$this->uuid], $consultas[1]['bindings']);
        $resultado = app(ConfirmarPrimeraConsultaAction::class)->ejecutar($this->actor, $request->validated('clave_idempotencia'));
        $this->assertTrue($resultado->esReplay);
        $this->assertSame($turno->id, $resultado->turno->id);
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function retries(): array
    {
        return array_map(fn ($caso) => [$caso], [
            'sólo UUID', 'slot pasado', 'D-01 propia', 'sin originales', 'profesional distinto',
            'slot distinto', 'motivo distinto', 'adjuntos distintos', 'seis adjuntos',
            'mime inválido', 'tamaño excedido', 'upload corrupto', 'no UploadedFile',
            'colección inválida', 'sin inspeccionar',
            'descripción ausente', 'descripción inválida', 'descripción extensa', 'descripción histórica NULL',
        ]);
    }

    #[DataProvider('contextosIncompatibles')]
    public function test_incompatible_uuid_retains_private_domain_error_before_new_creation_requirements(bool $otroPaciente): void
    {
        $paciente = $otroPaciente ? Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]) : $this->paciente;
        $turno = $this->turno($paciente, $otroPaciente ? TipoTurnoEnum::PRIMERA_CONSULTA : TipoTurnoEnum::SEGUIMIENTO);
        try {
            $this->validar(['clave_idempotencia' => $this->uuid]);
            $this->fail('Una UUID incompatible no debe pasar como replay ni validar creación.');
        } catch (ClaveIdempotenciaIncompatibleException $error) {
            $this->assertSame('La clave de intención no puede utilizarse.', $error->getMessage());
            $this->assertStringNotContainsString($turno->motivo_solicitud, $error->getMessage());
        }
        $this->assertDatabaseCount('turnos', 1);
    }

    public static function contextosIncompatibles(): array
    {
        return ['otro paciente' => [true], 'otro tipo' => [false]];
    }

    #[DataProvider('creacionInvalida')]
    public function test_new_intention_requires_creation_fields_and_explicit_datetime_format(array $cambios, string $campo): void
    {
        $errores = $this->errores($this->payload($cambios));
        $this->assertArrayHasKey($campo, $errores);
    }

    public static function creacionInvalida(): array
    {
        return [
            [['motivo_solicitud' => null], 'motivo_solicitud'],
            [['motivo_solicitud' => ''], 'motivo_solicitud'],
            [['motivo_solicitud' => '   '], 'motivo_solicitud'],
            [['motivo_solicitud' => str_repeat('a', 501)], 'motivo_solicitud'],
            [['motivo_solicitud' => []], 'motivo_solicitud'],
            [['kinesiologa_id' => null], 'kinesiologa_id'],
            [['kinesiologa_id' => 'abc'], 'kinesiologa_id'],
            [['kinesiologa_id' => 999999], 'kinesiologa_id'],
            [['inicio_at' => null], 'inicio_at'],
            [['inicio_at' => 'no-fecha'], 'inicio_at'],
            [['inicio_at' => '2026-10-05T10:00:00Z'], 'inicio_at'],
            [['inicio_at' => '2026-10-05 10:00'], 'inicio_at'],
            [['inicio_at' => '2026-02-30 10:00:00'], 'inicio_at'],
            [['inicio_at' => []], 'inicio_at'],
            [['adjuntos' => 'archivo.pdf'], 'adjuntos'],
            [['adjuntos' => null], 'adjuntos'],
        ];
    }

    public function test_new_uuid_alone_requires_all_creation_fields(): void
    {
        $this->assertSame(['motivo_solicitud', 'kinesiologa_id', 'inicio_at'], array_keys($this->errores(['clave_idempotencia' => $this->uuid])));
    }

    public function test_valid_creation_without_files_accepts_exact_500_characters_and_framework_metadata(): void
    {
        $payload = $this->payload(['motivo_solicitud' => str_repeat('á', 500), '_token' => 'csrf']);
        $request = $this->validar($payload);
        unset($payload['_token']);
        $this->assertSame($payload, $request->validated());
        $this->assertSame($payload, $request->safe()->all());
        $this->assertTrue($request->authorize());
    }

    #[DataProvider('condicionesMutables')]
    public function test_structurally_valid_creation_defers_mutable_conditions_to_action(string $condicion): void
    {
        $cambios = [];
        if ($condicion === 'pasado') {
            $cambios['inicio_at'] = '2020-01-01 10:00:00';
        } elseif ($condicion === 'no elegible') {
            $this->kinesiologa->user->update(['activo' => false]);
        } elseif ($condicion === 'D-01') {
            $this->turno();
            $cambios['clave_idempotencia'] = (string) Str::uuid();
        } elseif ($condicion === 'ocupado') {
            $otro = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
            $this->turno($otro);
            $cambios['clave_idempotencia'] = (string) Str::uuid();
        }
        DB::enableQueryLog();
        $this->validar($this->payload($cambios));
        $consultas = DB::getQueryLog();
        $this->assertCount(3, $consultas);
        $this->assertStringContainsString('"pacientes"', $consultas[0]['query']);
        $this->assertStringContainsString('"clave_idempotencia" = ?', $consultas[1]['query']);
        $this->assertStringContainsString('"kinesiologas"', $consultas[2]['query']);
        $this->assertStringNotContainsString('for update', implode(' ', array_column($consultas, 'query')));
    }

    public static function condicionesMutables(): array
    {
        return [['pasado'], ['no elegible'], ['D-01'], ['ocupado']];
    }

    public function test_action_can_resolve_replay_consolidated_after_request_validates_new_intention(): void
    {
        $request = $this->validar($this->payload());
        $turno = $this->turno();
        $resultado = app(ConfirmarPrimeraConsultaAction::class)->ejecutar($this->actor, $request->validated('clave_idempotencia'));
        $this->assertTrue($resultado->esReplay);
        $this->assertSame($turno->id, $resultado->turno->id);
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('cantidades')]
    public function test_new_creation_uses_policy_for_zero_one_five_and_six_files(int $cantidad, bool $valido): void
    {
        $archivos = array_map(fn () => $this->pdf(), range(1, max(1, $cantidad)));
        if ($cantidad === 0) {
            $archivos = [];
        }
        if ($valido) {
            $request = $this->validar($this->payload(['adjuntos' => $this->adjuntos($archivos)]));
            $this->assertCount($cantidad, $request->validated('adjuntos'));
        } else {
            $this->assertSame(['adjuntos'], array_keys($this->errores($this->payload(), $this->adjuntos($archivos))));
        }
    }

    public static function cantidades(): array
    {
        return [[0, true], [1, true], [5, true], [6, false]];
    }

    #[DataProvider('formatos')]
    public function test_new_creation_accepts_real_pdf_jpeg_and_png_ignoring_client_mime(string $formato): void
    {
        $contenido = match ($formato) {
            'pdf' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n",
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jhL8AAAAASUVORK5CYII=', true),
            'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCfAAv/2Q==', true),
        };
        $archivo = $this->archivo($contenido, 'fixture.'.$formato);
        $request = $this->validar($this->payload(), $this->adjuntos([$archivo]));
        $this->assertSame($archivo->getPathname(), $request->validated('adjuntos.0.archivo')->getPathname());
    }

    public static function formatos(): array
    {
        return [['pdf'], ['jpeg'], ['png']];
    }

    #[DataProvider('limites')]
    public function test_exact_physical_individual_and_total_byte_limits(string $alcance, int $exceso): void
    {
        $archivos = $alcance === 'individual'
            ? [$this->pdf(10 * 1024 * 1024 + $exceso)]
            : [$this->pdf(10 * 1024 * 1024), $this->pdf(10 * 1024 * 1024), $this->pdf(5 * 1024 * 1024 + $exceso)];
        if ($exceso === 0) {
            $request = $this->validar($this->payload(), $this->adjuntos($archivos));
            $this->assertCount(count($archivos), $request->validated('adjuntos'));
        } else {
            $campo = $alcance === 'individual' ? 'adjuntos.0.archivo' : 'adjuntos';
            $this->assertSame([$campo], array_keys($this->errores($this->payload(), $this->adjuntos($archivos))));
        }
    }

    public static function limites(): array
    {
        return [['individual', 0], ['individual', 1], ['total', 0], ['total', 1]];
    }

    public function test_invalid_real_content_and_non_upload_elements_are_identified_by_original_index(): void
    {
        $errores = $this->errores($this->payload(['adjuntos' => $this->adjuntos([
            0 => $this->pdf(),
            2 => $this->archivo('Texto que falsifica un PDF', 'falso.pdf'),
            4 => 'ruta_privada/informe.pdf',
            8 => $this->archivo('GIF89a', 'imagen.gif'),
        ])]));
        $this->assertSame(['adjuntos.2.archivo', 'adjuntos.4.archivo', 'adjuntos.8.archivo'], array_keys($errores));
    }

    public function test_policy_limits_are_reused_from_central_configuration(): void
    {
        config(['documentos_clinicos.max_archivos' => 1]);
        $this->assertSame(['adjuntos'], array_keys($this->errores($this->payload(), $this->adjuntos([$this->pdf(), $this->pdf()]))));
    }

    public function test_nested_multipart_preserves_sparse_indexes_file_pairs_and_trimmed_unicode_descriptions(): void
    {
        $primero = $this->pdf();
        $segundo = $this->pdf();
        $descripcion = str_repeat('á', 500);
        $request = $this->validar($this->payload(['adjuntos' => [
            2 => ['descripcion' => " \tAdjunto sintético 日本語.\n "],
            7 => ['descripcion' => '  '.$descripcion.'  '],
        ]]), [
            2 => ['archivo' => $primero],
            7 => ['archivo' => $segundo],
        ]);

        $this->assertSame([2, 7], array_keys($request->validated('adjuntos')));
        $this->assertSame($primero->getPathname(), $request->validated('adjuntos.2.archivo')->getPathname());
        $this->assertSame('Adjunto sintético 日本語.', $request->validated('adjuntos.2.descripcion'));
        $this->assertSame($segundo->getPathname(), $request->validated('adjuntos.7.archivo')->getPathname());
        $this->assertSame($descripcion, $request->validated('adjuntos.7.descripcion'));
        $this->assertSame($request->validated(), $request->safe()->all());
        $this->assertSame($this->uuid, $request->validated('clave_idempotencia'));
    }

    #[DataProvider('descripcionesInvalidas')]
    public function test_valid_file_requires_its_own_significant_string_description(mixed $descripcion, bool $presente): void
    {
        $adjunto = ['archivo' => $this->pdf()];
        if ($presente) {
            $adjunto['descripcion'] = $descripcion;
        }

        $errores = $this->errores($this->payload(['adjuntos' => [$adjunto]]));

        $this->assertSame(['adjuntos.0.descripcion'], array_keys($errores));
    }

    public static function descripcionesInvalidas(): array
    {
        return [
            'ausente' => [null, false],
            'NULL' => [null, true],
            'vacía' => ['', true],
            'espacios' => [" \t\r\n ", true],
            '501 caracteres' => [str_repeat('á', 501), true],
            'array' => [['texto'], true],
            'número' => [123, true],
            'booleano' => [true, true],
        ];
    }

    public function test_description_is_not_shifted_to_a_file_at_another_index(): void
    {
        $errores = $this->errores($this->payload(['adjuntos' => [
            2 => ['descripcion' => 'Descripción sin archivo.'],
            7 => ['archivo' => $this->pdf()],
        ]]));

        $this->assertArrayHasKey('adjuntos.2.archivo', $errores);
        $this->assertArrayHasKey('adjuntos.7.descripcion', $errores);
        $this->assertCount(2, $errores);
    }

    #[DataProvider('entradasSinArchivo')]
    public function test_malformed_attachment_entries_cannot_become_documents(mixed $entrada): void
    {
        $errores = $this->errores($this->payload(['adjuntos' => [3 => $entrada]]));

        $this->assertArrayHasKey('adjuntos.3.archivo', $errores);
    }

    public static function entradasSinArchivo(): array
    {
        return [
            'sólo descripción' => [['descripcion' => 'Texto sin archivo.']],
            'vacío' => [[]],
            'NULL' => [null],
            'texto plano' => ['ruta/archivo.pdf'],
        ];
    }

    public function test_flat_v1_upload_is_not_accepted_for_new_creation(): void
    {
        $errores = $this->errores($this->payload(), [$this->pdf()]);

        $this->assertArrayHasKey('adjuntos.0.archivo', $errores);
    }

    public function test_nested_derived_metadata_is_not_accepted(): void
    {
        foreach (['paciente_id', 'tipo', 'estado', 'fin_at', 'cargado_por_user_id', 'ruta_storage', 'mime_type', 'tamanio_bytes', 'otro'] as $campo) {
            $adjuntos = $this->adjuntos([$this->pdf()]);
            $adjuntos[0][$campo] = 'valor controlado por cliente';

            $this->assertArrayHasKey('adjuntos.0', $this->errores($this->payload(['adjuntos' => $adjuntos])));
        }
    }

    public function test_invalid_file_without_description_reports_binary_error_without_requiring_a_description(): void
    {
        $errores = $this->errores($this->payload(['adjuntos' => [
            ['archivo' => $this->archivo('PDF falso', 'falso.pdf')],
        ]]));

        $this->assertSame(['adjuntos.0.archivo'], array_keys($errores));
    }

    public function test_partial_upload_keeps_its_indexed_file_error(): void
    {
        $archivo = $this->pdf();
        $parcial = new UploadedFile($archivo->getPathname(), 'parcial.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);

        $this->assertSame(['adjuntos.0.archivo'], array_keys($this->errores($this->payload(), $this->adjuntos([$parcial]))));
    }

    public function test_identity_requires_patient_derived_from_actor_even_with_valid_uuid(): void
    {
        $this->actor = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->validar($this->payload());
    }

    private function turno(?Paciente $paciente = null, TipoTurnoEnum $tipo = TipoTurnoEnum::PRIMERA_CONSULTA): Turno
    {
        return Turno::factory()->for($paciente ?? $this->paciente)->for($this->kinesiologa)->create([
            'tipo' => $tipo, 'estado' => EstadoTurnoEnum::PENDIENTE, 'clave_idempotencia' => $this->uuid,
            'inicio_at' => '2026-10-05 10:00:00', 'fin_at' => '2026-10-05 10:30:00',
            'motivo_solicitud' => 'Motivo original privado',
        ]);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace([
            'clave_idempotencia' => $this->uuid, 'motivo_solicitud' => 'Dolor de espalda',
            'kinesiologa_id' => $this->kinesiologa->id, 'inicio_at' => '2026-10-05 10:00:00',
        ], $cambios);
    }

    private function adjuntos(array $archivos): array
    {
        $adjuntos = [];
        foreach ($archivos as $indice => $archivo) {
            $adjuntos[$indice] = ['archivo' => $archivo, 'descripcion' => 'Adjunto sintético '.$indice.'.'];
        }

        return $adjuntos;
    }

    private function validar(array $payload, array $archivos = []): ConfirmarPrimeraConsultaRequest
    {
        foreach ($archivos as $indice => $adjunto) {
            if (is_array($adjunto) && array_key_exists('descripcion', $adjunto)) {
                $payload['adjuntos'][$indice]['descripcion'] = $adjunto['descripcion'];
                unset($archivos[$indice]['descripcion']);
            }
        }

        $request = ConfirmarPrimeraConsultaRequest::create('/_tests/requests/confirmar', 'POST', $payload, [], $archivos === [] ? [] : ['adjuntos' => $archivos]);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $this->actor);
        $request->validateResolved();

        return $request;
    }

    private function errores(array $payload, array $archivos = []): array
    {
        try {
            $this->validar($payload, $archivos);
            $this->fail('Debe fallar la validación de entrada.');
        } catch (ValidationException $error) {
            $this->assertSame(422, $error->status);

            return $error->errors();
        }
    }

    private function pdf(?int $bytes = null): UploadedFile
    {
        return $this->archivo("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n", 'informe.pdf', $bytes);
    }

    private function archivo(string $contenido, string $nombre, ?int $bytes = null): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-request-');
        $this->temporales[] = $ruta;
        $handle = fopen($ruta, 'wb');
        try {
            fwrite($handle, $contenido);
            if ($bytes !== null) {
                ftruncate($handle, $bytes);
            }
        } finally {
            fclose($handle);
        }

        return new UploadedFile($ruta, $nombre, 'application/octet-stream', UPLOAD_ERR_OK, true);
    }
}
