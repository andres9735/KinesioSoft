<?php

namespace Tests\Unit\Services;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision as Fuente;
use App\Enums\BloqueRevisionDeclaracion;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\IntegranteNucleoFamiliarHc;
use App\Models\ItemDatosClinicosBase;
use App\Models\ItemDeclaracionSalud;
use App\Models\VersionDatosClinicosBase;
use App\Services\HistoriaClinica\PrepararResultadoRevisionDeclaracion;
use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica as Minimo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepararResultadoRevisionDeclaracionTest extends TestCase
{
    public function test_transforma_los_cinco_tipos_con_procedencia_y_codificacion_diferida_sin_sql_ni_confirmacion(): void
    {
        $fuente = $this->fuente();
        $consultas = [];
        DB::listen(function ($query) use (&$consultas): void {
            $consultas[] = $query->sql;
        });
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $this->entrada($fuente));
        $this->assertSame([], $consultas);
        $this->assertSame(10, $resultado->declaracionId);
        $this->assertSame(20, $resultado->pacienteId);
        $this->assertNull($resultado->versionBaseId);
        foreach (Minimo::TIPOS as $tipo => $nombre) {
            $bloque = $resultado->datosClinicos['bloques'][$tipo];
            $this->assertSame('INFORMADO', $bloque['resultado']);
            $this->assertSame('PENDIENTE_CODIFICACION', $bloque['items'][0]['estado_codificacion']);
            $this->assertSame('REFERIDO_PACIENTE', $bloque['items'][0]['procedencia']);
        }
        $this->assertSame('170.25', $resultado->datosClinicos['altura_cm']);
        $this->assertArrayNotHasKey('confirmar', $resultado->datosClinicos);
        $this->assertCount(10, $resultado->dictamenes);
        $this->assertSame($fuente->huella, $resultado->huellaDeclaracion);
        $this->assertSame('Antecedente personal declarado', $resultado->dictamenes['antecedentes']['datos_originales']['items'][0]['descripcion']);
        $this->assertSame(100, $resultado->linaje['admitidos']['antecedentes'][0]['item_origen_id']);
    }

    public function test_no_infiere_ausencia_de_listas_vacias_pero_admite_relevamiento_profesional_explicito(): void
    {
        $fuente = $this->fuente(false);
        $entrada = $this->entrada($fuente);
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('Las listas vacías no completan los relevamientos.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('bloques.personales.resultado', $error->errors());
            $this->assertArrayHasKey('bloques.familiares.resultado', $error->errors());
        }
        foreach (Minimo::TIPOS as $tipo => $nombre) {
            $entrada['complemento_profesional']['bloques'][$tipo] = ['resultado' => 'DESCONOCIDO', 'items' => []];
        }
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
        $this->assertSame('DESCONOCIDO', $resultado->datosClinicos['bloques']['alergias']['resultado']);
        $this->assertSame([], $resultado->dictamenes['alergias']['datos_originales']['items']);
    }

    public function test_no_clasifica_automaticamente_un_antecedente_como_personal_o_familiar(): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        unset($entrada['dictamenes']['antecedentes']['clasificacion']);
        $this->expectException(ValidationException::class);
        app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
    }

    public function test_validar_negativas_conserva_original_y_procedencia_sin_resolver_el_minimo_profesional(): void
    {
        $declaracion = $this->declaracion(false);
        foreach (array_keys(Fuente::LISTAS) as $bloque) {
            $declaracion->forceFill([$bloque.'_respuesta' => 'no']);
        }
        $declaracion->syncOriginal();
        $fuente = Fuente::desde($declaracion);
        $entrada = $this->entrada($fuente);
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('La negativa declarada no reemplaza el relevamiento profesional.');
        } catch (ValidationException $error) {
            foreach (array_keys(Minimo::TIPOS) as $tipo) {
                $this->assertArrayHasKey('bloques.'.$tipo.'.resultado', $error->errors());
            }
        }
        foreach (array_keys(Minimo::TIPOS) as $tipo) {
            $entrada['complemento_profesional']['bloques'][$tipo] = ['resultado' => 'DESCONOCIDO', 'items' => []];
        }
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
        foreach (array_keys(Fuente::LISTAS) as $bloque) {
            $dictamen = $resultado->dictamenes[$bloque];
            $this->assertSame(['items' => [], 'respuesta' => 'no'], $dictamen['datos_originales']);
            $this->assertSame('VALIDAR', $dictamen['dictamen']);
            $this->assertSame([], $dictamen['datos_admitidos']);
            $this->assertSame('DECLARACION_PACIENTE', $dictamen['procedencias']['origen']);
            $this->assertSame(10, $dictamen['procedencias']['declaracion_salud_id']);
        }
        foreach ($resultado->datosClinicos['bloques'] as $bloque) {
            $this->assertSame('DESCONOCIDO', $bloque['resultado']);
            $this->assertSame([], $bloque['items']);
        }
        $entradaConBase = $this->entrada($fuente);
        unset($entradaConBase['complemento_profesional']['familia']);
        $conBase = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entradaConBase, $this->base());
        $this->assertSame('INFORMADO', $conBase->datosClinicos['bloques']['alergias']['resultado']);
        $this->assertSame('Alergia profesional previa', $conBase->datosClinicos['bloques']['alergias']['items'][0]['descripcion']);
    }

    public function test_las_respuestas_explicitas_participan_en_la_huella_sin_inferir_las_historicas(): void
    {
        $declaracion = $this->declaracion(false);
        $historica = Fuente::desde($declaracion);
        foreach (array_keys(Fuente::LISTAS) as $bloque) {
            $this->assertArrayNotHasKey('respuesta', $historica->bloques[$bloque]);
            $copia = clone $declaracion;
            $copia->forceFill([$bloque.'_respuesta' => 'no'])->syncOriginal();
            $negativa = Fuente::desde($copia);
            $this->assertSame('no', $negativa->bloques[$bloque]['respuesta']);
            $this->assertNotSame($historica->huella, $negativa->huella);
            $copia->forceFill(['estado' => 'REVISADA'])->syncOriginal();
            $this->assertSame($negativa->huella, Fuente::desdeRevisada($copia)->huella);
        }
        $conItems = $this->declaracion();
        $itemsHistoricos = Fuente::desde($conItems);
        foreach (array_keys(Fuente::LISTAS) as $bloque) {
            $this->assertArrayNotHasKey('respuesta', $itemsHistoricos->bloques[$bloque]);
            $conItems->forceFill([$bloque.'_respuesta' => 'si']);
        }
        $conItems->syncOriginal();
        $afirmativa = Fuente::desde($conItems);
        foreach (array_keys(Fuente::LISTAS) as $bloque) {
            $this->assertSame('si', $afirmativa->bloques[$bloque]['respuesta']);
            $this->assertSame($itemsHistoricos->bloques[$bloque]['items'], $afirmativa->bloques[$bloque]['items']);
        }
        $entrada = $this->entrada($afirmativa);
        unset($entrada['dictamenes']['antecedentes']['clasificacion']);
        $this->expectException(ValidationException::class);
        app(PrepararResultadoRevisionDeclaracion::class)->preparar($afirmativa, $entrada);
    }

    public function test_corrige_valores_y_descripciones_sin_sobrescribir_originales_y_conserva_procedencia(): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.75']];
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [[
            'item_origen_id' => 103, 'tipo_destino' => 'alergias', 'descripcion' => 'Descripción precisada en entrevista', 'procedencia' => 'REFERIDO_PACIENTE',
        ]]]];
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
        $this->assertSame('171.75', $resultado->datosClinicos['altura_cm']);
        $this->assertSame('170.25', $fuente->bloques['altura_cm']['valor']);
        $this->assertSame(['valor' => '170.25'], $resultado->dictamenes['altura_cm']['datos_originales']);
        $this->assertSame(['valor' => '171.75'], $resultado->dictamenes['altura_cm']['correccion_profesional']);
        $this->assertSame('CORRECCION_PROFESIONAL', $resultado->dictamenes['alergias']['procedencias']['origen']);
        $this->assertSame('REFERIDO_PACIENTE', $resultado->datosClinicos['bloques']['alergias']['items'][0]['procedencia']);
        $this->assertSame('Alergia declarada', $fuente->bloques['alergias']['items'][0]['descripcion']);
    }

    #[DataProvider('dictamenesConNota')]
    public function test_observar_o_descartar_requiere_nota_y_no_incorpora_el_dato(string $dictamen, string $bloque): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        $entrada['dictamenes'][$bloque] = ['dictamen' => $dictamen];
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('Se esperaba la justificación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('dictamenes.'.$bloque.'.justificacion', $e->errors());
        }
        $entrada['dictamenes'][$bloque]['justificacion'] = 'Requiere aclaración durante la entrevista';
        if ($bloque === 'alergias') {
            $entrada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'DESCONOCIDO', 'items' => []];
        }
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
        $this->assertSame([], $resultado->dictamenes[$bloque]['datos_admitidos']);
        $this->assertSame($dictamen, $resultado->dictamenes[$bloque]['dictamen']);
        $this->assertSame('Requiere aclaración durante la entrevista', $resultado->dictamenes[$bloque]['observacion_profesional']);
        if ($bloque === 'altura_cm') {
            $this->assertNull($resultado->datosClinicos['altura_cm']);
        }
    }

    public static function dictamenesConNota(): array
    {
        return [['OBSERVAR', 'alergias'], ['DESCARTAR', 'alergias'], ['OBSERVAR', 'altura_cm'], ['DESCARTAR', 'altura_cm']];
    }

    public function test_un_dictamen_observado_no_resuelve_por_si_mismo_el_minimo_obligatorio(): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        $entrada['dictamenes']['antecedentes'] = ['dictamen' => 'OBSERVAR', 'justificacion' => 'Falta precisar ambos antecedentes'];
        $this->expectException(ValidationException::class);
        app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
    }

    public function test_conserva_datos_clinicos_previos_y_su_linaje_aunque_la_declaracion_se_descarte(): void
    {
        $fuente = $this->fuente();
        $base = $this->base();
        $entrada = $this->entrada($fuente);
        unset($entrada['complemento_profesional']['familia']);
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'DESCARTAR', 'justificacion' => 'No se admite este dato declarado'];
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
        $this->assertSame('Alergia profesional previa', $resultado->datosClinicos['bloques']['alergias']['items'][0]['descripcion']);
        $this->assertSame(30, $resultado->versionBaseId);
        $this->assertSame(900, $resultado->linaje['items_base'][0]['id']);
        $this->assertSame('RELEVAMIENTO_PROFESIONAL', $resultado->datosClinicos['bloques']['alergias']['items'][0]['procedencia']);
        $this->assertSame('Contacto de emergencia familiar', $resultado->datosClinicos['familia']['integrantes'][0]['nombre_apellido']);
        $this->assertTrue($resultado->linaje['integrantes_base'][0]['contacto_emergencia_confirmado']);
        $this->assertSame('Alergia profesional previa', $base->items->first()->descripcion);
    }

    public function test_exige_sustitucion_explicita_de_un_valor_previo_y_conserva_la_version_original(): void
    {
        $fuente = $this->fuente();
        $base = $this->base();
        $base->altura_cm = '180.50';
        $entrada = $this->entrada($fuente);
        unset($entrada['complemento_profesional']['familia']);
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
            $this->fail('Se esperaba consentimiento explícito de la sustitución.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sustituciones_base.altura_cm', $e->errors());
        }
        $entrada['sustituciones_base'] = ['altura_cm'];
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
        $this->assertSame('170.25', $resultado->datosClinicos['altura_cm']);
        $this->assertSame('180.50', $base->altura_cm);
        $this->assertSame(['altura_cm'], $resultado->linaje['sustituciones_explicitas']);
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_inconsistencias_del_contrato(string $caso): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        switch ($caso) {
            case 'sin dictamen': unset($entrada['dictamenes']['peso_kg']);
                break;
            case 'sin familia': unset($entrada['complemento_profesional']['familia']);
                break;
            case 'familia no relevada': $entrada['complemento_profesional']['familia']['resultado'] = 'NO_RELEVADO';
                break;
            case 'corregir sin correccion': $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR'];
                break;
            case 'validar con correccion': $entrada['dictamenes']['altura_cm']['correccion'] = ['valor' => 170];
                break;
            case 'altura imprecisa': $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '170.255']];
                break;
            case 'item ajeno': $entrada['dictamenes']['alergias'] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [['item_origen_id' => 999, 'tipo_destino' => 'alergias', 'descripcion' => 'Dato', 'procedencia' => 'REFERIDO_PACIENTE']]]];
                break;
            case 'clasificacion ajena': $entrada['dictamenes']['antecedentes']['clasificacion'][999] = 'familiares';
                break;
            case 'sustitucion sin base': $entrada['sustituciones_base'] = ['altura_cm'];
                break;
            case 'codificacion no aprobada': $entrada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'INFORMADO', 'items' => [['descripcion' => 'Dato', 'procedencia' => 'REFERIDO_PACIENTE', 'estado_codificacion' => 'SOLO_DESCRIPTIVO']]];
                break;
        }
        $this->expectException(ValidationException::class);
        app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
    }

    public static function entradasInvalidas(): array
    {
        return array_map(fn ($caso) => [$caso], ['sin dictamen', 'sin familia', 'familia no relevada', 'corregir sin correccion', 'validar con correccion',
            'altura imprecisa', 'item ajeno', 'clasificacion ajena', 'sustitucion sin base', 'codificacion no aprobada']);
    }

    public function test_la_huella_es_estable_frente_al_orden_de_dictamenes_y_cambia_con_la_correccion(): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        $servicio = app(PrepararResultadoRevisionDeclaracion::class);
        $a = $servicio->preparar($fuente, $entrada);
        $entrada['dictamenes'] = array_reverse($entrada['dictamenes'], true);
        $this->assertSame($a->huellaResultado, $servicio->preparar($fuente, $entrada)->huellaResultado);
        $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.25']];
        $this->assertNotSame($a->huellaResultado, $servicio->preparar($fuente, $entrada)->huellaResultado);
    }

    public function test_la_fuente_no_omite_tipos_desconocidos_ni_acepta_declaracion_revisada(): void
    {
        $declaracion = $this->declaracion();
        $declaracion->items->first()->tipo = 'DESCONOCIDO';
        $this->expectException(LogicException::class);
        Fuente::desde($declaracion);
    }

    public function test_rechaza_version_base_de_otro_paciente(): void
    {
        $fuente = $this->fuente();
        $base = $this->base();
        $base->historiaClinica->paciente_id = 999;
        $this->expectException(ValidationException::class);
        app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $this->entrada($fuente), $base);
    }

    public function test_rechaza_declaracion_ya_revisada(): void
    {
        $declaracion = $this->declaracion();
        $declaracion->estado = 'REVISADA';
        $this->expectException(LogicException::class);
        Fuente::desde($declaracion);
    }

    public function test_sustituir_un_bloque_o_familia_requiere_declarar_la_intencion_y_conserva_la_base(): void
    {
        $fuente = $this->fuente();
        $base = $this->base();
        $entrada = $this->entrada($fuente);
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
            $this->fail('El núcleo familiar previo no debe sustituirse silenciosamente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sustituciones_base.familia', $e->errors());
        }
        $entrada['sustituciones_base'] = ['familia', 'alergias'];
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
        $this->assertSame('Alergia declarada', $resultado->datosClinicos['bloques']['alergias']['items'][0]['descripcion']);
        $this->assertSame('INFORMACION_NO_DISPONIBLE', $resultado->datosClinicos['familia']['resultado']);
        $this->assertSame('Alergia profesional previa', $resultado->linaje['items_base'][0]['descripcion']);
        $this->assertSame('Contacto de emergencia familiar', $resultado->linaje['integrantes_base'][0]['nombre_apellido']);
        $this->assertSame('Alergia profesional previa', $base->items->first()->descripcion);
    }

    #[DataProvider('listasCorregidas')]
    public function test_cada_lista_contextualiza_todos_los_atributos_de_su_misma_fila_cero(string $bloque): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        $entrada['dictamenes'][$bloque] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [[
            'item_origen_id' => 999, 'tipo_destino' => 'invalido', 'descripcion' => '', 'procedencia' => '',
        ]]]];
        $antes = $entrada;
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('Se esperaban los errores de la corrección.');
        } catch (ValidationException $e) {
            $esperadas = array_map(fn ($campo) => 'dictamenes.'.$bloque.'.correccion.items.0.'.$campo,
                ['item_origen_id', 'tipo_destino', 'descripcion', 'procedencia']);
            $this->assertSame($esperadas, array_keys($e->errors()));
        }
        $this->assertSame($antes, $entrada);
    }

    public static function listasCorregidas(): array
    {
        return [['antecedentes'], ['cirugias'], ['alergias'], ['medicacion']];
    }

    public function test_localiza_la_clasificacion_por_id_original_y_no_por_posicion(): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        unset($entrada['dictamenes']['antecedentes']['clasificacion'][101]);
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('Se esperaba la clasificación del ítem 101.');
        } catch (ValidationException $e) {
            $this->assertSame(['dictamenes.antecedentes.clasificacion.101'], array_keys($e->errors()));
        }
    }

    public function test_conserva_la_fila_de_aporte_y_de_familia_despues_de_normalizar(): void
    {
        $fuente = $this->fuente();
        $entrada = $this->entrada($fuente);
        $entrada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'INFORMADO', 'items' => [[], [
            'descripcion' => 'Aporte nuevo', 'procedencia' => '', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]]];
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('Se esperaba la fila 1 del aporte.');
        } catch (ValidationException $e) {
            $this->assertSame(['bloques.alergias.items.1.procedencia'], array_keys($e->errors()));
        }
        $entrada['complemento_profesional']['bloques']['alergias']['items'][1]['procedencia'] = 'REFERIDO_PACIENTE';
        $entrada['complemento_profesional']['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [[], ['nombre_apellido' => 'Integrante', 'vinculo' => '']]];
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
            $this->fail('Se esperaba la fila 1 del núcleo familiar.');
        } catch (ValidationException $e) {
            $this->assertSame(['familia.integrantes.1.vinculo'], array_keys($e->errors()));
        }
        $entrada['complemento_profesional']['familia']['integrantes'][1]['vinculo'] = ' Hermana ';
        $resultado = app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada);
        $this->assertSame('Hermana', $resultado->datosClinicos['familia']['integrantes'][0]['vinculo']);
        $this->assertSame('Alergia declarada', $resultado->datosClinicos['bloques']['alergias']['items'][0]['descripcion']);
        $this->assertSame('Aporte nuevo', $resultado->datosClinicos['bloques']['alergias']['items'][1]['descripcion']);
        $this->assertSame([[], ['nombre_apellido' => 'Integrante', 'vinculo' => ' Hermana ']], $resultado->linaje['complemento_profesional']['familia']['integrantes']);
        $this->assertArrayNotHasKey('indices', $resultado->datosClinicos);
        $this->assertArrayNotHasKey('origenes', $resultado->linaje);
        $this->assertSame(Fuente::huellaDe(['declaracion' => $fuente->huella, 'datos' => $resultado->datosClinicos,
            'dictamenes' => $resultado->dictamenes, 'linaje' => $resultado->linaje]), $resultado->huellaResultado);
    }

    public function test_no_atribuye_errores_historicos_a_aportes_con_el_mismo_indice(): void
    {
        $fuente = $this->fuente();
        $base = $this->base();
        $base->items->first()->procedencia = 'INVALIDA';
        $entrada = $this->entrada($fuente);
        unset($entrada['complemento_profesional']['familia']);
        $entrada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'INFORMADO', 'items' => [[
            'descripcion' => 'Aporte válido', 'procedencia' => 'REFERIDO_PACIENTE', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]]];
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
            $this->fail('Se esperaba el error histórico general.');
        } catch (ValidationException $e) {
            $this->assertSame(['version_base'], array_keys($e->errors()));
        }
        $this->assertSame('INVALIDA', $base->items->first()->procedencia);
    }

    public function test_el_limite_del_conjunto_combinado_no_se_asigna_a_una_fila_del_formulario(): void
    {
        $fuente = $this->fuente();
        $base = $this->base();
        $base->setRelation('items', new Collection(array_map(function ($i) use ($base) {
            $item = clone $base->items->first();
            $item->descripcion = 'Previo '.$i;

            return $item;
        }, range(1, 30))));
        $entrada = $this->entrada($fuente);
        unset($entrada['complemento_profesional']['familia']);
        try {
            app(PrepararResultadoRevisionDeclaracion::class)->preparar($fuente, $entrada, $base);
            $this->fail('El conjunto de 31 antecedentes debe seguir rechazándose.');
        } catch (ValidationException $e) {
            $this->assertSame(['propuesta'], array_keys($e->errors()));
        }
        $this->assertCount(30, $base->items);
    }

    private function fuente(bool $conItems = true): Fuente
    {
        return Fuente::desde($this->declaracion($conItems));
    }

    private function declaracion(bool $conItems = true): DeclaracionSaludPaciente
    {
        $declaracion = new DeclaracionSaludPaciente;
        $declaracion->forceFill(['id' => 10, 'paciente_id' => 20, 'estado' => 'PENDIENTE_REVISION', 'objetivo_salud' => 'Objetivo declarado',
            'peso_kg' => '70.25', 'altura_cm' => '170.25', 'ocupacion' => 'Docente', 'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas', 'created_at' => '2026-11-01 10:00:00'])->syncOriginal();
        $declaracion->exists = true;
        $items = [];
        if ($conItems) {
            foreach ([['ANTECEDENTE', 'Antecedente personal declarado'], ['ANTECEDENTE', 'Antecedente familiar declarado'], ['CIRUGIA', 'Cirugía declarada'], ['ALERGIA', 'Alergia declarada'], ['MEDICACION', 'Medicación declarada']] as $i => [$tipo, $descripcion]) {
                $item = new ItemDeclaracionSalud;
                $item->forceFill(['id' => 100 + $i, 'declaracion_salud_id' => 10, 'tipo' => $tipo, 'descripcion' => $descripcion])->syncOriginal();
                $item->exists = true;
                $items[] = $item;
            }
        }
        $declaracion->setRelation('items', new Collection($items));

        return $declaracion;
    }

    private function entrada(Fuente $fuente): array
    {
        $dictamenes = array_fill_keys(array_column(BloqueRevisionDeclaracion::cases(), 'value'), ['dictamen' => 'VALIDAR']);
        $dictamenes['antecedentes']['clasificacion'] = $fuente->bloques['antecedentes']['items'] ? [100 => 'personales', 101 => 'familiares'] : [];

        return ['especialidad_id' => 3, 'procedencia_relevamiento' => 'RELEVAMIENTO_PROFESIONAL', 'dictamenes' => $dictamenes,
            'complemento_profesional' => ['familia' => ['resultado' => 'INFORMACION_NO_DISPONIBLE', 'motivo' => 'No recuerda nombres', 'integrantes' => []]]];
    }

    private function base(): VersionDatosClinicosBase
    {
        $historia = new HistoriaClinica(['paciente_id' => 20, 'activa' => true]);
        $historia->forceFill(['id' => 40]);
        $base = new VersionDatosClinicosBase;
        $base->forceFill(['id' => 30, 'historia_clinica_id' => 40, 'objetivo_salud' => 'Objetivo declarado', 'peso_kg' => '70.25', 'altura_cm' => '170.25',
            'ocupacion' => 'Docente', 'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Caminatas',
            'relevamientos' => array_fill_keys(array_keys(Minimo::TIPOS), 'NO_PRESENTA'),
            'resultado_nucleo_familiar' => 'INFORMADO', 'motivo_nucleo_familiar' => null]);
        $estados = $base->relevamientos;
        $estados['alergias'] = 'INFORMADO';
        $base->relevamientos = $estados;
        $base->exists = true;
        $item = new ItemDatosClinicosBase(['tipo' => 'alergias', 'descripcion' => 'Alergia profesional previa', 'procedencia' => 'RELEVAMIENTO_PROFESIONAL', 'estado_codificacion' => 'PENDIENTE_CODIFICACION']);
        $item->forceFill(['id' => 900, 'version_datos_clinicos_base_id' => 30]);
        $integrante = new IntegranteNucleoFamiliarHc(['nombre_apellido' => 'Contacto de emergencia familiar', 'vinculo' => 'Madre', 'procedencia' => 'CONTACTO_EMERGENCIA_CONFIRMADO', 'contacto_emergencia_confirmado' => true]);
        $integrante->forceFill(['id' => 901, 'version_datos_clinicos_base_id' => 30]);
        $base->setRelation('historiaClinica', $historia)->setRelation('items', new Collection([$item]))->setRelation('integrantes', new Collection([$integrante]));

        return $base;
    }
}
