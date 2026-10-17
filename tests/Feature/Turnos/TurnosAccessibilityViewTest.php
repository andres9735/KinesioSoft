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
        $this->assertSame(4, $xpath->query('//main//section/h2')->length);
        $this->assertSame(4, $xpath->query('//nav[@aria-label="Pasos de la solicitud"]/ol/li')->length);
        $this->assertSame(1, $xpath->query('//nav//li[@aria-current="step"]')->length);
        $this->assertSame(1, $xpath->query('//progress[@max="4" and @value="1" and @aria-label]')->length);
        foreach (['Motivo y documentación', 'Elegir profesional', 'Fecha y horario', 'Revisar y confirmar'] as $i => $nombre) {
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

    public function test_native_professional_select_and_label_cards_share_the_canonical_selection(): void
    {
        $xpath = $this->wizard();
        $select = $xpath->query('//select[@id="profesional-movil"]')->item(0);
        $this->assertNotNull($select);
        $this->assertSame("kinesiologaId ?? ''", $select->getAttribute(':value'));
        $this->assertSame('seleccionarProfesional($event.target.value)', $select->getAttribute('x-on:change'));
        $this->assertSame('profesional-error', $select->getAttribute('aria-describedby'));
        $this->assertSame(2, $xpath->query('//select/option[@value!=""]')->length);
        $radios = $xpath->query('//label/input[@type="radio" and @name="kinesiologa_id"]');
        $this->assertSame(2, $radios->length);
        foreach ($radios as $radio) {
            $this->assertStringContainsString('seleccionarProfesional(', $radio->getAttribute('x-on:change'));
            $this->assertSame('profesional-error', $radio->getAttribute('aria-describedby'));
        }
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
    }

    public function test_slots_loading_errors_and_review_have_native_semantics(): void
    {
        $xpath = $this->wizard();
        $this->assertGreaterThanOrEqual(2, $xpath->query('//main//*[@role="status"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="profesional-error" and @role="alert" and @tabindex="-1"]')->length);
        $slot = $xpath->query('//button[@*[name()="x-on:click"]="seleccionarSlot(slot.inicio_at)"]')->item(0);
        $this->assertSame('button', $slot->getAttribute('type'));
        $this->assertTrue($slot->hasAttribute(':aria-pressed'));
        $this->assertStringContainsString('fechaLegible(grupo.fecha)', $slot->getAttribute(':aria-label'));
        $this->assertSame(1, $xpath->query('ancestor::fieldset[1]/legend', $slot)->length);
        $this->assertSame(7, $xpath->query('//section[@aria-labelledby="titulo-paso-4"]//dl/div/dt')->length);
        $this->assertSame(7, $xpath->query('//section[@aria-labelledby="titulo-paso-4"]//dl/div/dd')->length);
        $confirm = $xpath->query('//button[@*[name()="x-on:click"]="confirmarTurno()"]')->item(0);
        $this->assertSame('!puedeConfirmar', $confirm->getAttribute(':disabled'));
        $this->assertSame('enviando', $confirm->getAttribute(':aria-busy'));
        $this->assertSame(1, $xpath->query('//main//fieldset[@*[name()=":disabled"]="bloqueado"]/legend')->length);
    }

    private function wizard(): DOMXPath
    {
        $html = (string) $this->view('turnos.solicitar.create', ['configuracionWizard' => [
            'uuid' => (string) Str::uuid(), 'maxMotivo' => 500, 'fechaInicial' => '2026-10-17',
            'profesionales' => [['id' => 1, 'nombre' => 'Ana Prueba'], ['id' => 2, 'nombre' => 'Bea Prueba']],
            'urlMisTurnos' => route('turnos.mis-turnos.index'), 'urlDisponibilidad' => route('turnos.solicitar.disponibilidad'),
            'urlConfirmacion' => route('turnos.solicitar.store'),
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
