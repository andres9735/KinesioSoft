<?php

namespace Tests\Feature\Models;

use App\Models\DocumentoClinico;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentoClinicoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_canonical_values_casts_and_explicit_relations(): void
    {
        $documento = $this->documento()->fresh();

        $this->assertSame('documentos_clinicos', $documento->getTable());
        $this->assertSame('SOLICITUD_TURNO', DocumentoClinico::ORIGEN_SOLICITUD_TURNO);
        $this->assertSame('PENDIENTE', DocumentoClinico::ESTADO_VALIDACION_PENDIENTE);
        $this->assertSame('SIN_CLASIFICAR', DocumentoClinico::TIPO_SIN_CLASIFICAR);
        $this->assertSame(DocumentoClinico::ORIGEN_SOLICITUD_TURNO, $documento->origen);
        $this->assertSame(DocumentoClinico::ESTADO_VALIDACION_PENDIENTE, $documento->estado_validacion);
        $this->assertSame(DocumentoClinico::TIPO_SIN_CLASIFICAR, $documento->tipo_documento_clinico);
        $this->assertSame(1024, $documento->tamanio_bytes);
        $this->assertInstanceOf(CarbonImmutable::class, $documento->created_at);
        $this->assertInstanceOf(CarbonImmutable::class, $documento->updated_at);
        $this->assertSame($documento->paciente_id, $documento->paciente->id);
        $this->assertSame($documento->cargado_por_user_id, $documento->cargadoPor->id);
        $this->assertTrue($documento->paciente->documentosClinicos->contains($documento));
        $this->assertTrue($documento->cargadoPor->documentosClinicosCargados->contains($documento));
        $this->assertNull($documento->origenTurno);
    }

    public function test_document_can_navigate_to_its_explicit_turno_origin(): void
    {
        $documento = $this->documento();
        $kinesiologa = Kinesiologa::create(['user_id' => User::factory()->create()->id]);
        $turno = Turno::factory()->for($documento->paciente)->for($kinesiologa)->create();
        $origen = $documento->origenTurno()->create(['turno_id' => $turno->id]);

        $this->assertTrue($documento->fresh()->origenTurno->is($origen));
        $this->assertTrue($documento->fresh()->origenTurno->turno->is($turno));
    }

    #[DataProvider('cambiosDelOriginal')]
    public function test_protected_metadata_cannot_be_overwritten_even_with_force_fill(string $campo, mixed $valor): void
    {
        $documento = $this->documento()->fresh();
        $original = $documento->getAttributes();
        if ($campo === 'paciente_id') {
            $valor = Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true])->id;
        } elseif ($campo === 'cargado_por_user_id') {
            $valor = User::factory()->create()->id;
        }

        try {
            $documento->forceFill([$campo => $valor])->save();
            $this->fail('Se esperaba rechazar la modificación del original.');
        } catch (LogicException) {
            $this->assertSame($original, $documento->fresh()->getAttributes());
        }
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function cambiosDelOriginal(): array
    {
        return [
            'paciente' => ['paciente_id', null],
            'usuario cargador' => ['cargado_por_user_id', null],
            'origen' => ['origen', 'OTRO_ORIGEN'],
            'nombre' => ['nombre_original', 'Otro.pdf'],
            'ruta' => ['ruta_storage', 'fixtures/reemplazo.pdf'],
            'MIME' => ['mime_type', 'image/png'],
            'tamaño' => ['tamanio_bytes', 2048],
        ];
    }

    public function test_document_cannot_be_deleted_and_its_original_remains_persisted(): void
    {
        $documento = $this->documento()->fresh();
        $original = $documento->getAttributes();

        try {
            $documento->delete();
            $this->fail('Se esperaba rechazar la eliminación del documento.');
        } catch (LogicException) {
            $this->assertSame($original, $documento->fresh()->getAttributes());
        }
    }

    #[DataProvider('cambiosEvolutivos')]
    public function test_validation_and_classification_can_evolve_without_changing_the_original(string $campo, string $valor): void
    {
        $documento = $this->documento()->fresh();
        $expected = $documento->getAttributes();
        $expected[$campo] = $valor;
        unset($expected['updated_at']);

        $documento->update([$campo => $valor]);
        $actual = $documento->fresh()->getAttributes();
        unset($actual['updated_at']);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cambiosEvolutivos(): array
    {
        // Fixtures de evolución futura; no definen un catálogo de valores autorizados.
        return [
            'validación' => ['estado_validacion', 'VALIDADO'],
            'clasificación' => ['tipo_documento_clinico', 'ESTUDIO'],
        ];
    }

    public function test_factory_provides_distinct_metadata_paths_for_multiple_documents(): void
    {
        $primero = $this->documento();
        $segundo = DocumentoClinico::factory()->for($primero->paciente)->for($primero->cargadoPor, 'cargadoPor')->create();

        $this->assertNotSame($primero->ruta_storage, $segundo->ruta_storage);
        $this->assertCount(2, $primero->paciente->fresh()->documentosClinicos);
        $this->assertCount(2, $primero->cargadoPor->fresh()->documentosClinicosCargados);
    }

    private function documento(): DocumentoClinico
    {
        $user = User::factory()->create();
        $paciente = Paciente::create(['user_id' => $user->id, 'activo' => true]);

        return DocumentoClinico::factory()->for($paciente)->for($user, 'cargadoPor')->create();
    }
}
