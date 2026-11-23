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

    #[DataProvider('coleccionesConFilasVacias')]
    public function test_los_indices_de_diagnostico_son_optativos_y_no_cambian_uc14_a(string $coleccion, string $atributo): void
    {
        $datos = $this->datos();
        if ($coleccion === 'familia.integrantes') {
            $datos['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [
                ['nombre_apellido' => '  ', 'vinculo' => ''], ['nombre_apellido' => 'Integrante', 'vinculo' => ''],
            ]];
        } else {
            $datos['bloques']['alergias'] = ['resultado' => 'INFORMADO', 'items' => [
                ['descripcion' => ' '], ['descripcion' => 'Antecedente', 'procedencia' => '', 'estado_codificacion' => 'PENDIENTE_CODIFICACION'],
            ]];
        }
        $antes = $datos;
        $validador = app(ValidarDatosAperturaHistoriaClinica::class);
        try {
            $validador->validar($datos);
            $this->fail('UC-14 A debe seguir rechazando la fila inválida.');
        } catch (ValidationException $e) {
            $this->assertSame([$coleccion.'.0.'.$atributo], array_keys($e->errors()));
            $mensajes = $e->errors()[$coleccion.'.0.'.$atributo];
        }
        $indices = [];
        try {
            $validador->validarResultadoProfesional($datos, $indices);
            $this->fail('UC-14 C debe rechazar exactamente el mismo dato.');
        } catch (ValidationException $e) {
            $this->assertSame([$coleccion.'.1.'.$atributo => $mensajes], $e->errors());
        }
        $this->assertSame([1], $indices[$coleccion]);
        $this->assertSame($antes, $datos);
        if ($coleccion === 'familia.integrantes') {
            $datos['familia']['integrantes'][1]['vinculo'] = ' Hermana ';
        } else {
            $datos['bloques']['alergias']['items'][1]['procedencia'] = 'REFERIDO_PACIENTE';
        }
        $normalizado = $validador->validarResultadoProfesional($datos);
        $this->assertSame($normalizado, $validador->validarResultadoProfesional($datos, $indices));
        $this->assertArrayNotHasKey('indices', $normalizado);
    }

    public static function coleccionesConFilasVacias(): array
    {
        return [['bloques.alergias.items', 'procedencia'], ['familia.integrantes', 'vinculo']];
    }

    public function test_el_diagnostico_de_bloque_conserva_el_resultado_y_los_errores_del_consumidor_anterior(): void
    {
        $validador = app(ValidarDatosAperturaHistoriaClinica::class);
        $bloque = ['resultado' => 'INFORMADO', 'items' => [[], [
            'descripcion' => ' Dato ', 'procedencia' => '', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]]];
        try {
            $validador->validarBloque('alergias', $bloque);
            $this->fail('Se esperaba el error de procedencia.');
        } catch (ValidationException $e) {
            $this->assertSame(['bloques.alergias.items.0.procedencia'], array_keys($e->errors()));
            $mensajes = array_values($e->errors());
        }
        $indices = [];
        try {
            $validador->validarBloque('alergias', $bloque, $indices);
            $this->fail('Se esperaba el mismo error con su índice de entrada.');
        } catch (ValidationException $e) {
            $this->assertSame(['bloques.alergias.items.1.procedencia'], array_keys($e->errors()));
            $this->assertSame($mensajes, array_values($e->errors()));
        }
        $bloque['items'][1]['procedencia'] = 'REFERIDO_PACIENTE';
        $this->assertSame($validador->validarBloque('alergias', $bloque), $validador->validarBloque('alergias', $bloque, $indices));
        $this->assertSame([1], $indices['bloques.alergias.items']);
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
