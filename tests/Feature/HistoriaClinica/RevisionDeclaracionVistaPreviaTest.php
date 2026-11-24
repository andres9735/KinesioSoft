<?php

namespace Tests\Feature\HistoriaClinica;

use App\Data\HistoriaClinica\FuenteDeclaracionParaRevision;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\VersionDatosClinicosBase;
use App\Policies\TurnoPolicy;
use App\Services\HistoriaClinica\ComprobanteVistaPreviaRevision;
use App\Services\HistoriaClinica\ConsultarRevisionDeclaracion;
use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica as Minimo;
use Database\Seeders\PermisoHistoriaClinicaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class RevisionDeclaracionVistaPreviaTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Paciente $paciente;

    private Turno $turno;

    private DeclaracionSaludPaciente $declaracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Queue::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermisoHistoriaClinicaSeeder::class);
        $this->profesional = $this->profesionalUc07();
        $this->profesional->user->givePermissionTo([TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_REVISAR_DECLARACION]);
        $this->paciente = $this->pacienteUc07();
        $this->declaracion = $this->paciente->declaracionesSalud()->sole();
        $this->declaracion->items()->create(['tipo' => 'ANTECEDENTE', 'descripcion' => 'Antecedente declarado sensible']);
        $this->declaracion->items()->create(['tipo' => 'ALERGIA', 'descripcion' => '<script>alert("dato")</script>']);
        $this->turno = Turno::factory()->for($this->profesional)->for($this->paciente)->create();
        $this->actingAs($this->profesional->user);
    }

    #[DataProvider('expedientes')]
    public function test_navega_y_previsualiza_sin_escrituras_con_y_sin_expediente_o_version(string $situacion): void
    {
        $base = $situacion === 'version' ? $this->base() : null;
        if ($situacion === 'cabecera') {
            $this->historia();
        }
        $antes = $this->snapshot();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()->assertSee($this->url(), false);
        $form = $this->get($this->url())->assertOk()->assertSee('Declarado por el paciente')
            ->assertSee('Antecedente declarado sensible')->assertSee('<script>alert("dato")</script>')
            ->assertDontSee('<script>alert("dato")</script>', false);
        $this->head($this->url())->assertOk();
        $entrada = $this->entrada();
        if ($base) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $respuesta = $this->post($this->previewUrl(), $this->postData($form->viewData('contexto'), $entrada))->assertOk()
            ->assertSee('Propuesta sin confirmar')->assertSee('Revisión de declaración de salud')
            ->assertSee('Resultado clínico propuesto')->assertSee('Pendiente de codificación')
            ->assertSee('Comparaciones detalladas y trazabilidad')->assertSee('Volver a editar esta propuesta')
            ->assertDontSee('Progreso de la revisión')->assertDontSee('data-seccion=', false);
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $this->assertSame($base ? count(array_filter($respuesta->viewData('secciones'), fn ($s) => $s['antes'] !== [])) : 0,
            $xpath->query('//*[@data-fuente="anterior"]')->length);
        if (! $base) {
            $respuesta->assertSee('Sin información clínica previa.')->assertDontSee('Dato clínico previo conservado');
        }
        $this->assertSame('no-referrer', $respuesta->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertNull($this->app['session']->getOldInput('propuesta'));
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame([], $writes);
        $this->assertSame(DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION, $this->declaracion->fresh()->estado);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public static function expedientes(): array
    {
        return [['sin historia'], ['cabecera'], ['version']];
    }

    #[DataProvider('expedientes')]
    public function test_negativas_originales_se_muestran_sin_inferir_resultados_y_sin_escrituras(string $situacion): void
    {
        DB::table('items_declaracion_salud')->where('declaracion_salud_id', $this->declaracion->id)->delete();
        DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update([
            'antecedentes_respuesta' => 'no', 'cirugias_respuesta' => 'no',
            'alergias_respuesta' => 'no', 'medicacion_respuesta' => 'no',
        ]);
        $base = $situacion === 'version' ? $this->base() : null;
        if ($situacion === 'cabecera') {
            $this->historia();
        }
        $entrada = $this->entrada();
        unset($entrada['dictamenes']['antecedentes']['clasificacion']);
        $entrada['complemento_profesional']['bloques'] = $base ? [] : array_fill_keys(
            array_keys(Minimo::TIPOS), ['resultado' => 'DESCONOCIDO', 'items' => []]
        );
        if ($base) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $antes = $this->snapshot();
        $form = $this->get($this->url())->assertOk()->assertSee('El paciente declaró No')
            ->assertDontSee('Sin respuesta original disponible')->assertDontSee('Sin información declarada. No acredita');
        $this->assertSame(1, substr_count($form->getContent(), 'data-guia-revision'));
        $form->assertDontSee('Validar esta declaración no registra automáticamente');
        $this->head($this->url())->assertOk();
        $previa = $this->post($this->previewUrl(), $this->postData($form->viewData('contexto'), $entrada))->assertOk()
            ->assertSee('El paciente declaró No')->assertSee('La negativa se admite como declaración del paciente.')
            ->assertSee('Validar un «No» conserva esa declaración; no registra por sí mismo «No presenta».');
        $this->assertSame(1, substr_count($previa->getContent(), 'data-guia-revision'));
        $previa->assertDontSee('Validar esta declaración no registra automáticamente');
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$previa->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        foreach (['antecedentes', 'cirugias', 'alergias', 'medicacion'] as $bloque) {
            $detalle = $xpath->query('//*[@data-dictamen-detalle="'.$bloque.'"]')->item(0);
            $this->assertNotNull($detalle);
            $this->assertStringContainsString('El paciente declaró No', $detalle->textContent);
        }
        $comparacion = $xpath->query('//*[@data-comparacion="Cirugías"]//*[@data-fuente="declaracion"]')->item(0);
        $this->assertNotNull($comparacion);
        $this->assertStringContainsString('El paciente declaró No', $comparacion->textContent);
        $clinica = $previa->viewData('resultado')->datosClinicos;
        if ($base) {
            $this->assertSame('Alergia oficial previa', $clinica['bloques']['alergias']['items'][0]['descripcion']);
        } else {
            foreach ($clinica['bloques'] as $bloque) {
                $this->assertSame('DESCONOCIDO', $bloque['resultado']);
                $this->assertSame([], $bloque['items']);
            }
            $previa->assertSee('Sin información clínica previa.')->assertDontSee('Dato clínico previo conservado');
        }
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_formulario_y_retorno_distinguen_si_no_y_evidencia_historica_sin_perder_items(): void
    {
        DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update([
            'cirugias_respuesta' => 'no', 'alergias_respuesta' => 'si',
        ]);
        $antes = $this->snapshot();
        $form = $this->get($this->url())->assertOk()->assertSee('El paciente declaró Sí')->assertSee('El paciente declaró No')
            ->assertSee('Sin respuesta original disponible')->assertSee('Antecedente declarado sensible')
            ->assertSee('<script>alert("dato")</script>')->assertDontSee('<script>alert("dato")</script>', false);
        $this->assertSame(1, substr_count($form->getContent(), 'data-guia-revision'));
        $entrada = $this->entrada();
        $previa = $this->post($this->previewUrl(), $this->postData($form->viewData('contexto'), $entrada))->assertOk()
            ->assertSee('El paciente declaró Sí')->assertSee('El paciente declaró No')
            ->assertSee('Sin respuesta original disponible')->assertSee('Volver a editar esta propuesta')
            ->assertSee('Revisá lo declarado por el paciente y completá el relevamiento profesional que aún falte, considerando los datos admitidos y la versión clínica previa.');
        $this->assertSame(1, substr_count($previa->getContent(), 'data-guia-revision'));
        $this->assertSame($entrada, $previa->viewData('entrada'));
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_una_respuesta_original_cambiada_invalida_el_contexto_sin_escribir(): void
    {
        $contexto = $this->contexto();
        DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update(['cirugias_respuesta' => 'no']);
        $antes = $this->snapshot();
        $this->post($this->previewUrl(), $this->postData($contexto))->assertStatus(409);
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('expedientes')]
    public function test_dux06_prioriza_el_resultado_y_conserva_confirmacion_retorno_y_fuentes_reales(string $situacion): void
    {
        $base = $situacion === 'version' ? $this->base() : null;
        if ($situacion === 'cabecera') {
            $this->historia();
        }
        $entrada = $this->entrada();
        if ($base) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $contexto = $this->contexto();
        $antes = $this->snapshot();
        $respuesta = $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertOk()
            ->assertSee('Propuesta sin confirmar — los datos clínicos todavía no fueron incorporados')
            ->assertSee('Antecedentes y relevamientos complementarios')->assertSee('Núcleo familiar')
            ->assertDontSee('Sin datos en este apartado')->assertDontSee('<script>alert("dato")</script>', false);
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $html = $respuesta->getContent();
        $this->assertLessThan(strpos($html, 'id="previa-comparaciones"'), strpos($html, 'id="previa-resultado"'));
        foreach (['resultado', 'comparaciones', 'excluidos', 'confirmacion'] as $destino) {
            $this->assertSame(1, $xpath->query('//*[@id="previa-'.$destino.'"]')->length);
            $this->assertSame(1, $xpath->query('//aside//a[@href="#previa-'.$destino.'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//details[@id="previa-comparaciones" and not(@open)]/summary')->length);
        $this->assertSame(10, $xpath->query('//details[@data-dictamen-detalle and not(@open)]/summary')->length);
        $this->assertSame($base ? '2' : '1', trim($xpath->query('//*[@data-version-propuesta]')->item(0)->textContent));
        foreach (['VALIDAR' => 10, 'CORREGIR' => 0, 'OBSERVAR' => 0, 'DESCARTAR' => 0] as $dictamen => $cantidad) {
            $this->assertSame((string) $cantidad, trim($xpath->query('//*[@data-resumen-dictamen="'.$dictamen.'"]/*[@data-cantidad]')->item(0)->textContent));
        }
        $urlConfirmar = route('historia-clinica.revision.confirmar', ['turno' => $this->turno->id, 'declaracion' => $this->declaracion->id]);
        $formulario = $xpath->query('//form[@action="'.$urlConfirmar.'"]');
        $this->assertSame(1, $formulario->length);
        $this->assertSame('POST', $formulario->item(0)->getAttribute('method'));
        $nombres = [];
        foreach ($xpath->query('.//*[@name]', $formulario->item(0)) as $control) {
            $nombres[] = $control->getAttribute('name');
        }
        $this->assertSame(['_token', 'confirmacion', 'confirmar'], $nombres);
        $this->assertSame($respuesta->viewData('comprobante'), $xpath->query('.//input[@name="confirmacion"]', $formulario->item(0))->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('.//input[@name="confirmar" and @value="1" and @required and not(@checked)]', $formulario->item(0))->length);
        $this->assertSame(1, $xpath->query('.//button[@type="submit"]', $formulario->item(0))->length);
        $this->assertSame('propuestaEditada', $xpath->query('.//fieldset', $formulario->item(0))->item(0)->getAttribute(':disabled'));
        $retorno = $xpath->query('//details[@data-retorno-edicion]')->item(0);
        // libxml no conserva el prefijo @ de Alpine en atributos HTML.
        $this->assertStringContainsString('@input="propuestaEditada = true" @change="propuestaEditada = true"', $html);
        $this->assertSame(1, $xpath->query('.//form[@action="'.$this->previewUrl().'"]', $retorno)->length);
        $this->assertSame($contexto, $xpath->query('.//input[@name="contexto"]', $retorno)->item(0)->getAttribute('value'));
        $this->assertSame('JSON.stringify(contrato())', $xpath->query('.//input[@name="propuesta"]', $retorno)->item(0)->getAttribute(':value'));
        $sobre = app(ComprobanteVistaPreviaRevision::class)->leer(
            $respuesta->viewData('comprobante'), $this->profesional->user, $this->turno->id, $this->declaracion->id);
        $this->assertSame($entrada, $sobre['entrada']);
        $this->assertSame($entrada, $respuesta->viewData('entrada'));
        $this->assertSame($respuesta->viewData('resultado')->huellaResultado, $sobre['huella_resultado']);
        $this->assertSame($antes, $this->snapshot());
        foreach ($xpath->query('//*[@data-fuentes-comparacion]') as $grilla) {
            $this->assertSame('grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr))', $grilla->getAttribute('style'));
        }
        $this->assertStringContainsString('xl:grid-cols-[minmax(0,1fr)_20rem]', $html);
        $this->assertSame(0, $xpath->query('//*[@id="previa-resultado"]//table')->length);
    }

    public function test_dux06_distingue_exclusion_declarada_de_datos_previos_conservados(): void
    {
        $base = $this->base();
        $entrada = $this->entrada();
        unset($entrada['complemento_profesional']['familia']);
        $nota = trim("Requiere aclaración profesional.\n".str_repeat('Justificación íntegra y disponible. ', 30));
        $entrada['dictamenes']['ocupacion'] = ['dictamen' => 'OBSERVAR', 'justificacion' => $nota];
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'DESCARTAR', 'justificacion' => 'No se admite la alergia declarada.'];
        $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.75']];
        $entrada['sustituciones_base'] = ['altura_cm'];
        $antes = $this->snapshot();
        $respuesta = $this->post($this->previewUrl(), $this->postData($this->contexto(), $entrada))->assertOk()
            ->assertSee('Corrección profesional')->assertSee($nota)->assertSee('Alergia oficial previa')->assertSee('Se sustituiría');
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $ocupacion = $xpath->query('//*[@data-resultado-campo="ocupacion"]')->item(0)->textContent;
        $this->assertStringContainsString('Docente', $ocupacion);
        $this->assertStringContainsString('Dato clínico previo conservado', $ocupacion);
        $this->assertStringContainsString($nota, $xpath->query('//*[@data-exclusion="ocupacion"]')->item(0)->textContent);
        $alergias = $xpath->query('//*[@data-resultado-categoria="Alergias"]')->item(0)->textContent;
        $this->assertStringContainsString('Alergia oficial previa', $alergias);
        $this->assertStringNotContainsString('<script>alert("dato")</script>', $alergias);
        $altura = $xpath->query('//*[@data-resultado-campo="altura_cm"]')->item(0)->textContent;
        $this->assertStringContainsString('170.00', $altura);
        $this->assertStringContainsString('171.75', $altura);
        foreach (['VALIDAR' => 7, 'CORREGIR' => 1, 'OBSERVAR' => 1, 'DESCARTAR' => 1] as $dictamen => $cantidad) {
            $this->assertSame((string) $cantidad, trim($xpath->query('//*[@data-resumen-dictamen="'.$dictamen.'"]/*[@data-cantidad]')->item(0)->textContent));
        }
        $this->assertSame('170.00', $base->fresh()->altura_cm);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_dux06_no_inventa_datos_para_valores_vacios_y_distingue_las_fuentes_disponibles(): void
    {
        $entrada = $this->entrada();
        foreach (FuenteDeclaracionParaRevision::CAMPOS as $campo) {
            $entrada['dictamenes'][$campo] = ['dictamen' => 'DESCARTAR', 'justificacion' => 'Dato no admitido en esta revisión.'];
        }
        $respuesta = $this->post($this->previewUrl(), $this->postData($this->contexto(), $entrada))->assertOk()
            ->assertSee('Sin datos propuestos para incorporar en esta categoría.')->assertSee('Sin información clínica previa.');
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $this->assertSame(0, $xpath->query('//*[@data-resultado-campo]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-fuente="anterior"]')->length);
        $this->assertSame(6, $xpath->query('//*[@data-exclusion]')->length);
        $familiares = $xpath->query('//*[@data-resultado-categoria="Antecedentes familiares"]')->item(0)->textContent;
        $this->assertStringContainsString('Se desconoce', $familiares);
        $this->assertStringNotContainsString('No presenta', $familiares);
        $this->assertStringContainsString('Información no disponible', $xpath->query('//*[@data-resultado-categoria="Núcleo familiar"]')->item(0)->textContent);
    }

    #[DataProvider('expedientes')]
    public function test_av01_solo_menciona_conservacion_clinica_anterior_si_existe_version_oficial(string $situacion): void
    {
        $base = $situacion === 'version' ? $this->base() : null;
        if ($situacion === 'cabecera') {
            $this->historia();
        }
        $entrada = $this->entrada();
        if ($base) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $entrada['dictamenes']['ocupacion'] = ['dictamen' => 'OBSERVAR', 'justificacion' => 'Requiere aclaración durante la entrevista.'];
        $entrada['dictamenes']['detalle_actividad_fisica'] = ['dictamen' => 'DESCARTAR', 'justificacion' => 'No se admite el detalle declarado.'];
        $contexto = $this->contexto();
        $antes = $this->snapshot();
        $respuesta = $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertOk()
            ->assertSee('Declaración original conservada')->assertSee('Requiere aclaración durante la entrevista.')
            ->assertSee('No se admite el detalle declarado.')->assertSee('Declaración del paciente')
            ->assertSee('Observar')->assertSee('Descartar');
        if ($base) {
            $respuesta->assertSee('Los datos clínicos previos se conservan salvo sustitución explícita')
                ->assertSee('el historial permanece conservado')->assertSee('Alergia oficial previa')
                ->assertSee('Dato clínico previo conservado')->assertDontSee('Sin información clínica previa.');
        } else {
            $respuesta->assertDontSee('Los datos clínicos previos se conservan')
                ->assertDontSee('el historial permanece conservado')
                ->assertDontSee('la versión clínica anterior y la versión propuesta son fuentes diferentes')
                ->assertSee('La declaración original permanece preservada.')->assertSee('Sin información clínica previa.');
        }
        $this->assertSame($entrada, $respuesta->viewData('entrada'));
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('expedientes')]
    public function test_el_redisenio_tiene_cuatro_secciones_accesibles_y_conserva_el_contrato_http(string $situacion): void
    {
        if ($situacion === 'version') {
            $this->base();
        } elseif ($situacion === 'cabecera') {
            $this->historia();
        }
        $antes = $this->snapshot();
        $respuesta = $this->get($this->url())->assertOk()->assertSee('Progreso de la revisión')
            ->assertSee('Sin información declarada')->assertSee('Sin aporte complementario');
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($documento);
        $this->assertSame(4, $xpath->query('//section[@data-seccion]')->length);
        $guia = $xpath->query('//*[@data-guia-revision]');
        $this->assertSame(1, $guia->length);
        $this->assertSame('En la Sección 2 revisás lo declarado por el paciente. En la Sección 3 completás el relevamiento profesional que aún falte, considerando los datos admitidos y la versión clínica previa. Validar un «No» conserva esa declaración; no registra por sí mismo «No presenta».', trim($guia->item(0)->textContent));
        $this->assertSame(0, $xpath->query('ancestor::*[@x-show or @hidden or @x-cloak or self::details]', $guia->item(0))->length);
        $this->assertSame(0, $xpath->query('ancestor::section[@data-seccion]', $guia->item(0))->length);
        $respuesta->assertSee('Este dato declarado no se incorporará a la futura versión.')
            ->assertSee('Corrección profesional — conserva el original')
            ->assertSee('Justificación clínica (obligatoria al observar o descartar)')
            ->assertSee('Los antecedentes admitidos quedan Pendientes de codificación.')
            ->assertSee('Una lista vacía no declara ausencia. Los aportes se suman a la información admitida o previa; para sustituir datos previos, indicá el reemplazo expresamente.')
            ->assertDontSee('Validar esta declaración no registra automáticamente');
        foreach (['salud', 'declarados', 'complementos', 'familia'] as $seccion) {
            $this->assertSame(1, $xpath->query('//*[@id="revision-panel-'.$seccion.'" and @role="region"]')->length);
            $this->assertSame(1, $xpath->query('//button[@aria-controls="revision-panel-'.$seccion.'" and @type="button"]')->length);
        }
        $this->assertSame(1, $xpath->query('//form[@id="revision-formulario"]')->length);
        $nombres = [];
        foreach ($xpath->query('//form[@id="revision-formulario"]//*[@name]') as $control) {
            $nombres[] = $control->getAttribute('name');
        }
        $this->assertSame(['_token', 'contexto', 'propuesta'], $nombres);
        foreach (array_keys(ConsultarRevisionDeclaracion::ETIQUETAS) as $bloque) {
            $this->assertSame(1, $xpath->query('//select[@x-model="dictamenes.'.$bloque.'.dictamen"]')->length);
        }
        $this->assertSame(10, $xpath->query('//h3[text()="Declarado por el paciente"]')->length);
        $this->assertSame(10, $xpath->query('//option[text()="Elegí un dictamen"]')->length);
        foreach ($xpath->query('//*[@data-tarjetas-revision]') as $grilla) {
            $this->assertSame('grid-template-columns: repeat(auto-fit, minmax(min(100%, 28rem), 1fr))', $grilla->getAttribute('style'));
        }
        foreach ($xpath->query('//*[@data-comparacion-declaracion]') as $comparacion) {
            $this->assertSame('grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr))', $comparacion->getAttribute('style'));
        }
        $escritorio = $xpath->query('//form[@id="revision-formulario"]//button[@data-acceso-previa="escritorio"]');
        $estrecho = $xpath->query('//form[@id="revision-formulario"]//button[@data-acceso-previa="estrecho"]');
        $this->assertSame(1, $escritorio->length);
        $this->assertSame(1, $estrecho->length);
        $this->assertStringContainsString('hidden', $escritorio->item(0)->getAttribute('class'));
        $this->assertStringContainsString('xl:block', $escritorio->item(0)->getAttribute('class'));
        $this->assertStringContainsString('xl:hidden', $estrecho->item(0)->getAttribute('class'));
        // El acceso de escritorio sigue visible aunque se cierre el detalle de progreso.
        $this->assertSame(0, $xpath->query('//details//button[@data-acceso-previa="escritorio"]')->length);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_el_redisenio_recupera_errores_contextualizados_sin_cambiar_propuesta_o_persistir(): void
    {
        $contexto = $this->contexto();
        $entrada = $this->entrada();
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [[
            'item_origen_id' => null, 'tipo_destino' => 'alergias', 'descripcion' => 'Corrección conservada', 'procedencia' => '',
        ]]]];
        $antes = $this->snapshot();
        $respuesta = $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertStatus(422)
            ->assertSee('Revisá el resultado profesional')->assertSee('dictamenes.alergias.correccion.items.0.procedencia')
            ->assertSee('Hay errores generales sin ubicación inequívoca');
        $this->assertSame($entrada, $respuesta->viewData('entrada'));
        $this->assertSame(['dictamenes.alergias.correccion.items.0.procedencia'], $respuesta->viewData('erroresRevision')->keys());
        $this->assertSame($antes, $this->snapshot());
        $entrada = $this->entrada();
        $entrada['dictamenes']['ocupacion'] = ['dictamen' => 'OBSERVAR', 'justificacion' => ''];
        $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertStatus(422)
            ->assertViewHas('erroresRevision', fn ($errores) => $errores->has('dictamenes.ocupacion.justificacion'));
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_la_respuesta_422_conserva_categorias_filas_y_valores_al_editar_desde_la_vista_previa(): void
    {
        $contexto = $this->contexto();
        $entrada = $this->entrada();
        $antes = $this->snapshot();
        $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertOk()->assertSee('Volver a editar esta propuesta');
        foreach (['alergias', 'cirugias'] as $tipo) {
            $editada = $entrada;
            $editada['dictamenes'][$tipo] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [[
                'item_origen_id' => null, 'tipo_destino' => $tipo, 'descripcion' => 'Corrección '.$tipo, 'procedencia' => '',
            ]]]];
            $respuesta = $this->post($this->previewUrl(), $this->postData($contexto, $editada))->assertStatus(422);
            $this->assertSame(['dictamenes.'.$tipo.'.correccion.items.0.procedencia'], $respuesta->viewData('erroresRevision')->keys());
            $this->assertSame($editada, $respuesta->viewData('entrada'));
        }
        $editada = $entrada;
        $editada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'INFORMADO', 'items' => [[], [
            'descripcion' => 'Aporte conservado', 'procedencia' => '', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]]];
        $respuesta = $this->post($this->previewUrl(), $this->postData($contexto, $editada))->assertStatus(422);
        $this->assertSame(['bloques.alergias.items.1.procedencia'], $respuesta->viewData('erroresRevision')->keys());
        $this->assertSame($editada, $respuesta->viewData('entrada'));
        $editada['complemento_profesional']['bloques']['alergias']['items'][1]['procedencia'] = 'REFERIDO_PACIENTE';
        $editada['complemento_profesional']['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [[], ['nombre_apellido' => 'Integrante', 'vinculo' => '']]];
        $respuesta = $this->post($this->previewUrl(), $this->postData($contexto, $editada))->assertStatus(422);
        $this->assertSame(['familia.integrantes.1.vinculo'], $respuesta->viewData('erroresRevision')->keys());
        $this->assertSame($editada, $respuesta->viewData('entrada'));
        $this->assertSame($antes, $this->snapshot());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_el_permiso_de_apertura_no_habilita_revision_y_sin_consulta_tampoco(): void
    {
        $this->profesional->user->revokePermissionTo(TurnoPolicy::PERMISO_REVISAR_DECLARACION);
        $this->profesional->user->givePermissionTo(TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA);
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()->assertDontSee($this->url(), false);
        $this->get($this->url())->assertForbidden()->assertDontSee('Antecedente declarado sensible');
        $this->post($this->previewUrl(), [])->assertForbidden();
        $this->profesional->user->givePermissionTo(TurnoPolicy::PERMISO_REVISAR_DECLARACION);
        $this->profesional->user->revokePermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA);
        $this->get($this->url())->assertForbidden();
    }

    public function test_el_permiso_de_revision_no_requiere_permiso_de_apertura(): void
    {
        $this->assertFalse($this->profesional->user->checkPermissionTo(TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA));
        $this->get($this->url())->assertOk();
    }

    public function test_otra_profesional_no_lee_ninguna_declaracion_y_reasignar_revoca_la_vista_previa(): void
    {
        $contexto = $this->contexto();
        $otra = $this->profesionalUc07();
        $otra->user->givePermissionTo([TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_REVISAR_DECLARACION]);
        $consultas = [];
        DB::listen(function ($query) use (&$consultas): void {
            if (str_contains($query->sql, 'declaraciones_salud_paciente') || str_contains($query->sql, 'items_declaracion_salud')) {
                $consultas[] = $query->sql;
            }
        });
        $this->actingAs($otra->user)->get($this->url())->assertForbidden();
        $this->assertSame([], $consultas);
        $this->actingAs($this->profesional->user);
        $this->turno->forceFill(['kinesiologa_id' => $otra->id])->save();
        $this->post($this->previewUrl(), $this->postData($contexto))->assertForbidden();
    }

    #[DataProvider('revocaciones')]
    public function test_revalida_habilitacion_y_permiso_con_actor_precargado(string $caso): void
    {
        $actor = $this->profesional->user;
        $actor->load('roles', 'permissions', 'kinesiologa.matriculasProfesionales', 'kinesiologa.especialidades');
        match ($caso) {
            'permiso' => $actor->permissions()->detach(),
            'cuenta' => DB::table('users')->where('id', $actor->id)->update(['activo' => false]),
            'matricula' => $this->profesional->matriculasProfesionales()->update(['activa' => false]),
            'especialidad' => $this->profesional->especialidades()->detach(),
        };
        $this->expectException(AuthorizationException::class);
        app(ConsultarRevisionDeclaracion::class)->consultar($actor, $this->turno->id, $this->declaracion->id);
    }

    public static function revocaciones(): array
    {
        return [['permiso'], ['cuenta'], ['matricula'], ['especialidad']];
    }

    public function test_guest_y_rol_no_profesional_no_acceden_a_rutas_directas(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get($this->url())->assertRedirect(route('login'));
        $paciente = $this->paciente->user;
        $paciente->givePermissionTo([TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_REVISAR_DECLARACION]);
        $this->actingAs($paciente)->get($this->url())->assertForbidden();
    }

    public function test_no_acepta_declaracion_de_otro_paciente_ni_identidades_del_cliente(): void
    {
        $otro = $this->pacienteUc07();
        $ajena = $otro->declaracionesSalud()->sole();
        $this->get(route('historia-clinica.revision.create', ['turno' => $this->turno->id, 'declaracion' => $ajena->id]))->assertNotFound();
        $this->get($this->url().'?paciente_id='.$otro->id)->assertOk()->assertViewHas('paciente', fn ($p) => $p->id === $this->paciente->id);
    }

    #[DataProvider('cambiosContexto')]
    public function test_detecta_contexto_cambiado_sin_reexponer_formulario_anterior(string $caso): void
    {
        $contexto = $this->contexto();
        match ($caso) {
            'historia' => $this->historia(),
            'version' => $this->base(),
            'original' => DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update(['altura_cm' => 171.25]),
            'contacto' => $this->paciente->update(['contacto_emergencia_nombre' => 'Otro contacto']),
            'token' => $contexto = 'invalido',
        };
        $entrada = $this->entrada();
        $entrada['dictamenes']['ocupacion'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => 'Dato privado del formulario anterior']];
        $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertStatus(409)
            ->assertDontSee('Dato privado del formulario anterior');
        $this->assertNull($this->app['session']->getOldInput('propuesta'));
    }

    public static function cambiosContexto(): array
    {
        return [['historia'], ['version'], ['original'], ['contacto'], ['token']];
    }

    #[DataProvider('erroresProfesionales')]
    public function test_rechaza_resultados_invalidos_sin_escribir_o_guardar_clinica_en_sesion(string $caso): void
    {
        $contexto = $this->contexto();
        $entrada = $this->entrada();
        match ($caso) {
            'clasificacion' => $entrada['dictamenes']['antecedentes']['clasificacion'] = [],
            'nota' => $entrada['dictamenes']['alergias'] = ['dictamen' => 'OBSERVAR'],
            'minimo' => $entrada['complemento_profesional']['bloques']['familiares']['resultado'] = 'NO_RELEVADO',
            'familia' => $entrada['complemento_profesional']['familia']['resultado'] = 'NO_RELEVADO',
            'especialidad' => $entrada['especialidad_id'] = 999999,
            'precision' => $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '170.255']],
            'vacio' => $entrada['complemento_profesional']['bloques'] = [],
        };
        $antes = $this->snapshot();
        $this->post($this->previewUrl(), $this->postData($contexto, $entrada))->assertStatus(422)
            ->assertSee('Revisá el resultado profesional');
        $this->assertSame($antes, $this->snapshot());
        $this->assertNull($this->app['session']->getOldInput('propuesta'));
    }

    public static function erroresProfesionales(): array
    {
        return array_map(fn ($c) => [$c], ['clasificacion', 'nota', 'minimo', 'familia', 'especialidad', 'precision', 'vacio']);
    }

    public function test_muestra_validar_corregir_observar_descartar_y_procedencias_sin_cambiar_original(): void
    {
        $entrada = $this->entrada();
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [[
            'item_origen_id' => $this->declaracion->items()->where('tipo', 'ALERGIA')->value('id'),
            'tipo_destino' => 'alergias', 'descripcion' => 'Alergia precisada', 'procedencia' => 'RELEVAMIENTO_PROFESIONAL',
        ]]]];
        $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.75']];
        $entrada['dictamenes']['ocupacion'] = ['dictamen' => 'OBSERVAR', 'justificacion' => 'Requiere aclaración'];
        $entrada['dictamenes']['detalle_actividad_fisica'] = ['dictamen' => 'DESCARTAR', 'justificacion' => 'No corresponde admitirlo'];
        $respuesta = $this->post($this->previewUrl(), $this->postData($this->contexto(), $entrada))->assertOk()
            ->assertSee('Alergia precisada')->assertSee('Relevamiento profesional')->assertSee('171.75')
            ->assertSee('Incluye correcciones profesionales')
            ->assertSee('Requiere aclaración')->assertSee('Dato declarado excluido')->assertSee('No corresponde admitirlo');
        $resultado = $respuesta->viewData('resultado');
        $this->assertNull($resultado->datosClinicos['ocupacion']);
        $this->assertSame('170.00', $this->declaracion->fresh()->altura_cm);
        $this->assertSame('DOCENTE', strtoupper($this->declaracion->ocupacion));
    }

    public function test_previa_conserva_antecedentes_y_muestra_sustitucion_explicita_y_contacto_confirmado(): void
    {
        $base = $this->base();
        $entrada = $this->entrada();
        $entrada['sustituciones_base'] = ['altura_cm', 'familia'];
        $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '180.25']];
        $entrada['complemento_profesional']['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [],
            'incluir_contacto' => true, 'vinculo_contacto' => 'Hermana'];
        $antes = $this->snapshot();
        $respuesta = $this->post($this->previewUrl(), $this->postData($this->contexto(), $entrada))->assertOk()
            ->assertSee('Alergia oficial previa')->assertSee('180.25')->assertSee('Hermana')
            ->assertSee('Contacto de emergencia confirmado como integrante');
        $altura = collect($respuesta->viewData('secciones'))->firstWhere('titulo', 'Altura (cm)');
        $this->assertSame(['170.00'], $altura['sustituido']);
        $this->assertSame(['180.25'], $altura['incorporado']);
        $this->assertSame('170.00', $base->fresh()->altura_cm);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_inactiva_o_revisada_impiden_revision_y_confirmacion_solo_admite_post(): void
    {
        $this->historia(false);
        $this->get($this->url())->assertStatus(409);
        $this->assertTrue(app('router')->has('historia-clinica.revision.confirmar'));
        $this->assertSame(['POST'], app('router')->getRoutes()->getByName('historia-clinica.revision.confirmar')->methods());
        $this->get(route('historia-clinica.revision.confirmar', ['turno' => $this->turno->id, 'declaracion' => $this->declaracion->id]))->assertStatus(405);
        $this->post($this->url(), [])->assertStatus(405);
        $this->get($this->previewUrl())->assertStatus(405);
        DB::table('historias_clinicas')->update(['activa' => true]);
        DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update(['estado' => 'REVISADA']);
        $this->get($this->url())->assertStatus(409);
    }

    public function test_payload_invalido_no_flasha_ni_repite_contenido_sensible(): void
    {
        $this->post($this->previewUrl(), ['contexto' => $this->contexto(), 'propuesta' => '{Secreto clínico inválido'])
            ->assertStatus(422)->assertDontSee('Secreto clínico inválido');
        $this->assertNull($this->app['session']->getOldInput('propuesta'));
    }

    public function test_una_nueva_version_del_mismo_expediente_invalida_el_contexto(): void
    {
        $base = $this->base();
        $contexto = $this->contexto();
        $base->replicate()->forceFill(['numero_version' => 2, 'clave_apertura' => null, 'created_at' => now()])->save();
        $this->post($this->previewUrl(), $this->postData($contexto))->assertStatus(409);
    }

    public function test_cambiar_el_paciente_del_turno_impide_reexponer_el_formulario_anterior(): void
    {
        $contexto = $this->contexto();
        $otro = $this->pacienteUc07();
        $this->turno->forceFill(['paciente_id' => $otro->id])->save();
        $this->post($this->previewUrl(), $this->postData($contexto))->assertNotFound()
            ->assertDontSee('Antecedente declarado sensible');
    }

    public function test_varias_pendientes_tienen_accesos_individuales_y_no_mezclan_originales(): void
    {
        $otra = $this->declaracion->replicate();
        $otra->objetivo_salud = 'Otra declaración independiente';
        $otra->save();
        $url = route('historia-clinica.revision.create', ['turno' => $this->turno->id, 'declaracion' => $otra->id]);
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()->assertSee($url, false)->assertSee($this->url(), false);
        $this->get($url)->assertOk()->assertSee('Otra declaración independiente')->assertDontSee('Antecedente declarado sensible');
    }

    public function test_corregir_una_lista_explica_que_items_originales_quedan_excluidos(): void
    {
        $entrada = $this->entrada();
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => []]];
        $entrada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'DESCONOCIDO', 'items' => []];
        $respuesta = $this->post($this->previewUrl(), $this->postData($this->contexto(), $entrada))->assertOk()
            ->assertSee('Excluido del resultado corregido')->assertSee('El original permanece en la declaración');
        $this->assertSame('DESCONOCIDO', $respuesta->viewData('resultado')->datosClinicos['bloques']['alergias']['resultado']);
        $this->assertDatabaseCount('items_declaracion_salud', 2);
    }

    public function test_comparacion_cuenta_registros_iguales_al_sustituir_una_lista_previa(): void
    {
        $base = $this->base();
        $base->items()->create(['tipo' => 'alergias', 'descripcion' => 'Alergia oficial previa',
            'procedencia' => 'RELEVAMIENTO_PROFESIONAL', 'estado_codificacion' => 'PENDIENTE_CODIFICACION']);
        $entrada = $this->entrada();
        unset($entrada['complemento_profesional']['familia']);
        $entrada['sustituciones_base'] = ['alergias'];
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'CORREGIR', 'correccion' => ['items' => [[
            'tipo_destino' => 'alergias', 'descripcion' => 'Alergia oficial previa', 'procedencia' => 'RELEVAMIENTO_PROFESIONAL',
        ]]]];
        $respuesta = $this->post($this->previewUrl(), $this->postData($this->contexto(), $entrada))->assertOk();
        $alergias = collect($respuesta->viewData('secciones'))->firstWhere('titulo', 'Alergias');
        $this->assertCount(1, $alergias['sustituido']);
        $this->assertStringContainsString('Alergia oficial previa', $alergias['sustituido'][0]);
        $this->assertCount(2, $alergias['conservado']); // Estado informado y una de las dos ocurrencias.
        $this->assertCount(0, $alergias['incorporado']);
        $this->assertSame(2, $base->items()->count());
    }

    public function test_version_incorporada_se_rotula_como_revision_y_no_carga_directa(): void
    {
        $this->base('REVISION_DECLARACION');
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()
            ->assertSee('Revisión de declaración de salud')->assertDontSee('Carga profesional directa');
    }

    private function historia(bool $activa = true): HistoriaClinica
    {
        return HistoriaClinica::create(['paciente_id' => $this->paciente->id, 'nro_historia_clinica' => 'HC-REVISION', 'activa' => $activa]);
    }

    private function base(string $origen = 'CARGA_PROFESIONAL_DIRECTA'): VersionDatosClinicosBase
    {
        $version = VersionDatosClinicosBase::create(['historia_clinica_id' => $this->historia()->id, 'numero_version' => 1,
            'kinesiologa_id' => $this->profesional->id, 'autor_user_id' => $this->profesional->user_id,
            'especialidad_id' => $this->profesional->especialidades()->first()->id, 'turno_origen_id' => $this->turno->id,
            'autor_nombre_snapshot' => 'Profesional', 'especialidad_nombre_snapshot' => 'Especialidad', 'origen' => $origen,
            'huella_solicitud' => str_repeat('a', 64), 'altura_cm' => 170, 'peso_kg' => 70, 'ocupacion' => 'Docente',
            'objetivo_salud' => 'Mejorar movilidad', 'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Caminatas',
            'procedencia_relevamiento' => 'REFERIDO_PACIENTE', 'relevamientos' => array_fill_keys(array_keys(Minimo::TIPOS), 'NO_PRESENTA'),
            'resultado_nucleo_familiar' => 'INFORMACION_NO_DISPONIBLE', 'created_at' => now()]);
        // Primer antecedente previo para verificar que la revisión no lo borre.
        DB::table('versiones_datos_clinicos_base')->where('id', $version->id)->update([
            'relevamientos' => json_encode([...$version->relevamientos, 'alergias' => 'INFORMADO']),
        ]);
        $version->items()->create(['tipo' => 'alergias', 'descripcion' => 'Alergia oficial previa',
            'procedencia' => 'RELEVAMIENTO_PROFESIONAL', 'estado_codificacion' => 'PENDIENTE_CODIFICACION']);

        return $version->fresh();
    }

    private function entrada(): array
    {
        $dictamenes = array_fill_keys(array_keys(ConsultarRevisionDeclaracion::ETIQUETAS), ['dictamen' => 'VALIDAR']);
        $id = $this->declaracion->items()->where('tipo', 'ANTECEDENTE')->value('id');
        $dictamenes['antecedentes']['clasificacion'] = [$id => 'personales'];

        return ['especialidad_id' => $this->profesional->especialidades()->first()->id,
            'procedencia_relevamiento' => 'RELEVAMIENTO_PROFESIONAL', 'dictamenes' => $dictamenes,
            'complemento_profesional' => ['bloques' => array_fill_keys(['familiares', 'cirugias', 'medicacion'], ['resultado' => 'DESCONOCIDO', 'items' => []]),
                'familia' => ['resultado' => 'INFORMACION_NO_DISPONIBLE', 'integrantes' => [], 'incluir_contacto' => false]]];
    }

    private function contexto(): string
    {
        return $this->get($this->url())->assertOk()->viewData('contexto');
    }

    private function postData(string $contexto, ?array $entrada = null): array
    {
        return ['contexto' => $contexto, 'propuesta' => json_encode($entrada ?? $this->entrada(), JSON_THROW_ON_ERROR)];
    }

    private function url(): string
    {
        return route('historia-clinica.revision.create', ['turno' => $this->turno->id, 'declaracion' => $this->declaracion->id]);
    }

    private function previewUrl(): string
    {
        return route('historia-clinica.revision.preview', ['turno' => $this->turno->id, 'declaracion' => $this->declaracion->id]);
    }

    private function snapshot(): array
    {
        $datos = [];
        foreach (['turnos', 'historias_clinicas', 'declaraciones_salud_paciente', 'items_declaracion_salud',
            'versiones_datos_clinicos_base', 'items_datos_clinicos_base', 'integrantes_nucleo_familiar_hc',
            'confirmaciones_revision_declaracion', 'revisiones_declaracion_salud', 'incorporaciones_declaracion_salud', 'auditorias'] as $tabla) {
            $datos[$tabla] = DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $datos;
    }
}
