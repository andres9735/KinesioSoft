<?php

namespace Tests\Feature\Actions\Catalogos;

use App\Actions\Catalogos\CambiarEstadoEjercicioDomiciliarioAction;
use App\Models\Auditoria;
use App\Models\CategoriaClinica;
use App\Models\EjercicioDomiciliario;
use App\Models\RecursoEjercicio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CambiarEstadoEjercicioDomiciliarioActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivates_and_audits_without_touching_categories_resources_or_files(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $ejercicio = $this->ejercicio(true, [$anatomica]);
        $recurso = $this->recursoLocal($ejercicio, false);
        $ruta = $recurso->ruta_storage;

        $actualizado = app(CambiarEstadoEjercicioDomiciliarioAction::class)
            ->ejecutar($ejercicio, false);

        $this->assertFalse($actualizado->activo);
        $this->assertSame([$anatomica->id], $actualizado->categorias()->pluck('categorias_clinicas.id')->all());
        $this->assertFalse($recurso->refresh()->activo);
        $this->assertSame($ruta, $recurso->ruta_storage);
        Storage::disk('public')->assertExists($ruta);

        $auditoria = Auditoria::query()->sole();
        $this->assertSame('ejercicio_domiciliario_inactivado', $auditoria->evento);
        $this->assertSame('Catálogos', $auditoria->modulo);
        $this->assertSame(['activo' => true], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => false], $auditoria->valores_nuevos);
    }

    public function test_state_targets_are_idempotent_before_dependency_validation(): void
    {
        $funcional = $this->categoria('FUNCIONAL', false);
        $inactivo = $this->ejercicio(false, [$funcional]);

        $sinCambio = app(CambiarEstadoEjercicioDomiciliarioAction::class)
            ->ejecutar($inactivo, false);

        $this->assertFalse($sinCambio->activo);
        $this->assertFalse($sinCambio->wasChanged());

        $equipamiento = $this->categoria('EQUIPAMIENTO');
        $activo = $this->ejercicio(true, [$equipamiento], 'EJ-002');

        $tambienSinCambio = app(CambiarEstadoEjercicioDomiciliarioAction::class)
            ->ejecutar($activo, true);

        $this->assertTrue($tambienSinCambio->activo);
        $this->assertFalse($tambienSinCambio->wasChanged());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivates_with_active_anatomical_and_tolerates_inactive_historical_categories(): void
    {
        Storage::fake('public');
        $anatomica = $this->categoria('ANATOMICA');
        $funcional = $this->categoria('FUNCIONAL', false);
        $terapeutica = $this->categoria('TERAPEUTICA', false);
        $ejercicio = $this->ejercicio(false, [$anatomica, $funcional, $terapeutica]);
        $recurso = $this->recursoLocal($ejercicio, false);
        $ruta = $recurso->ruta_storage;

        $actualizado = app(CambiarEstadoEjercicioDomiciliarioAction::class)
            ->ejecutar($ejercicio, true);

        $this->assertTrue($actualizado->activo);
        $this->assertFalse($recurso->refresh()->activo);
        $this->assertSame($ruta, $recurso->ruta_storage);
        Storage::disk('public')->assertExists($ruta);
        $auditoria = Auditoria::query()->sole();
        $this->assertSame('ejercicio_domiciliario_reactivado', $auditoria->evento);
        $this->assertSame(['activo' => false], $auditoria->valores_anteriores);
        $this->assertSame(['activo' => true], $auditoria->valores_nuevos);
    }

    public function test_reactivation_without_active_anatomical_category_is_blocked(): void
    {
        $funcional = $this->categoria('FUNCIONAL');
        $soloFuncional = $this->ejercicio(false, [$funcional]);
        $anatomicaInactiva = $this->categoria('ANATOMICA', false);
        $soloAnatomicaInactiva = $this->ejercicio(false, [$anatomicaInactiva], 'EJ-002');

        foreach ([$soloFuncional, $soloAnatomicaInactiva] as $ejercicio) {
            $this->assertReactivationRejected($ejercicio);
            $this->assertFalse($ejercicio->refresh()->activo);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_reactivation_with_equipment_is_blocked_even_with_active_anatomical(): void
    {
        $equipamiento = $this->categoria('EQUIPAMIENTO', false);
        $soloEquipamiento = $this->ejercicio(false, [$equipamiento]);
        $anatomica = $this->categoria('ANATOMICA');
        $conAnatomica = $this->ejercicio(false, [$anatomica, $equipamiento], 'EJ-002');

        foreach ([$soloEquipamiento, $conAnatomica] as $ejercicio) {
            $this->assertReactivationRejected($ejercicio);
            $this->assertFalse($ejercicio->refresh()->activo);
        }

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_rolls_back_inactivation_when_audit_fails(): void
    {
        $anatomica = $this->categoria('ANATOMICA');
        $this->assertRollback($this->ejercicio(true, [$anatomica]), false, true);
    }

    public function test_rolls_back_reactivation_when_audit_fails(): void
    {
        $anatomica = $this->categoria('ANATOMICA');
        $this->assertRollback($this->ejercicio(false, [$anatomica]), true, false);
    }

    private function assertRollback(
        EjercicioDomiciliario $ejercicio,
        bool $objetivo,
        bool $estadoEsperado
    ): void {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        DB::table('users')->where('id', $actor->id)->delete();

        try {
            app(CambiarEstadoEjercicioDomiciliarioAction::class)
                ->ejecutar($ejercicio, $objetivo);
            $this->fail('Se esperaba una QueryException al persistir la auditoría.');
        } catch (QueryException) {
            $this->assertSame($estadoEsperado, (bool) $ejercicio->refresh()->activo);
            $this->assertDatabaseCount('auditorias', 0);
        }
    }

    private function assertReactivationRejected(EjercicioDomiciliario $ejercicio): void
    {
        try {
            app(CambiarEstadoEjercicioDomiciliarioAction::class)
                ->ejecutar($ejercicio, true);
            $this->fail('Se esperaba el rechazo de la reactivación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categorias', $exception->errors());
        }
    }

    private function categoria(string $tipo, bool $activo = true): CategoriaClinica
    {
        static $secuencia = 0;
        $secuencia++;

        return CategoriaClinica::create([
            'codigo' => 'CAT-'.$secuencia,
            'nombre' => 'Categoría '.$secuencia,
            'tipo' => $tipo,
            'activo' => $activo,
        ]);
    }

    /**
     * @param  list<CategoriaClinica>  $categorias
     */
    private function ejercicio(
        bool $activo,
        array $categorias,
        string $codigo = 'EJ-001'
    ): EjercicioDomiciliario {
        $ejercicio = EjercicioDomiciliario::create([
            'codigo' => $codigo,
            'nombre' => 'Ejercicio',
            'descripcion' => 'Descripción',
            'dificultad_base' => 'BAJA',
            'activo' => $activo,
        ]);
        $ejercicio->categorias()->sync(array_map(
            fn (CategoriaClinica $categoria): int => $categoria->id,
            $categorias
        ));

        return $ejercicio;
    }

    private function recursoLocal(
        EjercicioDomiciliario $ejercicio,
        bool $activo
    ): RecursoEjercicio {
        $ruta = UploadedFile::fake()->create('recurso.jpg', 10, 'image/jpeg')
            ->store('ejercicios/recursos', 'public');

        return $ejercicio->recursos()->create([
            'tipo' => 'IMAGEN',
            'origen' => 'LOCAL',
            'ruta_storage' => $ruta,
            'url_externa' => null,
            'titulo' => 'Recurso',
            'orden' => 1,
            'activo' => $activo,
        ]);
    }
}
