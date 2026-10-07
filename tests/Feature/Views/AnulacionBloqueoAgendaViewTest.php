<?php

namespace Tests\Feature\Views;

use App\Services\Agenda\AgendaSnapshotService;
use PHPUnit\Framework\Attributes\DataProvider;

class AnulacionBloqueoAgendaViewTest extends AgendaViewTestCase
{
    public function test_formulario_renderiza_datos_read_only_snapshot_contexto_y_confirmacion_final(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 12:00:00',
            'Congreso profesional'
        );

        $response = $this->actingAs($actor)->get(route(
            'turnos.agenda.bloqueos.anular.create',
            [
                'bloqueo' => $bloqueo,
                'origen' => 'bloqueos',
                'filtro' => 'proximos',
                'bloqueos_page' => 2,
            ]
        ));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertViewIs('agenda.bloqueos.anular')
            ->assertSee('Anular bloqueo')
            ->assertSee('Próximo')
            ->assertSee('07/10/2026 09:00')
            ->assertSee('07/10/2026 12:00')
            ->assertSee('Congreso profesional')
            ->assertSee($bloqueo->created_at->format('d/m/Y H:i'))
            ->assertSee('name="motivo_anulacion"', false)
            ->assertSee('maxlength="500"', false)
            ->assertSee('required', false)
            ->assertSee('name="snapshot"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="origen" value="bloqueos"', false)
            ->assertSee('name="filtro" value="proximos"', false)
            ->assertSee('name="bloqueos_page" value="2"', false)
            ->assertSee('Cancelar')
            ->assertSee('Continuar con la anulación')
            ->assertSee('js-confirm-action', false)
            ->assertSee('data-confirm-title="¿Confirmar anulación del bloqueo?"', false)
            ->assertSee('data-confirm-button="Confirmar anulación"', false)
            ->assertSee('data-cancel-button="Volver"', false)
            ->assertSee('data-confirm-focus-cancel="true"', false)
            ->assertSee("focusCancel: form.dataset.confirmFocusCancel === 'true'", false);

        $this->assertMatchesRegularExpression('/name="snapshot" value="[^\"]+"/', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        foreach (['inicio_at', 'fin_at', 'motivo', 'kinesiologa_id', 'anulado_at', 'estado', 'tipo'] as $campo) {
            $this->assertStringNotContainsString('name="'.$campo.'"', $html);
        }
    }

    #[DataProvider('estadosElegibles')]
    public function test_formulario_admite_bloqueos_no_anulados_proximos_en_curso_y_pasados(
        string $inicio,
        string $fin,
        string $estado
    ): void {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo($actor->kinesiologa, $inicio, $fin, 'Corrección necesaria');

        $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.anular.create', $bloqueo))
            ->assertOk()
            ->assertSee($estado)
            ->assertSee('Continuar con la anulación');
    }

    public static function estadosElegibles(): array
    {
        return [
            'próximo' => ['2026-10-07 09:00:00', '2026-10-07 10:00:00', 'Próximo'],
            'en curso' => ['2026-10-06 11:00:00', '2026-10-06 13:00:00', 'En curso'],
            'pasado' => ['2026-10-05 09:00:00', '2026-10-05 10:00:00', 'Pasado'],
        ];
    }

    public function test_bloqueo_ya_anulado_no_obtiene_un_segundo_formulario(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:00:00',
            'Registro anulado',
            '2026-10-05 08:00:00'
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.anular.create', $bloqueo))
            ->assertForbidden();
    }

    public function test_muestra_solo_otros_bloqueos_activos_de_la_misma_kinesiologa_y_escapa_motivos(): void
    {
        $actor = $this->actor();
        $otra = $this->actor('otra');
        $principal = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 12:00:00',
            '<em>Motivo original</em>'
        );
        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 13:00:00',
            '<script>alert("visible")</script>'
        );
        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 10:00:00',
            '2026-10-07 11:00:00',
            'Bloqueo anulado oculto',
            '2026-10-06 08:00:00'
        );
        $this->bloqueo(
            $otra->kinesiologa,
            '2026-10-07 10:00:00',
            '2026-10-07 11:00:00',
            'Bloqueo ajeno oculto'
        );

        $response = $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.anular.create', $principal));

        $response
            ->assertOk()
            ->assertSee('El intervalo puede continuar afectado')
            ->assertSee('&lt;em&gt;Motivo original&lt;/em&gt;', false)
            ->assertSee('&lt;script&gt;alert(&quot;visible&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<em>Motivo original</em>', false)
            ->assertDontSee('<script>alert("visible")</script>', false)
            ->assertDontSee('Bloqueo anulado oculto')
            ->assertDontSee('Bloqueo ajeno oculto')
            ->assertDontSee('El horario quedará disponible')
            ->assertDontSee('Disponible nuevamente');
    }

    public function test_sin_superposiciones_usa_un_mensaje_prudente_sin_prometer_disponibilidad(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:00:00',
            'Bloqueo aislado'
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.anular.create', $bloqueo))
            ->assertOk()
            ->assertSee('No se encontraron otros bloqueos no anulados superpuestos.')
            ->assertSee('La disponibilidad se recalculará')
            ->assertSee('los turnos existentes y otras restricciones')
            ->assertDontSee('El horario quedará disponible');
    }

    public function test_error_de_validacion_conserva_motivo_y_contexto_saneado(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-05 09:00:00',
            '2026-10-05 10:00:00',
            'Carga incorrecta'
        );
        $url = route('turnos.agenda.bloqueos.anular.create', [
            'bloqueo' => $bloqueo,
            'origen' => 'detalle',
            'filtro' => 'pasados',
            'bloqueos_page' => 3,
        ]);
        $motivo = str_repeat('M', 501);

        $this->actingAs($actor)
            ->from($url)
            ->patch(route('turnos.agenda.bloqueos.anular.store', $bloqueo), [
                'motivo_anulacion' => $motivo,
                'snapshot' => app(AgendaSnapshotService::class)->generarParaBloqueo($bloqueo),
                'origen' => 'detalle',
                'filtro' => 'pasados',
                'bloqueos_page' => 3,
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('motivo_anulacion');

        $this->get($url)
            ->assertOk()
            ->assertSee($motivo)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="error-motivo-anulacion"', false)
            ->assertSee('motivo de anulación no debe tener más de 500 caracteres')
            ->assertSee(route('turnos.agenda.bloqueos.show', [
                'bloqueo' => $bloqueo,
                'origen' => 'detalle',
                'filtro' => 'pasados',
                'bloqueos_page' => 3,
            ]));
    }

    public function test_cancelar_desde_listado_preserva_contexto_seguro_y_descarta_url_arbitraria(): void
    {
        $actor = $this->actor();
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-05 09:00:00',
            '2026-10-05 10:00:00',
            'Corrección'
        );

        $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.anular.create', [
                'bloqueo' => $bloqueo,
                'origen' => 'https://example.com/robo',
                'filtro' => 'pasados',
                'bloqueos_page' => 4,
                'return_to' => 'https://example.com/robo',
            ]))
            ->assertOk()
            ->assertSee(route('turnos.agenda.index', [
                'filtro' => 'pasados',
                'bloqueos_page' => 4,
            ]))
            ->assertDontSee('https://example.com/robo', false);
    }
}
