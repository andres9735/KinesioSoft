<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TurnosAccessibilityViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(RoleEnum::PACIENTE->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::PACIENTE->value);
        $this->actingAs($user);
    }

    public function test_headings_stepper_and_mobile_progress_identify_the_same_four_steps(): void
    {
        $xpath = $this->wizard();
        $this->assertSame('Solicitar turno', trim($xpath->query('//h1')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(4, $xpath->query('//main//section//h2[starts-with(@id,"titulo-paso-")]')->length);
        $this->assertSame(4, $xpath->query('//nav[@aria-label="Pasos de la solicitud"]/ol/li')->length);
        $this->assertSame(1, $xpath->query('//nav//li[@aria-current="step"]')->length);
        $this->assertSame(1, $xpath->query('//progress[@max="4" and @value="1" and @aria-label]')->length);
        foreach (['Fecha y horario', 'Elegir profesional', 'Motivo y documentación', 'Revisar y confirmar'] as $i => $nombre) {
            $this->assertSame($nombre, trim($xpath->query('//*[@id="titulo-paso-'.($i + 1).'"]')->item(0)->textContent));
        }
        $this->assertStringContainsString('Paso 1 de 4', $xpath->document->textContent);
    }

    public function test_static_labels_references_and_ids_are_valid_without_positive_tab_order(): void
    {
        $xpath = $this->wizard();
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            $this->assertNotContains($id, $ids);
            $ids[] = $id;
        }
        foreach ($xpath->query('//main//label[@for]') as $label) {
            $this->assertContains($label->getAttribute('for'), $ids);
        }
        foreach ($xpath->query('//main//*[@aria-describedby or @aria-labelledby]') as $node) {
            foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attribute)), flags: PREG_SPLIT_NO_EMPTY) as $id) {
                    $this->assertContains($id, $ids);
                }
            }
        }
        $this->assertSame(0, $xpath->query('//*[@tabindex > 0]')->length);
    }

    public function test_step_one_keeps_professionals_separate_and_step_two_has_accessible_dynamic_cards(): void
    {
        $xpath = $this->wizard();
        $this->assertSame(0, $xpath->query('//section[@aria-labelledby="titulo-paso-1"]//*[@name="kinesiologa_id"]')->length);
        $this->assertSame('Elegir profesional', trim($xpath->query('//*[@id="titulo-paso-2"]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//main//select[@id="profesional-movil"]')->length);
        $card = $xpath->query('//section[@aria-labelledby="titulo-paso-2"]//template/button')->item(0);
        $this->assertNotNull($card);
        $this->assertSame('button', $card->getAttribute('type'));
        $this->assertSame('kinesiologaId === profesional.kinesiologa_id', $card->getAttribute(':aria-pressed'));
        $this->assertSame('profesional-error', $card->getAttribute('aria-describedby'));
        $this->assertTrue($card->hasAttribute(':aria-label'));
        $this->assertSame(1, $xpath->query('ancestor::fieldset[1]/legend', $card)->length);
        $this->assertSame(1, $xpath->query('ancestor::fieldset[1][@*[name()=":disabled"]]', $card)->length);
        $this->assertSame(1, $xpath->query('//*[@x-ref="errorProfesionales" and @role="alert" and @tabindex="-1"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="profesional-error" and @role="alert" and @tabindex="-1"]')->length);
        $this->assertSame(1, $xpath->query('//button[@*[name()="x-on:click"]="cargarProfesionalesDisponibles()"]')->length);
    }

    public function test_reason_and_files_expose_help_errors_and_named_removal(): void
    {
        $xpath = $this->wizard();
        $reason = $xpath->query('//textarea[@id="motivo-solicitud"]')->item(0);
        $this->assertStringContainsString('erroresServidor.motivo_solicitud', $reason->getAttribute(':aria-invalid'));
        $this->assertStringContainsString('motivo-error-servidor', $reason->getAttribute('aria-describedby'));
        $file = $xpath->query('//input[@type="file"]')->item(0);
        $this->assertTrue($file->hasAttribute('multiple'));
        $this->assertStringContainsString('erroresServidor.adjuntos', $file->getAttribute(':aria-invalid'));
        $this->assertSame(1, $xpath->query('//ul[@aria-labelledby="documentacion-titulo"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="archivos-errores" and @aria-live="polite"]')->length);
        $removal = $xpath->query('//button[@*[name()="x-on:click"]="quitarArchivo(entrada.id)"]')->item(0);
        $this->assertNotNull($removal);
        $this->assertStringContainsString('entrada.archivo.name', $removal->getAttribute(':aria-label'));
        $this->assertStringContainsString('archivo-estado-', $removal->getAttribute(':aria-describedby'));
        $entrada = $xpath->query('//template[@x-for="entrada in archivos"]')->item(0);
        $descripcion = $xpath->query('.//input[@type="text"]', $entrada)->item(0);
        $this->assertNotNull($descripcion);
        $this->assertSame(1, $xpath->query('.//label[@*[name()=":for"]="\'descripcion-adjunto-\' + entrada.id"]', $entrada)->length);
        $this->assertSame(1, $xpath->query('.//label/span[@class="sr-only" and @x-text="\' de \' + entrada.archivo.name"]', $entrada)->length);
        foreach (['descripcion-ayuda-', 'descripcion-error-', 'descripcion-error-servidor-'] as $prefijo) {
            $this->assertStringContainsString($prefijo, $descripcion->getAttribute(':aria-describedby'));
            $this->assertSame(1, $xpath->query('.//*[@*[name()=":id"]="\''.$prefijo.'\' + entrada.id"]', $entrada)->length);
        }
        $this->assertSame(1, $xpath->query('.//input[@type="file" and @*[name()=":id"]="\'reemplazo-adjunto-\' + entrada.id"]', $entrada)->length);
        $this->assertSame(1, $xpath->query('.//label[@*[name()=":for"]="\'reemplazo-adjunto-\' + entrada.id"]', $entrada)->length);
        $this->assertSame(1, $xpath->query('.//label/span[@x-text="\' \' + entrada.archivo.name"]', $entrada)->length);
        $this->assertSame('!!errorDescripcion(entrada) || !!entrada.errorDescripcionServidor', $descripcion->getAttribute(':aria-invalid'));
    }

    public function test_file_picker_presents_the_accepted_selection_instead_of_the_reset_native_value(): void
    {
        $xpath = $this->wizard();
        $input = $xpath->query('//input[@id="adjuntos"]')->item(0);
        $this->assertSame('file', $input->getAttribute('type'));
        $this->assertTrue($input->hasAttribute('multiple'));
        $this->assertSame('.pdf,.jpg,.jpeg,.png', $input->getAttribute('accept'));
        $this->assertSame('agregarArchivos($event)', $input->getAttribute('x-on:change'));
        $this->assertFalse($input->hasAttribute('hidden'));
        $this->assertFalse($input->hasAttribute('aria-hidden'));
        $this->assertFalse($input->hasAttribute('tabindex'));
        $this->assertStringContainsString('sr-only', $input->getAttribute('class'));
        $this->assertStringContainsString('peer', $input->getAttribute('class'));
        $label = $xpath->query('//label[@for="adjuntos"]')->item(0);
        $this->assertSame('Elegir archivos', trim($label->textContent));
        $this->assertStringContainsString('peer-focus:ring-2', $label->getAttribute('class'));
        $this->assertStringContainsString('peer-focus:ring-indigo-500', $label->getAttribute('class'));
        $status = $xpath->query('//*[@id="archivos-seleccion"]')->item(0);
        $this->assertSame('Ningún archivo seleccionado', trim($status->textContent));
        $this->assertSame("archivos.length > 0 ? 'Agregar más archivos' : 'Ningún archivo seleccionado'", $status->getAttribute('x-text'));
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertStringContainsString('archivos-seleccion', $input->getAttribute('aria-describedby'));
        $this->assertSame(1, $xpath->query('//p[@*[name()="x-text"]="archivos.length > 0 ? \'Podés sumar más estudios o documentación si lo necesitás.\' : \'Elegí archivos o arrastralos aquí.\'"]')->length);
        $this->assertSame(1, $xpath->query('//div[@*[name()="x-on:drop.prevent"]="agregarArchivos({ target: { files: $event.dataTransfer.files, value: \'\' } })"]')->length);
    }

    public function test_professional_heading_keeps_its_programmatic_focus_without_a_selection_ring(): void
    {
        $heading = $this->wizard()->query('//h2[@id="titulo-paso-2"]')->item(0);
        $this->assertSame('tituloPaso2', $heading->getAttribute('x-ref'));
        $this->assertSame('-1', $heading->getAttribute('tabindex'));
        $classes = preg_split('/\s+/', $heading->getAttribute('class'));
        $this->assertContains('focus:outline-none', $classes);
        $this->assertDoesNotMatchRegularExpression('/\bfocus(?:-visible)?:ring(?:-|\b)/', $heading->getAttribute('class'));
    }

    public function test_slots_loading_errors_and_review_have_native_semantics(): void
    {
        $xpath = $this->wizard();
        $this->assertGreaterThanOrEqual(2, $xpath->query('//main//*[@role="status"]')->length);
        $fecha = $xpath->query('//button[@*[name()="x-on:click"]="seleccionarFecha(dia.fecha)"]')->item(0);
        $this->assertNotNull($fecha);
        $this->assertSame('button', $fecha->getAttribute('type'));
        $this->assertTrue($fecha->hasAttribute(':disabled'));
        $this->assertTrue($fecha->hasAttribute(':aria-pressed'));
        $slot = $xpath->query('//button[@*[name()="x-on:click"]="seleccionarInicio(horario.inicio_at)"]')->item(0);
        $this->assertNotNull($slot);
        $this->assertSame('button', $slot->getAttribute('type'));
        $this->assertTrue($slot->hasAttribute(':aria-pressed'));
        $this->assertStringContainsString('fechaLegible(fechaSeleccionada)', $slot->getAttribute(':aria-label'));
        $this->assertSame(1, $xpath->query('ancestor::fieldset[1]/legend', $slot)->length);
        $this->assertSame(8, $xpath->query('//section[@aria-labelledby="titulo-paso-4"]//dl/div/dt')->length);
        $this->assertSame(8, $xpath->query('//section[@aria-labelledby="titulo-paso-4"]//dl/div/dd')->length);
        $review = $xpath->query('//section[@aria-labelledby="titulo-paso-4"]')->item(0);
        foreach (['revision-fecha-titulo', 'revision-profesional-titulo', 'revision-motivo-titulo', 'revision-documentacion-titulo'] as $titulo) {
            $this->assertSame(1, $xpath->query('.//section[@aria-labelledby="'.$titulo.'"]', $review)->length);
        }
        foreach ([1, 2, 3] as $paso) {
            $this->assertSame(1, $xpath->query('.//button[@type="button" and @*[name()="x-on:click"]="editarDesdeRevision('.$paso.')" and @*[name()=":disabled"]="bloqueado"]', $review)->length);
        }
        $confirm = $xpath->query('//button[@*[name()="x-on:click"]="confirmarTurno()"]')->item(0);
        $this->assertSame('!puedeConfirmar', $confirm->getAttribute(':disabled'));
        $this->assertSame('enviando', $confirm->getAttribute(':aria-busy'));
        $this->assertSame(1, $xpath->query('//main//fieldset[@*[name()=":disabled"]="bloqueado"]/legend')->length);
    }

    public function test_recovery_announces_context_and_keeps_its_retry_reachable_outside_the_disabled_form(): void
    {
        $xpath = $this->wizard();
        $banner = $xpath->query('//*[@x-ref="mensajeConfirmacion"]')->item(0);
        $this->assertSame('alert', $banner->getAttribute('role'));
        $this->assertSame('-1', $banner->getAttribute('tabindex'));
        $this->assertSame(1, $xpath->query('.//p[@x-show="tipoRecuperacion" and @x-text="detalleRecuperacion"]', $banner)->length);
        $this->assertSame(1, $xpath->query('.//*[@role="status" and @x-show="recuperando"]', $banner)->length);
        $retry = $xpath->query('.//button[@*[name()="x-on:click"]="reintentarRecuperacion()"]', $banner)->item(0);
        $this->assertNotNull($retry);
        $this->assertSame('button', $retry->getAttribute('type'));
        $this->assertSame('Reintentar consulta', trim($retry->textContent));
        $this->assertSame(0, $xpath->query('ancestor::fieldset', $retry)->length);
        $this->assertFalse($retry->hasAttribute('tabindex'));
        $this->assertStringContainsString('focus:ring-', $retry->getAttribute('class'));
    }

    public function test_primary_controls_are_native_keyboard_targets_with_visible_focus(): void
    {
        $xpath = $this->wizard();
        $this->assertSame(0, $xpath->query('//main//*[self::div or self::span][@*[name()="x-on:click"]]')->length);
        foreach ($xpath->query('//main//button | //main//input | //main//textarea') as $control) {
            $this->assertStringContainsString('focus:', $control->getAttribute('class'));
            if ($control->tagName === 'button') {
                $this->assertSame('button', $control->getAttribute('type'));
            }
        }
        $this->assertSame(1, $xpath->query('//button[@*[name()="x-on:click"]="cambiarVentana(-1)" and @aria-label="Semana anterior" and @*[name()=":disabled"]]')->length);
        $this->assertSame(1, $xpath->query('//button[@*[name()="x-on:click"]="cambiarVentana(1)" and @aria-label="Semana siguiente"]')->length);
    }

    public function test_responsive_structure_preserves_a_single_column_and_long_content_wrapping(): void
    {
        $xpath = $this->wizard();
        $contenedor = $xpath->query('//main//*[@class and contains(@class,"max-w-6xl")]')->item(0);
        $this->assertNotNull($contenedor);
        $this->assertStringContainsString('[overflow-wrap:anywhere]', $contenedor->getAttribute('class'));
        $this->assertSame(1, $xpath->query('//section[@aria-labelledby="titulo-paso-1"]//div[contains(@class,"lg:grid-cols-")]')->length);
        $card = $xpath->query('//section[@aria-labelledby="titulo-paso-2"]//template/button')->item(0);
        foreach (['min-w-0', 'flex-col', 'sm:flex-row'] as $patron) {
            $this->assertStringContainsString($patron, $card->getAttribute('class'));
        }
        $nombre = $xpath->query('.//*[@x-text="profesional.nombre"]', $card)->item(0);
        $this->assertStringContainsString('[overflow-wrap:anywhere]', $nombre->getAttribute('class'));
        $entrada = $xpath->query('//section[@aria-labelledby="titulo-paso-3"]//template[@x-for="entrada in archivos"]/li')->item(0);
        $this->assertSame(3, $xpath->query('./div', $entrada)->length);
        $this->assertStringContainsString('flex-col', $xpath->query('./div[1]', $entrada)->item(0)->getAttribute('class'));
        foreach (['Fecha y hora elegidas', 'Turno elegido'] as $contexto) {
            $this->assertStringContainsString('flex-col', $xpath->query('//div[@aria-label="'.$contexto.'"]')->item(0)->getAttribute('class'));
        }
        $this->assertSame(1, $xpath->query('.//p[@x-text="entrada.archivo.name" and contains(@class,"break-words")]', $entrada)->length);
        $nombreArchivo = $xpath->query('.//p[@x-text="entrada.archivo.name"]', $entrada)->item(0);
        $this->assertStringContainsString('[overflow-wrap:anywhere]', $nombreArchivo->getAttribute('class'));
        $revision = $xpath->query('//section[@aria-labelledby="titulo-paso-4"]')->item(0);
        foreach ($xpath->query('.//*[self::dl or self::ul][contains(@class,"grid")]', $revision) as $grupo) {
            $this->assertStringContainsString('grid-cols-1', $grupo->getAttribute('class'));
        }
        foreach (['motivo', 'entrada.descripcion'] as $contenido) {
            $nodo = $xpath->query('.//*[@x-text="'.$contenido.'"]', $revision)->item(0);
            $this->assertStringContainsString('whitespace-pre-wrap', $nodo->getAttribute('class'));
            $this->assertStringContainsString('break-words', $nodo->getAttribute('class'));
            $this->assertStringContainsString('[overflow-wrap:anywhere]', $nodo->getAttribute('class'));
        }
    }

    public function test_empty_and_technical_error_have_distinct_semantics_and_reachable_actions(): void
    {
        $xpath = $this->wizard();
        $vacio = $xpath->query('//section[@aria-labelledby="titulo-paso-1"]//div[@x-show="estadoDisponibilidad === \'empty\'"]')->item(0);
        $this->assertSame('status', $vacio->getAttribute('role'));
        $this->assertSame(1, $xpath->query('.//button[@*[name()="x-on:click"]="cambiarVentana(1)"]', $vacio)->length);
        $this->assertStringContainsString('No hay horarios disponibles', $vacio->textContent);
        $error = $xpath->query('//*[@x-ref="errorDisponibilidad"]')->item(0);
        $this->assertSame('alert', $error->getAttribute('role'));
        $this->assertSame('-1', $error->getAttribute('tabindex'));
        $this->assertSame(1, $xpath->query('.//button[@*[name()="x-on:click"]="consultarDisponibilidad()"]', $error)->length);
        $this->assertStringContainsString('No pudimos consultar la disponibilidad', $error->textContent);
    }

    public function test_d01_has_a_named_alert_and_real_navigation_without_an_operational_wizard_on_entry(): void
    {
        foreach ([false, true] as $bloqueado) {
            $xpath = $this->wizard($bloqueado);
            $bloqueo = $xpath->query('//section[@id="bloqueo-primera-consulta"]')->item(0);
            $this->assertNotNull($bloqueo);
            $this->assertSame('alert', $bloqueo->getAttribute('role'));
            $this->assertSame('-1', $bloqueo->getAttribute('tabindex'));
            $this->assertSame('bloqueo-primera-consulta-titulo', $bloqueo->getAttribute('aria-labelledby'));
            $this->assertSame('Ya tenés una Primera consulta activa', trim($xpath->query('.//h2', $bloqueo)->item(0)->textContent));
            $cta = $xpath->query('.//a', $bloqueo)->item(0);
            $this->assertSame('Ir a Mis turnos', trim($cta->textContent));
            $this->assertSame(route('turnos.mis-turnos.index'), $cta->getAttribute('href'));
            $this->assertStringContainsString('focus:ring-', $cta->getAttribute('class'));
            $this->assertFalse($cta->hasAttribute('tabindex'));
            if ($bloqueado) {
                $this->assertFalse($bloqueo->hasAttribute('x-cloak'));
                $this->assertSame(0, $xpath->query('//main//*[@x-data or @x-init or @x-show] | //main//fieldset | //main//progress')->length);
                $this->assertFalse($cta->hasAttribute('x-on:click.prevent'));
            } else {
                $this->assertSame('primeraConsultaActiva', $bloqueo->getAttribute('x-show'));
                $this->assertSame('bloqueoPrimeraConsulta', $bloqueo->getAttribute('x-ref'));
                $this->assertSame('salirPorPrimeraConsultaActiva()', $cta->getAttribute('x-on:click.prevent'));
                $this->assertSame(1, $xpath->query('//div[@x-show="!primeraConsultaActiva"]//nav[@aria-label="Pasos de la solicitud"]')->length);
            }
        }
    }

    public function test_mis_turnos_has_semantic_cards_textual_state_and_an_operational_named_cta(): void
    {
        $paciente = Paciente::create(['user_id' => auth()->id(), 'activo' => true]);
        $profesional = Kinesiologa::create(['user_id' => User::factory()->create()->id]);
        $turno = Turno::factory()->for($paciente)->for($profesional)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => EstadoTurnoEnum::PENDIENTE,
            'inicio_at' => '2026-10-20 10:00:00', 'fin_at' => '2026-10-20 10:45:00',
        ]);
        $html = (string) $this->view('turnos.mis-turnos.index', [
            'proximos' => Turno::with('kinesiologa.user')->whereKey($turno->id)->paginate(10, ['*'], 'proximos_page'),
            'historicos' => Turno::whereKey([])->paginate(10, ['*'], 'historicos_page'),
            'turnoDestacadoId' => null, 'primeraConsultaActiva' => true,
        ]);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//h1[normalize-space(.)="Mis turnos"]')->length);
        $cta = $xpath->query('//header//a[@href="'.route('turnos.solicitar.create').'"]')->item(0);
        $this->assertNotNull($cta);
        $this->assertSame('Solicitar nuevo turno', trim($cta->textContent));
        $this->assertStringContainsString('focus:ring-', $cta->getAttribute('class'));
        $this->assertFalse($cta->hasAttribute('aria-disabled'));
        $this->assertSame(0, $xpath->query('//*[@tabindex > 0]')->length);
        $card = $xpath->query('//section[@aria-labelledby="proximos-titulo"]/ul/li/article')->item(0);
        $this->assertNotNull($card);
        $this->assertSame('Primera consulta', trim($xpath->query('.//h3', $card)->item(0)->textContent));
        $this->assertSame(1, $xpath->query('.//span[normalize-space(.)="Pendiente"]', $card)->length);
        $this->assertSame(1, $xpath->query('.//dt[normalize-space(.)="Fecha"]/following-sibling::dd/time[@datetime="2026-10-20"]', $card)->length);
        $this->assertSame(1, $xpath->query('.//dt[normalize-space(.)="Horario"]/following-sibling::dd/span[normalize-space(.)="10:00 – 10:45 hs"]', $card)->length);
        foreach ($xpath->query('//main//svg | //header//a/svg') as $icono) {
            $this->assertGreaterThan(0, $xpath->query('ancestor-or-self::*[@aria-hidden="true"]', $icono)->length);
        }
        foreach ($xpath->query('//main/descendant::section[@aria-labelledby]') as $seccion) {
            $this->assertSame(1, $xpath->query('.//h2[@id="'.$seccion->getAttribute('aria-labelledby').'"]', $seccion)->length);
        }
    }

    private function wizard(bool $bloqueado = false): DOMXPath
    {
        $html = (string) $this->view('turnos.solicitar.create', $bloqueado ? [
            'bloqueadoPorPrimeraConsulta' => true, 'urlMisTurnos' => route('turnos.mis-turnos.index'),
        ] : ['configuracionWizard' => [
            'uuid' => (string) Str::uuid(), 'maxMotivo' => 500, 'fechaInicial' => '2026-10-17',
            'urlMisTurnos' => route('turnos.mis-turnos.index'), 'urlDisponibilidad' => route('turnos.solicitar.disponibilidad'),
            'urlConfirmacion' => route('turnos.solicitar.store'),
            'urlProfesionalesDisponibles' => route('turnos.solicitar.profesionales-disponibles'),
            'limites' => ['maxArchivos' => 5, 'maxBytesPorArchivo' => 10485760, 'maxBytesTotal' => 26214400,
                'mimePermitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'extensionesPermitidas' => ['pdf', 'jpg', 'jpeg', 'png']],
        ]]);
        // libxml HTML4 descarta @click/@change; x-on es el alias equivalente de Alpine.
        $html = preg_replace('/\s@([a-z][a-z.-]*)=/', ' x-on:$1=', $html);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }
}
