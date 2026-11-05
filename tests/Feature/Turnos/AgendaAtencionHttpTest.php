<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Http\Controllers\AgendaAtencionController;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
            ->assertSee('05/11/2026 07:15')->assertSee('45 minutos')
            ->assertSee(trim($this->propia->user->name.' '.$this->propia->user->apellido))
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
        $orden = ['05/11/2026 06:30', '05/11/2026 07:15', 'Primera consulta', 'Pendiente'];
        foreach ($estados as $i => [, $etiqueta]) {
            array_push($orden, '05/11/2026 '.(10 + $i).':10', '05/11/2026 '.(10 + $i).':35',
                $i % 2 === 0 ? 'Primera consulta' : 'Seguimiento', $etiqueta);
        }

        $this->actingAs($this->propia->user)->get(route('turnos.agenda-atencion.index'))
            ->assertOk()->assertSeeInOrder($orden)
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
            ->assertOk()->assertSeeInOrder(['05/11/2026 23:50', '06/11/2026 00:25', '35 minutos', 'Seguimiento'])
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
            ->assertSee('01/01/2100 00:05')->assertDontSee('PacienteAjeno610');

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
        $respuesta->assertOk()->assertDontSee('profesional_id=')->assertDontSee('page=')
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
            ->assertSee('28 turnos encontrados. Página 1 de 2.')
            ->assertSee('aria-label="Páginas de resultados"', false)->assertSee('Página siguiente')
            ->assertDontSee('pagination.next')->assertDontSee('pagination.previous')
            ->assertDontSee('user_id=')->assertDontSee('PacienteAjeno610');
        $this->assertEnlaceConsulta($primera, $criterios + ['page' => '2']);
        foreach (['turnos', 'pacientes'] as $tabla) {
            $this->assertCount(1, array_filter($consultas, fn ($consulta) => preg_match('/from "'.$tabla.'"/i', $consulta['query'])));
        }
        $this->get(route('turnos.agenda-atencion.index', $criterios + ['page' => 2]))->assertOk()
            ->assertViewHas('turnos', fn ($turnos) => array_column($turnos, 'id') === array_slice($ids, 25))
            ->assertSee('28 turnos encontrados. Página 2 de 2.');
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
