<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\ColegioEmisor;
use App\Models\DeclaracionSaludPaciente;
use App\Models\EspecialidadKinesiologia;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\Localidad;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\Provincia;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SolicitarTurnoCreateTest extends TestCase
{
    use RefreshDatabase;

    private Paciente $paciente;

    private Kinesiologa $kinesiologa;

    private CarbonImmutable $ahora;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
        $this->ahora = CarbonImmutable::parse('2026-10-17 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->ahora);
        $this->paciente = $this->pacienteCompleto();
        $this->kinesiologa = $this->profesional();
        Storage::shouldReceive('disk')->never();
    }

    protected function tearDown(): void
    {
        Str::createUuidsNormally();
        CarbonImmutable::setTestNow();
        DB::disableQueryLog();
        parent::tearDown();
    }

    public function test_verified_complete_patient_gets_the_four_step_local_wizard(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk()->assertViewIs('turnos.solicitar.create');
        foreach (['Fecha y horario', 'Elegir profesional', 'Motivo y documentación', 'Revisar y confirmar'] as $paso) {
            $response->assertSee($paso);
        }
        $response->assertSee('Paso 1 de 4')->assertSee('aria-label="Pasos de la solicitud"', false)
            ->assertSee(':aria-current=', false)->assertSee('x-show="paso === 1"', false)
            ->assertSee('hidden grid-cols-4', false)->assertSee('md:hidden', false)
            ->assertSee('x-data="solicitarTurno(', false)->assertSee('x-cloak', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $titulos = [];
        foreach ($xpath->query('//h2[starts-with(@id,"titulo-paso-")]') as $titulo) {
            $titulos[] = [$titulo->getAttribute('id'), $titulo->getAttribute('x-ref'), trim($titulo->textContent)];
        }
        $this->assertSame([
            ['titulo-paso-1', 'tituloPaso1', 'Fecha y horario'],
            ['titulo-paso-2', 'tituloPaso2', 'Elegir profesional'],
            ['titulo-paso-3', 'tituloPaso3', 'Motivo y documentación'],
            ['titulo-paso-4', 'tituloPaso4', 'Revisar y confirmar'],
        ], $titulos);
    }

    public function test_contextual_recovery_has_a_separate_get_retry_outside_the_disabled_form(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $html = preg_replace('/\s@([a-z][a-z.-]*)=/', ' x-on:$1=', $response->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $banner = $xpath->query('//*[@x-ref="mensajeConfirmacion"]')->item(0);
        $this->assertSame('alert', $banner->getAttribute('role'));
        $this->assertSame('-1', $banner->getAttribute('tabindex'));
        $this->assertSame(1, $xpath->query('.//p[@x-text="detalleRecuperacion"]', $banner)->length);
        $retry = $xpath->query('.//button[@*[name()="x-on:click"]="reintentarRecuperacion()"]', $banner)->item(0);
        $this->assertNotNull($retry);
        $this->assertSame('button', $retry->getAttribute('type'));
        $this->assertSame("recuperacion?.estado === 'error'", $retry->getAttribute('x-show'));
        $this->assertSame('enviando || recuperando || consolidado || redireccionando', $retry->getAttribute(':disabled'));
        $this->assertSame(0, $xpath->query('ancestor::fieldset', $retry)->length);
        $this->assertSame(0, $xpath->query('.//*[@*[name()="x-on:click"]="confirmarTurno()"]', $banner)->length);
        foreach (['errorDisponibilidad', 'errorProfesionales'] as $ref) {
            $panel = $xpath->query('//*[@x-ref="'.$ref.'"]')->item(0);
            $this->assertStringContainsString('&& !recuperacion', $panel->getAttribute('x-show'));
        }
    }

    #[DataProvider('actoresBloqueados')]
    public function test_access_uses_existing_patient_middleware(string $actor): void
    {
        if ($actor !== 'guest') {
            $user = match ($actor) {
                'kinesiologo' => $this->kinesiologa->user,
                'administrador' => $this->usuario(RoleEnum::ADMINISTRADOR),
                'superadmin' => $this->usuario(RoleEnum::SUPERADMIN),
                'incompleto' => $this->usuario(RoleEnum::PACIENTE),
                default => $this->paciente->user,
            };
            if ($actor === 'no_verificado') {
                $user->forceFill(['email_verified_at' => null])->save();
            }
            $this->actingAs($user);
        }
        $response = $this->get($this->url());
        match ($actor) {
            'guest' => $response->assertRedirect(route('login')),
            'no_verificado' => $response->assertRedirect(route('verification.notice')),
            'incompleto' => $response->assertRedirect(route('perfil.completar')),
            default => $response->assertForbidden(),
        };
    }

    public static function actoresBloqueados(): array
    {
        return array_map(fn ($actor) => [$actor], ['guest', 'no_verificado', 'incompleto', 'kinesiologo', 'administrador', 'superadmin']);
    }

    #[DataProvider('estadosCanonicos')]
    public function test_d01_entry_preserves_eligibility_without_generating_a_uuid_when_blocked(EstadoTurnoEnum $estado): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => $estado]);
        $ocupa = in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true);
        if ($ocupa) {
            Str::createUuidsUsing(static function (): never {
                throw new RuntimeException('No debe generarse UUID en una entrada bloqueada.');
            });
        }
        $response = $this->actingAs($this->paciente->user)->get($this->url());
        if ($ocupa) {
            $response->assertOk()->assertViewIs('turnos.solicitar.create')
                ->assertViewHas('bloqueadoPorPrimeraConsulta', true)
                ->assertViewHas('urlMisTurnos', route('turnos.mis-turnos.index'))
                ->assertSessionMissing('warning')
                ->assertSee('Ya tenés una Primera consulta activa')
                ->assertSee('No podés registrar otra Primera consulta mientras este turno continúe activo.')
                ->assertDontSee($this->kinesiologa->user->name)
                ->assertDontSee($turno->inicio_at->toDateTimeString())->assertDontSee('Motivo privado');
            $this->assertArrayNotHasKey('configuracionWizard', $response->original->getData());
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $bloqueo = $xpath->query('//main//section[@id="bloqueo-primera-consulta"]')->item(0);
            $this->assertNotNull($bloqueo);
            $this->assertSame('alert', $bloqueo->getAttribute('role'));
            $this->assertSame(1, $xpath->query('.//a[@href="'.route('turnos.mis-turnos.index').'" and normalize-space(.)="Ir a Mis turnos"]', $bloqueo)->length);
            $this->assertSame(0, $xpath->query('//main//*[@x-data or @x-init or @x-show or @x-ref]')->length);
            $this->assertSame(0, $xpath->query('//main//fieldset | //main//input | //main//button | //nav[@aria-label="Pasos de la solicitud"]')->length);
            foreach (['consultarDisponibilidad()', 'cargarProfesionalesDisponibles()', 'confirmarTurno()', 'clave_idempotencia', 'uuid', 'horario in horariosDisponibles'] as $prohibido) {
                $response->assertDontSee($prohibido, false);
            }
        } else {
            $response->assertOk()->assertViewIs('turnos.solicitar.create');
        }
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('documentos_clinicos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function estadosCanonicos(): array
    {
        return array_map(fn ($estado) => [$estado], EstadoTurnoEnum::cases());
    }

    #[DataProvider('turnosNoBloqueantes')]
    public function test_past_exact_now_followup_and_other_patient_do_not_block_entry(string $caso): void
    {
        $cambios = ['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA];
        if ($caso === 'pasado' || $caso === 'ahora') {
            $cambios['inicio_at'] = $caso === 'pasado' ? $this->ahora->subSecond() : $this->ahora;
        } elseif ($caso === 'seguimiento') {
            $cambios['tipo'] = TipoTurnoEnum::SEGUIMIENTO;
        } else {
            $cambios['paciente_id'] = $this->pacienteCompleto()->id;
        }
        $this->turno($cambios);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()->assertViewIs('turnos.solicitar.create');
    }

    public static function turnosNoBloqueantes(): array
    {
        return [['pasado'], ['ahora'], ['seguimiento'], ['otro_paciente']];
    }

    public function test_each_get_has_a_new_valid_server_uuid_without_persisting_it_or_other_capture_data(): void
    {
        $this->actingAs($this->paciente->user);
        $uuids = [];
        for ($i = 0; $i < 2; $i++) {
            $response = $this->get($this->url())->assertOk();
            $config = $response->viewData('configuracionWizard');
            $uuids[] = $config['uuid'];
            $this->assertTrue(Str::isUuid($config['uuid']));
            $this->assertStringNotContainsString($config['uuid'], json_encode(session()->all()));
            $response->assertDontSee('name="clave_idempotencia"', false);
        }
        $this->assertNotSame($uuids[0], $uuids[1]);
        foreach (['turnos', 'documentos_clinicos', 'documentos_clinicos_origen_turno', 'auditorias'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }

    #[DataProvider('condicionesProfesionales')]
    public function test_initial_get_does_not_preload_professionals_regardless_of_their_eligibility(string $condicion): void
    {
        $otra = $this->profesional();
        match ($condicion) {
            'activo' => $otra->user->update(['activo' => false]),
            'rol' => $otra->user->removeRole(RoleEnum::KINESIOLOGO->value),
            'verificado' => $otra->user->forceFill(['email_verified_at' => null])->save(),
            'datos_personales' => $otra->user->update(['telefono' => null]),
            'titulo' => $otra->update(['titulo_profesional_id' => null]),
            'matricula' => $otra->matriculasProfesionales()->update(['activa' => false]),
            'especialidad' => $otra->especialidades()->detach(),
        };
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $this->assertArrayNotHasKey('profesionales', $response->viewData('configuracionWizard'));
        $response->assertDontSee($otra->user->name)->assertDontSee($this->kinesiologa->user->name);
    }

    public static function condicionesProfesionales(): array
    {
        return array_map(fn ($condicion) => [$condicion], ['activo', 'rol', 'verificado', 'datos_personales', 'titulo', 'matricula', 'especialidad']);
    }

    public function test_multiple_eligible_professionals_are_not_exposed_as_an_initial_catalog(): void
    {
        $this->kinesiologa->user->update(['apellido' => 'Alfa', 'name' => 'Bea']);
        $zulu = $this->profesional(['apellido' => 'Zulu', 'name' => 'Ana']);
        $zoe = $this->profesional(['apellido' => 'Alfa', 'name' => 'Zoe']);
        $empate = $this->profesional(['apellido' => 'Alfa', 'name' => 'Bea']);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $this->assertArrayNotHasKey('profesionales', $response->viewData('configuracionWizard'));
        foreach ([$this->kinesiologa, $empate, $zoe, $zulu] as $profesional) {
            $response->assertDontSee(trim($profesional->user->name.' '.$profesional->user->apellido))
                ->assertDontSee($profesional->user->email)->assertDontSee($profesional->user->dni)
                ->assertDontSee($profesional->user->telefono)->assertDontSee('MP-'.$profesional->user_id);
        }
        $response->assertDontSee('name="kinesiologa_id"', false)->assertDontSee('titulo_profesional_id')->assertDontSee('paciente_id');
    }

    public function test_without_eligible_professionals_the_initial_get_does_not_claim_empty_availability(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('configuracionWizard', fn ($config) => ! array_key_exists('profesionales', $config))
            ->assertDontSee('No hay profesionales disponibles para nuevas solicitudes en este momento.')
            ->assertSee('Las profesionales se consultan después de seleccionar una fecha y un horario.')
            ->assertSee('Volver a Mis turnos')->assertDontSee('Sin horarios disponibles');
    }

    public function test_initial_get_neither_lists_professionals_nor_consults_agenda_or_writes(): void
    {
        $this->profesional();
        $this->profesional();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        foreach (['kinesiologas', 'matriculas_profesionales', 'especialidades_kinesiologia', 'configuraciones_agenda', 'franjas_horarias_agenda', 'bloqueos_agenda', 'for update', 'insert into', 'update ', 'delete from'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $sql);
        }
    }

    public function test_initial_configuration_is_a_minimal_whitelist_and_document_limits_come_from_policy(): void
    {
        config([
            'documentos_clinicos.max_archivos' => 2, 'documentos_clinicos.max_bytes_por_archivo' => 6 * 1024 * 1024,
            'documentos_clinicos.max_bytes_total' => 12 * 1024 * 1024, 'documentos_clinicos.mime_permitidos' => ['application/pdf', 'image/png'],
        ]);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $config = $response->viewData('configuracionWizard');
        $this->assertEqualsCanonicalizing(['uuid', 'maxMotivo', 'limites', 'urlMisTurnos', 'urlDisponibilidad', 'urlProfesionalesDisponibles', 'urlConfirmacion', 'fechaInicial'], array_keys($config));
        $this->assertTrue(Str::isUuid($config['uuid']));
        $this->assertSame(500, $config['maxMotivo']);
        $this->assertSame(route('turnos.mis-turnos.index'), $config['urlMisTurnos']);
        $this->assertSame(route('turnos.solicitar.store'), $config['urlConfirmacion']);
        $this->assertSame(route('turnos.solicitar.disponibilidad'), $config['urlDisponibilidad']);
        $this->assertSame(route('turnos.solicitar.profesionales-disponibles'), $config['urlProfesionalesDisponibles']);
        $this->assertSame('2026-10-17', $config['fechaInicial']);
        $this->assertArrayNotHasKey('profesionales', $config);
        $this->assertEqualsCanonicalizing(['maxArchivos', 'maxBytesPorArchivo', 'maxBytesTotal', 'mimePermitidos', 'extensionesPermitidas'], array_keys($config['limites']));
        $this->assertSame(2, $config['limites']['maxArchivos']);
        $this->assertSame(6 * 1024 * 1024, $config['limites']['maxBytesPorArchivo']);
        $this->assertSame(12 * 1024 * 1024, $config['limites']['maxBytesTotal']);
        $this->assertSame(config('documentos_clinicos.mime_permitidos'), $config['limites']['mimePermitidos']);
        $this->assertSame(['pdf', 'png'], $config['limites']['extensionesPermitidas']);
        $response->assertSee('Hasta 2 archivos.')->assertSee('Máximo 6 MiB por archivo y 12 MiB en total.')->assertSee('PDF/PNG');
    }

    public function test_initial_date_tracks_the_canonical_clock_across_midnight(): void
    {
        $this->actingAs($this->paciente->user);
        foreach (['2026-10-17 23:59:59' => '2026-10-17', '2026-10-18 00:00:00' => '2026-10-18'] as $ahora => $fecha) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($ahora, config('app.timezone')));
            $this->get($this->url())->assertOk()->assertViewHas('configuracionWizard', fn ($config) => $config['fechaInicial'] === $fecha);
        }
    }

    public function test_blade_initializes_the_v2_module_without_the_v1_compatibility_adapter(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $this->assertArrayNotHasKey('profesionales', $response->viewData('configuracionWizard'));
        $response->assertSee('x-data="solicitarTurno(', false)->assertDontSee('profesionales: []', false)
            ->assertSee('dia in fechasCalendario', false)->assertSee('horario in horariosDisponibles', false)
            ->assertSee('seleccionarInicio(horario.inicio_at)', false)->assertSee('intervaloSeleccionado', false)
            ->assertDontSee('slotSeleccionado', false)->assertDontSee('slot.fin_at', false)
            ->assertDontSee('No hay profesionales disponibles para nuevas solicitudes en este momento.');
    }

    public function test_reason_and_optional_files_have_local_controls_and_approved_default_limits(): void
    {
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertSee('name="motivo_solicitud"', false)->assertSee('required maxlength="500"', false)
            ->assertSee('motivo-error')->assertSee('archivos-errores')->assertSee('aria-describedby=', false)
            ->assertSee('(opcional)')->assertSee('Hasta 5 archivos.')->assertSee('Máximo 10 MiB por archivo y 25 MiB en total.')
            ->assertSee('PDF/JPG/JPEG/PNG')->assertSee('type="file" multiple', false)
            ->assertSee('Quitar')->assertSee('quitarArchivo(entrada.id)', false);
    }

    public function test_configuration_is_safely_serialized_and_professional_names_are_absent(): void
    {
        $peligroso = '</script><img src=x onerror=alert(1)>';
        $this->kinesiologa->user->update(['name' => $peligroso]);
        // Aísla el escape de @js; la validez normal de la UUID se certifica por separado.
        Str::createUuidsUsing(static fn (): string => $peligroso);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $response->assertDontSee($peligroso, false)->assertSee('\\u003C', false);
        $this->assertSame($peligroso, $response->viewData('configuracionWizard')['uuid']);
    }

    public function test_review_has_fetch_confirmation_and_keeps_existing_routes(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertSee('x-show="paso === 3"', false)->assertSee('x-show="paso === 4"', false)
            ->assertSee('Consultando horarios disponibles…')->assertSee('No hay horarios disponibles entre el')
            ->assertSee("estadoDisponibilidad === 'error'", false)->assertSee('Reintentar')
            ->assertSee('Semana anterior')->assertSee('Semana siguiente')->assertSee('Horarios disponibles')
            ->assertSee(':aria-pressed=', false)->assertSee('Pendiente de selección')
            ->assertSee('@click="confirmarTurno()"', false)->assertSee(':disabled="!puedeConfirmar"', false)
            ->assertSee(':disabled="bloqueado"', false)->assertSee('Registrando turno…')
            ->assertSee('Confirmar turno')->assertSee('ocupa agenda.')
            ->assertSee('name="csrf-token"', false)->assertSee('erroresServidor.adjuntos')
            ->assertDontSee('Sin horarios disponibles');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//main//form')->length);
        $this->assertSame(0, $xpath->query('//main//*[@type="submit"]')->length);
        $botonesAnteriores = $xpath->query('//main//button[@aria-label="Semana anterior"]');
        $this->assertSame(1, $botonesAnteriores->length);
        $botonAnterior = $botonesAnteriores->item(0);
        $this->assertSame('button', $botonAnterior->getAttribute('type'));
        // DOMDocument descarta atributos que comienzan con @; comprobar los bindings en ese botón del HTML original.
        $this->assertSame(1, preg_match('/<button\b(?=[^>]*aria-label="Semana anterior")[^>]*>.*?<\/button>/s', $response->getContent(), $botonHtml));
        $this->assertStringContainsString('@click="cambiarVentana(-1)"', $botonHtml[0]);
        $this->assertStringContainsString(
            ':disabled="ventana.desde === '.Js::from($response->viewData('configuracionWizard')['fechaInicial'])->toHtml().'"',
            $botonHtml[0],
        );
        $this->assertSame([
            'turnos.mis-turnos.index', 'turnos.mis-turnos.show', 'turnos.solicitar.create', 'turnos.solicitar.disponibilidad', 'turnos.solicitar.profesionales-disponibles', 'turnos.solicitar.store',
        ], collect(Route::getRoutes())->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'turnos/') && ! str_starts_with($ruta->uri(), 'turnos/agenda'))
            ->map(fn ($ruta) => $ruta->getName())->values()->all());
    }

    public function test_step_one_has_separate_native_date_and_time_controls_without_professional_or_universal_end(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $html = preg_replace('/\s@([a-z][a-z.-]*)=/', ' x-on:$1=', $response->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $paso = $xpath->query('//section[@aria-labelledby="titulo-paso-1"]')->item(0);
        $this->assertNotNull($paso);
        $fecha = $xpath->query('.//button[@*[name()="x-on:click"]="seleccionarFecha(dia.fecha)"]', $paso)->item(0);
        $hora = $xpath->query('.//button[@*[name()="x-on:click"]="seleccionarInicio(horario.inicio_at)"]', $paso)->item(0);
        foreach ([$fecha, $hora] as $control) {
            $this->assertNotNull($control);
            $this->assertSame('button', $control->getAttribute('type'));
            $this->assertTrue($control->hasAttribute(':aria-pressed'));
            $this->assertTrue($control->hasAttribute(':aria-label'));
            $this->assertTrue($control->hasAttribute(':disabled'));
        }
        $this->assertSame("!['disponible', 'seleccionada'].includes(dia.estado)", $fecha->getAttribute(':disabled'));
        $this->assertSame("estadoDisponibilidad !== 'success'", $hora->getAttribute(':disabled'));
        $this->assertSame(1, $xpath->query('.//button[@aria-label="Semana siguiente" and @*[name()="x-on:click"]="cambiarVentana(1)"]', $paso)->length);
        $this->assertSame(1, $xpath->query('.//button[@*[name()="x-on:click"]="consultarDisponibilidad()" and normalize-space(.)="Reintentar"]', $paso)->length);
        $this->assertGreaterThanOrEqual(2, $xpath->query('.//*[@role="status"]', $paso)->length);
        $this->assertSame(1, $xpath->query('.//*[@role="alert" and @x-ref="errorDisponibilidad"]', $paso)->length);
        $markup = $dom->saveHTML($paso);
        foreach (['fin_at', 'kinesiologa_id', 'profesionalSeleccionada', 'configuracionWizard.profesionales', 'seleccionarFecha(grupo.fecha);'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $markup);
        }
        $this->assertStringContainsString('horario in horariosDisponibles', $markup);
        $this->assertStringContainsString('No consultada', $markup);
        $this->assertStringContainsString('Seleccionar un horario no lo reserva.', $markup);
        $this->assertStringContainsString('min-w-0', $markup);
        $response->assertSee('(paso === 1 && !puedeElegirProfesional)', false)->assertDontSee('x-init=', false);
    }

    public function test_step_two_renders_only_dynamic_professionals_with_their_own_interval_and_query_states(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $html = preg_replace('/\s@([a-z][a-z.-]*)=/', ' x-on:$1=', $response->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $paso = $xpath->query('//section[@aria-labelledby="titulo-paso-2"]')->item(0);
        $this->assertNotNull($paso);
        $this->assertSame('paso === 2', $paso->getAttribute('x-show'));
        $templates = $xpath->query('.//template[@x-for="profesional in profesionales"]', $paso);
        $this->assertSame(1, $templates->length);
        $this->assertSame('profesional.kinesiologa_id', $templates->item(0)->getAttribute(':key'));
        $cards = $xpath->query('.//template/button', $paso);
        $this->assertSame(1, $cards->length);
        $card = $cards->item(0);
        $this->assertSame('button', $card->getAttribute('type'));
        $this->assertSame('seleccionarProfesional(profesional.kinesiologa_id)', $card->getAttribute('x-on:click'));
        $this->assertSame('kinesiologaId === profesional.kinesiologa_id', $card->getAttribute(':aria-pressed'));
        $this->assertStringContainsString('horaLegible(inicioSeleccionado)', $card->getAttribute(':aria-label'));
        $this->assertStringContainsString('horaLegible(profesional.fin_at)', $card->getAttribute(':aria-label'));
        $fieldset = $xpath->query('ancestor::fieldset[1]', $card)->item(0);
        $this->assertSame("estadoProfesionales === 'success' && inicioSeleccionado !== null && inicioProfesionales === inicioSeleccionado", $fieldset->getAttribute('x-show'));
        $this->assertSame("bloqueado || estadoProfesionales !== 'success' || inicioProfesionales !== inicioSeleccionado", $fieldset->getAttribute(':disabled'));
        $this->assertSame(1, $xpath->query('.//*[@x-text="profesional.nombre"]', $card)->length);
        $this->assertSame(1, $xpath->query('.//*[@aria-hidden="true" and @x-text="inicialesProfesional(profesional.nombre)"]', $card)->length);
        $this->assertSame(1, $xpath->query('.//button[@*[name()="x-on:click"]="cargarProfesionalesDisponibles()" and normalize-space(.)="Reintentar"]', $paso)->length);
        foreach (["estadoProfesionales === 'idle'", "estadoProfesionales === 'loading'", "estadoProfesionales === 'empty' && inicioProfesionales === inicioSeleccionado"] as $estado) {
            $this->assertSame(1, $xpath->query('.//*[@role="status" and @x-show="'.$estado.'"]', $paso)->length);
        }
        $this->assertSame(1, $xpath->query('.//*[@role="alert" and @x-ref="errorProfesionales" and @tabindex="-1"]', $paso)->length);
        $markup = $dom->saveHTML($paso);
        foreach (['dni', 'email', 'telefono', 'matricula', 'titulo_profesional_id', 'user_id', 'foto_url', 'profesionalesNoElegibles', 'finSeleccionado'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $markup);
        }
        $this->assertStringContainsString("horaLegible(inicioSeleccionado) + ' a ' + horaLegible(profesional.fin_at)", $markup);
        $this->assertStringContainsString('duracionLegible(inicioSeleccionado, profesional.fin_at)', $markup);
        $this->assertSame(0, $xpath->query('.//img | .//select | .//input', $paso)->length);
        $this->assertArrayNotHasKey('profesionales', $response->viewData('configuracionWizard'));
        $response->assertSee('(paso === 2 && !profesionalSeleccionada)', false)
            ->assertSee('No quedan profesionales disponibles para este horario de inicio.');
    }

    public function test_step_three_keeps_description_and_replacement_controls_inside_each_local_file_entry(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $html = preg_replace('/\s@([a-z][a-z.-]*)=/', ' x-on:$1=', $response->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $paso = $xpath->query('//section[@aria-labelledby="titulo-paso-3"]')->item(0);
        $this->assertNotNull($paso);
        $this->assertSame('paso === 3', $paso->getAttribute('x-show'));
        $motivo = $xpath->query('.//textarea[@id="motivo-solicitud"]', $paso)->item(0);
        $this->assertSame('motivo', $motivo->getAttribute('x-model'));
        $this->assertTrue($motivo->hasAttribute('required'));
        $this->assertSame('500', $motivo->getAttribute('maxlength'));
        $agregar = $xpath->query('.//input[@id="adjuntos"]', $paso)->item(0);
        $this->assertTrue($agregar->hasAttribute('multiple'));
        $this->assertSame('.pdf,.jpg,.jpeg,.png', $agregar->getAttribute('accept'));
        $this->assertSame('agregarArchivos($event)', $agregar->getAttribute('x-on:change'));
        $entrada = $xpath->query('.//template[@x-for="entrada in archivos"]', $paso)->item(0);
        $this->assertSame('entrada.id', $entrada->getAttribute(':key'));
        $descripcion = $xpath->query('.//input[@type="text"]', $entrada);
        $this->assertSame(1, $descripcion->length);
        $this->assertSame(1, $xpath->query('.//input[@type="text"]', $paso)->length);
        $descripcion = $descripcion->item(0);
        $this->assertSame("'descripcion-adjunto-' + entrada.id", $descripcion->getAttribute(':id'));
        $this->assertSame('entrada.descripcion', $descripcion->getAttribute(':value'));
        $this->assertSame('corregirDescripcion(entrada.id, $event.target.value)', $descripcion->getAttribute('x-on:input'));
        $this->assertSame('revisarDescripcion(entrada.id)', $descripcion->getAttribute('x-on:blur'));
        $this->assertTrue($descripcion->hasAttribute('required'));
        $this->assertSame('500', $descripcion->getAttribute('maxlength'));
        $this->assertStringContainsString('entrada.errorDescripcionServidor', $descripcion->getAttribute(':aria-invalid'));
        $reemplazo = $xpath->query('.//input[@type="file"]', $entrada)->item(0);
        $this->assertSame("'reemplazo-adjunto-' + entrada.id", $reemplazo->getAttribute(':id'));
        $this->assertSame($agregar->getAttribute('accept'), $reemplazo->getAttribute('accept'));
        $this->assertFalse($reemplazo->hasAttribute('multiple'));
        $this->assertSame("if (\$event.target.files.length) reemplazarArchivo(entrada.id, \$event.target.files[0]); \$event.target.value = ''", $reemplazo->getAttribute('x-on:change'));
        $this->assertSame(1, $xpath->query('.//button[@type="button" and @*[name()="x-on:click"]="quitarArchivo(entrada.id)"]', $entrada)->length);
        $this->assertSame(1, $xpath->query('.//template[@x-for="rechazo in erroresSeleccionArchivos"]//button[@type="button" and @*[name()="x-on:click"]="descartarErrorSeleccion(rechazo.id)"]', $paso)->length);
        $this->assertSame(1, $xpath->query('.//*[@x-text="errorDescripcion(entrada)"]', $entrada)->length);
        $this->assertSame(1, $xpath->query('.//*[@x-text="entrada.errorDescripcionServidor"]', $entrada)->length);
        $response->assertSee('(paso === 3 && !puedeAvanzarPaso3)', false)
            ->assertSee('Los archivos se enviarán al confirmar el turno.')
            ->assertDontSee('C:\\fakepath', false);
    }

    public function test_review_shows_the_real_interval_and_each_file_description_without_editable_payload_inputs(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $html = preg_replace('/\s@([a-z][a-z.-]*)=/', ' x-on:$1=', $response->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $paso = $xpath->query('//section[@aria-labelledby="titulo-paso-4"]')->item(0);
        $this->assertNotNull($paso);
        $this->assertSame('paso === 4', $paso->getAttribute('x-show'));
        $campos = [];
        foreach ($xpath->query('.//dl/div/dt', $paso) as $campo) {
            $campos[] = trim($campo->textContent);
        }
        $this->assertSame(['Tipo', 'Fecha', 'Hora de inicio', 'Horario de atención', 'Profesional', 'Motivo', 'Documentación adjunta', 'Estado al registrar'], $campos);
        $this->assertSame(1, $xpath->query('.//*[@x-text="intervaloSeleccionado ? horaLegible(intervaloSeleccionado.inicio_at) + \' a \' + horaLegible(intervaloSeleccionado.fin_at) : \'Pendiente de selección\'"]', $paso)->length);
        $this->assertSame(1, $xpath->query('.//*[@x-text="profesionalSeleccionada ? profesionalSeleccionada.nombre : \'Sin seleccionar\'"]', $paso)->length);
        $motivo = $xpath->query('.//dd[@x-text="motivo"]', $paso)->item(0);
        $this->assertNotNull($motivo);
        $this->assertStringContainsString('whitespace-pre-wrap', $motivo->getAttribute('class'));
        $documentos = $xpath->query('.//template[@x-for="entrada in archivos"]', $paso);
        $this->assertSame(1, $documentos->length);
        $this->assertSame('entrada.id', $documentos->item(0)->getAttribute(':key'));
        foreach (['entrada.archivo.name', 'tamanioLegible(entrada.archivo.size)', 'entrada.descripcion'] as $dato) {
            $this->assertSame(1, $xpath->query('.//*[@x-text="'.$dato.'"]', $documentos->item(0))->length);
        }
        $this->assertSame(1, $xpath->query('.//p[@x-show="archivos.length === 0" and normalize-space(.)="Sin documentación adjunta"]', $paso)->length);
        $this->assertSame(0, $xpath->query('.//input | .//textarea | .//*[@x-html]', $paso)->length);
        $markup = $dom->saveHTML($paso);
        foreach (['erroresSeleccionArchivos', 'Asignada', 'finSeleccionado', 'duracion_turno_minutos', 'foto_url', 'matricula', 'especialidad'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $markup);
        }
        $boton = $xpath->query('.//button[@*[name()="x-on:click"]="confirmarTurno()"]', $paso)->item(0);
        $this->assertNotNull($boton);
        $this->assertSame('button', $boton->getAttribute('type'));
        $this->assertSame('!puedeConfirmar', $boton->getAttribute(':disabled'));
        $this->assertSame('enviando', $boton->getAttribute(':aria-busy'));
        $this->assertStringContainsString('Confirmar turno', $boton->textContent);
        $this->assertStringContainsString('Primera consulta', $paso->textContent);
        $this->assertStringContainsString('Pendiente', $paso->textContent);
        $this->assertStringContainsString('ocupa agenda', $paso->textContent);
    }

    private function url(): string
    {
        return route('turnos.solicitar.create');
    }

    private function turno(array $cambios): Turno
    {
        return Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create(array_replace([
            'inicio_at' => $this->ahora->addDay(), 'fin_at' => $this->ahora->addDay()->addMinutes(45), 'motivo_solicitud' => 'Motivo privado',
        ], $cambios));
    }

    private function usuario(RoleEnum $rol, array $cambios = []): User
    {
        $user = User::factory()->create(array_replace([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true,
        ], $cambios));
        $user->assignRole($rol->value);

        return $user;
    }

    private function pacienteCompleto(): Paciente
    {
        $provincia = Provincia::firstOrCreate(['georef_id' => '54'], ['nombre' => 'Misiones']);
        $localidad = Localidad::firstOrCreate(['georef_id' => '54098010'], ['provincia_id' => $provincia->id, 'nombre' => 'Posadas', 'departamento_nombre' => 'Capital']);
        $paciente = Paciente::create([
            'user_id' => $this->usuario(RoleEnum::PACIENTE)->id, 'activo' => true,
            'localidad_id' => $localidad->id, 'calle' => 'Mitre', 'altura_domicilio' => '123',
            'contacto_emergencia_nombre' => 'Contacto', 'contacto_emergencia_telefono' => '3764123457',
        ]);
        DeclaracionSaludPaciente::create([
            'paciente_id' => $paciente->id, 'objetivo_salud' => 'Mejorar movilidad',
            'peso_kg' => 70, 'altura_cm' => 170, 'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Caminatas',
            'estado' => DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION,
        ]);

        return $paciente;
    }

    private function profesional(array $cambios = []): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-create'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-create'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-create'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'CREA'], ['nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $user = $this->usuario(RoleEnum::KINESIOLOGO, $cambios);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create(['kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id, 'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa;
    }
}
