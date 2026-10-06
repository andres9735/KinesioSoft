<?php

namespace Tests\Feature\Controllers;

use App\Enums\RoleEnum;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnulacionBloqueoAgendaController;
use App\Http\Controllers\AnulacionConfiguracionAgendaController;
use App\Http\Controllers\ConfiguracionAgendaController;
use App\Http\Controllers\HistorialConfiguracionAgendaController;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\FranjaHorariaAgenda;
use App\Models\Kinesiologa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendaReadControllersTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('filtrosBloqueos')]
    public function test_dashboard_normaliza_filtros_y_aisla_bloqueos_propios(
        string $filtroSolicitado,
        string $filtroEsperado,
        int $cantidadEsperada
    ): void {
        $actor = $this->actor();
        $otra = $this->actor('otra');
        $this->bloqueo($actor->kinesiologa, '2026-10-06 13:00:00', '2026-10-06 14:00:00');
        $this->bloqueo($actor->kinesiologa, '2026-10-06 11:00:00', '2026-10-06 13:00:00');
        $this->bloqueo($actor->kinesiologa, '2026-10-06 09:00:00', '2026-10-06 10:00:00');
        $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-06 15:00:00',
            '2026-10-06 16:00:00',
            '2026-10-05 10:00:00'
        );
        $this->bloqueo($otra->kinesiologa, '2026-10-06 13:00:00', '2026-10-06 14:00:00');
        $request = $this->request($actor, "/turnos/agenda?filtro={$filtroSolicitado}");
        $datos = $this->capturarVista('agenda.index');

        $resultado = app(AgendaController::class)->index($request);

        $this->assertInstanceOf(View::class, $resultado);
        $this->assertSame($filtroEsperado, $datos()['filtroBloqueos']);
        $this->assertCount($cantidadEsperada, $datos()['bloqueos']->items());
        $this->assertSame(10, $datos()['bloqueos']->perPage());
        $this->assertSame(1, $datos()['cantidadBloqueosEnCurso']);
    }

    public static function filtrosBloqueos(): array
    {
        return [
            'default desconocido' => ['invalido', 'proximos', 1],
            'proximos' => ['proximos', 'proximos', 1],
            'en curso' => ['en_curso', 'en_curso', 1],
            'pasados' => ['pasados', 'pasados', 1],
            'anulados' => ['anulados', 'anulados', 1],
            'todos' => ['todos', 'todos', 4],
        ];
    }

    public function test_dashboard_conserva_hueco_como_null_y_separa_futuras_e_historial(): void
    {
        $actor = $this->actor();
        $historica = $this->configuracion($actor->kinesiologa, '2026-09-01', '2026-10-05');
        $futura = $this->configuracion($actor->kinesiologa, '2026-10-10');
        $anulada = $this->configuracion(
            $actor->kinesiologa,
            '2026-10-08',
            '2026-10-09',
            '2026-10-05 09:00:00'
        );
        $request = $this->request($actor, '/turnos/agenda');
        $datos = $this->capturarVista('agenda.index');

        app(AgendaController::class)->index($request);

        $this->assertNull($datos()['configuracionVigente']);
        $this->assertSame([$futura->id], $datos()['configuracionesFuturas']->modelKeys());
        $this->assertTrue($datos()['proximaConfiguracion']->is($futura));
        $this->assertSame(2, $datos()['cantidadHistorial']);
        $this->assertTrue($datos()['hayAntecedentes']);
        $this->assertFalse($datos()['puedeCambiarDuracion']);
        $this->assertDatabaseHas('configuraciones_agenda', ['id' => $historica->id]);
        $this->assertDatabaseHas('configuraciones_agenda', ['id' => $anulada->id]);
    }

    public function test_paginacion_de_bloqueos_conserva_solo_el_filtro_normalizado(): void
    {
        $actor = $this->actor();

        foreach (range(1, 11) as $indice) {
            $this->bloqueo(
                $actor->kinesiologa,
                sprintf('2026-10-07 %02d:00:00', $indice),
                sprintf('2026-10-07 %02d:30:00', $indice)
            );
        }

        $request = $this->request(
            $actor,
            '/turnos/agenda?filtro=proximos&return_to=https://example.com'
        );
        $datos = $this->capturarVista('agenda.index');

        app(AgendaController::class)->index($request);

        $urlSegundaPagina = $datos()['bloqueos']->url(2);
        $this->assertStringContainsString('filtro=proximos', $urlSegundaPagina);
        $this->assertStringContainsString('bloqueos_page=2', $urlSegundaPagina);
        $this->assertStringNotContainsString('return_to', $urlSegundaPagina);
    }

    public function test_historial_pagina_diez_y_excluye_vigente_y_futuras_activas(): void
    {
        $actor = $this->actor();

        foreach (range(1, 11) as $indice) {
            $this->configuracion(
                $actor->kinesiologa,
                sprintf('2026-09-%02d', $indice),
                sprintf('2026-09-%02d', $indice)
            );
        }

        $this->configuracion($actor->kinesiologa, '2026-10-06');
        $this->configuracion($actor->kinesiologa, '2026-12-01');
        $request = $this->request($actor, '/turnos/agenda/configuraciones/historial');
        $datos = $this->capturarVista('agenda.configuraciones.historial');

        app(HistorialConfiguracionAgendaController::class)->index($request);

        $this->assertSame(10, $datos()['configuraciones']->perPage());
        $this->assertSame(11, $datos()['configuraciones']->total());
        $this->assertCount(10, $datos()['configuraciones']->items());
    }

    public function test_gets_protegidos_generan_snapshots_y_contexto_saneado(): void
    {
        $actor = $this->actor();
        $configuracion = $this->configuracion($actor->kinesiologa, '2026-10-10');
        $bloqueo = $this->bloqueo(
            $actor->kinesiologa,
            '2026-10-05 09:00:00',
            '2026-10-05 10:00:00'
        );

        $requestConfig = $this->request(
            $actor,
            '/turnos/agenda/configuraciones/'.$configuracion->id.'/editar?origen=https://example.com&page=-5'
        );
        $datosConfig = $this->capturarVista('agenda.configuraciones.edit');
        app(ConfiguracionAgendaController::class)->edit($requestConfig, $configuracion);

        $this->assertNotSame('', $datosConfig()['snapshot']);
        $this->assertSame(['origen' => 'dashboard'], $datosConfig()['contextoRetorno']);

        $requestAnulacionConfig = $this->request(
            $actor,
            '/turnos/agenda/configuraciones/'.$configuracion->id.'/anular?origen=detalle'
        );
        $datosAnulacionConfig = $this->capturarVista('agenda.configuraciones.anular');
        app(AnulacionConfiguracionAgendaController::class)->create(
            $requestAnulacionConfig,
            $configuracion
        );

        $this->assertNotSame('', $datosAnulacionConfig()['snapshot']);
        $this->assertArrayHasKey('predecesoraVinculada', $datosAnulacionConfig());

        $requestBloqueo = $this->request(
            $actor,
            '/turnos/agenda/bloqueos/'.$bloqueo->id.'/anular?filtro=pasados&bloqueos_page=2'
        );
        $datosBloqueo = $this->capturarVista('agenda.bloqueos.anular');
        app(AnulacionBloqueoAgendaController::class)->create($requestBloqueo, $bloqueo);

        $this->assertNotSame('', $datosBloqueo()['snapshot']);
        $this->assertSame('pasados', $datosBloqueo()['contextoRetorno']['filtro']);
        $this->assertSame(2, $datosBloqueo()['contextoRetorno']['bloqueos_page']);
    }

    private function request(User $actor, string $uri): Request
    {
        $this->actingAs($actor);
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn (): User => $actor);
        app()->instance('request', $request);

        return $request;
    }

    /** @return callable(): array<string, mixed> */
    private function capturarVista(string $nombre): callable
    {
        $datos = null;
        $vista = Mockery::mock(View::class);
        ViewFacade::shouldReceive('make')
            ->once()
            ->with($nombre, Mockery::on(function (array $recibidos) use (&$datos): bool {
                $datos = $recibidos;

                return true;
            }), [])
            ->andReturn($vista);

        return function () use (&$datos): array {
            $this->assertIsArray($datos);

            return $datos;
        };
    }

    private function actor(string $prefijo = 'actor'): User
    {
        $user = User::factory()->create([
            'email' => $prefijo.'_'.uniqid().'@example.com',
            'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        Kinesiologa::create(['user_id' => $user->id]);

        return $user->fresh('kinesiologa');
    }

    private function configuracion(
        Kinesiologa $kinesiologa,
        string $desde,
        ?string $hasta = null,
        ?string $anuladoAt = null
    ): ConfiguracionAgenda {
        $configuracion = ConfiguracionAgenda::factory()->for($kinesiologa)->create([
            'vigencia_desde' => $desde,
            'vigencia_hasta' => $hasta,
            'anulado_at' => $anuladoAt,
            'motivo_anulacion' => $anuladoAt === null ? null : 'AnulaciÃ³n de prueba',
        ]);
        FranjaHorariaAgenda::factory()
            ->for($configuracion, 'configuracionAgenda')
            ->create();

        return $configuracion;
    }

    private function bloqueo(
        Kinesiologa $kinesiologa,
        string $inicio,
        string $fin,
        ?string $anuladoAt = null
    ): BloqueoAgenda {
        return BloqueoAgenda::factory()->for($kinesiologa)->create([
            'inicio_at' => $inicio,
            'fin_at' => $fin,
            'anulado_at' => $anuladoAt,
            'motivo_anulacion' => $anuladoAt === null ? null : 'AnulaciÃ³n de prueba',
        ]);
    }
}
