<?php

namespace Tests\Feature\Views;

use App\Services\Agenda\AgendaSnapshotService;
use PHPUnit\Framework\Attributes\DataProvider;

class AnulacionConfiguracionAgendaViewTest extends AgendaViewTestCase
{
    public function test_formulario_renderiza_resumen_matriz_read_only_snapshot_y_confirmacion(): void
    {
        $actor = $this->actor();
        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-10',
            '2026-10-20',
            duracion: 60
        );
        $this->franja($configuracion, 1, '14:00:00', '18:00:00');
        $this->franja($configuracion, 3, '09:00:00', '12:00:00');

        $response = $this->actingAs($actor)->get(route(
            'turnos.agenda.configuraciones.anular.create',
            ['configuracion' => $configuracion, 'origen' => 'dashboard']
        ));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertViewIs('agenda.configuraciones.anular')
            ->assertSee('Anular configuración futura')
            ->assertSee('Futura')
            ->assertSee('10/10/2026')
            ->assertSee('20/10/2026')
            ->assertSee('60 minutos')
            ->assertSee('2')
            ->assertSee('3')
            ->assertSee('Matriz semanal de sólo lectura')
            ->assertSeeInOrder(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'])
            ->assertSee('08:00')
            ->assertSee('12:00')
            ->assertSee('14:00')
            ->assertSee('18:00')
            ->assertSee('Sin atención')
            ->assertDontSee('Agregar franja')
            ->assertDontSee('Quitar franja')
            ->assertSee('name="motivo_anulacion"', false)
            ->assertSee('Motivo de la anulación <span class="text-red-700">(obligatorio)</span>', false)
            ->assertSee('aria-describedby="ayuda-motivo-anulacion"', false)
            ->assertSee('name="snapshot"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="origen" value="dashboard"', false)
            ->assertSee('Cancelar')
            ->assertSee('Continuar con la anulación')
            ->assertSee('data-confirm-title="¿Confirmar anulación de la configuración?"', false)
            ->assertSee('data-confirm-button="Confirmar anulación"', false)
            ->assertSee('data-cancel-button="Volver"', false)
            ->assertSee('data-confirm-focus-cancel="true"', false)
            ->assertSee("focusCancel: form.dataset.confirmFocusCancel === 'true'", false)
            ->assertSee('no modificará automáticamente los turnos ya otorgados')
            ->assertDontSee('reprogramar turno', false)
            ->assertDontSee('cancelar turno', false)
            ->assertDontSee('resolver turnos', false);

        $this->assertMatchesRegularExpression('/name="snapshot" value="[^\"]+"/', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        foreach (['vigencia_desde', 'vigencia_hasta', 'duracion_turno_minutos', 'franjas', 'anulado_at', 'kinesiologa_id', 'estado'] as $campo) {
            $this->assertStringNotContainsString('name="'.$campo.'"', $html);
        }
    }

    public function test_preview_a_vinculada_con_c_muestra_extension_proyectada_y_preserva_inicio_de_c(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-10-01', '2026-10-09');
        $objetivo = $this->configuracion($actor->kinesiologa, '2026-10-10', '2026-10-19');
        $this->configuracion($actor->kinesiologa, '2026-10-20');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.anular.create', $objetivo))
            ->assertOk()
            ->assertSee('Estaba vinculada con la configuración que se anulará.')
            ->assertSee('pueda extenderse hasta el 19/10/2026')
            ->assertSee('Su inicio el 20/10/2026 se conserva')
            ->assertSee('se revalidará y recalculará al confirmar')
            ->assertDontSee('se reactivará');
    }

    public function test_preview_a_vinculada_sin_c_muestra_posible_reapertura_sin_fin(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-10-01', '2026-10-09');
        $objetivo = $this->configuracion($actor->kinesiologa, '2026-10-10');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.anular.create', $objetivo))
            ->assertOk()
            ->assertSee('pueda quedar nuevamente sin fecha de fin')
            ->assertSee('Sin configuración siguiente')
            ->assertDontSee('se reactivará');
    }

    public function test_preview_con_hueco_deliberado_conserva_periodo_y_fechas_de_a(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-10-01', '2026-10-05');
        $objetivo = $this->configuracion($actor->kinesiologa, '2026-10-10');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.anular.create', $objetivo))
            ->assertOk()
            ->assertSee('No estaba vinculada con la configuración que se anulará.')
            ->assertSee('El período sin configuración previo se conservará')
            ->assertSee('las fechas de la configuración anterior no se modificarán')
            ->assertDontSee('pueda extenderse');
    }

    public function test_preview_sin_a_no_inventa_predecesora_y_muestra_c_sin_moverla(): void
    {
        $actor = $this->actor();
        $objetivo = $this->configuracion($actor->kinesiologa, '2026-10-10', '2026-10-19');
        $this->configuracion($actor->kinesiologa, '2026-10-20');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.anular.create', $objetivo))
            ->assertOk()
            ->assertSee('Sin configuración anterior')
            ->assertSee('puede permanecer un período sin configuración hasta la siguiente vigencia')
            ->assertSee('Su inicio el 20/10/2026 se conserva')
            ->assertDontSee('Estaba vinculada con la configuración que se anulará.');
    }

    public function test_error_de_validacion_conserva_motivo_matriz_y_contexto(): void
    {
        $actor = $this->actor();
        $configuracion = $this->configuracion($actor->kinesiologa, '2026-10-10');
        $url = route('turnos.agenda.configuraciones.anular.create', [
            'configuracion' => $configuracion,
            'origen' => 'detalle',
        ]);
        $motivo = str_repeat('F', 501);

        $this->actingAs($actor)
            ->from($url)
            ->patch(route('turnos.agenda.configuraciones.anular.store', $configuracion), [
                'motivo_anulacion' => $motivo,
                'snapshot' => app(AgendaSnapshotService::class)
                    ->generarParaConfiguracion($configuracion->load('franjasHorarias')),
                'origen' => 'detalle',
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('motivo_anulacion');

        $this->get($url)
            ->assertOk()
            ->assertSee($motivo)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('autofocus', false)
            ->assertSee('id="error-motivo-anulacion"', false)
            ->assertSee('motivo de anulación no debe tener más de 500 caracteres')
            ->assertSee('Matriz semanal de sólo lectura')
            ->assertSee('08:00')
            ->assertSee(route('turnos.agenda.configuraciones.show', [
                'configuracion' => $configuracion,
                'origen' => 'detalle',
            ]), false);
    }

    public function test_cancelar_desde_historial_preserva_pagina_y_descarta_url_arbitraria(): void
    {
        $actor = $this->actor();
        $configuracion = $this->configuracion($actor->kinesiologa, '2026-10-10');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.anular.create', [
                'configuracion' => $configuracion,
                'origen' => 'historial',
                'page' => 3,
                'return_to' => 'https://example.com/robo',
            ]))
            ->assertOk()
            ->assertSee(route('turnos.agenda.configuraciones.historial', ['page' => 3]), false)
            ->assertSee('name="origen" value="historial"', false)
            ->assertSee('name="page" value="3"', false)
            ->assertDontSee('https://example.com/robo', false);
    }

    #[DataProvider('configuracionesNoElegibles')]
    public function test_configuracion_no_elegible_no_obtiene_formulario(
        string $desde,
        ?string $hasta,
        ?string $anuladoAt
    ): void {
        $actor = $this->actor();
        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            $desde,
            $hasta,
            $anuladoAt
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.configuraciones.anular.create', $configuracion))
            ->assertForbidden();
    }

    public static function configuracionesNoElegibles(): array
    {
        return [
            'comienza hoy' => ['2026-10-06', null, null],
            'histórica' => ['2026-09-01', '2026-09-30', null],
            'anulada' => ['2026-10-10', null, '2026-10-05 09:00:00'],
        ];
    }
}
