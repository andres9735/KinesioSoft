<?php

namespace Tests\Feature\Views;

use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Enums\EstadoTurnoEnum;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;

class BloqueoAgendaFormViewTest extends AgendaViewTestCase
{
    public function test_captura_renderiza_dos_modos_presets_y_payload_canonico(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)
            ->get(route('turnos.agenda.bloqueos.create'));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertViewIs('agenda.bloqueos.create')
            ->assertSee('Registrar bloqueo excepcional')
            ->assertSee('Todo el día / rango de días completos')
            ->assertSee('Intervalo parcial')
            ->assertSee('Motivo')
            ->assertSee('Feriado')
            ->assertSee('Vacaciones')
            ->assertSee('Licencia')
            ->assertSee('Congreso')
            ->assertSee('Revisar bloqueo')
            ->assertSee('Cancelar')
            ->assertSee('action="'.route('turnos.agenda.bloqueos.review').'"', false)
            ->assertSee('name="inicio_at"', false)
            ->assertSee('name="fin_at"', false)
            ->assertSee('name="motivo"', false)
            ->assertSee('fieldset', false)
            ->assertSee('legend', false)
            ->assertDontSee('name="tipo"', false)
            ->assertDontSee('name="preset"', false)
            ->assertDontSee('name="todo_el_dia"', false)
            ->assertDontSee('name="modo"', false)
            ->assertDontSee('name="kinesiologa_id"', false)
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('name="anulado_at"', false)
            ->assertDontSee('min="2026-10-06"', false);

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'action="'.route('turnos.agenda.bloqueos.review').'"'));
        $this->assertDatabaseCount('bloqueos_agenda', 0);
    }

    public function test_review_sin_advertencias_muestra_estados_vacios_y_form_final_seguro(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)
            ->post(route('turnos.agenda.bloqueos.review'), $this->payload());
        $revision = $response->viewData('revision');
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertViewIs('agenda.bloqueos.create')
            ->assertSee('Revisar bloqueo')
            ->assertSee('07/10/2026 10:00')
            ->assertSee('07/10/2026 12:00')
            ->assertSee('Congreso profesional')
            ->assertSee('No se encontraron turnos otorgados dentro del intervalo.')
            ->assertSee('No se superpone con otros bloqueos.')
            ->assertSee('Confirmar registro')
            ->assertSee('Volver a editar')
            ->assertSee('action="'.route('turnos.agenda.bloqueos.store').'"', false)
            ->assertSee('name="snapshot"', false)
            ->assertDontSee('name="turnos_afectados"', false)
            ->assertDontSee('name="bloqueos_superpuestos"', false);

        $this->assertNotEmpty($revision['snapshot']);
        $this->assertMatchesRegularExpression('/name="snapshot" value="[^"]+"/', $html);
        $this->assertSame(1, substr_count($html, 'action="'.route('turnos.agenda.bloqueos.review').'"'));
        $this->assertSame(1, substr_count($html, 'action="'.route('turnos.agenda.bloqueos.store').'"'));
        $this->assertDatabaseCount('bloqueos_agenda', 0);
    }

    public function test_review_muestra_turnos_y_bloqueos_reales_sin_impedir_confirmacion(): void
    {
        $actor = $this->actor();
        $this->turno(
            $actor->kinesiologa,
            '2026-10-07 10:15:00',
            '2026-10-07 11:00:00',
            EstadoTurnoEnum::PENDIENTE
        );
        $this->turno(
            $actor->kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 11:45:00',
            EstadoTurnoEnum::CONFIRMADO
        );
        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:30:00',
            '<script>alert("bloqueo")</script>'
        );

        $response = $this->actingAs($actor)
            ->post(route('turnos.agenda.bloqueos.review'), $this->payload([
                'motivo' => '<script>alert("candidato")</script>',
            ]));

        $response
            ->assertOk()
            ->assertSee('Hay 2 turno(s) otorgado(s) dentro de este intervalo.')
            ->assertSee('Pendiente')
            ->assertSee('Confirmado')
            ->assertSee('Registrar el bloqueo no reprogramará ni cancelará estos turnos.')
            ->assertSee('se superpone con 1 bloqueo(s) existente(s).')
            ->assertSee('Podés continuar: los bloqueos se conservarán como registros independientes.')
            ->assertSee('Confirmar registro')
            ->assertSee('&lt;script&gt;alert(&quot;candidato&quot;)&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;alert(&quot;bloqueo&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("candidato")</script>', false)
            ->assertDontSee('<script>alert("bloqueo")</script>', false)
            ->assertDontSee('Reprogramar turno')
            ->assertDontSee('Cancelar turno')
            ->assertDontSee('name="reprogramar"', false)
            ->assertDontSee('name="cancelar_turno"', false)
            ->assertSee('<table', false)
            ->assertSee('sm:hidden', false);

        $this->assertDatabaseCount('bloqueos_agenda', 1);
    }

    public function test_review_stale_renderiza_nueva_revision_snapshot_y_mensaje_sin_success(): void
    {
        $actor = $this->actor();
        $revisionOriginal = app(RevisarBloqueoAgendaAction::class)
            ->ejecutar($actor, $this->payload());
        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 13:00:00',
            'Licencia concurrente'
        );

        $response = $this->actingAs($actor)
            ->post(route('turnos.agenda.bloqueos.store'), [
                ...$this->payload(),
                'snapshot' => $revisionOriginal['snapshot'],
            ]);
        $revisionNueva = $response->viewData('revision');
        $mensajeStale = session('error');

        $response
            ->assertOk()
            ->assertSessionHas('error')
            ->assertSessionMissing('success')
            ->assertSee('La revisión anterior quedó desactualizada.')
            ->assertSee($mensajeStale)
            ->assertSee('cambió')
            ->assertSee('Revisá')
            ->assertDontSee('Ã')
            ->assertSee('Licencia concurrente')
            ->assertSee('Confirmar registro');

        $this->assertNotSame($revisionOriginal['snapshot'], $revisionNueva['snapshot']);
        $this->assertStringContainsString(
            'name="snapshot" value="'.e($revisionNueva['snapshot']).'"',
            $response->getContent()
        );
        $this->assertDatabaseCount('bloqueos_agenda', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_fin_vencido_entre_review_y_store_vuelve_a_captura_con_datos_y_sin_success(): void
    {
        $actor = $this->actor();
        $payload = $this->payload([
            'inicio_at' => '2026-10-06 10:00:00',
            'fin_at' => '2026-10-06 13:00:00',
            'motivo' => 'Bloqueo en curso',
        ]);
        $revision = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $payload);
        CarbonImmutable::setTestNow(CarbonImmutable::create(
            2026,
            10,
            6,
            13,
            0,
            0,
            config('app.timezone')
        ));

        $url = route('turnos.agenda.bloqueos.create');
        $post = $this->actingAs($actor)
            ->from($url)
            ->post(route('turnos.agenda.bloqueos.store'), [
                ...$payload,
                'snapshot' => $revision['snapshot'],
            ]);

        $post
            ->assertRedirect($url)
            ->assertSessionHasErrors('fin_at')
            ->assertSessionMissing('success');

        $this->get($url)
            ->assertOk()
            ->assertSee('Bloqueo en curso')
            ->assertSee('2026-10-06 10:00:00', false)
            ->assertSee('2026-10-06 13:00:00', false)
            ->assertSee('La fecha y hora de finalización debe ser posterior al momento actual.');

        $this->assertDatabaseCount('bloqueos_agenda', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function turno(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        EstadoTurnoEnum $estado
    ): Turno {
        $paciente = Paciente::create([
            'user_id' => User::factory()->create()->id,
            'activo' => true,
        ]);

        return Turno::factory()->for($paciente)->for($kinesiologa)->create([
            'estado' => $estado,
            'inicio_at' => $inicio,
            'fin_at' => $fin,
        ]);
    }

    /** @return array<string, string> */
    private function payload(array $cambios = []): array
    {
        return array_replace([
            'inicio_at' => '2026-10-07 10:00:00',
            'fin_at' => '2026-10-07 12:00:00',
            'motivo' => 'Congreso profesional',
        ], $cambios);
    }
}
