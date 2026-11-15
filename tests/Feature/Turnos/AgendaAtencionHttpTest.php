<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Http\Controllers\AgendaAtencionController;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class AgendaAtencionHttpTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $propia;

    private Kinesiologa $ajena;

    private Paciente $paciente;

    private Paciente $otroPaciente;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 01:00:00 UTC'));
        $this->propia = $this->profesionalUc07();
        $this->ajena = $this->profesionalUc07();
        $usuario = $this->usuarioUc07(RoleEnum::PACIENTE);
        $usuario->update(['name' => 'PacientePropio610', 'apellido' => 'Prueba', 'email' => 'privado610@example.test']);
        $this->paciente = Paciente::create(['user_id' => $usuario->id, 'activo' => true]);
        $otro = $this->usuarioUc07(RoleEnum::PACIENTE);
        $otro->update(['name' => 'PacienteAjeno610', 'apellido' => 'Reservado']);
        $this->otroPaciente = Paciente::create(['user_id' => $otro->id, 'activo' => true]);
        $this->turno = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'inicio_at' => '2026-11-05 06:30:00', 'fin_at' => '2026-11-05 07:15:00',
            'motivo_solicitud' => 'Motivo clínico privado 610',
        ]);
        Turno::factory()->for($this->otroPaciente)->for($this->ajena)->create([
            'inicio_at' => '2026-11-05 06:30:00', 'fin_at' => '2026-11-05 07:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_la_ruta_tiene_solo_lectura_y_middleware_profesional_antes_del_perfil(): void
    {
        $ruta = Route::getRoutes()->getByName('turnos.agenda-atencion.index');

        $this->assertSame(['GET', 'HEAD'], $ruta->methods());
        $this->assertSame('turnos/agenda-atencion', $ruta->uri());
        $this->assertSame(AgendaAtencionController::class.'@index', $ruta->getActionName());
        $this->assertSame(['web', 'auth', 'perfil.kinesiologo', 'verified', 'perfil.completo'], $ruta->gatherMiddleware());
        $this->assertSame([], $ruta->parameterNames());
    }

    public function test_guest_es_redirigido_sin_consultar_datos_de_pacientes(): void
    {
        $this->getSinDatos()->assertRedirect(route('login'));
    }

    #[DataProvider('rolesNoProfesionales')]
    public function test_otros_roles_no_acceden_ni_evaluan_el_perfil_de_paciente(RoleEnum $rol): void
    {
        $this->actingAs($this->usuarioUc07($rol));

        $this->getSinDatos()->assertForbidden();
    }

    public static function rolesNoProfesionales(): array
    {
        return [[RoleEnum::PACIENTE], [RoleEnum::ADMINISTRADOR], [RoleEnum::SUPERADMIN]];
    }

    public function test_cuenta_inactiva_conserva_la_expulsion_transversal_sin_leer_turnos(): void
    {
        $actor = $this->propia->user;
        $actor->update(['activo' => false]);
        $this->actingAs($actor);

        $this->getSinDatos()->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_correo_no_verificado_es_redirigido_antes_de_consultar_turnos(): void
    {
        $actor = $this->propia->user;
        $actor->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($actor->hasVerifiedEmail());
        $this->actingAs($actor);

        $this->getSinDatos()->assertRedirect(route('verification.notice'));
    }

    #[DataProvider('perfilesIncompletos')]
    public function test_el_perfil_incompleto_no_llega_a_la_consulta(string $caso): void
    {
        $actor = $this->propia->user;
        match ($caso) {
            'sin perfil' => $actor = $this->usuarioUc07(RoleEnum::KINESIOLOGO),
            'datos personales' => $actor->update(['telefono' => null]),
            'sin titulo' => $this->propia->update(['titulo_profesional_id' => null]),
            'sin matricula activa' => $this->propia->matriculasProfesionales()->update(['activa' => false]),
            'sin especialidad' => $this->propia->especialidades()->detach(),
        };
        $this->actingAs($actor);

        $this->getSinDatos()->assertRedirect(route('perfil.kinesiologa.completar'));
    }

    public static function perfilesIncompletos(): array
    {
        return [['sin perfil'], ['datos personales'], ['sin titulo'], ['sin matricula activa'], ['sin especialidad']];
    }

    public function test_presenta_la_jornada_local_actual_con_datos_administrativos_minimos(): void
    {
        $antes = $this->contenidoBase();

        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'));

        $respuesta->assertOk()->assertViewIs('turnos.agenda-atencion.index')
            ->assertViewHas('fecha', fn ($fecha) => $fecha->toDateString() === '2026-11-05')
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id])
            ->assertSee('PacientePropio610')->assertSee('05/11/2026 06:30')
            ->assertSee('datetime="2026-11-05T07:15:00"', false)->assertSee('07:15')->assertSee('45 minutos')
            ->assertSee('Primera consulta')->assertSee('Pendiente')
            ->assertSee('value="2026-11-05"', false)
            ->assertDontSee('PacienteAjeno610')->assertDontSee('Motivo clínico privado 610')
            ->assertDontSee('privado610@example.test');
        $this->assertStringContainsString('text/html', $respuesta->headers->get('Content-Type'));
        $this->assertSame($antes, $this->contenidoBase());
    }

    #[DataProvider('jornadasParaNavegar')]
    public function test_la_navegacion_http_conserva_la_fecha_y_cruza_meses_y_anios(
        string $fecha,
        string $anterior,
        string $siguiente,
    ): void {
        $antes = $this->contenidoBase();
        $this->actingAs($this->propia->user);

        $respuesta = $this->get(route('turnos.agenda-atencion.index', ['fecha' => $fecha]));
        $respuesta->assertOk()
            ->assertSee('href="'.route('turnos.agenda-atencion.index', ['fecha' => $anterior]).'"', false)
            ->assertSee('href="'.route('turnos.agenda-atencion.index', ['fecha' => $siguiente]).'"', false)
            ->assertSee('value="'.$fecha.'"', false)
            ->assertSee('method="GET"', false)
            ->assertSee('action="'.route('turnos.agenda-atencion.index').'"', false)
            ->assertSee('Día anterior')->assertSee('Día siguiente');
        foreach ([$anterior, $siguiente] as $destino) {
            $this->get(route('turnos.agenda-atencion.index', ['fecha' => $destino]))
                ->assertOk()->assertViewHas('fecha', fn ($valor) => $valor->toDateString() === $destino)
                ->assertSee('value="'.$destino.'"', false);
        }

        $this->assertSame($antes, $this->contenidoBase());
    }

    public static function jornadasParaNavegar(): array
    {
        return [
            ['2026-11-05', '2026-11-04', '2026-11-06'],
            ['2024-03-01', '2024-02-29', '2024-03-02'],
            ['2026-12-31', '2026-12-30', '2027-01-01'],
        ];
    }

    public function test_el_calendario_muestra_ambos_tipos_y_seis_estados_en_orden_sin_escrituras(): void
    {
        $estados = [
            [EstadoTurnoEnum::PENDIENTE, 'Pendiente'],
            [EstadoTurnoEnum::CONFIRMADO, 'Confirmado'],
            [EstadoTurnoEnum::EN_ATENCION, 'En atención'],
            [EstadoTurnoEnum::ATENDIDO, 'Atendido'],
            [EstadoTurnoEnum::AUSENTE, 'Ausente'],
            [EstadoTurnoEnum::CANCELADO, 'Cancelado'],
        ];
        // Insertar en orden inverso para comprobar el orden de la presentación HTTP.
        for ($i = 5; $i >= 0; $i--) {
            Turno::factory()->for($this->paciente)->for($this->propia)->create([
                'tipo' => $i % 2 === 0 ? TipoTurnoEnum::PRIMERA_CONSULTA : TipoTurnoEnum::SEGUIMIENTO,
                'estado' => $estados[$i][0],
                'inicio_at' => '2026-11-05 '.(10 + $i).':10:00',
                'fin_at' => '2026-11-05 '.(10 + $i).':35:00',
            ]);
        }
        $antes = $this->contenidoBase();
        $orden = ['datetime="2026-11-05T06:30:00"', 'datetime="2026-11-05T07:15:00"', 'Primera consulta', 'Pendiente'];
        foreach ($estados as $i => [, $etiqueta]) {
            array_push($orden, 'datetime="2026-11-05T'.(10 + $i).':10:00"', 'datetime="2026-11-05T'.(10 + $i).':35:00"',
                $i % 2 === 0 ? 'Primera consulta' : 'Seguimiento', $etiqueta);
        }

        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'))
            ->assertOk()->assertSeeInOrder($orden, false)
            ->assertSee('25 minutos')->assertDontSee('PacienteAjeno610')
            ->assertDontSee('Motivo clínico privado 610')->assertDontSee('privado610@example.test');

        $this->assertSame($antes, $this->contenidoBase());
    }

    public function test_el_intervalo_real_conserva_el_fin_en_la_jornada_siguiente(): void
    {
        Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO,
            'inicio_at' => '2026-11-05 23:50:00', 'fin_at' => '2026-11-06 00:25:00',
        ]);

        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'))
            ->assertOk()->assertSeeInOrder(['datetime="2026-11-05T23:50:00"', 'datetime="2026-11-06T00:25:00"', '05/11/2026 al 06/11/2026', '35 minutos', 'Seguimiento'], false)
            ->assertSee('datetime="2026-11-05T23:50:00"', false)
            ->assertSee('datetime="2026-11-06T00:25:00"', false);
    }

    public function test_la_fecha_invalida_muestra_un_error_asociado_al_control_y_permite_navegar(): void
    {
        $this->actingAs($this->propia->user);
        $this->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-02-30']))
            ->assertRedirect(route('turnos.agenda-atencion.index'));

        $this->get(route('turnos.agenda-atencion.index'))->assertOk()
            ->assertSee('Ingresá una fecha válida con formato AAAA-MM-DD.')
            ->assertSee('for="fecha"', false)->assertSee('id="fecha"', false)
            ->assertSee('type="date"', false)->assertSee('name="fecha"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="fecha-ayuda fecha-error"', false)
            ->assertSee('id="fecha-error"', false)->assertSee('role="alert"', false)
            ->assertSee('aria-label="Navegación entre jornadas"', false)
            ->assertSee('aria-label="Turnos de la jornada"', false);
    }

    public function test_los_nombres_se_escapan_en_el_html_sin_habilitar_contenido_activo(): void
    {
        $nombre = '<script>alert("paciente")</script>';
        $this->paciente->user->update(['name' => $nombre]);

        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'))
            ->assertOk()->assertSee($nombre)->assertDontSee($nombre, false);
    }

    public function test_identificadores_del_cliente_no_sustituyen_la_profesional_persistida(): void
    {
        $actor = $this->propia->user;
        $actor->setRelation('kinesiologa', $this->ajena);
        $this->actingAs($actor);

        $this->get(route('turnos.agenda-atencion.index', [
            'kinesiologa_id' => $this->ajena->id, 'profesional_id' => $this->ajena->id,
            'user_id' => $this->ajena->user_id, 'paciente_id' => $this->otroPaciente->id,
        ]))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id])
            ->assertDontSee('PacienteAjeno610');
    }

    #[DataProvider('fechasValidas')]
    public function test_consulta_fechas_validas_sin_limite_de_anticipacion(string $fecha): void
    {
        $turno = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'inicio_at' => $fecha.' 10:00:00', 'fin_at' => $fecha.' 10:25:00',
        ]);
        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', ['fecha' => $fecha]))
            ->assertOk()->assertViewHas('fecha', fn ($valor) => $valor->toDateString() === $fecha)
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$turno->id]);
    }

    public static function fechasValidas(): array
    {
        return [['2001-02-03'], ['2099-12-31']];
    }

    #[DataProvider('fechasInvalidas')]
    public function test_fecha_invalida_es_rechazada_antes_de_leer_pacientes(mixed $fecha): void
    {
        $this->actingAs($this->propia->user);

        $this->getSinDatos(['fecha' => $fecha])
            ->assertRedirect(route('turnos.agenda-atencion.index'))->assertSessionHasErrors('fecha');
    }

    public static function fechasInvalidas(): array
    {
        return [['2026-02-30'], ['05/11/2026'], ['ayer'], ['2026-11-05 10:00:00'], [['2026-11-05']], ['']];
    }

    public function test_no_depende_de_uc29_y_recupera_altas_posteriores_a_una_consulta(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 08:59:00 UTC'));
        $this->actingAs($this->propia->user);
        $this->get(route('turnos.agenda-atencion.index'))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id]);
        $nuevo = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'inicio_at' => '2026-11-05 11:00:00', 'fin_at' => '2026-11-05 11:30:00',
        ]);

        $this->get(route('turnos.agenda-atencion.index'))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id, $nuevo->id]);
    }

    public function test_jornada_vacia_se_distingue_de_errores_y_no_crea_turnos(): void
    {
        $antes = $this->contenidoBase();

        $this->actingAs($this->propia->user)
            ->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-11-07']))
            ->assertOk()->assertSee('No hay turnos registrados para esta fecha.')
            ->assertViewHas('turnos', []);

        $this->assertSame($antes, $this->contenidoBase());
    }

    public function test_el_controlador_propaga_el_rechazo_de_la_frontera_sin_renderizar_pacientes(): void
    {
        $actor = $this->propia->user;
        $actor->load('roles', 'kinesiologa');
        $this->actingAs($actor);
        // El guard conserva el rol cargado, pero la frontera revalida su revocación real.
        $actor->fresh()->removeRole(RoleEnum::KINESIOLOGO->value);

        $this->getSinDatos()->assertForbidden();
    }

    public function test_filtra_y_busca_por_nombre_autorizado_combinando_estado_tipo_y_horario(): void
    {
        $this->paciente->user->update(['name' => 'Ángela', 'apellido' => 'Pérez']);
        $coincidente = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'estado' => EstadoTurnoEnum::CONFIRMADO,
            'inicio_at' => '2026-11-05 10:15:00', 'fin_at' => '2026-11-05 10:50:00',
        ]);
        $antes = $this->contenidoBase();

        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', [
            'fecha' => '2026-11-05', 'paciente' => '  ÁNGELA pÉr  ',
            'estado' => 'CONFIRMADO', 'tipo' => 'SEGUIMIENTO',
            'hora_desde' => '10:15', 'hora_hasta' => '10:15',
        ]))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$coincidente->id])
            ->assertSee('Ángela Pérez')->assertSee('05/11/2026 10:15')->assertSee('35 minutos')
            ->assertDontSee('05/11/2026 06:30')->assertDontSee('PacienteAjeno610');

        $this->assertSame($antes, $this->contenidoBase());
    }

    #[DataProvider('filtrosIndividuales')]
    public function test_cada_filtro_se_aplica_individualmente_sobre_turnos_autorizados(array $criterios, bool $coincide): void
    {
        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', $criterios))
            ->assertOk()->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === ($coincide ? [$this->turno->id] : []));
    }

    public static function filtrosIndividuales(): array
    {
        return [
            [['paciente' => 'propio610'], true], [['paciente' => 'PacienteAjeno610'], false],
            [['paciente' => 'privado610@example.test'], false], [['paciente' => 'Motivo clínico privado 610'], false],
            [['hora_desde' => '06:30'], true], [['hora_desde' => '06:31'], false],
            [['hora_hasta' => '06:30'], true], [['hora_hasta' => '06:29'], false],
            [['estado' => 'PENDIENTE'], true], [['estado' => 'AUSENTE'], false],
            [['tipo' => 'PRIMERA_CONSULTA'], true], [['tipo' => 'SEGUIMIENTO'], false],
        ];
    }

    public function test_periodo_inclusivo_permite_historial_y_futuro_sin_heredar_ventanas_de_disponibilidad(): void
    {
        $primero = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'inicio_at' => '2001-02-03 00:00:00', 'fin_at' => '2001-02-03 00:20:00',
        ]);
        $ultimo = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'inicio_at' => '2099-12-31 23:40:00', 'fin_at' => '2100-01-01 00:05:00',
        ]);
        Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'inicio_at' => '2100-01-01 00:00:00', 'fin_at' => '2100-01-01 00:30:00',
        ]);
        $antes = $this->contenidoBase();

        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', [
            'desde' => '2001-02-03', 'hasta' => '2099-12-31',
        ]))->assertOk()->assertViewHas('esPeriodo', true)
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$primero->id, $this->turno->id, $ultimo->id])
            ->assertSee('Período del')->assertSee('03/02/2001')->assertSee('31/12/2099')
            ->assertSee('datetime="2100-01-01T00:05:00"', false)->assertSee('00:05')
            ->assertSee('31/12/2099 al 01/01/2100')->assertDontSee('PacienteAjeno610');

        $this->assertSame($antes, $this->contenidoBase());
    }

    #[DataProvider('parametrosInvalidos')]
    public function test_parametros_invalidos_se_rechazan_antes_de_leer_datos_protegidos(array $criterios, string $campo): void
    {
        $this->actingAs($this->propia->user);

        $this->getSinDatos($criterios)->assertRedirect(route('turnos.agenda-atencion.index'))
            ->assertSessionHasErrors($campo);
    }

    public static function parametrosInvalidos(): array
    {
        return [
            [['desde' => '2026-11-05'], 'hasta'], [['hasta' => '2026-11-05'], 'desde'],
            [['desde' => '', 'hasta' => ''], 'desde'],
            [['desde' => '2026-11-06', 'hasta' => '2026-11-05'], 'hasta'],
            [['desde' => '2026-02-30', 'hasta' => '2026-11-05'], 'desde'],
            [['desde' => ['2026-11-05'], 'hasta' => '2026-11-05'], 'desde'],
            [['fecha' => '2026-11-05', 'desde' => '2026-11-05', 'hasta' => '2026-11-06'], 'fecha'],
            [['hora_desde' => '24:00'], 'hora_desde'], [['hora_hasta' => '08:15:30'], 'hora_hasta'],
            [['hora_desde' => '12:00', 'hora_hasta' => '11:59'], 'hora_hasta'],
            [['hora_desde' => ['06:30']], 'hora_desde'], [['paciente' => ['nombre']], 'paciente'],
            [['hora_desde' => ['06:30'], 'hora_hasta' => '09:00'], 'hora_desde'],
            [['estado' => 'NO_CONFIRMADO'], 'estado'], [['estado' => ['PENDIENTE']], 'estado'],
            [['tipo' => 'SOBRETURNO'], 'tipo'], [['page' => '0'], 'page'],
            [['page' => '-1'], 'page'], [['page' => '1.5'], 'page'], [['page' => ['2']], 'page'],
        ];
    }

    public function test_vacios_filtrados_y_del_periodo_son_distintos_y_limpiar_conserva_el_ambito_temporal(): void
    {
        $this->actingAs($this->propia->user);
        $contexto = ['desde' => '2026-11-04', 'hasta' => '2026-11-06'];

        $this->get(route('turnos.agenda-atencion.index', $contexto + ['paciente' => 'Sin coincidencias']))
            ->assertOk()->assertViewHas('turnos', [])
            ->assertSee('No hay turnos que coincidan con los criterios seleccionados.')
            ->assertDontSee('No hay turnos registrados para este período.')
            ->assertSee('href="'.e(route('turnos.agenda-atencion.index', $contexto)).'"', false);
        $this->get(route('turnos.agenda-atencion.index', $contexto))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id]);
        $this->get(route('turnos.agenda-atencion.index', ['desde' => '2026-12-01', 'hasta' => '2026-12-03']))
            ->assertOk()->assertSee('No hay turnos registrados para este período.')
            ->assertDontSee('No hay turnos que coincidan con los criterios seleccionados.');
    }

    public function test_la_navegacion_diaria_preserva_solo_los_filtros_validados_y_reinicia_la_pagina(): void
    {
        $filtros = ['paciente' => 'Propio610', 'estado' => 'PENDIENTE', 'tipo' => 'PRIMERA_CONSULTA', 'hora_desde' => '06:00'];
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', [
            'fecha' => '2026-11-05', 'profesional_id' => $this->ajena->id,
        ] + $filtros));

        foreach (['2026-11-04', '2026-11-06'] as $fecha) {
            $this->assertEnlaceConsulta($respuesta, ['fecha' => $fecha] + $filtros);
        }
        $respuesta->assertOk()->assertDontSee('profesional_id=')
            ->assertSee('value="Propio610"', false)->assertSee('value="06:00"', false);
    }

    public function test_paginacion_estable_conserva_criterios_sin_duplicados_ni_consultas_por_turno(): void
    {
        $ids = [$this->turno->id];
        for ($i = 0; $i < 27; $i++) {
            $ids[] = Turno::factory()->for($this->paciente)->for($this->propia)->create([
                'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => EstadoTurnoEnum::PENDIENTE,
                'inicio_at' => '2026-11-05 09:00:00', 'fin_at' => '2026-11-05 09:30:00',
            ])->id;
        }
        $criterios = ['desde' => '2026-11-05', 'hasta' => '2026-11-05', 'paciente' => 'Propio610',
            'estado' => 'PENDIENTE', 'tipo' => 'PRIMERA_CONSULTA', 'hora_desde' => '00:00', 'hora_hasta' => '23:59'];
        $antes = $this->contenidoBase();
        $this->actingAs($this->propia->user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $primera = $this->get(route('turnos.agenda-atencion.index', $criterios + ['user_id' => $this->ajena->user_id]));
            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $primera->assertOk()->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === array_slice($ids, 0, 25))
            ->assertViewHas('paginacion', fn ($paginacion) => $paginacion->total() === 28 && $paginacion->lastPage() === 2)
            ->assertSeeText('28 turnos encontrados. Página 1 de 2.')
            ->assertSee('aria-label="Páginas de resultados"', false)->assertSee('Página siguiente')
            ->assertDontSee('pagination.next')->assertDontSee('pagination.previous')
            ->assertDontSee('user_id=')->assertDontSee('PacienteAjeno610');
        $this->assertEnlaceConsulta($primera, $criterios + ['page' => '2']);
        foreach (['turnos', 'pacientes'] as $tabla) {
            $this->assertCount(1, array_filter($consultas, fn ($consulta) => preg_match('/from "'.$tabla.'"/i', $consulta['query'])));
        }
        $this->get(route('turnos.agenda-atencion.index', $criterios + ['page' => 2]))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === array_slice($ids, 25))
            ->assertSeeText('28 turnos encontrados. Página 2 de 2.');
        $fueraDeRango = $this->get(route('turnos.agenda-atencion.index', $criterios + ['page' => 999]))->assertStatus(302);
        $urlEsperada = route('turnos.agenda-atencion.index', $criterios + ['page' => 2]);
        $this->assertSame(parse_url($urlEsperada, PHP_URL_PATH), parse_url($fueraDeRango->headers->get('Location'), PHP_URL_PATH));
        parse_str(parse_url($fueraDeRango->headers->get('Location'), PHP_URL_QUERY), $parametros);
        $this->assertEquals($criterios + ['page' => '2'], $parametros);

        $this->assertSame($antes, $this->contenidoBase());
    }

    private function assertEnlaceConsulta(TestResponse $respuesta, array $parametros): void
    {
        $html = new \DOMDocument;
        @$html->loadHTML($respuesta->getContent(), LIBXML_NONET);
        $encontrado = false;
        foreach ($html->getElementsByTagName('a') as $enlace) {
            $url = $enlace->getAttribute('href');
            if (parse_url($url, PHP_URL_PATH) !== parse_url(route('turnos.agenda-atencion.index'), PHP_URL_PATH)) {
                continue;
            }
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $actuales);
            if ($actuales == $parametros) {
                $encontrado = true;
                break;
            }
        }
        $this->assertTrue($encontrado, 'Falta el enlace con el contexto autorizado esperado.');
    }

    public function test_filtros_vacios_no_restringen_y_los_controles_tienen_etiquetas_y_errores_asociados(): void
    {
        $this->actingAs($this->propia->user);
        $this->get(route('turnos.agenda-atencion.index', ['paciente' => '', 'hora_desde' => '', 'hora_hasta' => '', 'estado' => '', 'tipo' => '']))
            ->assertOk()->assertViewHas('filtros', [])
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id]);
        $this->get(route('turnos.agenda-atencion.index', ['hora_desde' => '25:00']))
            ->assertRedirect(route('turnos.agenda-atencion.index'));
        $respuesta = $this->get(route('turnos.agenda-atencion.index'))->assertOk()
            ->assertSee('id="hora_desde-error"', false)->assertSee('aria-describedby="filtros-ayuda hora_desde-error"', false)
            ->assertSee('role="alert"', false);
        foreach (['paciente', 'hora_desde', 'hora_hasta', 'estado', 'tipo', 'desde', 'hasta'] as $campo) {
            $respuesta->assertSee('for="'.$campo.'"', false)->assertSee('id="'.$campo.'"', false);
        }
    }

    #[DataProvider('estadosDetalle')]
    public function test_detalle_presenta_estados_y_tipos_sin_clinica_ni_escrituras(EstadoTurnoEnum $estado, TipoTurnoEnum $tipo, string $etiqueta): void
    {
        $turno = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'estado' => $estado, 'tipo' => $tipo, 'motivo_solicitud' => 'Clinica excluida del detalle',
            'inicio_at' => '2026-11-05 23:45:00', 'fin_at' => '2026-11-06 00:20:00',
        ]);
        $antes = $this->contenidoBase();
        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.show', $turno->id))
            ->assertOk()->assertViewHas('turno', fn ($datos) => $datos['id'] === $turno->id)
            ->assertSee('PacientePropio610')->assertSee($etiqueta)
            ->assertSee($tipo === TipoTurnoEnum::PRIMERA_CONSULTA ? 'Primera consulta' : 'Seguimiento')
            ->assertSee('05/11/2026 23:45')->assertSee('06/11/2026 00:20')->assertSee('35 minutos')
            ->assertDontSee('Clinica excluida del detalle')->assertDontSee('privado610@example.test')
            ->assertDontSee('/documentos-clinicos/')->assertDontSee('confirmar-asistencia')
            ->assertDontSee('PacienteAjeno610')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($antes, $this->contenidoBase());
    }

    public static function estadosDetalle(): array
    {
        return [
            [EstadoTurnoEnum::PENDIENTE, TipoTurnoEnum::PRIMERA_CONSULTA, 'Pendiente'],
            [EstadoTurnoEnum::CONFIRMADO, TipoTurnoEnum::SEGUIMIENTO, 'Confirmado'],
            [EstadoTurnoEnum::EN_ATENCION, TipoTurnoEnum::PRIMERA_CONSULTA, 'En atención'],
            [EstadoTurnoEnum::ATENDIDO, TipoTurnoEnum::SEGUIMIENTO, 'Atendido'],
            [EstadoTurnoEnum::AUSENTE, TipoTurnoEnum::PRIMERA_CONSULTA, 'Ausente'],
            [EstadoTurnoEnum::CANCELADO, TipoTurnoEnum::SEGUIMIENTO, 'Cancelado'],
        ];
    }

    #[DataProvider('estadosDetalle')]
    public function test_modal_reutiliza_los_campos_escapados_del_detalle_sin_layout_ni_escrituras(EstadoTurnoEnum $estado, TipoTurnoEnum $tipo, string $etiqueta): void
    {
        $nombre = '<script>detalle-modal</script>';
        $this->paciente->user->update(['name' => $nombre]);
        $turno = Turno::factory()->for($this->paciente)->for($this->propia)->create([
            'estado' => $estado, 'tipo' => $tipo, 'inicio_at' => '2026-11-05 06:30:00', 'fin_at' => '2026-11-05 07:15:00',
            'motivo_solicitud' => 'Motivo clínico privado 610',
        ]);
        $antes = $this->contenidoBase();
        $this->actingAs($this->propia->user)->withHeader('X-Agenda-Detalle', 'modal');
        $this->get(route('turnos.agenda-atencion.show', $turno->id))
            ->assertOk()->assertViewIs('turnos.agenda-atencion.detalle')
            ->assertHeader('X-Agenda-Detalle', 'administrativo')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Vary', 'X-Agenda-Detalle')
            ->assertSee($nombre)->assertDontSee($nombre, false)->assertSee($etiqueta)
            ->assertSee($tipo === TipoTurnoEnum::PRIMERA_CONSULTA ? 'Primera consulta' : 'Seguimiento')
            ->assertSee('05/11/2026 06:30')->assertSee('05/11/2026 07:15')->assertSee('45 minutos')
            ->assertSee($this->propia->user->name)
            ->assertDontSee('<html', false)->assertDontSee('<nav', false)->assertDontSee('Volver a la agenda')
            ->assertDontSee('Motivo clínico privado 610')->assertDontSee('privado610@example.test')
            ->assertDontSee('PacienteAjeno610')->assertDontSee('/documentos-clinicos/');
        $this->assertSame($antes, $this->contenidoBase());
    }

    #[DataProvider('estadosDetalle')]
    public function test_listado_y_modal_comparten_distintivo_y_etiqueta_del_estado(EstadoTurnoEnum $estado, TipoTurnoEnum $tipo, string $etiqueta): void
    {
        $this->turno->forceFill(['estado' => $estado])->save();
        $this->actingAs($this->propia->user);
        $lista = $this->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-11-05', 'estado' => $estado->value]))->assertOk();
        $modal = $this->withHeader('X-Agenda-Detalle', 'modal')->get(route('turnos.agenda-atencion.show', $this->turno->id))->assertOk();
        $distintivos = [];
        foreach ([$lista, $modal] as $respuesta) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$respuesta->getContent(), LIBXML_NONET);
            $nodos = (new \DOMXPath($dom))->query('//span[contains(concat(" ", normalize-space(@class), " "), " uc05-estado ")]');
            $this->assertSame(1, $nodos->length);
            $this->assertSame($etiqueta, trim($nodos->item(0)->textContent));
            $distintivos[] = $nodos->item(0)->getAttribute('class');
        }
        $this->assertSame($distintivos[0], $distintivos[1]);
        $this->assertStringContainsString('uc05-estado--'.strtolower($estado->value), $distintivos[0]);
    }

    public function test_controles_de_hora_siguen_siendo_nativos_con_relojes_decorativos_y_jornada_navegable(): void
    {
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', [
            'fecha' => '2026-11-05', 'hora_desde' => '08:00', 'hora_hasta' => '18:00',
        ]))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$respuesta->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//input[@type="time"]')->length);
        $this->assertSame(2, $xpath->query('//div[contains(@class,"uc05-campo-hora")]/*[local-name()="svg" and @aria-hidden="true" and @focusable="false"]')->length);
        $this->assertSame(1, $xpath->query('//input[@type="time" and @name="hora_desde" and @value="08:00"]')->length);
        $this->assertSame(1, $xpath->query('//input[@type="time" and @name="hora_hasta" and @value="18:00"]')->length);
        $this->assertSame(1, $xpath->query('//form[@aria-label="Consultar una jornada"]/following-sibling::nav[@aria-label="Navegación entre jornadas"]')->length);
    }

    public function test_detalle_ajeno_e_inexistente_no_cargan_pacientes_y_no_revelan_identidad(): void
    {
        $ajeno = Turno::where('kinesiologa_id', $this->ajena->id)->value('id');
        $this->actingAs($this->propia->user);
        foreach ([$ajeno, 99999999] as $id) {
            foreach (['page', 'modal'] as $representacion) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $respuesta = $this->withHeader('X-Agenda-Detalle', $representacion)->get(route('turnos.agenda-atencion.show', $id));
                    $consultas = DB::getQueryLog();
                } finally {
                    DB::disableQueryLog();
                    DB::flushQueryLog();
                }
                $respuesta->assertNotFound()->assertDontSee('PacienteAjeno610')->assertDontSee('PacientePropio610');
                foreach ($consultas as $consulta) {
                    $this->assertDoesNotMatchRegularExpression('/\b(pacientes|documentos_clinicos)\b/i', $consulta['query']);
                }
            }
        }
    }

    #[DataProvider('accesosDetalle')]
    public function test_detalle_aplica_controles_transversales_y_revalida_el_rol(string $caso, int $codigo): void
    {
        $actor = $this->propia->user;
        match ($caso) {
            'paciente' => $actor = $this->paciente->user,
            'administrador' => $actor = $this->usuarioUc07(RoleEnum::ADMINISTRADOR),
            'inactivo' => $actor->update(['activo' => false]),
            'correo' => $actor->forceFill(['email_verified_at' => null])->save(),
            'perfil' => $actor->update(['telefono' => null]),
            default => null,
        };
        if ($caso !== 'guest') {
            $this->actingAs($actor);
        }
        if ($caso === 'rol revocado') {
            $actor->load('roles', 'kinesiologa');
            $actor->fresh()->removeRole(RoleEnum::KINESIOLOGO->value);
        }
        $this->get(route('turnos.agenda-atencion.show', $this->turno->id))->assertStatus($codigo)
            ->assertDontSee('PacientePropio610')->assertDontSee('Motivo clínico privado 610');
        $this->withHeader('X-Agenda-Detalle', 'modal')
            ->get(route('turnos.agenda-atencion.show', $this->turno->id))->assertStatus($codigo)
            ->assertDontSee('PacientePropio610')->assertDontSee('Motivo clínico privado 610');
    }

    public static function accesosDetalle(): array
    {
        return [['guest', 302], ['paciente', 403], ['administrador', 403], ['inactivo', 302], ['correo', 302], ['perfil', 302], ['rol revocado', 403]];
    }

    public function test_detalle_preserva_contexto_validado_y_escapa_nombres(): void
    {
        $nombre = '<script>alert("detalle")</script>';
        $this->paciente->user->update(['name' => $nombre]);
        $contexto = ['desde' => '2026-11-01', 'hasta' => '2026-11-30', 'paciente' => 'Prueba', 'estado' => 'PENDIENTE', 'page' => '2'];
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.show', ['turno' => $this->turno->id] + $contexto + ['kinesiologa_id' => $this->ajena->id]));
        $respuesta->assertOk()->assertSee($nombre)->assertDontSee($nombre, false)->assertDontSee('kinesiologa_id=');
        $this->assertEnlaceConsulta($respuesta, $contexto);
        $ruta = Route::getRoutes()->getByName('turnos.agenda-atencion.show');
        $this->assertSame(['GET', 'HEAD'], $ruta->methods());
        $this->assertSame(['web', 'auth', 'perfil.kinesiologo', 'verified', 'perfil.completo'], $ruta->gatherMiddleware());
        $this->get(route('turnos.agenda-atencion.index', ['fecha' => '2026-11-05']))->assertOk()
            ->assertSee('href="'.e(route('turnos.agenda-atencion.show', ['turno' => $this->turno->id, 'fecha' => '2026-11-05', 'page' => 1])).'"', false);
        $this->get(route('turnos.agenda-atencion.show', ['turno' => $this->turno->id, 'fecha' => 'ayer']))
            ->assertRedirect(route('turnos.agenda-atencion.index'))->assertSessionHasErrors('fecha');
    }

    public function test_listado_conserva_url_de_detalle_con_periodo_filtros_y_pagina_para_modal_y_sin_javascript(): void
    {
        Turno::factory()->count(26)->for($this->paciente)->for($this->propia)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => EstadoTurnoEnum::PENDIENTE,
            'inicio_at' => '2026-11-05 08:00:00', 'fin_at' => '2026-11-05 09:00:00',
        ]);
        $contexto = ['desde' => '2026-11-01', 'hasta' => '2026-11-30', 'paciente' => 'PacientePropio610',
            'estado' => 'PENDIENTE', 'tipo' => 'PRIMERA_CONSULTA', 'hora_desde' => '08:00', 'hora_hasta' => '09:00', 'page' => 2];
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', $contexto))->assertOk();
        $turnos = $respuesta->viewData('turnos');
        $this->assertCount(1, $turnos);
        $respuesta->assertSee('x-data="agendaAtencion"', false)
            ->assertSee('x-on:click="abrirDetalle($event)" aria-haspopup="dialog"', false);
        preg_match('/<a href="([^"]+)"\s+x-on:click="abrirDetalle/', $respuesta->getContent(), $enlace);
        $url = html_entity_decode($enlace[1]);
        $this->assertSame(route('turnos.agenda-atencion.show', $turnos[0]['id']), strtok($url, '?'));
        parse_str(parse_url($url, PHP_URL_QUERY), $criterios);
        $this->assertEquals($contexto, $criterios);
        $this->get(route('turnos.agenda-atencion.show', ['turno' => $turnos[0]['id']] + $contexto))
            ->assertOk()->assertViewIs('turnos.agenda-atencion.show')->assertHeaderMissing('X-Agenda-Detalle');
    }

    #[DataProvider('canalesExportacion')]
    public function test_exportacion_respeta_periodo_filtros_y_todos_los_resultados_sin_escrituras(string $canal): void
    {
        $ids = [];
        for ($i = 0; $i < 27; $i++) {
            $ids[] = Turno::factory()->for($this->paciente)->for($this->propia)->create([
                'tipo' => TipoTurnoEnum::SEGUIMIENTO, 'estado' => EstadoTurnoEnum::CONFIRMADO,
                'inicio_at' => '2026-11-06 10:00:00', 'fin_at' => '2026-11-06 10:25:00',
                'motivo_solicitud' => 'Privado excluido de exportacion',
            ])->id;
        }
        $datosRenderizados = null;
        View::composer('turnos.agenda-atencion.exportacion', function ($vista) use (&$datosRenderizados): void {
            $datosRenderizados = $vista->getData();
        });
        $antes = $this->contenidoBase();
        $criterios = ['desde' => '2026-11-05', 'hasta' => '2026-11-06', 'paciente' => 'propio610',
            'hora_desde' => '10:00', 'hora_hasta' => '10:00', 'estado' => 'CONFIRMADO', 'tipo' => 'SEGUIMIENTO'];
        $this->actingAs($this->propia->user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $respuesta = $this->get(route('turnos.agenda-atencion.'.$canal, $criterios + ['page' => 2, 'kinesiologa_id' => $this->ajena->id]));
            $consultas = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $respuesta->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($ids, array_column($datosRenderizados['resultados'], 'id'));
        $this->assertEquals($criterios, $datosRenderizados['parametros']);
        $this->assertSame('05/11/2026 22:00:00', $datosRenderizados['generadoEn']->format('d/m/Y H:i:s'));
        foreach ($datosRenderizados['resultados'] as $turno) {
            $this->assertSame(['id', 'paciente', 'kinesiologa', 'tipo', 'estado', 'inicio_at', 'fin_at'], array_keys($turno));
            $this->assertSame(['id', 'nombre'], array_keys($turno['paciente']));
        }
        foreach (['turnos', 'pacientes'] as $tabla) {
            $this->assertCount(1, array_filter($consultas, fn ($consulta) => preg_match('/from "'.$tabla.'"/i', $consulta['query'])));
        }
        if ($canal === 'pdf') {
            $respuesta->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('attachment; filename="agenda-atencion_', $respuesta->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
            $this->assertStringContainsString('%%EOF', $respuesta->getContent());
            $htmlPdf = view('turnos.agenda-atencion.exportacion', $datosRenderizados)->render();
            $this->assertSame(2, substr_count($htmlPdf, '<table>'));
            $this->assertSame(27, substr_count($htmlPdf, '<td>PacientePropio610 Prueba</td>'));
            $this->assertSame(1, substr_count($htmlPdf, 'id="titulo-tabla-agenda"'));
            $this->assertSame(1, substr_count($htmlPdf, 'id="titulo-tabla-agenda-1"'));
        } else {
            $respuesta->assertSee('05/11/2026 22:00:00')->assertSee('06/11/2026 10:00')
                ->assertSee('25 minutos')->assertSee('Confirmado')->assertSee('Ctrl+P')
                ->assertDontSee('Privado excluido de exportacion')->assertDontSee('Motivo clínico privado 610')
                ->assertDontSee('privado610@example.test')->assertDontSee('PacienteAjeno610')->assertDontSee('page=');
            $this->assertSame(27, substr_count($respuesta->getContent(), '<td>PacientePropio610 Prueba</td>'));
        }
        $this->assertSame($antes, $this->contenidoBase());
    }

    public static function canalesExportacion(): array
    {
        return [['imprimir'], ['pdf']];
    }

    #[DataProvider('accesosExportacion')]
    public function test_exportacion_rechaza_actores_no_habilitados_sin_generar_documentos(string $canal, string $caso, int $codigo): void
    {
        Pdf::shouldReceive('setOptions')->never();
        $actor = $this->propia->user;
        match ($caso) {
            'paciente' => $actor = $this->paciente->user,
            'administrador' => $actor = $this->usuarioUc07(RoleEnum::ADMINISTRADOR),
            'superadmin' => $actor = $this->usuarioUc07(RoleEnum::SUPERADMIN),
            'inactivo' => $actor->update(['activo' => false]),
            'correo' => $actor->forceFill(['email_verified_at' => null])->save(),
            'perfil' => $actor->update(['telefono' => null]),
            default => null,
        };
        if ($caso !== 'guest') {
            $this->actingAs($actor);
        }
        if ($caso === 'rol revocado') {
            $actor->load('roles', 'kinesiologa');
            $actor->fresh()->removeRole(RoleEnum::KINESIOLOGO->value);
        }
        $this->get(route('turnos.agenda-atencion.'.$canal))->assertStatus($codigo)
            ->assertDontSee('PacientePropio610')->assertDontSee('PacienteAjeno610');
    }

    public static function accesosExportacion(): array
    {
        $casos = [];
        foreach (['imprimir', 'pdf'] as $canal) {
            foreach (array_merge(self::accesosDetalle(), [['superadmin', 403]]) as [$actor, $codigo]) {
                $casos[] = [$canal, $actor, $codigo];
            }
        }

        return $casos;
    }

    #[DataProvider('canalesExportacion')]
    public function test_exportacion_valida_criterios_y_distingue_vacio_de_error(string $canal): void
    {
        $this->actingAs($this->propia->user);
        $this->get(route('turnos.agenda-atencion.'.$canal, ['estado' => 'NO_CONFIRMADO']))
            ->assertRedirect(route('turnos.agenda-atencion.index'))->assertSessionHasErrors('estado');
        $this->get(route('turnos.agenda-atencion.'.$canal, ['desde' => '2026-11-06', 'hasta' => '2026-11-05']))
            ->assertRedirect(route('turnos.agenda-atencion.index'))->assertSessionHasErrors('hasta');
        $respuesta = $this->get(route('turnos.agenda-atencion.'.$canal, ['fecha' => '2026-11-07']))->assertOk();
        if ($canal === 'pdf') {
            $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        } else {
            $respuesta->assertSee('No hay turnos registrados para esta fecha.');
        }
        $ruta = Route::getRoutes()->getByName('turnos.agenda-atencion.'.$canal);
        $this->assertSame(['GET', 'HEAD'], $ruta->methods());
        $this->assertSame(['web', 'auth', 'perfil.kinesiologo', 'verified', 'perfil.completo'], $ruta->gatherMiddleware());
    }

    #[DataProvider('canalesExportacion')]
    public function test_exportacion_fallida_permite_reintento_sin_revelar_excepcion_ni_modificar_datos(string $canal): void
    {
        $antes = $this->contenidoBase();
        if ($canal === 'pdf') {
            Pdf::shouldReceive('setOptions')->once()->andThrow(new \RuntimeException('SECRETO_INTERNO_NO_MOSTRAR'));
        } else {
            View::composer('turnos.agenda-atencion.exportacion', fn () => throw new \RuntimeException('SECRETO_INTERNO_NO_MOSTRAR'));
        }
        $criterios = ['fecha' => '2026-11-05', 'estado' => 'PENDIENTE'];
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.'.$canal, $criterios));
        $respuesta->assertStatus(503)->assertSee('Reintentar')->assertDontSee('SECRETO_INTERNO_NO_MOSTRAR')
            ->assertDontSee('PacientePropio610')->assertDontSee('No hay turnos registrados');
        $this->assertEnlaceConsulta($respuesta, $criterios);
        $respuesta->assertSee('href="'.e(route('turnos.agenda-atencion.'.$canal, $criterios)).'"', false);
        $this->assertSame($antes, $this->contenidoBase());
    }

    public function test_exportacion_escapa_contenido_y_nueva_consulta_muestra_el_estado_vigente(): void
    {
        $this->paciente->user->update(['name' => '<img src="https://externo.invalid/imagen">']);
        $this->actingAs($this->propia->user);
        $anterior = $this->get(route('turnos.agenda-atencion.imprimir'))->assertOk()
            ->assertSee('<img src="https://externo.invalid/imagen">')->assertDontSee('<img src=', false);
        $this->turno->forceFill(['estado' => EstadoTurnoEnum::CANCELADO])->save();
        $this->assertSame(EstadoTurnoEnum::CANCELADO, $this->turno->fresh()->estado);
        $this->get(route('turnos.agenda-atencion.imprimir'))->assertOk()->assertSee('Cancelado');
        $anterior->assertSee('Pendiente')->assertDontSee('Cancelado');
    }

    public function test_ux_agenda_tiene_entrada_profesional_salto_al_contenido_y_controles_semanticos(): void
    {
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'))->assertOk();
        $respuesta->assertSee('href="#agenda-atencion-contenido"', false)
            ->assertSee('id="agenda-atencion-contenido" tabindex="-1"', false)
            ->assertSee('aria-label="Consultar una jornada"', false)
            ->assertSee('aria-label="Consultar un período"', false)
            ->assertSee('id="resumen-resultados" role="status"', false)
            ->assertSee('Actualizar agenda')->assertDontSee('fonts.bunny.net');
        $this->assertEnlaceConsulta($respuesta, ['fecha' => '2026-11-05', 'page' => '1']);
        $menu = view('layouts.navigation')->render();
        $this->assertSame(2, substr_count($menu, route('turnos.agenda-atencion.index')));
        $this->actingAs($this->paciente->user);
        $this->assertStringNotContainsString(route('turnos.agenda-atencion.index'), view('layouts.navigation')->render());
    }

    public function test_ux_vacio_de_periodo_y_exportacion_tienen_instrucciones_y_contexto_coherentes(): void
    {
        $this->actingAs($this->propia->user);
        $this->get(route('turnos.agenda-atencion.index', ['desde' => '2026-12-01', 'hasta' => '2026-12-03']))
            ->assertOk()->assertSee('Elegí otras fechas para consultar otro período.')
            ->assertDontSee('navegá hacia la jornada anterior o siguiente.');
        $this->get(route('turnos.agenda-atencion.imprimir'))->assertOk()
            ->assertSee('role="region" aria-labelledby="titulo-tabla-agenda" tabindex="0"', false)
            ->assertSee('scope="col"', false)->assertSee('Ctrl+P');
    }

    public function test_limpiar_fechas_es_un_boton_local_y_los_enlaces_conservan_la_consulta_vigente(): void
    {
        $criterios = ['desde' => '2026-11-05', 'hasta' => '2026-11-06', 'estado' => 'PENDIENTE'];
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', $criterios))->assertOk();
        $respuesta->assertSee('x-on:click="limpiarFechas($event)"', false)->assertSee('Limpiar fechas');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $boton = $xpath->query('//form[@aria-label="Consultar un período"]//button[normalize-space(.)="Limpiar fechas"]');
        $this->assertSame(1, $boton->length);
        $this->assertSame('button', $boton->item(0)->getAttribute('type'));
        $this->assertEnlaceConsulta($respuesta, $criterios + ['page' => 1]);
        $respuesta->assertSee('href="'.e(route('turnos.agenda-atencion.pdf', $criterios)).'"', false)
            ->assertSee('href="'.e(route('turnos.agenda-atencion.index', ['desde' => '2026-11-05', 'hasta' => '2026-11-06'])).'"', false);
    }

    public function test_tabla_compacta_separa_horario_paciente_estado_y_accion_sin_profesional_en_las_filas(): void
    {
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$respuesta->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($dom);
        $tabla = '//table[@aria-label="Turnos de la jornada"]';
        $encabezados = $xpath->query($tabla.'/thead/tr/th');
        $this->assertSame(['Horario', 'Paciente', 'Estado', 'Acciones'], array_map(fn ($nodo) => trim($nodo->textContent), iterator_to_array($encabezados)));
        $this->assertSame(1, $xpath->query($tabla.'/thead/tr/th[4]/span[@class="sr-only"]')->length);
        $celdas = $xpath->query($tabla.'/tbody/tr/td');
        $this->assertSame(4, $celdas->length);
        foreach (['06:30', '07:15', '05/11/2026', '45 minutos'] as $dato) {
            $this->assertStringContainsString($dato, $celdas->item(0)->textContent);
        }
        $this->assertStringContainsString('PacientePropio610', $celdas->item(1)->textContent);
        $this->assertStringContainsString('Primera consulta', $celdas->item(1)->textContent);
        $this->assertSame('Pendiente', trim($celdas->item(2)->textContent));
        $this->assertSame('Ver detalle de turno', trim($celdas->item(3)->textContent));
        $this->assertStringNotContainsString(trim($this->propia->user->name.' '.$this->propia->user->apellido), $xpath->query($tabla.'/tbody')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//p[@id="resumen-resultados"]/following-sibling::nav[@aria-label="Obtener agenda"]')->length);
        $this->assertSame(1, $xpath->query('//p[@id="resumen-resultados"]/following::table')->length);
    }

    public function test_filtros_aplicados_se_conservan_aunque_el_panel_inicie_plegado(): void
    {
        $criterios = ['fecha' => '2026-11-05', 'estado' => 'PENDIENTE', 'paciente' => 'Propio610'];
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index', $criterios))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$respuesta->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//details')->length);
        $this->assertSame(0, $xpath->query('//details[@open]')->length);
        $this->assertSame(1, $xpath->query('//details//select[@name="estado"]/option[@value="PENDIENTE" and @selected]')->length);
        $respuesta->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === [$this->turno->id]);
        $this->assertEnlaceConsulta($respuesta, $criterios + ['page' => 1]);
    }

    #[DataProvider('rutasDeLectura')]
    public function test_ux_error_de_carga_ofrece_reintento_sin_vacio_ni_datos_sensibles(string $accion): void
    {
        $this->actingAs($this->propia->user);
        DB::listen(function ($consulta): void {
            if (preg_match('/from "turnos"/i', $consulta->sql)) {
                throw new QueryException('sqlite', 'SELECT SECRETO_INTERNO', [], new \PDOException('NO_REVELAR_ERROR'));
            }
        });
        $criterios = ['fecha' => '2026-11-05', 'estado' => 'PENDIENTE'];
        $parametros = $accion === 'show' ? ['turno' => $this->turno->id] + $criterios : $criterios;
        $this->get(route('turnos.agenda-atencion.'.$accion, $parametros))->assertStatus(503)
            ->assertSee('No pudimos consultar la agenda')->assertSee('Reintentar')
            ->assertSee('href="'.e(route('turnos.agenda-atencion.'.$accion, $parametros)).'"', false)
            ->assertDontSee('No hay turnos registrados')->assertDontSee('SECRETO_INTERNO')
            ->assertDontSee('NO_REVELAR_ERROR')->assertDontSee('PacientePropio610');
    }

    public static function rutasDeLectura(): array
    {
        return [['index'], ['show'], ['imprimir'], ['pdf']];
    }

    public function test_ux_pdf_real_no_habilita_recursos_remotos_o_ejecucion_y_escapa_nombres(): void
    {
        $renderizador = null;
        app()->afterResolving('dompdf.wrapper', function ($pdf) use (&$renderizador): void {
            $renderizador = $pdf;
        });
        $this->paciente->user->update(['name' => '<img src="https://externo.invalid/imagen">']);
        $respuesta = $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.pdf'))->assertOk();
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        // La fachada resuelve una instancia nueva en cada llamada estática.
        $opciones = $renderizador->getOptions();
        $this->assertFalse($opciones->getIsRemoteEnabled());
        $this->assertFalse($opciones->getIsPhpEnabled());
        $this->assertFalse($opciones->getIsJavascriptEnabled());
        $this->assertSame(['file://'], array_keys($opciones->getAllowedProtocols()));
        $this->assertSame('print', $opciones->getDefaultMediaType());
    }

    public function test_ux_html_de_agenda_detalle_e_impresion_tiene_referencias_y_foco_validos(): void
    {
        $this->actingAs($this->propia->user);
        foreach (['index', 'show', 'imprimir'] as $accion) {
            $parametros = $accion === 'show' ? ['turno' => $this->turno->id] : [];
            $respuesta = $this->get(route('turnos.agenda-atencion.'.$accion, $parametros))->assertOk();
            $documento = new \DOMDocument;
            @$documento->loadHTML($respuesta->getContent(), LIBXML_NONET);
            $xpath = new \DOMXPath($documento);
            $this->assertSame(1, $xpath->query('//main')->length);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame(0, $xpath->query('//*[@tabindex > 0]')->length);
            $ids = [];
            foreach ($xpath->query('//*[@id]') as $nodo) {
                $this->assertNotContains($nodo->getAttribute('id'), $ids);
                $ids[] = $nodo->getAttribute('id');
            }
            foreach ($xpath->query('//main//label[@for]') as $etiqueta) {
                $this->assertContains($etiqueta->getAttribute('for'), $ids);
            }
            foreach ($xpath->query('//main//*[@aria-describedby or @aria-labelledby]') as $nodo) {
                foreach (['aria-describedby', 'aria-labelledby'] as $atributo) {
                    foreach (preg_split('/\s+/', trim($nodo->getAttribute($atributo)), flags: PREG_SPLIT_NO_EMPTY) as $id) {
                        $this->assertContains($id, $ids);
                    }
                }
            }
            foreach ($xpath->query('//main//a') as $enlace) {
                $this->assertNotSame('', trim($enlace->textContent.$enlace->getAttribute('aria-label')));
            }
        }
    }

    #[DataProvider('volumenesAgenda')]
    public function test_rendimiento_acotado_y_volumen_sin_n_mas_uno_ni_perdida_de_resultados(int $cantidad): void
    {
        $pacientes = [$this->paciente];
        $this->paciente->user->update(['name' => 'Volumen0', 'apellido' => 'Sintetico']);
        for ($i = 1; $i < 30; $i++) {
            $usuario = $this->usuarioUc07(RoleEnum::PACIENTE);
            $usuario->update(['name' => 'Volumen'.$i, 'apellido' => 'Sintetico']);
            $pacientes[] = Paciente::create(['user_id' => $usuario->id, 'activo' => true]);
        }
        $base = CarbonImmutable::parse('2026-11-05 07:00:00', config('app.timezone'));
        $this->turno->update(['inicio_at' => $base, 'fin_at' => $base->addMinutes(20)]);
        $ids = [$this->turno->id];
        for ($i = 1; $i < $cantidad; $i++) {
            $inicio = $base->addDays(intdiv($i, 30))->addMinutes(($i % 30) * 20);
            $ids[] = Turno::factory()->create([
                'paciente_id' => $pacientes[$i % 30]->id, 'kinesiologa_id' => $this->propia->id,
                'tipo' => $i % 2 === 0 ? TipoTurnoEnum::PRIMERA_CONSULTA : TipoTurnoEnum::SEGUIMIENTO,
                'estado' => EstadoTurnoEnum::cases()[$i % 6], 'inicio_at' => $inicio, 'fin_at' => $inicio->addMinutes(20),
            ])->id;
        }
        $periodo = ['desde' => $base->toDateString(), 'hasta' => $base->addDays(intdiv($cantidad - 1, 30))->toDateString()];
        $operaciones = [
            'jornada' => ['index', ['fecha' => $base->toDateString()]],
            'periodo' => ['index', $periodo],
            'filtros' => ['index', $periodo + ['paciente' => 'Volumen', 'estado' => 'PENDIENTE']],
            'detalle' => ['show', ['turno' => $this->turno->id]],
            'impresion' => ['imprimir', $periodo],
            'pdf' => ['pdf', $periodo],
        ];
        $antes = $this->contenidoBase();
        $this->actingAs($this->propia->user);
        $mediciones = [];
        // Medición de operación habitual con Blade compilado, independiente del orden
        // de PHPUnit. Registrar por separado la preparación inicial; no certifica carga fría.
        $inicioPreparacion = hrtime(true);
        $this->get(route('turnos.agenda-atencion.index', ['fecha' => $base->toDateString()]))->assertOk();
        $preparacion = (hrtime(true) - $inicioPreparacion) / 1e9;
        foreach ($operaciones as $nombre => [$accion, $parametros]) {
            gc_collect_cycles();
            DB::enableQueryLog();
            DB::flushQueryLog();
            memory_reset_peak_usage();
            $memoriaInicial = memory_get_usage(false);
            $inicio = hrtime(true);
            try {
                $respuesta = $this->get(route('turnos.agenda-atencion.'.$accion, $parametros));
                $segundos = (hrtime(true) - $inicio) / 1e9;
                $pico = memory_get_peak_usage(false);
                $consultas = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }
            $respuesta->assertOk();
            foreach (['turnos', 'pacientes'] as $tabla) {
                $this->assertCount(1, array_filter($consultas, fn ($consulta) => preg_match('/from "'.$tabla.'"/i', $consulta['query'])));
            }
            $mediciones[$nombre] = [
                'segundos' => round($segundos, 4), 'consultas' => count($consultas),
                'pico_heap_mb' => round($pico / 1048576, 2),
                'incremento_pico_mb' => round(max(0, $pico - $memoriaInicial) / 1048576, 2),
            ];
            if ($nombre === 'jornada') {
                $respuesta->assertViewHas('paginacion', fn ($pagina) => $pagina->total() === 30);
                $this->assertLessThanOrEqual(2, $segundos, 'CA-05-30: jornada sintética de 30 turnos.');
            } elseif ($nombre === 'periodo') {
                $respuesta->assertViewHas('paginacion', fn ($pagina) => $pagina->total() === $cantidad)
                    ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === array_slice($ids, 0, 25));
            } elseif ($nombre === 'filtros') {
                $respuesta->assertViewHas('paginacion', fn ($pagina) => $pagina->total() === intdiv($cantidad, 6));
                if ($cantidad === 30) {
                    $this->assertLessThanOrEqual(1, $segundos, 'CA-05-31: filtros de jornada sintética de 30 turnos.');
                }
            } elseif (in_array($nombre, ['impresion', 'pdf'], true)) {
                if ($nombre === 'pdf') {
                    $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
                } else {
                    $this->assertSame($cantidad, substr_count($respuesta->getContent(), ' Sintetico</td>'));
                }
                if ($cantidad === 30) {
                    $this->assertLessThanOrEqual(10, $segundos, 'CA-05-32: representación de jornada sintética de 30 turnos.');
                }
            }
            unset($respuesta, $consultas);
        }
        $this->get(route('turnos.agenda-atencion.index', $periodo + ['page' => 2]))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === array_slice($ids, 25, 25));
        $this->assertSame($antes, $this->contenidoBase());
        fwrite(STDOUT, 'UC05_VOLUMEN '.json_encode(['turnos' => $cantidad, 'pacientes' => 30, 'turnos_por_jornada' => 30, 'preparacion_segundos' => round($preparacion, 4), 'mediciones' => $mediciones]).PHP_EOL);
    }

    public static function volumenesAgenda(): array
    {
        return [[30], [150], [600]];
    }

    private function getSinDatos(array $parametros = []): TestResponse
    {
        $conexion = DB::connection();
        $conexion->enableQueryLog();
        $conexion->flushQueryLog();
        try {
            $respuesta = $this->get(route('turnos.agenda-atencion.index', $parametros));
            $consultas = $conexion->getQueryLog();
        } finally {
            $conexion->disableQueryLog();
            $conexion->flushQueryLog();
        }
        foreach ($consultas as $consulta) {
            $this->assertDoesNotMatchRegularExpression('/\b(turnos|pacientes|documentos_clinicos)\b/i', $consulta['query']);
        }
        $respuesta->assertDontSee('PacientePropio610')->assertDontSee('PacienteAjeno610')
            ->assertDontSee('Motivo clínico privado 610')->assertDontSee('privado610@example.test');

        return $respuesta;
    }

    private function contenidoBase(): array
    {
        $resultado = [];
        foreach (DB::connection()->getSchemaBuilder()->getTableListing() as $tabla) {
            $resultado[$tabla] = DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $resultado;
    }
}
