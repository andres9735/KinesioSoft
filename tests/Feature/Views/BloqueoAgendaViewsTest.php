<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\DataProvider;

class BloqueoAgendaViewsTest extends AgendaViewTestCase
{
    #[DataProvider('estadosBloqueo')]
    public function test_detalle_renderiza_estado_intervalo_trazabilidad_y_accion_legal(
        string $inicio,
        string $fin,
        ?string $anuladoAt,
        string $estado,
        bool $muestraAnular
    ): void {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            $inicio,
            $fin,
            'Congreso profesional',
            $anuladoAt
        );

        $response = $this->actingAs($actor)->get(route(
            'turnos.agenda.bloqueos.show',
            ['bloqueo' => $bloqueo, 'filtro' => 'todos', 'bloqueos_page' => 2]
        ));

        $response
            ->assertOk()
            ->assertSee('Consultar bloqueo')
            ->assertSee($estado)
            ->assertSee($bloqueo->inicio_at->format('d/m/Y H:i'))
            ->assertSee($bloqueo->fin_at->format('d/m/Y H:i'))
            ->assertSee('Congreso profesional')
            ->assertSee($bloqueo->created_at->format('d/m/Y H:i'))
            ->assertDontSee('>Editar<', false)
            ->assertDontSee('>Eliminar<', false);

        if ($muestraAnular) {
            $response->assertSee('Anular bloqueo');
        } else {
            $response
                ->assertDontSee('>Anular bloqueo<', false)
                ->assertSee('Trazabilidad de la anulación')
                ->assertSee('05/10/2026 08:00')
                ->assertSee('Corrección administrativa');
        }
    }

    public static function estadosBloqueo(): array
    {
        return [
            'próximo' => ['2026-10-07 09:00:00', '2026-10-07 10:00:00', null, 'Próximo', true],
            'en curso' => ['2026-10-06 11:00:00', '2026-10-06 13:00:00', null, 'En curso', true],
            'pasado' => ['2026-10-05 09:00:00', '2026-10-05 10:00:00', null, 'Pasado', true],
            'anulado' => ['2026-10-07 09:00:00', '2026-10-07 10:00:00', '2026-10-05 08:00:00', 'Anulado', false],
        ];
    }

    public function test_detalle_muestra_otros_bloqueos_superpuestos_y_excluye_el_propio(): void
    {
        $actor = $this->actor();
        $principal = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 12:00:00',
            'Bloqueo principal'
        );
        $otro = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 14:00:00',
            'Superposición visible'
        );

        $response = $this->actingAs($actor)->get(route('turnos.agenda.bloqueos.show', $principal));

        $response
            ->assertOk()
            ->assertSee('Otros bloqueos superpuestos')
            ->assertSee('Superposición visible')
            ->assertSee($otro->inicio_at->format('d/m/Y H:i'));
        $this->assertSame([$otro->id], $response->viewData('otrosBloqueosSuperpuestos')->modelKeys());
    }

    public function test_detalle_sin_superposiciones_informa_sin_prometer_disponibilidad(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:00:00',
            'Bloqueo aislado'
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.show', $bloqueo))
            ->assertOk()
            ->assertSee('No se encontraron otros bloqueos no anulados superpuestos.')
            ->assertDontSee('Horario disponible')
            ->assertDontSee('Intervalo libre')
            ->assertDontSee('Turno libre')
            ->assertDontSee('Disponible nuevamente');
    }

    public function test_detalle_conserva_filtro_y_pagina_en_el_retorno_seguro(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-05 09:00:00',
            '2026-10-05 10:00:00',
            'Bloqueo histórico'
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.show', [
                'bloqueo' => $bloqueo,
                'filtro' => 'pasados',
                'bloqueos_page' => 3,
                'return_to' => 'https://example.com/robo',
            ]))
            ->assertOk()
            ->assertSee('filtro=pasados', false)
            ->assertSee('bloqueos_page=3', false)
            ->assertDontSee('https://example.com/robo', false);
    }
}
