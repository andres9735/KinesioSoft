<?php

namespace Tests\Feature\Models;

use App\Models\Calle;
use App\Models\Localidad;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeografiaRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_use_approved_tables_and_fillable_fields(): void
    {
        $this->assertSame('provincias', (new Provincia)->getTable());
        $this->assertSame('localidades', (new Localidad)->getTable());
        $this->assertSame('calles', (new Calle)->getTable());

        $this->assertSame(
            ['georef_id', 'nombre'],
            (new Provincia)->getFillable()
        );
        $this->assertSame(
            [
                'georef_id',
                'provincia_id',
                'nombre',
                'departamento_nombre',
            ],
            (new Localidad)->getFillable()
        );
        $this->assertSame(
            ['georef_id', 'localidad_id', 'nombre'],
            (new Calle)->getFillable()
        );

        $pacienteFillable = (new Paciente)->getFillable();

        $this->assertContains('direccion', $pacienteFillable);
        $this->assertContains('localidad_id', $pacienteFillable);
        $this->assertContains('calle', $pacienteFillable);
        $this->assertContains('altura_domicilio', $pacienteFillable);
        $this->assertContains('piso_departamento', $pacienteFillable);
        $this->assertContains('referencia_domicilio', $pacienteFillable);
        $this->assertNotContains('provincia_id', $pacienteFillable);
        $this->assertNotContains('calle_id', $pacienteFillable);
    }

    public function test_geographic_relations_are_bidirectional(): void
    {
        $provincia = Provincia::create([
            'georef_id' => '06',
            'nombre' => 'Buenos Aires',
        ]);
        $localidad = Localidad::create([
            'georef_id' => '06441030',
            'provincia_id' => $provincia->id,
            'nombre' => 'La Plata',
            'departamento_nombre' => 'La Plata',
        ]);
        $calle = Calle::create([
            'georef_id' => '064410300001234',
            'localidad_id' => $localidad->id,
            'nombre' => 'Calle 12',
        ]);
        $paciente = Paciente::create([
            'user_id' => User::factory()->create()->id,
            'direccion' => 'Dirección histórica',
            'localidad_id' => $localidad->id,
            'calle' => 'Calle 12',
            'altura_domicilio' => '1234 bis',
            'piso_departamento' => '2 B',
            'referencia_domicilio' => 'Frente a la plaza',
        ]);

        $this->assertTrue(
            $provincia->fresh()->localidades->contains($localidad)
        );
        $this->assertTrue($localidad->fresh()->provincia->is($provincia));
        $this->assertTrue($localidad->fresh()->calles->contains($calle));
        $this->assertTrue($calle->fresh()->localidad->is($localidad));
        $this->assertTrue(
            $localidad->fresh()->pacientes->contains($paciente)
        );
        $this->assertTrue($paciente->fresh()->localidad->is($localidad));
    }

    public function test_paciente_can_exist_without_localidad_while_incomplete(): void
    {
        $paciente = Paciente::create([
            'user_id' => User::factory()->create()->id,
            'localidad_id' => null,
        ])->fresh();

        $this->assertNull($paciente->localidad_id);
        $this->assertNull($paciente->localidad);
    }
}
