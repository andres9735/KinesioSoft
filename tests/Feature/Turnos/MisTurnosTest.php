<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\ColegioEmisor;
use App\Models\DeclaracionSaludPaciente;
use App\Models\DocumentoClinico;
use App\Models\DocumentoClinicoOrigenTurno;
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
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MisTurnosTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_patient_can_access_with_both_navigation_entries_and_an_operational_cta(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url());
        $response->assertOk()->assertSee('Todavía no tenés turnos registrados')
            ->assertSee('Solicitar nuevo turno')
            ->assertSee('Consultá tus próximos turnos y el historial de atenciones.');
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.$this->url().'"'));
        $this->assertTrue(Route::has('turnos.solicitar.create'));
        $response->assertSee('href="'.route('turnos.solicitar.create').'"', false);
        $this->assertTrue(Route::has('turnos.solicitar.store'));
        $this->assertTrue(Route::has('turnos.solicitar.disponibilidad'));
        $response->assertDontSee('href="#"', false);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_unverified_patient_is_redirected_to_verification(): void
    {
        $this->paciente->user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->paciente->user)->get($this->url())->assertRedirect(route('verification.notice'));
    }

    public function test_incomplete_patient_is_redirected_to_profile(): void
    {
        $user = $this->usuario(RoleEnum::PACIENTE);
        $this->actingAs($user)->get($this->url())->assertRedirect(route('perfil.completar'));
    }

    #[DataProvider('rolesNoPacientes')]
    public function test_other_roles_cannot_access_and_do_not_have_patient_navigation(RoleEnum $rol): void
    {
        $user = $rol === RoleEnum::KINESIOLOGO ? $this->kinesiologa->user : $this->usuario($rol);
        $this->actingAs($user)->get($this->url())->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('href="'.$this->url().'"', false);
    }

    public static function rolesNoPacientes(): array
    {
        return [[RoleEnum::KINESIOLOGO], [RoleEnum::ADMINISTRADOR], [RoleEnum::SUPERADMIN]];
    }

    public function test_patient_scope_ignores_client_patient_and_professional_filters(): void
    {
        $propio = $this->turno();
        $otroPaciente = $this->pacienteCompleto();
        $otraProfesional = $this->profesional();
        $ajeno = $this->turno(['paciente_id' => $otroPaciente->id, 'kinesiologa_id' => $otraProfesional->id, 'inicio_at' => $this->ahora->addYears(2)]);
        $response = $this->actingAs($this->paciente->user)->get($this->url([
            'paciente_id' => $otroPaciente->id, 'kinesiologa_id' => $otraProfesional->id, 'tipo' => 'otro',
        ]));
        $response->assertOk()->assertViewHas('proximos', fn ($p) => $p->total() === 1 && $p->first()->id === $propio->id)
            ->assertViewHas('historicos', fn ($p) => $p->total() === 0)
            ->assertDontSee($otraProfesional->user->name)->assertDontSee($ajeno->inicio_at->format('d/m/Y'));
    }

    public function test_sections_order_by_scheduled_start_and_id_including_future_cancellations_in_history(): void
    {
        $futuroTarde = $this->turno(['inicio_at' => $this->ahora->addDays(3)]);
        $futuroUno = $this->turno(['inicio_at' => $this->ahora->addSecond(), 'estado' => EstadoTurnoEnum::CONFIRMADO]);
        $futuroDos = $this->turno(['inicio_at' => $futuroUno->inicio_at]);
        $pasado = $this->turno(['inicio_at' => $this->ahora->subSecond(), 'estado' => EstadoTurnoEnum::PENDIENTE]);
        $exactoUno = $this->turno(['inicio_at' => $this->ahora]);
        $exactoDos = $this->turno(['inicio_at' => $this->ahora]);
        $canceladoUno = $this->turno(['inicio_at' => $this->ahora->addDays(4), 'estado' => EstadoTurnoEnum::CANCELADO]);
        $canceladoDos = $this->turno(['inicio_at' => $canceladoUno->inicio_at, 'estado' => EstadoTurnoEnum::CANCELADO]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->pluck('id')->all() === [$futuroUno->id, $futuroDos->id, $futuroTarde->id])
            ->assertViewHas('historicos', fn ($p) => $p->pluck('id')->all() === [$canceladoDos->id, $canceladoUno->id, $exactoDos->id, $exactoUno->id, $pasado->id]);
    }

    #[DataProvider('clasificacionDeTurnos')]
    public function test_sections_partition_both_types_by_state_and_full_start_instant_without_mutation(
        EstadoTurnoEnum $estado,
        TipoTurnoEnum $tipo,
        int $segundos,
        string $seccion
    ): void {
        // El inicio pasado aún tiene fin futuro: la clasificación no depende de fin_at.
        $turno = $this->turno(['estado' => $estado, 'tipo' => $tipo, 'inicio_at' => $this->ahora->addSeconds($segundos)]);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $otra = $seccion === 'proximos' ? 'historicos' : 'proximos';
        $this->assertSame([$turno->id], $response->viewData($seccion)->pluck('id')->all());
        $this->assertSame(1, $response->viewData($seccion)->total());
        $this->assertSame(0, $response->viewData($otra)->total());
        $this->assertSame($estado, $turno->fresh()->estado);
        $this->assertDatabaseCount('turnos', 1);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function clasificacionDeTurnos(): array
    {
        $casos = [];
        foreach ([
            [EstadoTurnoEnum::PENDIENTE, 'proximos'],
            [EstadoTurnoEnum::CONFIRMADO, 'proximos'],
            [EstadoTurnoEnum::EN_ATENCION, 'historicos'],
            [EstadoTurnoEnum::ATENDIDO, 'historicos'],
            [EstadoTurnoEnum::AUSENTE, 'historicos'],
            [EstadoTurnoEnum::CANCELADO, 'historicos'],
        ] as [$estado, $seccionFutura]) {
            foreach (TipoTurnoEnum::cases() as $tipo) {
                foreach ([-1 => 'historicos', 0 => 'historicos', 1 => $seccionFutura] as $segundos => $seccion) {
                    $casos[$estado->name.'_'.$tipo->name.'_'.$segundos] = [$estado, $tipo, $segundos, $seccion];
                }
            }
        }

        return $casos;
    }

    public function test_history_does_not_leak_foreign_past_or_future_terminal_appointments(): void
    {
        $propio = $this->turno(['estado' => EstadoTurnoEnum::CANCELADO]);
        $otroPaciente = $this->pacienteCompleto();
        $otraProfesional = $this->profesional();
        foreach (EstadoTurnoEnum::cases() as $estado) {
            foreach ([-1, 1] as $dias) {
                $this->turno([
                    'paciente_id' => $otroPaciente->id, 'kinesiologa_id' => $otraProfesional->id,
                    'estado' => $estado, 'inicio_at' => $this->ahora->addDays($dias),
                ]);
            }
        }
        $this->actingAs($this->paciente->user)->get($this->url(['paciente_id' => $otroPaciente->id]))->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->total() === 0)
            ->assertViewHas('historicos', fn ($p) => $p->total() === 1 && $p->pluck('id')->all() === [$propio->id])
            ->assertDontSee($otraProfesional->user->name);
    }

    public function test_classification_uses_application_timezone_when_clock_is_frozen_in_utc(): void
    {
        $this->ahora = $this->ahora->startOfDay();
        CarbonImmutable::setTestNow($this->ahora->setTimezone('UTC'));
        $pasado = $this->turno(['inicio_at' => $this->ahora->subSecond()]);
        $exacto = $this->turno(['inicio_at' => $this->ahora]);
        $futuro = $this->turno(['inicio_at' => $this->ahora->addSecond(), 'estado' => EstadoTurnoEnum::CONFIRMADO]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->pluck('id')->all() === [$futuro->id])
            ->assertViewHas('historicos', fn ($p) => $p->pluck('id')->all() === [$exacto->id, $pasado->id]);
    }

    public function test_appointment_moves_to_history_at_its_start_without_changing_state(): void
    {
        $turno = $this->turno(['inicio_at' => $this->ahora->addSecond(), 'estado' => EstadoTurnoEnum::CONFIRMADO]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->pluck('id')->all() === [$turno->id])
            ->assertViewHas('historicos', fn ($p) => $p->total() === 0);
        CarbonImmutable::setTestNow($turno->inicio_at);
        $this->get($this->url())->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->total() === 0)
            ->assertViewHas('historicos', fn ($p) => $p->pluck('id')->all() === [$turno->id]);
        $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $turno->fresh()->estado);
        $this->assertDatabaseCount('auditorias', 0);
    }

    #[DataProvider('paginas')]
    public function test_ten_per_page_and_independent_paginators(int $proximosPage, int $historicosPage): void
    {
        $proximos = [];
        $historicos = [];
        for ($i = 1; $i <= 12; $i++) {
            $proximos[] = $this->turno(['inicio_at' => $this->ahora->addDays($i)])->id;
            $historicos[] = $this->turno(['inicio_at' => $this->ahora->subDays($i)])->id;
        }
        $this->turno(['paciente_id' => $this->pacienteCompleto()->id]);
        $response = $this->actingAs($this->paciente->user)->get($this->url([
            'proximos_page' => $proximosPage, 'historicos_page' => $historicosPage, 'paciente_id' => 999,
        ]))->assertOk();
        foreach (['proximos' => [$proximosPage, $proximos], 'historicos' => [$historicosPage, $historicos]] as $nombre => [$pagina, $ids]) {
            $paginator = $response->viewData($nombre);
            $this->assertSame(12, $paginator->total());
            $this->assertSame(10, $paginator->perPage());
            $this->assertSame($pagina, $paginator->currentPage());
            $this->assertSame($nombre.'_page', $paginator->getPageName());
            $this->assertSame(array_slice($ids, ($pagina - 1) * 10, 10), $paginator->pluck('id')->all());
            $this->assertStringNotContainsString('paciente_id', $paginator->url(1));
        }
        $this->assertStringContainsString('historicos_page='.$historicosPage, $response->viewData('proximos')->url(1));
        $this->assertStringContainsString('proximos_page='.$proximosPage, $response->viewData('historicos')->url(1));
    }

    public static function paginas(): array
    {
        return [[1, 1], [2, 1], [1, 2], [2, 2]];
    }

    public function test_persisted_uc07_result_is_visible_once_in_its_own_card_without_client_state(): void
    {
        $inicio = $this->ahora->addDays(3)->setTime(10, 0);
        $turno = $this->turno([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => EstadoTurnoEnum::PENDIENTE,
            'inicio_at' => $inicio, 'fin_at' => $inicio->addMinutes(45), 'clave_idempotencia' => (string) Str::uuid(),
        ]);
        $otra = $this->profesional();
        $this->turno(['kinesiologa_id' => $otra->id, 'estado' => EstadoTurnoEnum::CONFIRMADO]);
        $this->turno(['paciente_id' => $this->pacienteCompleto()->id, 'kinesiologa_id' => $otra->id]);
        // No existe agenda que la vista pueda usar para reconstruir la duración del snapshot.
        $this->assertDatabaseCount('configuraciones_agenda', 0);

        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('turnoDestacadoId', null)
            ->assertViewHas('proximos', fn ($p) => $p->total() === 2 && $p->pluck('id')->contains($turno->id));
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//section[@aria-labelledby="proximos-titulo"]/ul/li/article');
        $this->assertSame(2, $cards->length);
        $propios = $xpath->query('//section[@aria-labelledby="proximos-titulo"]/ul/li/article[.//h3[normalize-space(.)="Primera consulta"]]');
        $this->assertSame(1, $propios->length);
        $card = $propios->item(0);
        $this->assertSame(1, $xpath->query('.//span[normalize-space(.)="Pendiente"]', $card)->length);
        $this->assertSame(0, $xpath->query('.//*[normalize-space(.)="Confirmado" or normalize-space(.)="Seguimiento"]', $card)->length);
        $this->assertSame(1, $xpath->query('.//time[@datetime="'.$inicio->format('Y-m-d').'"]', $card)->length);
        $texto = preg_replace('/\s+/u', ' ', trim($card->textContent));
        foreach ([trim($this->kinesiologa->user->name.' '.$this->kinesiologa->user->apellido), $inicio->format('d/m/Y'), '10:00 – 10:45 hs'] as $dato) {
            $this->assertStringContainsString($dato, $texto);
        }
        $this->assertStringNotContainsString(trim($otra->user->name.' '.$otra->user->apellido), $texto);
        $response->assertDontSee($turno->clave_idempotencia)->assertDontSee('Turno destacado');
    }

    public function test_visible_card_fields_exclude_clinical_and_internal_data(): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => 'MOTIVO PRIVADO', 'clave_idempotencia' => (string) Str::uuid()]);
        $documento = DocumentoClinico::factory()->create([
            'paciente_id' => $this->paciente->id, 'cargado_por_user_id' => $this->paciente->user_id,
            'nombre_original' => 'ARCHIVO-CLINICO-PRIVADO.pdf', 'descripcion' => 'DESCRIPCION CLINICA PRIVADA',
            'ruta_storage' => 'fixtures/RUTA-CLINICA-PRIVADA.pdf',
        ]);
        DocumentoClinicoOrigenTurno::create(['documento_clinico_id' => $documento->id, 'turno_id' => $turno->id]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertSee('Primera consulta')->assertSee($this->kinesiologa->user->name.' '.$this->kinesiologa->user->apellido)
            ->assertSee($turno->inicio_at->format('d/m/Y'))->assertSee('12:00 – 12:45')->assertSee('Pendiente')
            ->assertSee('<article', false)->assertSee('md:flex-row', false)->assertDontSee('<table', false)
            ->assertDontSee('MOTIVO PRIVADO')->assertDontSee($turno->clave_idempotencia)
            ->assertDontSee($documento->descripcion)->assertDontSee($documento->nombre_original)->assertDontSee($documento->ruta_storage)
            ->assertDontSee($this->kinesiologa->user->dni)->assertDontSee($this->kinesiologa->user->email)
            ->assertDontSee($this->kinesiologa->user->telefono)->assertDontSee('MP-'.$this->kinesiologa->user_id)
            ->assertDontSee('motivo_solicitud')->assertDontSee('paciente_id')->assertDontSee('recordatorio_enviado_at')
            ->assertDontSee('/documentos-clinicos/')->assertDontSee('Confirmar asistencia')->assertDontSee('Reprogramar')
            ->assertDontSee('Cancelar turno')->assertDontSee('Iniciar consulta')->assertDontSee('Cambiar estado');
    }

    public function test_all_canonical_states_are_human_readable_and_types_are_separate(): void
    {
        foreach (EstadoTurnoEnum::cases() as $estado) {
            $this->turno(['estado' => $estado]);
        }
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk()->assertSee('Seguimiento');
        foreach (['Pendiente', 'Confirmado', 'En atención', 'Atendido', 'Ausente', 'Cancelado'] as $label) {
            $response->assertSee($label);
        }
    }

    #[DataProvider('seccionesVacias')]
    public function test_section_specific_empty_states_do_not_hide_the_other_section(bool $futuro): void
    {
        $this->turno(['inicio_at' => $futuro ? $this->ahora->addDay() : $this->ahora->subDay()]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertSee('Próximos turnos')->assertSee('Historial de atenciones')->assertSee('Seguimiento')
            ->assertSee($futuro ? 'Todavía no tenés turnos en el historial.' : 'No tenés próximos turnos.')
            ->assertDontSee('Todavía no tenés turnos registrados');
    }

    public static function seccionesVacias(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('estadosCanonicos')]
    public function test_cta_always_reaches_create_where_canonical_d01_is_authoritative(EstadoTurnoEnum $estado): void
    {
        $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => $estado]);
        $ocupa = in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('primeraConsultaActiva', $ocupa)
            ->assertSee('href="'.route('turnos.solicitar.create').'"', false)
            ->assertDontSee('Ya tenés una Primera consulta futura')->assertDontSee('id="estado-solicitud"', false);
        if ($ocupa) {
            $this->get(route('turnos.solicitar.create'))->assertOk()
                ->assertViewIs('turnos.solicitar.create')->assertViewHas('bloqueadoPorPrimeraConsulta', true);
        } else {
            $this->get(route('turnos.solicitar.create'))->assertOk()
                ->assertViewIs('turnos.solicitar.create')->assertViewHas('configuracionWizard');
        }
    }

    public static function estadosCanonicos(): array
    {
        return array_map(fn ($estado) => [$estado], EstadoTurnoEnum::cases());
    }

    public function test_past_first_consultation_and_future_followup_do_not_block_preliminary_d01(): void
    {
        $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'inicio_at' => $this->ahora]);
        $this->turno(['tipo' => TipoTurnoEnum::SEGUIMIENTO]);
        $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'paciente_id' => $this->pacienteCompleto()->id]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()->assertViewHas('primeraConsultaActiva', false);
    }

    #[DataProvider('seccionesVacias')]
    public function test_localization_counts_rows_with_ties_in_the_same_order_and_preserves_other_page(bool $futuro): void
    {
        $turnos = [];
        for ($i = 0; $i < 22; $i++) {
            $turnos[] = $this->turno(['inicio_at' => $futuro ? $this->ahora->addDay() : $this->ahora->subDay()]);
            $this->turno(['inicio_at' => $futuro ? $this->ahora->subDay() : $this->ahora->addDay()]);
        }
        $ordenados = $futuro ? $turnos : array_reverse($turnos);
        $target = $ordenados[10];
        $seccion = $futuro ? 'proximos' : 'historicos';
        $otra = $futuro ? 'historicos' : 'proximos';
        $response = $this->actingAs($this->paciente->user)->get($this->url([
            'turno' => $target->id, $seccion.'_page' => 3, $otra.'_page' => 2,
        ]))->assertOk()->assertViewHas('turnoDestacadoId', $target->id)->assertSee('Turno destacado');
        $this->assertSame(2, $response->viewData($seccion)->currentPage());
        $this->assertSame(2, $response->viewData($otra)->currentPage());
        $this->assertSame(array_map(fn ($t) => $t->id, array_slice($ordenados, 10, 10)), $response->viewData($seccion)->pluck('id')->all());
        $this->assertStringNotContainsString('turno=', $response->viewData($seccion)->url(1));
        $this->assertStringContainsString('turno='.$target->id, $response->viewData($otra)->url(1));
        $this->get($response->viewData($seccion)->url(1))->assertOk()
            ->assertViewHas($seccion, fn ($p) => $p->currentPage() === 1
                && $p->pluck('id')->all() === array_map(fn ($t) => $t->id, array_slice($ordenados, 0, 10)));
    }

    public function test_localization_of_a_new_later_appointment_finds_its_second_page(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->turno(['inicio_at' => $this->ahora->addDays($i)]);
        }
        $nuevo = $this->turno(['inicio_at' => $this->ahora->addDays(11)]);
        $this->actingAs($this->paciente->user)->get($this->url(['turno' => $nuevo->id]))->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->currentPage() === 2 && $p->first()->id === $nuevo->id)
            ->assertViewHas('turnoDestacadoId', $nuevo->id)->assertSee('Turno destacado');
    }

    public function test_localization_of_future_cancelled_appointment_uses_history_order_and_pagination(): void
    {
        $turnos = [];
        for ($i = 0; $i < 22; $i++) {
            $turnos[] = $this->turno(['inicio_at' => $this->ahora->addDay(), 'estado' => EstadoTurnoEnum::CANCELADO]);
            $this->turno(['inicio_at' => $this->ahora->addDay(), 'estado' => EstadoTurnoEnum::CONFIRMADO]);
        }
        $ordenados = array_reverse($turnos);
        $destacado = $ordenados[10];
        $response = $this->actingAs($this->paciente->user)->get($this->url([
            'turno' => $destacado->id, 'historicos_page' => 3, 'proximos_page' => 2,
        ]))->assertOk()->assertViewHas('turnoDestacadoId', $destacado->id)->assertSee('Turno destacado');
        $this->assertSame(22, $response->viewData('historicos')->total());
        $this->assertSame(22, $response->viewData('proximos')->total());
        $this->assertSame(2, $response->viewData('historicos')->currentPage());
        $this->assertSame(2, $response->viewData('proximos')->currentPage());
        $this->assertSame(array_map(fn ($t) => $t->id, array_slice($ordenados, 10, 10)), $response->viewData('historicos')->pluck('id')->all());
        $this->assertStringNotContainsString('turno=', $response->viewData('historicos')->url(1));
        $this->assertStringContainsString('turno='.$destacado->id, $response->viewData('proximos')->url(1));
        $this->get($response->viewData('historicos')->url(1))->assertOk()
            ->assertViewHas('historicos', fn ($p) => $p->currentPage() === 1
                && $p->pluck('id')->all() === array_map(fn ($t) => $t->id, array_slice($ordenados, 0, 10)));
    }

    public function test_foreign_and_missing_localization_are_indistinguishable_and_do_not_affect_counts_or_pages(): void
    {
        $this->turno();
        $otraProfesional = $this->profesional();
        $ajeno = $this->turno(['paciente_id' => $this->pacienteCompleto()->id, 'kinesiologa_id' => $otraProfesional->id, 'inicio_at' => $this->ahora->addYears(3)]);
        $html = [];
        foreach ([$ajeno->id, 999999] as $id) {
            $response = $this->actingAs($this->paciente->user)->get($this->url(['turno' => $id]))->assertOk()
                ->assertViewHas('turnoDestacadoId', null)->assertDontSee('Turno destacado')
                ->assertDontSee($otraProfesional->user->name)->assertDontSee($ajeno->inicio_at->format('d/m/Y'))
                ->assertViewHas('proximos', fn ($p) => $p->total() === 1 && $p->currentPage() === 1);
            $html[] = $response->getContent();
        }
        $this->assertSame($html[0], $html[1]);
    }

    #[DataProvider('parametrosInvalidos')]
    public function test_invalid_technical_parameters_fall_back_to_normal_listing(mixed $valor): void
    {
        $this->turno();
        $this->actingAs($this->paciente->user)->get($this->url(['turno' => $valor, 'proximos_page' => $valor, 'historicos_page' => $valor]))
            ->assertOk()->assertViewHas('turnoDestacadoId', null)
            ->assertViewHas('proximos', fn ($p) => $p->currentPage() === 1);
    }

    public static function parametrosInvalidos(): array
    {
        return [['abc'], ['0'], ['-1'], [['id' => 1]], ['999999999999999999999999']];
    }

    public function test_historical_professional_is_shown_even_if_inactive_and_no_longer_eligible(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->kinesiologa->user->removeRole(RoleEnum::KINESIOLOGO->value);
        $this->turno(['inicio_at' => $this->ahora->subDay()]);
        $this->paciente->update(['activo' => false]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()->assertSee($this->kinesiologa->user->name);
    }

    public function test_professional_select_count_is_constant_for_few_and_many_distinct_professionals(): void
    {
        $this->turno();
        $this->turno(['inicio_at' => $this->ahora->subDay()]);
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
            $queries = collect(DB::getQueryLog())->pluck('query');
            DB::disableQueryLog();

            return $queries->filter(fn ($sql) => str_contains($sql, 'from "kinesiologas"')
                || str_contains($sql, 'select "id", "name", "apellido" from "users"'))->count();
        };
        $pocos = $contar();
        for ($i = 0; $i < 7; $i++) {
            $profesional = $this->profesional();
            $this->turno(['kinesiologa_id' => $profesional->id]);
            $this->turno(['kinesiologa_id' => $profesional->id, 'inicio_at' => $this->ahora->subDay()]);
        }
        $muchos = $contar();
        $this->assertSame(4, $pocos);
        $this->assertSame($pocos, $muchos);
    }

    private function url(array $query = []): string
    {
        return route('turnos.mis-turnos.index', $query);
    }

    private function turno(array $cambios = []): Turno
    {
        $inicio = $cambios['inicio_at'] ?? $this->ahora->addDay();

        return Turno::factory()->for($this->paciente)->for($this->kinesiologa)->create(array_replace([
            'inicio_at' => $inicio, 'fin_at' => $inicio->addMinutes(45),
        ], $cambios));
    }

    private function usuario(RoleEnum $rol): User
    {
        $user = User::factory()->create([
            'dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true,
        ]);
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

    private function profesional(): Kinesiologa
    {
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-mis-turnos'], ['nombre' => 'Título', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-mis-turnos'], ['nombre' => 'Especialidad', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-mis-turnos'], ['nombre' => 'Jurisdicción', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'MIST'], ['nombre' => 'Colegio', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $user = $this->usuario(RoleEnum::KINESIOLOGO);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create(['kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id, 'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true]);
        $kinesiologa->especialidades()->attach($especialidad);

        return $kinesiologa;
    }
}
