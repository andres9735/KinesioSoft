<?php

namespace Tests\Feature\Views;

class AgendaAccessibilityViewTest extends AgendaViewTestCase
{
    public function test_las_diez_pantallas_renderizan_un_unico_h1_visible(): void
    {
        $actor = $this->actor();
        $vigente = $this->configuracion($actor->kinesiologa, '2026-10-01');
        $futura = $this->configuracion($actor->kinesiologa, '2026-10-10');
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 12:00:00',
            'Congreso profesional'
        );

        $rutas = [
            'AGENDA-01' => route('turnos.agenda.index'),
            'AGENDA-02' => route('turnos.agenda.configuraciones.create'),
            'AGENDA-03' => route('turnos.agenda.configuraciones.edit', $futura),
            'AGENDA-04' => route('turnos.agenda.duracion.create'),
            'AGENDA-05' => route('turnos.agenda.configuraciones.show', $vigente),
            'AGENDA-06' => route('turnos.agenda.configuraciones.historial'),
            'AGENDA-07' => route('turnos.agenda.bloqueos.create'),
            'AGENDA-08' => route('turnos.agenda.bloqueos.show', $bloqueo),
            'AGENDA-09' => route('turnos.agenda.bloqueos.anular.create', $bloqueo),
            'AGENDA-10' => route('turnos.agenda.configuraciones.anular.create', $futura),
        ];

        foreach ($rutas as $pantalla => $ruta) {
            $contenido = $this->actingAs($actor)->get($ruta)->assertOk()->getContent();

            $this->assertSame(
                1,
                preg_match_all('/<h1\b/i', $contenido),
                "{$pantalla} debe renderizar exactamente un h1."
            );
        }
    }

    public function test_formularios_representativos_exponen_labels_grupos_y_errores_accesibles(): void
    {
        $actor = $this->actor();
        $futura = $this->configuracion($actor->kinesiologa, '2026-10-10');
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 12:00:00',
            'Congreso profesional'
        );

        $configuracion = $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.create'));
        $configuracion
            ->assertOk()
            ->assertSee('for="vigencia_desde"', false)
            ->assertSee('for="duracion_turno_minutos"', false)
            ->assertSee(':aria-label="\'Hora de inicio de Lunes, franja \' + (posicion + 1)"', false)
            ->assertSee('id="agenda-agregar-franja-1"', false)
            ->assertSee('@click="confirmarCancelacion($event)"', false);
        $this->assertSame(7, substr_count($configuracion->getContent(), '<fieldset'));
        $this->assertSame(7, substr_count($configuracion->getContent(), '<legend'));

        $captura = $this->get(route('turnos.agenda.bloqueos.create'));
        $captura
            ->assertOk()
            ->assertSee('id="selector-modo-bloqueo"', false)
            ->assertSee('aria-describedby="ayuda-modo-bloqueo"', false)
            ->assertSee('Fecha inicial <span class="text-red-700">(obligatorio)</span>', false)
            ->assertSee('Hora final <span class="text-red-700">(obligatorio)</span>', false)
            ->assertSee('Motivo <span class="text-red-700">(obligatorio)</span>', false);

        foreach ([
            route('turnos.agenda.bloqueos.anular.create', $bloqueo),
            route('turnos.agenda.configuraciones.anular.create', $futura),
        ] as $rutaAnulacion) {
            $this->get($rutaAnulacion)
                ->assertOk()
                ->assertSee('for="motivo_anulacion"', false)
                ->assertSee('(obligatorio)')
                ->assertSee('aria-invalid="false"', false)
                ->assertSee('aria-describedby="ayuda-motivo-anulacion"', false);
        }
    }

    public function test_listados_desktop_y_mobile_conservan_datos_criticos_y_escapan_motivos(): void
    {
        $actor = $this->actor();
        $motivo = '<script>alert(1)</script>'.str_repeat(' motivo-extenso', 20);
        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 12:00:00',
            $motivo
        );
        $this->configuracion($actor->kinesiologa, '2026-09-01', '2026-09-30');

        $dashboard = $this->actingAs($actor)
            ->get(route('turnos.agenda.index', ['filtro' => 'proximos']));
        $dashboard
            ->assertOk()
            ->assertSee('hidden md:block', false)
            ->assertSee('md:hidden', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('break-words', false)
            ->assertSee('Consultar')
            ->assertSee('Anular');
        $this->assertGreaterThanOrEqual(2, substr_count($dashboard->getContent(), e($motivo)));

        $historial = $this->get(route('turnos.agenda.configuraciones.historial'));
        $historial
            ->assertOk()
            ->assertSee('hidden md:block', false)
            ->assertSee('md:hidden', false)
            ->assertSee('Desde 01/09/2026')
            ->assertSee('30/09/2026')
            ->assertSee('Duración')
            ->assertSee('Resumen')
            ->assertSee('Histórica')
            ->assertSee('Consultar');
    }
}
