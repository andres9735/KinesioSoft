<?php

namespace Tests\Feature\Models;

use App\Models\DocumentoClinico;
use App\Models\DocumentoClinicoOrigenTurno;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentoClinicoOrigenTurnoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_primary_key_configuration_and_all_inverse_relations(): void
    {
        $origen = $this->origen()->fresh();

        $this->assertSame('documentos_clinicos_origen_turno', $origen->getTable());
        $this->assertSame('documento_clinico_id', $origen->getKeyName());
        $this->assertSame('int', $origen->getKeyType());
        $this->assertFalse($origen->getIncrementing());
        $this->assertFalse($origen->usesTimestamps());
        $this->assertSame(['documento_clinico_id', 'turno_id'], array_keys($origen->getAttributes()));
        $this->assertSame($origen->documento_clinico_id, $origen->getKey());
        $this->assertSame($origen->documento_clinico_id, $origen->documentoClinico->id);
        $this->assertSame($origen->turno_id, $origen->turno->id);
        $this->assertTrue($origen->documentoClinico->origenTurno->is($origen));
        $this->assertTrue($origen->turno->documentosClinicosOrigenTurno->contains($origen));
    }

    #[DataProvider('clavesProtegidas')]
    public function test_each_origin_key_is_immutable(string $campo): void
    {
        $origen = $this->origen()->fresh();
        $otro = $this->origen();
        $original = $origen->getAttributes();

        try {
            $origen->forceFill([$campo => $otro->{$campo}])->save();
            $this->fail('Se esperaba rechazar la modificación del origen-turno.');
        } catch (LogicException) {
            $this->assertSame($original, DocumentoClinicoOrigenTurno::findOrFail(
                $original['documento_clinico_id']
            )->getAttributes());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function clavesProtegidas(): array
    {
        return [
            'documento' => ['documento_clinico_id'],
            'turno' => ['turno_id'],
        ];
    }

    public function test_origin_cannot_be_deleted_and_the_original_association_remains(): void
    {
        $origen = $this->origen()->fresh();
        $original = $origen->getAttributes();

        try {
            $origen->delete();
            $this->fail('Se esperaba rechazar la eliminación del origen-turno.');
        } catch (LogicException) {
            $this->assertSame($original, $origen->fresh()->getAttributes());
        }
    }

    public function test_multiple_documents_can_belong_to_the_same_turno(): void
    {
        $primero = $this->origen();
        $documento = DocumentoClinico::factory()
            ->for($primero->documentoClinico->paciente)
            ->for($primero->documentoClinico->cargadoPor, 'cargadoPor')
            ->create();
        $segundo = $documento->origenTurno()->create(['turno_id' => $primero->turno_id]);

        $this->assertNotSame($primero->getKey(), $segundo->getKey());
        $this->assertCount(2, $primero->turno->fresh()->documentosClinicosOrigenTurno);
        $this->assertTrue($documento->fresh()->origenTurno->is($segundo));
    }

    private function origen(): DocumentoClinicoOrigenTurno
    {
        $user = User::factory()->create();
        $paciente = Paciente::create(['user_id' => $user->id, 'activo' => true]);
        $kinesiologa = Kinesiologa::create(['user_id' => User::factory()->create()->id]);
        $turno = Turno::factory()->for($paciente)->for($kinesiologa)->create();
        $documento = DocumentoClinico::factory()->for($paciente)->for($user, 'cargadoPor')->create();

        return $documento->origenTurno()->create(['turno_id' => $turno->id]);
    }
}
