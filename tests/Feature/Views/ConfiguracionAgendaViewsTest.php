<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\DataProvider;

class ConfiguracionAgendaViewsTest extends AgendaViewTestCase
{
    #[DataProvider('estadosConfiguracion')]
    public function test_detalle_renderiza_cada_estado_y_sus_acciones_legales(
        string $desde,
        ?string $hasta,
        ?string $anuladoAt,
        string $estado,
        bool $muestraAcciones
    ): void {
        $actor = $this->actor();
        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            $desde,
            $hasta,
            $anuladoAt,
            duracion: 60
        );

        $response = $this->actingAs($actor)->get(route(
            'turnos.agenda.configuraciones.show',
            ['configuracion' => $configuracion, 'origen' => 'dashboard']
        ));

        $response
            ->assertOk()
            ->assertSee('Consultar configuración de agenda')
            ->assertSee($estado)
            ->assertSee($configuracion->vigencia_desde->format('d/m/Y'))
            ->assertSee('60 minutos')
            ->assertSee('Matriz semanal')
            ->assertSee('Lunes')
            ->assertSee('Domingo')
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('Sin atención');

        if ($muestraAcciones) {
            $response->assertSee('Editar')->assertSee('Anular');
        } else {
            $response
                ->assertDontSee('>Editar<', false)
                ->assertDontSee('>Anular<', false);
        }

        if ($estado === 'Anulada') {
            $response
                ->assertSee('Trazabilidad de la anulación')
                ->assertSee('05/10/2026 09:00')
                ->assertSee('Cambio de planificación');
        }

        if ($estado === 'Histórica') {
            $response->assertSee('sólo lectura');
        }
    }

    public static function estadosConfiguracion(): array
    {
        return [
            'vigente' => ['2026-10-01', null, null, 'Vigente', false],
            'futura' => ['2026-10-10', null, null, 'Futura', true],
            'histórica' => ['2026-09-01', '2026-09-30', null, 'Histórica', false],
            'anulada' => ['2026-10-10', null, '2026-10-05 09:00:00', 'Anulada', false],
        ];
    }

    public function test_detalle_conserva_retorno_seguro_a_historial_y_descarta_origen_arbitrario(): void
    {
        $actor = $this->actor();
        $historica = $this->configuracion($actor->kinesiologa, '2026-09-01', '2026-09-30');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.show', [
                'configuracion' => $historica,
                'origen' => 'historial',
                'page' => 3,
            ]))
            ->assertOk()
            ->assertSee(route('turnos.agenda.configuraciones.historial', ['page' => 3]), false);

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.show', [
                'configuracion' => $historica,
                'origen' => 'https://example.com/robo',
                'page' => 8,
            ]))
            ->assertOk()
            ->assertSee(route('turnos.agenda.index'), false)
            ->assertDontSee('https://example.com/robo', false);
    }

    public function test_historial_renderiza_solo_historicas_y_anuladas_en_orden(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-09-01', '2026-09-30', duracion: 30);
        $this->configuracion($actor->kinesiologa, '2026-10-20', null, '2026-10-05 09:00:00', 60);
        $this->configuracion($actor->kinesiologa, '2026-10-01');
        $this->configuracion($actor->kinesiologa, '2026-11-01');

        $response = $this->actingAs($actor)->get(route('turnos.agenda.configuraciones.historial'));

        $response
            ->assertOk()
            ->assertSee('Historial de configuraciones')
            ->assertSeeInOrder(['20/10/2026', '01/09/2026'])
            ->assertSee('Anulada')
            ->assertSee('Histórica')
            ->assertSee('1 día(s), 1 franja(s)')
            ->assertSee('Consultar')
            ->assertDontSee('01/10/2026')
            ->assertDontSee('01/11/2026')
            ->assertDontSee('>Editar<', false)
            ->assertDontSee('>Anular<', false);
    }

    public function test_historial_pagina_diez_preserva_contexto_y_excluye_otra_kinesiologa(): void
    {
        $actor = $this->actor();
        $otra = $this->actor('otra');

        foreach (range(1, 11) as $indice) {
            $this->configuracion(
                $actor->kinesiologa,
                sprintf('2026-09-%02d', $indice),
                sprintf('2026-09-%02d', $indice)
            );
        }
        $this->configuracion($otra->kinesiologa, '2026-08-01', '2026-08-02', duracion: 99);

        $response = $this->actingAs($actor)->get(route('turnos.agenda.configuraciones.historial'));
        $paginador = $response->viewData('configuraciones');

        $response
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('origen=historial', false)
            ->assertDontSee('99 minutos');
        $this->assertCount(10, $paginador->items());
        $this->assertSame(11, $paginador->total());
    }

    public function test_historial_vacio_muestra_mensaje_y_retorno(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-10-01');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.historial'))
            ->assertOk()
            ->assertSee('No existen configuraciones históricas ni futuras anuladas para consultar.')
            ->assertSee('Volver a la agenda');
    }
}
