<?php

namespace Tests\Feature\Turnos;

use App\Enums\RoleEnum;
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

    private function wizard(): DOMXPath
    {
        $html = (string) $this->view('turnos.solicitar.create', ['configuracionWizard' => [
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
