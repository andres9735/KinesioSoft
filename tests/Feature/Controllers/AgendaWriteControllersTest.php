<?php

namespace Tests\Feature\Controllers;

use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Enums\RoleEnum;
use App\Http\Middleware\EnsureUserProfileIsComplete;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendaWriteControllersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web');

        CarbonImmutable::setTestNow(CarbonImmutable::create(
            2026,
            10,
            6,
            12,
            0,
            0,
            config('app.timezone')
        ));

        $this->withoutMiddleware([
            EnsureUserProfileIsComplete::class,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_store_configuracion_invoca_el_flujo_y_redirige_con_success(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->post(
            route('turnos.agenda.configuraciones.store'),
            $this->payloadConfiguracion()
        );

        $response
            ->assertRedirect(route('turnos.agenda.index'))
            ->assertSessionHas('success', 'Configuración registrada correctamente.');

        $this->assertDatabaseHas('configuraciones_agenda', [
            'kinesiologa_id' => $actor->kinesiologa->id,
            'vigencia_desde' => '2026-10-07 00:00:00',
            'duracion_turno_minutos' => 45,
        ]);

        $this->assertDatabaseCount('franjas_horarias_agenda', 1);
    }

    public function test_update_usa_binding_snapshot_y_contexto_seguro_sin_open_redirect(): void
    {
        $actor = $this->actor();

        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-10'
        );

        $snapshot = app(AgendaSnapshotService::class)
            ->generarParaConfiguracion(
                $configuracion->load('franjasHorarias')
            );

        $payload = $this->payloadConfiguracion([
            'vigencia_desde' => '2026-10-10',
            'duracion_turno_minutos' => 60,
            'snapshot' => $snapshot,
            'origen' => 'detalle',
            'return_to' => 'https://example.com/robo',
        ]);

        $this->actingAs($actor)
            ->put(
                route(
                    'turnos.agenda.configuraciones.update',
                    $configuracion
                ),
                $payload
            )
            ->assertRedirect(
                route(
                    'turnos.agenda.configuraciones.show',
                    $configuracion
                )
            )
            ->assertSessionHas(
                'success',
                'Configuración futura actualizada correctamente.'
            );

        $this->assertDatabaseHas('configuraciones_agenda', [
            'id' => $configuracion->id,
            'duracion_turno_minutos' => 60,
        ]);

        $ajena = $this->actor('ajena');

        $configuracionAjena = $this->configuracion(
            $ajena->kinesiologa,
            '2026-10-20'
        );

        $this->actingAs($actor)
            ->put(
                route(
                    'turnos.agenda.configuraciones.update',
                    $configuracionAjena
                ),
                $this->payloadConfiguracion([
                    'vigencia_desde' => '2026-10-20',
                    'snapshot' => 'token',
                ])
            )
            ->assertForbidden();
    }

    public function test_cambio_de_duracion_crea_nueva_vigencia_y_redirige(): void
    {
        $actor = $this->actor();

        $origen = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-01'
        );

        $snapshot = app(AgendaSnapshotService::class)
            ->generarParaConfiguracion(
                $origen->load('franjasHorarias')
            );

        $this->actingAs($actor)
            ->post(route('turnos.agenda.duracion.store'), [
                'vigencia_desde' => '2026-10-07',
                'duracion_turno_minutos' => 60,
                'franjas' => [[
                    'dia_semana' => 1,
                    'hora_inicio' => '08:00',
                    'hora_fin' => '12:00',
                ]],
                'snapshot' => $snapshot,
            ])
            ->assertRedirect(route('turnos.agenda.index'))
            ->assertSessionHas(
                'success',
                'Cambio de duración programado correctamente.'
            );

        $this->assertDatabaseHas('configuraciones_agenda', [
            'kinesiologa_id' => $actor->kinesiologa->id,
            'vigencia_desde' => '2026-10-07 00:00:00',
            'duracion_turno_minutos' => 60,
        ]);
    }

    public function test_snapshot_stale_de_configuracion_no_se_presenta_como_success(): void
    {
        $actor = $this->actor();

        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-10'
        );

        $snapshot = app(AgendaSnapshotService::class)
            ->generarParaConfiguracion(
                $configuracion->load('franjasHorarias')
            );

        $configuracion->update([
            'duracion_turno_minutos' => 60,
        ]);

        $this->actingAs($actor)
            ->put(
                route(
                    'turnos.agenda.configuraciones.update',
                    $configuracion
                ),
                $this->payloadConfiguracion([
                    'vigencia_desde' => '2026-10-10',
                    'snapshot' => $snapshot,
                ])
            )
            ->assertRedirect(
                route(
                    'turnos.agenda.configuraciones.edit',
                    [
                        'configuracion' => $configuracion,
                        'origen' => 'dashboard',
                    ]
                )
            )
            ->assertSessionHas('error')
            ->assertSessionMissing('success');
    }

    public function test_review_de_bloqueo_no_escribe_y_store_confirma_con_filtro_util(): void
    {
        $actor = $this->actor();
        $payload = $this->payloadBloqueo();

        $datosVista = null;

        $vista = Mockery::mock(View::class);
        $vista->shouldReceive('render')->andReturn('');

        ViewFacade::shouldReceive('share')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        ViewFacade::shouldReceive('make')
            ->once()
            ->with(
                'agenda.bloqueos.create',
                Mockery::on(
                    function (array $datos) use (&$datosVista): bool {
                        $datosVista = $datos;

                        return true;
                    }
                ),
                []
            )
            ->andReturn($vista);

        $this->actingAs($actor)
            ->post(
                route('turnos.agenda.bloqueos.review'),
                $payload
            )
            ->assertOk();

        $this->assertDatabaseCount('bloqueos_agenda', 0);
        $this->assertIsArray($datosVista['revision']);

        $this->actingAs($actor)
            ->post(
                route('turnos.agenda.bloqueos.store'),
                [
                    ...$payload,
                    'snapshot' => $datosVista['revision']['snapshot'],
                ]
            )
            ->assertRedirect(
                route(
                    'turnos.agenda.index',
                    ['filtro' => 'proximos']
                )
            )
            ->assertSessionHas(
                'success',
                'Bloqueo registrado correctamente.'
            );

        $this->assertDatabaseCount('bloqueos_agenda', 1);
    }

    public function test_review_stale_recalcula_advertencias_y_exige_reconfirmacion(): void
    {
        $actor = $this->actor();
        $payload = $this->payloadBloqueo();

        $revisionOriginal = app(
            RevisarBloqueoAgendaAction::class
        )->ejecutar(
            $actor,
            $payload
        );

        $superpuesto = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 11:00:00',
            '2026-10-07 13:00:00'
        );

        $datosVista = null;

        $vista = Mockery::mock(View::class);
        $vista->shouldReceive('render')->andReturn('');

        ViewFacade::shouldReceive('share')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        ViewFacade::shouldReceive('make')
            ->once()
            ->with(
                'agenda.bloqueos.create',
                Mockery::on(
                    function (array $datos) use (&$datosVista): bool {
                        $datosVista = $datos;

                        return true;
                    }
                ),
                []
            )
            ->andReturn($vista);

        $mensajeStale = 'La agenda cambió desde la revisión. '
            .'Revisá nuevamente las advertencias antes de confirmar.';

        $this->actingAs($actor)
            ->post(
                route('turnos.agenda.bloqueos.store'),
                [
                    ...$payload,
                    'snapshot' => $revisionOriginal['snapshot'],
                ]
            )
            ->assertOk()
            ->assertSessionMissing('error')
            ->assertSessionMissing('success');

        $this->assertSame(
            $mensajeStale,
            $datosVista['mensajeRevisionObsoleta']
        );

        $this->assertSame(
            1,
            $datosVista['revision']['cantidad_bloqueos_superpuestos']
        );

        $this->assertSame(
            $superpuesto->id,
            $datosVista['revision']['bloqueos_superpuestos'][0]['id']
        );

        $this->assertNotSame(
            $revisionOriginal['snapshot'],
            $datosVista['revision']['snapshot']
        );

        $this->assertDatabaseCount('bloqueos_agenda', 1);
    }

    public function test_anulaciones_usan_actions_snapshots_y_redirects_internos(): void
    {
        $actor = $this->actor();

        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-05 09:00:00',
            '2026-10-05 10:00:00'
        );

        $snapshotBloqueo = app(
            AgendaSnapshotService::class
        )->generarParaBloqueo(
            $bloqueo
        );

        $this->actingAs($actor)
            ->patch(
                route(
                    'turnos.agenda.bloqueos.anular.store',
                    $bloqueo
                ),
                [
                    'motivo_anulacion' => 'Corrección de carga',
                    'snapshot' => $snapshotBloqueo,
                    'filtro' => 'pasados',
                    'bloqueos_page' => 2,
                    'return_to' => 'https://example.com',
                ]
            )
            ->assertRedirect(
                route(
                    'turnos.agenda.index',
                    [
                        'filtro' => 'pasados',
                        'bloqueos_page' => 2,
                    ]
                )
            )
            ->assertSessionHas(
                'success',
                'Bloqueo anulado correctamente.'
            );

        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-10'
        );

        $snapshotConfiguracion = app(
            AgendaSnapshotService::class
        )->generarParaConfiguracion(
            $configuracion->load('franjasHorarias')
        );

        $this->actingAs($actor)
            ->patch(
                route(
                    'turnos.agenda.configuraciones.anular.store',
                    $configuracion
                ),
                [
                    'motivo_anulacion' => 'Cambio de planificación',
                    'snapshot' => $snapshotConfiguracion,
                ]
            )
            ->assertRedirect(route('turnos.agenda.index'))
            ->assertSessionHas(
                'success',
                'Configuración futura anulada y continuidad recalculada.'
            );

        $this->assertNotNull(
            $bloqueo->fresh()->anulado_at
        );

        $this->assertNotNull(
            $configuracion->fresh()->anulado_at
        );
    }

    public function test_snapshots_stale_de_anulacion_no_generan_success(): void
    {
        $actor = $this->actor();

        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-07 09:00:00',
            '2026-10-07 10:00:00'
        );

        $snapshotBloqueo = app(
            AgendaSnapshotService::class
        )->generarParaBloqueo(
            $bloqueo
        );

        $bloqueo
            ->forceFill([
                'motivo' => 'Cambio concurrente',
            ])
            ->save();

        $this->actingAs($actor)
            ->patch(
                route(
                    'turnos.agenda.bloqueos.anular.store',
                    $bloqueo
                ),
                [
                    'motivo_anulacion' => 'Corrección',
                    'snapshot' => $snapshotBloqueo,
                ]
            )
            ->assertRedirect(
                route(
                    'turnos.agenda.bloqueos.anular.create',
                    [
                        'bloqueo' => $bloqueo,
                        'origen' => 'bloqueos',
                        'filtro' => 'proximos',
                    ]
                )
            )
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertNull(
            $bloqueo->fresh()->anulado_at
        );

        $configuracion = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-10'
        );

        $snapshotConfiguracion = app(
            AgendaSnapshotService::class
        )->generarParaConfiguracion(
            $configuracion->load('franjasHorarias')
        );

        $configuracion->update([
            'duracion_turno_minutos' => 60,
        ]);

        $this->actingAs($actor)
            ->patch(
                route(
                    'turnos.agenda.configuraciones.anular.store',
                    $configuracion
                ),
                [
                    'motivo_anulacion' => 'Cambio de planificación',
                    'snapshot' => $snapshotConfiguracion,
                ]
            )
            ->assertRedirect(
                route(
                    'turnos.agenda.configuraciones.anular.create',
                    [
                        'configuracion' => $configuracion,
                        'origen' => 'dashboard',
                    ]
                )
            )
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertNull(
            $configuracion->fresh()->anulado_at
        );
    }

    private function actor(string $prefijo = 'actor'): User
    {
        $user = User::factory()->create([
            'email' => $prefijo.'_'.uniqid().'@example.com',
            'email_verified_at' => now(),
            'activo' => true,
        ]);

        $user->assignRole(
            RoleEnum::KINESIOLOGO->value
        );

        Kinesiologa::create([
            'user_id' => $user->id,
        ]);

        return $user->fresh('kinesiologa');
    }

    /** @return array<string, mixed> */
    private function payloadConfiguracion(
        array $cambios = []
    ): array {
        return array_replace([
            'vigencia_desde' => '2026-10-07',
            'vigencia_hasta' => null,
            'duracion_turno_minutos' => 45,
            'franjas' => [[
                'dia_semana' => 1,
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
            ]],
        ], $cambios);
    }

    /** @return array<string, string> */
    private function payloadBloqueo(): array
    {
        return [
            'inicio_at' => '2026-10-07 10:00:00',
            'fin_at' => '2026-10-07 12:00:00',
            'motivo' => 'Congreso',
        ];
    }

    private function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null
    ): ConfiguracionAgenda {
        $configuracion = ConfiguracionAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'vigencia_desde' => $desde,
                'vigencia_hasta' => $hasta,
            ]);

        FranjaHorariaAgenda::factory()
            ->for(
                $configuracion,
                'configuracionAgenda'
            )
            ->create([
                'dia_semana' => 1,
                'hora_inicio' => '08:00:00',
                'hora_fin' => '12:00:00',
            ]);

        return $configuracion;
    }

    private function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()
            ->for($kinesiologa)
            ->create([
                'inicio_at' => $inicio,
                'fin_at' => $fin,
            ]);
    }
}
