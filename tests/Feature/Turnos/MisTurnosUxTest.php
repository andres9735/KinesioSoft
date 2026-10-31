<?php

namespace Tests\Feature\Turnos;

use App\Models\Paciente;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FixturesAutogestionTurnos;
use Tests\TestCase;

class MisTurnosUxTest extends TestCase
{
    use RefreshDatabase;

    private FixturesAutogestionTurnos $fixtures;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 10:00:00', config('app.timezone')));
        $this->fixtures = new FixturesAutogestionTurnos;
        $this->paciente = $this->fixtures->paciente();
        $this->actingAs($this->paciente->user);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_system_font_and_skip_link_are_scoped_to_mis_turnos(): void
    {
        $turno = $this->fixtures->turno($this->paciente, $this->fixtures->profesional());
        foreach ([route('turnos.mis-turnos.index'), route('turnos.mis-turnos.show', $turno)] as $url) {
            $response = $this->get($url)->assertOk()->assertDontSee('fonts.bunny.net');
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//body[contains(@class,"uc06-mis-turnos")]')->length);
            $this->assertSame(1, $xpath->query('//a[@href="#mis-turnos-contenido" and contains(@class,"focus:not-sr-only")]')->length);
            $this->assertSame(1, $xpath->query('//main[@id="mis-turnos-contenido" and @tabindex="-1"]')->length);
        }
        $this->get(route('turnos.solicitar.create'))->assertOk()->assertSee('fonts.bunny.net')->assertDontSee('uc06-mis-turnos');
    }

    public function test_filtered_empty_results_offer_a_real_reset_without_old_pages(): void
    {
        $this->fixtures->turno($this->paciente, $this->fixtures->profesional());
        $response = $this->get(route('turnos.mis-turnos.index', ['profesional' => 'NoExisteFixture', 'proximos_page' => 4]))->assertOk();
        $response->assertSee('No hay turnos que coincidan')->assertDontSee('Todavía no tenés turnos registrados');
        $xpath = $this->xpath($response->getContent());
        $link = $xpath->query('//main//a[normalize-space(.)="Mostrar todos mis turnos"]')->item(0);
        $this->assertNotNull($link);
        $this->assertSame(route('turnos.mis-turnos.index'), $link->getAttribute('href'));
        $this->assertStringContainsString('focus:ring-', $link->getAttribute('class'));
        $this->get($link->getAttribute('href'))->assertOk()->assertSee('Ver detalle');
    }

    public function test_unregistered_patient_keeps_the_uc07_entry_and_honest_empty_message(): void
    {
        $response = $this->get(route('turnos.mis-turnos.index'))->assertOk();
        $response->assertSee('Todavía no tenés turnos registrados')->assertDontSee('Mostrar todos mis turnos');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//header//a[@href="'.route('turnos.solicitar.create').'"]')->length);
    }

    public function test_invalid_date_range_links_the_error_to_the_correct_field(): void
    {
        $this->get(route('turnos.mis-turnos.index', ['fecha_desde' => '2026-11-20', 'fecha_hasta' => '2026-11-01']))
            ->assertRedirect(route('turnos.mis-turnos.index'))->assertSessionHasErrors('fecha_hasta');
        $response = $this->get(route('turnos.mis-turnos.index'))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@role="alert"]//a[@href="#fecha_hasta"]')->length);
        $field = $xpath->query('//input[@id="fecha_hasta"]')->item(0);
        $this->assertSame('true', $field->getAttribute('aria-invalid'));
        $this->assertSame('2026-11-01', $field->getAttribute('value'));
        $this->assertStringContainsString('fecha_hasta-error', $field->getAttribute('aria-describedby'));
    }

    public function test_detail_recovery_and_section_focus_preserve_filters_and_independent_pages(): void
    {
        $turno = $this->fixtures->turno($this->paciente, $this->fixtures->profesional());
        $response = $this->get(route('turnos.mis-turnos.show', ['turno' => $turno,
            'estado' => 'PENDIENTE', 'proximos_page' => 2, 'historicos_page' => 3, 'seccion' => 'proximos']))->assertOk();
        $response->assertSee('urlVolver', false)->assertSee('volverAlListado', false);
        $xpath = $this->xpath($response->getContent());
        $back = $xpath->query('//header//a[normalize-space(.)="Volver a Mis turnos"]')->item(0);
        parse_str(parse_url($back->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame('PENDIENTE', $query['estado']);
        $this->assertSame('2', $query['proximos_page']);
        $this->assertSame('3', $query['historicos_page']);
        $this->assertSame('proximos-titulo', parse_url($back->getAttribute('href'), PHP_URL_FRAGMENT));
        $this->assertSame(1, $xpath->query('//*[@*[name()="x-ref"]="resultado" and @tabindex="-1" and @role="status" and @aria-live="polite"]')->length);
        $listing = $this->xpath($this->get($back->getAttribute('href'))->assertOk()->getContent());
        foreach (['proximos', 'historicos'] as $section) {
            $heading = $listing->query('//h2[@id="'.$section.'-titulo"]')->item(0);
            $this->assertNotNull($heading);
            $this->assertSame('-1', $heading->getAttribute('tabindex'));
            $this->assertStringContainsString('focus:ring-', $heading->getAttribute('class'));
        }
        $this->assertSame(0, $listing->query('//*[@tabindex > 0]')->length);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }
}
