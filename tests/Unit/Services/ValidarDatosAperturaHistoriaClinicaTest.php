<?php

namespace Tests\Unit\Services;

use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidarDatosAperturaHistoriaClinicaTest extends TestCase
{
    #[DataProvider('combinacionesInvalidas')]
    public function test_resultados_y_codificacion_son_independientes_y_coherentes(string $resultado, array $items): void
    {
        $datos = $this->datos();
        $datos['bloques']['personales'] = ['resultado' => $resultado, 'items' => $items];
        $this->expectException(ValidationException::class);
        app(ValidarDatosAperturaHistoriaClinica::class)->validar($datos);
    }

    public static function combinacionesInvalidas(): array
    {
        $item = ['descripcion' => 'Antecedente', 'procedencia' => 'REFERIDO_PACIENTE', 'estado_codificacion' => 'PENDIENTE_CODIFICACION'];

        return [
            ['INFORMADO', []], ['NO_PRESENTA', [$item]], ['DESCONOCIDO', [$item]], ['NO_RELEVADO', []],
            ['INFORMADO', [[...$item, 'estado_codificacion' => 'CODIFICADO']]],
            ['INFORMADO', [[...$item, 'estado_codificacion' => 'SOLO_DESCRIPTIVO']]],
            ['INFORMADO', [[...$item, 'procedencia' => '']]],
            ['INFORMADO', [[...$item, 'descripcion' => '   ']]],
        ];
    }

    public function test_el_minimo_admite_desconocido_sin_datos_complementarios_y_familia_no_disponible(): void
    {
        $datos = $this->datos();
        $datos['familia'] = ['resultado' => 'INFORMACION_NO_DISPONIBLE'];
        $datos['bloques']['personales']['resultado'] = 'DESCONOCIDO';
        $validados = app(ValidarDatosAperturaHistoriaClinica::class)->validar($datos);
        $this->assertSame('DESCONOCIDO', $validados['bloques']['personales']['resultado']);
        $this->assertSame([], $validados['familia']['integrantes']);
        $this->assertArrayNotHasKey('peso_kg', $validados);
    }

    public function test_el_contacto_confirmado_requiere_vinculo(): void
    {
        $datos = $this->datos();
        $datos['familia'] = ['resultado' => 'INFORMADO', 'incluir_contacto' => true];
        $this->expectException(ValidationException::class);
        app(ValidarDatosAperturaHistoriaClinica::class)->validar($datos);
    }

    private function datos(): array
    {
        $bloques = [];
        foreach (ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $nombre) {
            $bloques[$tipo] = ['resultado' => 'NO_PRESENTA', 'items' => []];
        }

        return [
            'especialidad_id' => 1, 'procedencia_relevamiento' => 'REFERIDO_PACIENTE', 'confirmar' => '1',
            'bloques' => $bloques, 'familia' => ['resultado' => 'SIN_INTEGRANTES'],
        ];
    }
}
