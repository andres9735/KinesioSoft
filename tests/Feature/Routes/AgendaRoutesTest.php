<?php

namespace Tests\Feature\Routes;

use App\Enums\RoleEnum;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnulacionBloqueoAgendaController;
use App\Http\Controllers\AnulacionConfiguracionAgendaController;
use App\Http\Controllers\BloqueoAgendaController;
use App\Http\Controllers\CambioDuracionAgendaController;
use App\Http\Controllers\ConfiguracionAgendaController;
use App\Http\Controllers\HistorialConfiguracionAgendaController;
use App\Models\ColegioEmisor;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\TituloProfesional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendaRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function test_mapa_de_rutas_coincide_con_el_diseno_tecnico_congelado(): void
    {
        $esperadas = [
            'turnos.agenda.index' => ['GET', 'turnos/agenda', AgendaController::class.'@index'],
            'turnos.agenda.configuraciones.create' => ['GET', 'turnos/agenda/configuraciones/crear', ConfiguracionAgendaController::class.'@create'],
            'turnos.agenda.configuraciones.store' => ['POST', 'turnos/agenda/configuraciones', ConfiguracionAgendaController::class.'@store'],
            'turnos.agenda.configuraciones.historial' => ['GET', 'turnos/agenda/configuraciones/historial', HistorialConfiguracionAgendaController::class.'@index'],
            'turnos.agenda.configuraciones.show' => ['GET', 'turnos/agenda/configuraciones/{configuracion}', ConfiguracionAgendaController::class.'@show'],
            'turnos.agenda.configuraciones.edit' => ['GET', 'turnos/agenda/configuraciones/{configuracion}/editar', ConfiguracionAgendaController::class.'@edit'],
            'turnos.agenda.configuraciones.update' => ['PUT', 'turnos/agenda/configuraciones/{configuracion}', ConfiguracionAgendaController::class.'@update'],
            'turnos.agenda.duracion.create' => ['GET', 'turnos/agenda/duracion/crear', CambioDuracionAgendaController::class.'@create'],
            'turnos.agenda.duracion.store' => ['POST', 'turnos/agenda/duracion', CambioDuracionAgendaController::class.'@store'],
            'turnos.agenda.configuraciones.anular.create' => ['GET', 'turnos/agenda/configuraciones/{configuracion}/anular', AnulacionConfiguracionAgendaController::class.'@create'],
            'turnos.agenda.configuraciones.anular.store' => ['PATCH', 'turnos/agenda/configuraciones/{configuracion}/anular', AnulacionConfiguracionAgendaController::class.'@store'],
            'turnos.agenda.bloqueos.create' => ['GET', 'turnos/agenda/bloqueos/crear', BloqueoAgendaController::class.'@create'],
            'turnos.agenda.bloqueos.review' => ['POST', 'turnos/agenda/bloqueos/revisar', BloqueoAgendaController::class.'@review'],
            'turnos.agenda.bloqueos.store' => ['POST', 'turnos/agenda/bloqueos', BloqueoAgendaController::class.'@store'],
            'turnos.agenda.bloqueos.show' => ['GET', 'turnos/agenda/bloqueos/{bloqueo}', BloqueoAgendaController::class.'@show'],
            'turnos.agenda.bloqueos.anular.create' => ['GET', 'turnos/agenda/bloqueos/{bloqueo}/anular', AnulacionBloqueoAgendaController::class.'@create'],
            'turnos.agenda.bloqueos.anular.store' => ['PATCH', 'turnos/agenda/bloqueos/{bloqueo}/anular', AnulacionBloqueoAgendaController::class.'@store'],
        ];

        $rutasAgenda = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'turnos.agenda.'));

        $this->assertCount(17, $rutasAgenda);

        foreach ($esperadas as $nombre => [$metodo, $uri, $action]) {
            $route = Route::getRoutes()->getByName($nombre);

            $this->assertNotNull($route, "Falta la ruta [{$nombre}].");
            $this->assertContains($metodo, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertSame($action, $route->getActionName());
        }

        $this->assertFalse($rutasAgenda->contains(
            fn ($route): bool => in_array('DELETE', $route->methods(), true)
                || str_contains((string) $route->getName(), 'destroy')
                || (str_contains($route->uri(), 'bloqueos') && str_contains($route->uri(), 'editar'))
        ));
    }

    public function test_todas_las_rutas_tienen_el_middleware_operativo_exclusivo(): void
    {
        $esperados = ['auth', 'verified', 'perfil.completo', 'perfil.kinesiologo'];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'turnos.agenda.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            foreach ($esperados as $esperado) {
                $this->assertContains($esperado, $middleware, "Falta [{$esperado}] en [{$route->getName()}].");
            }

            $this->assertNotContains('admin.or.superadmin', $middleware);
        }
    }

    public function test_rutas_estaticas_no_son_capturadas_por_los_bindings(): void
    {
        $casos = [
            '/turnos/agenda/configuraciones/crear' => 'turnos.agenda.configuraciones.create',
            '/turnos/agenda/configuraciones/historial' => 'turnos.agenda.configuraciones.historial',
            '/turnos/agenda/duracion/crear' => 'turnos.agenda.duracion.create',
            '/turnos/agenda/bloqueos/crear' => 'turnos.agenda.bloqueos.create',
            '/turnos/agenda/bloqueos/revisar' => 'turnos.agenda.bloqueos.review',
        ];

        foreach ($casos as $uri => $nombre) {
            $metodo = str_ends_with($uri, '/revisar') ? 'POST' : 'GET';
            $route = Route::getRoutes()->match(Request::create($uri, $metodo));

            $this->assertSame($nombre, $route->getName());
        }

        $this->assertSame(
            ['configuracion'],
            Route::getRoutes()->getByName('turnos.agenda.configuraciones.show')->parameterNames()
        );
        $this->assertSame(
            ['bloqueo'],
            Route::getRoutes()->getByName('turnos.agenda.bloqueos.show')->parameterNames()
        );
    }

    public function test_middleware_rechaza_guest_no_verificado_perfil_incompleto_y_roles_ajenos(): void
    {
        $this->get(route('turnos.agenda.index'))
            ->assertRedirect(route('login'));

        $noVerificado = $this->usuario(RoleEnum::KINESIOLOGO, verificado: false);
        $this->actingAs($noVerificado)
            ->get(route('turnos.agenda.index'))
            ->assertRedirect(route('verification.notice'));

        $incompleto = $this->usuario(RoleEnum::KINESIOLOGO);
        $this->actingAs($incompleto)
            ->get(route('turnos.agenda.index'))
            ->assertRedirect(route('perfil.kinesiologa.completar'));

        foreach ([RoleEnum::ADMINISTRADOR, RoleEnum::SUPERADMIN] as $role) {
            $actor = $this->usuario($role);
            $this->actingAs($actor)
                ->get(route('turnos.agenda.index'))
                ->assertForbidden();
        }

        $paciente = $this->usuario(RoleEnum::PACIENTE);
        $this->actingAs($paciente)
            ->get(route('turnos.agenda.index'))
            ->assertRedirect(route('perfil.completar'));
    }

    public function test_kinesiologa_valida_alcanza_el_controller_sin_bypass_administrativo(): void
    {
        $kinesiologa = $this->kinesiologaCompleta();

        $this->actingAs($kinesiologa)
            ->get(route('turnos.agenda.duracion.create'))
            ->assertRedirect(route('turnos.agenda.index'))
            ->assertSessionHas('error');
    }

    private function usuario(RoleEnum $role, bool $verificado = true): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $verificado ? now() : null,
            'activo' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function kinesiologaCompleta(): User
    {
        $titulo = TituloProfesional::create([
            'nombre' => 'KinesiÃ³logo',
            'slug' => 'kinesiologo-agenda',
            'activo' => true,
        ]);
        $jurisdiccion = Jurisdiccion::create([
            'nombre' => 'Misiones',
            'slug' => 'misiones-agenda',
            'activo' => true,
        ]);
        $colegio = ColegioEmisor::create([
            'nombre' => 'Colegio de KinesiÃ³logos de Agenda',
            'sigla' => 'CKA',
            'jurisdiccion_id' => $jurisdiccion->id,
            'activo' => true,
        ]);
        $especialidad = EspecialidadKinesiologia::create([
            'nombre' => 'KinesiologÃ­a de Agenda',
            'slug' => 'kinesiologia-agenda',
            'activo' => true,
        ]);
        $user = User::factory()->create([
            'dni' => '30999888',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino',
            'telefono' => '3764123456',
            'email_verified_at' => now(),
            'activo' => true,
        ]);
        $user->assignRole(RoleEnum::KINESIOLOGO->value);
        $kinesiologa = Kinesiologa::create([
            'user_id' => $user->id,
            'titulo_profesional_id' => $titulo->id,
        ]);
        MatriculaProfesionalKinesiologia::create([
            'kinesiologa_id' => $kinesiologa->id,
            'nro_matricula_profesional' => 'AG-001',
            'jurisdiccion_id' => $jurisdiccion->id,
            'colegio_emisor_id' => $colegio->id,
            'activa' => true,
        ]);
        $kinesiologa->especialidades()->attach($especialidad->id);

        return $user;
    }
}
