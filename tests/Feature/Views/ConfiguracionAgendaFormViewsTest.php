<?php

namespace Tests\Feature\Views;

use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;

class ConfiguracionAgendaFormViewsTest extends AgendaViewTestCase
{
    public function test_primera_configuracion_renderiza_defaults_matriz_completa_y_contrato_seguro(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->get(route('turnos.agenda.configuraciones.create'));

        $response
            ->assertOk()
            ->assertViewIs('agenda.configuraciones.create')
            ->assertSee('Crear primera configuración')
            ->assertSeeInOrder(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'])
            ->assertSee('08:00')
            ->assertSee('17:00')
            ->assertSee('45')
            ->assertSee('min="2026-10-06"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_token"', false)
            ->assertSee(route('turnos.agenda.configuraciones.store'), false)
            ->assertSee('matrizSemanalAgenda', false)
            ->assertSee('Confirmar guardado')
            ->assertSee('Esta configuración comenzará hoy')
            ->assertDontSee('name="kinesiologa_id"', false)
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('name="snapshot"', false)
            ->assertDontSee('name="id"', false);

        $this->assertSame(7, substr_count($response->getContent(), '<fieldset'));
        $this->assertSame(7, substr_count($response->getContent(), 'Agregar franja para'));
        $this->assertDatabaseCount('configuraciones_agenda', 0);
        $this->assertDatabaseCount('franjas_horarias_agenda', 0);
    }

    public function test_nueva_configuracion_sin_origen_no_inventa_defaults_ni_copia_matriz(): void
    {
        $actor = $this->actor();
        $this->configuracion($actor->kinesiologa, '2026-09-01', '2026-09-30');

        $response = $this->actingAs($actor)->get(route('turnos.agenda.configuraciones.create'));

        $response
            ->assertOk()
            ->assertSee('Nueva configuración de agenda')
            ->assertSee('La nueva configuración comienza vacía')
            ->assertDontSee('08:00')
            ->assertDontSee('17:00');

        $this->assertSame(1, ConfiguracionAgenda::query()->count());
        $this->assertSame(1, FranjaHorariaAgenda::query()->count());
    }

    public function test_edicion_precarga_multiples_franjas_ordenadas_snapshot_y_retorno_permitido(): void
    {
        $actor = $this->actor();
        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-20',
            '2026-11-30',
            duracion: 30,
            conFranja: false
        );
        $this->franja($configuracion, 1, '14:00:00', '18:00:00');
        $this->franja($configuracion, 3, '09:00:00', '13:00:00');
        $this->franja($configuracion, 1, '08:00:00', '12:00:00');

        $response = $this->actingAs($actor)->get(route('turnos.agenda.configuraciones.edit', [
            'configuracion' => $configuracion,
            'origen' => 'detalle',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('agenda.configuraciones.edit')
            ->assertSee('Editar configuración futura')
            ->assertSee('Futura')
            ->assertSeeInOrder(['08:00', '12:00', '14:00', '18:00', '09:00', '13:00'])
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="snapshot"', false)
            ->assertSee('2026-10-20')
            ->assertSee('2026-11-30')
            ->assertSee('30')
            ->assertSee('name="origen" value="detalle"', false)
            ->assertSee(route('turnos.agenda.configuraciones.show', $configuracion), false)
            ->assertDontSee('name="kinesiologa_id"', false)
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('franjas[0][id]', false);

        $this->assertMatchesRegularExpression(
            '/name="snapshot" value="[^"]+"/',
            $response->getContent()
        );
        $this->assertStringNotContainsString('name="updated_at"', $response->getContent());

        $this->assertSame(1, ConfiguracionAgenda::query()->count());
        $this->assertSame(3, FranjaHorariaAgenda::query()->count());
    }

    public function test_cambio_duracion_hereda_matriz_y_no_expone_vigencia_hasta(): void
    {
        $actor = $this->actor();
        $origen = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-01',
            null,
            duracion: 45,
            conFranja: false
        );
        $this->franja($origen, 1, '08:00:00', '12:00:00');
        $this->franja($origen, 1, '14:00:00', '18:00:00');
        $this->franja($origen, 3, '09:00:00', '13:00:00');

        $response = $this->actingAs($actor)->get(route('turnos.agenda.duracion.create'));

        $response
            ->assertOk()
            ->assertViewIs('agenda.configuraciones.cambiar-duracion')
            ->assertSee('Cambiar duración estándar')
            ->assertSee('45 minutos')
            ->assertSeeInOrder(['08:00', '12:00', '14:00', '18:00', '09:00', '13:00'])
            ->assertSee('min="2026-10-07"', false)
            ->assertSee('x-text="mensajeCompatibilidad"', false)
            ->assertSee('name="snapshot"', false)
            ->assertSee(route('turnos.agenda.duracion.store'), false)
            ->assertDontSee('name="vigencia_hasta"', false)
            ->assertDontSee('name="kinesiologa_id"', false)
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('franjas[0][id]', false);

        $this->assertMatchesRegularExpression(
            '/name="snapshot" value="[^"]+"/',
            $response->getContent()
        );

        $this->assertSame(1, ConfiguracionAgenda::query()->count());
        $this->assertSame(3, FranjaHorariaAgenda::query()->count());
    }

    public function test_rechazo_backend_conserva_old_input_parcial_y_tres_franjas_del_mismo_dia(): void
    {
        $actor = $this->actor();
        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-20',
            null,
            conFranja: false
        );
        $this->franja($configuracion, 1, '08:00:00', '12:00:00');

        $urlEdicion = route('turnos.agenda.configuraciones.edit', [
            'configuracion' => $configuracion,
            'origen' => 'dashboard',
        ]);
        $getInicial = $this->actingAs($actor)->get($urlEdicion);

        $post = $this->from($urlEdicion)->put(route(
            'turnos.agenda.configuraciones.update',
            $configuracion
        ), [
            'vigencia_desde' => '2026-10-22',
            'vigencia_hasta' => '2026-12-05',
            'duracion_turno_minutos' => 55,
            'snapshot' => $getInicial->viewData('snapshot'),
            'origen' => 'dashboard',
            'franjas' => [
                ['dia_semana' => 1, 'hora_inicio' => '06:15', 'hora_fin' => '09:15'],
                ['dia_semana' => 1, 'hora_inicio' => '10:15', 'hora_fin' => '13:15'],
                ['dia_semana' => 1, 'hora_inicio' => '14:15', 'hora_fin' => ''],
            ],
        ]);

        $post->assertRedirect($urlEdicion)->assertSessionHasErrors('franjas.2.hora_fin');

        $response = $this->get($urlEdicion);

        $response
            ->assertOk()
            ->assertSee('No pudimos actualizar la configuración')
            ->assertSee('role="alert"', false)
            ->assertSee('2026-10-22')
            ->assertSee('2026-12-05')
            ->assertSee('55')
            ->assertSee('06:15')
            ->assertSee('09:15')
            ->assertSee('10:15')
            ->assertSee('13:15')
            ->assertSee('14:15')
            ->assertSee('franjas.2.hora_fin', false)
            ->assertDontSee('08:00');

        $this->assertSame('08:00:00', $configuracion->franjasHorarias()->sole()->hora_inicio);
        $this->assertSame(1, FranjaHorariaAgenda::query()->count());
    }

    public function test_create_con_errores_conserva_fechas_duracion_matriz_y_fila_parcial(): void
    {
        $actor = $this->actor();
        $url = route('turnos.agenda.configuraciones.create');

        $post = $this->actingAs($actor)->from($url)->post(
            route('turnos.agenda.configuraciones.store'),
            [
                'vigencia_desde' => '2026-10-08',
                'vigencia_hasta' => '2026-12-20',
                'duracion_turno_minutos' => 55,
                'franjas' => [
                    ['dia_semana' => 2, 'hora_inicio' => '07:10', 'hora_fin' => '10:10'],
                    ['dia_semana' => 2, 'hora_inicio' => '11:10', 'hora_fin' => ''],
                ],
            ]
        );

        $post->assertRedirect($url)->assertSessionHasErrors('franjas.1.hora_fin');

        $response = $this->get($url);

        $response
            ->assertOk()
            ->assertSee('No pudimos guardar la configuración')
            ->assertSee('2026-10-08')
            ->assertSee('2026-12-20')
            ->assertSee('55')
            ->assertSee('07:10')
            ->assertSee('10:10')
            ->assertSee('11:10')
            ->assertSee('franjas.1.hora_fin', false);

        $this->assertDatabaseCount('configuraciones_agenda', 0);
        $this->assertDatabaseCount('franjas_horarias_agenda', 0);
    }

    public function test_cambio_duracion_con_errores_conserva_old_input_completo_y_parcial(): void
    {
        $actor = $this->actor();
        $origen = $this->configuracion($actor->kinesiologa, '2026-10-01', null, duracion: 45);
        $url = route('turnos.agenda.duracion.create');
        $getInicial = $this->actingAs($actor)->get($url);

        $post = $this->from($url)->post(route('turnos.agenda.duracion.store'), [
            'vigencia_desde' => '2026-10-09',
            'duracion_turno_minutos' => 70,
            'snapshot' => $getInicial->viewData('snapshot'),
            'franjas' => [
                ['dia_semana' => 4, 'hora_inicio' => '08:25', 'hora_fin' => '12:25'],
                ['dia_semana' => 4, 'hora_inicio' => '14:25', 'hora_fin' => ''],
            ],
        ]);

        $post->assertRedirect($url)->assertSessionHasErrors('franjas.1.hora_fin');

        $response = $this->get($url);

        $response
            ->assertOk()
            ->assertSee('No pudimos programar la nueva duración')
            ->assertSee('2026-10-09')
            ->assertSee('70')
            ->assertSee('08:25')
            ->assertSee('12:25')
            ->assertSee('14:25')
            ->assertSee('franjas.1.hora_fin', false)
            ->assertDontSee('name="vigencia_hasta"', false);

        $this->assertSame(1, ConfiguracionAgenda::query()->count());
        $this->assertSame(1, $origen->franjasHorarias()->count());
    }

    public function test_errores_de_campos_y_matriz_se_asocian_y_resumen_en_region_alerta(): void
    {
        $actor = $this->actor();
        $url = route('turnos.agenda.configuraciones.create');

        $this->actingAs($actor)
            ->from($url)
            ->post(route('turnos.agenda.configuraciones.store'), [
                'vigencia_desde' => '',
                'vigencia_hasta' => '',
                'duracion_turno_minutos' => 14,
                'franjas' => [],
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors([
                'vigencia_desde',
                'duracion_turno_minutos',
                'franjas',
            ]);

        $response = $this->get($url);

        $response
            ->assertOk()
            ->assertSee('id="resumen-errores"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('id="error-vigencia-desde"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="error-duracion"', false)
            ->assertSee('id="error-franjas"', false);

        $this->assertDatabaseCount('configuraciones_agenda', 0);
    }

    public function test_markup_de_matriz_expone_payload_plano_controles_accesibles_y_resumen_reactivo(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->get(route('turnos.agenda.configuraciones.create'));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee("'franjas[' + indiceFranja(franja) + '][dia_semana]'", false)
            ->assertSee("'franjas[' + indiceFranja(franja) + '][hora_inicio]'", false)
            ->assertSee("'franjas[' + indiceFranja(franja) + '][hora_fin]'", false)
            ->assertSee('type="time"', false)
            ->assertSee('step="60"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Quitar franja de Lunes', false)
            ->assertSee('id="agenda-agregar-franja-1"', false)
            ->assertSee("'Hora de inicio de Lunes, franja ' + (posicion + 1)", false)
            ->assertSee('x-text="resumenMatriz"', false)
            ->assertSee('x-show="franjasDia(7).length === 0"', false);

        $js = file_get_contents(resource_path('js/agenda/matriz-semanal.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString('confirmarCancelacion(event)', $js);
        $this->assertStringContainsString('destino?.focus()', $js);

        $this->assertStringNotContainsString('[frontend_key]', $html);
        $this->assertStringNotContainsString('[key]', $html);
        $this->assertStringNotContainsString('[id]', $html);
        $this->assertStringNotContainsString('novalidate', $html);

        foreach ([
            'kinesiologa_id',
            'anulado_at',
            'motivo_anulacion',
            'estado',
            'activo',
            'dia_habilitado',
            'duracion_franja',
            'frontendKey',
        ] as $campoInterno) {
            $this->assertStringNotContainsString('name="'.$campoInterno.'"', $html);
        }
    }
}
