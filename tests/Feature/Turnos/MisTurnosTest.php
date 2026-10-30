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
use App\Policies\DocumentoClinicoPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $this->assertSame(2, (new \DOMXPath($dom))->query('//nav//a[@href="'.$this->url().'"]')->length);
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

    public function test_patient_scope_ignores_client_patient_and_professional_ids(): void
    {
        $propio = $this->turno();
        $otroPaciente = $this->pacienteCompleto();
        $otraProfesional = $this->profesional();
        $ajeno = $this->turno(['paciente_id' => $otroPaciente->id, 'kinesiologa_id' => $otraProfesional->id, 'inicio_at' => $this->ahora->addYears(2)]);
        $response = $this->actingAs($this->paciente->user)->get($this->url([
            'paciente_id' => $otroPaciente->id, 'kinesiologa_id' => $otraProfesional->id, 'tipo' => TipoTurnoEnum::SEGUIMIENTO->value,
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

    #[DataProvider('busquedasProfesional')]
    public function test_professional_search_is_partial_case_insensitive_and_scoped_to_own_appointments(string $termino): void
    {
        $this->kinesiologa->user->update(['name' => 'Ángela María', 'apellido' => 'ÑÚÑEZ Παπαδοπούλου']);
        $proximo = $this->turno();
        $historico = $this->turno(['estado' => EstadoTurnoEnum::CANCELADO]);
        $otraProfesional = $this->profesional();
        $otraProfesional->user->update(['name' => 'Profesional distinta', 'apellido' => 'Sin coincidencia']);
        $this->turno(['kinesiologa_id' => $otraProfesional->id]);
        $this->turno(['paciente_id' => $this->pacienteCompleto()->id]);
        $this->actingAs($this->paciente->user)->get($this->url(['profesional' => $termino]))->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->total() === 1 && $p->pluck('id')->all() === [$proximo->id])
            ->assertViewHas('historicos', fn ($p) => $p->total() === 1 && $p->pluck('id')->all() === [$historico->id]);
    }

    public static function busquedasProfesional(): array
    {
        return [['áng'], ['MARÍA'], ['ñúñ'], ['ÑÚÑEZ'], ['  mArÍa  '], ['ΠΑΠΑΔΟ']];
    }

    #[DataProvider('caracteresBusqueda')]
    public function test_search_treats_wildcards_escape_characters_and_sql_text_as_literals(string $termino): void
    {
        $this->kinesiologa->user->update(['name' => 'Literal'.$termino.'Nombre', 'apellido' => 'Coincidencia']);
        $propio = $this->turno();
        $this->turno(['kinesiologa_id' => $this->profesional()->id]);
        $this->actingAs($this->paciente->user)->get($this->url(['profesional' => $termino]))->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->total() === 1 && $p->first()->id === $propio->id);
    }

    public static function caracteresBusqueda(): array
    {
        return [['%'], ['_'], ['!'], ['\\'], ["'"], ["' OR 1=1 --"]];
    }

    #[DataProvider('tiposFiltro')]
    public function test_type_filter_applies_to_both_sections(TipoTurnoEnum $tipo): void
    {
        $esperados = [];
        foreach (TipoTurnoEnum::cases() as $caso) {
            $futuro = $this->turno(['tipo' => $caso]);
            $pasado = $this->turno(['tipo' => $caso, 'inicio_at' => $this->ahora->subDay()]);
            if ($caso === $tipo) {
                $esperados = [$futuro->id, $pasado->id];
            }
        }
        $this->actingAs($this->paciente->user)->get($this->url(['tipo' => $tipo->value]))->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->pluck('id')->all() === [$esperados[0]])
            ->assertViewHas('historicos', fn ($p) => $p->pluck('id')->all() === [$esperados[1]]);
    }

    public static function tiposFiltro(): array
    {
        return [[TipoTurnoEnum::PRIMERA_CONSULTA], [TipoTurnoEnum::SEGUIMIENTO]];
    }

    #[DataProvider('estadosCanonicos')]
    public function test_each_state_filter_preserves_the_canonical_partition(EstadoTurnoEnum $estado): void
    {
        $futuro = $pasado = null;
        foreach (EstadoTurnoEnum::cases() as $caso) {
            $uno = $this->turno(['estado' => $caso]);
            $dos = $this->turno(['estado' => $caso, 'inicio_at' => $this->ahora->subDay()]);
            if ($caso === $estado) {
                [$futuro, $pasado] = [$uno, $dos];
            }
        }
        $response = $this->actingAs($this->paciente->user)->get($this->url(['estado' => $estado->value]))->assertOk();
        if (in_array($estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)) {
            $this->assertSame([$futuro->id], $response->viewData('proximos')->pluck('id')->all());
            $this->assertSame([$pasado->id], $response->viewData('historicos')->pluck('id')->all());
        } else {
            $this->assertSame(0, $response->viewData('proximos')->total());
            $this->assertSame([$futuro->id, $pasado->id], $response->viewData('historicos')->pluck('id')->all());
            $response->assertSee('No hay próximos turnos que coincidan con los criterios seleccionados.');
        }
    }

    #[DataProvider('rangosFechas')]
    public function test_date_filters_are_inclusive_open_and_based_on_local_scheduled_start(array $filtros, array $indices): void
    {
        $medianoche = $this->ahora->addDay()->startOfDay();
        $ids = [];
        foreach ([$medianoche->subSecond(), $medianoche, $medianoche->endOfDay()->setMicrosecond(0), $medianoche->addDay()] as $inicio) {
            $ids[] = $this->turno(['inicio_at' => $inicio, 'estado' => EstadoTurnoEnum::CANCELADO])->id;
        }
        $esperados = array_map(fn ($i) => $ids[$i], $indices);
        $this->actingAs($this->paciente->user)->get($this->url($filtros))->assertOk()
            ->assertViewHas('historicos', fn ($p) => $p->pluck('id')->all() === $esperados)
            ->assertViewHas('proximos', fn ($p) => $p->total() === 0);
    }

    public static function rangosFechas(): array
    {
        return [
            'desde inclusivo y abierto' => [['fecha_desde' => '2026-10-18'], [3, 2, 1]],
            'hasta inclusivo y abierto' => [['fecha_hasta' => '2026-10-18'], [2, 1, 0]],
            'mismo día completo' => [['fecha_desde' => '2026-10-18', 'fecha_hasta' => '2026-10-18'], [2, 1]],
        ];
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_invalid_filters_are_rejected_without_querying_unvalidated_criteria(array $filtros, string $campo): void
    {
        $this->actingAs($this->paciente->user)->getJson($this->url($filtros))->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertDatabaseCount('turnos', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public static function filtrosInvalidos(): array
    {
        return [
            [['tipo' => 'otro'], 'tipo'], [['estado' => 'VENCIDO'], 'estado'],
            [['profesional' => ['x']], 'profesional'], [['profesional' => str_repeat('a', 256)], 'profesional'],
            [['tipo' => ['PENDIENTE']], 'tipo'], [['estado' => ['PENDIENTE']], 'estado'],
            [['fecha_desde' => '2026-02-30'], 'fecha_desde'], [['fecha_hasta' => '18/10/2026'], 'fecha_hasta'],
            [['fecha_desde' => ['2026-10-18'], 'fecha_hasta' => '2026-10-19'], 'fecha_desde'],
            [['fecha_desde' => '2026-10-19', 'fecha_hasta' => '2026-10-18'], 'fecha_hasta'],
        ];
    }

    public function test_invalid_range_returns_to_the_filter_form_with_accessible_errors_and_preserved_input(): void
    {
        $this->actingAs($this->paciente->user)->get($this->url(['fecha_desde' => '2026-10-19', 'fecha_hasta' => '2026-10-18']))
            ->assertRedirect($this->url())->assertSessionHasErrors('fecha_hasta');
        $this->get($this->url())->assertOk()->assertSee('La fecha desde no puede ser posterior a la fecha hasta.')
            ->assertSee('role="alert"', false)->assertSee('value="2026-10-19"', false)
            ->assertSee('id="fecha_hasta-error"', false);
    }

    #[DataProvider('paginas')]
    public function test_combined_filters_and_independent_pagination_preserve_only_validated_criteria(int $proximosPage, int $historicosPage): void
    {
        $this->kinesiologa->user->update(['name' => 'Clara', 'apellido' => 'Pérez']);
        $ids = ['proximos' => [], 'historicos' => []];
        for ($i = 1; $i <= 12; $i++) {
            $ids['proximos'][] = $this->turno(['inicio_at' => $this->ahora->addDays($i)])->id;
            $ids['historicos'][] = $this->turno(['inicio_at' => $this->ahora->subDays($i)])->id;
        }
        $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA]);
        $this->turno(['estado' => EstadoTurnoEnum::CONFIRMADO]);
        $this->turno(['kinesiologa_id' => $this->profesional()->id]);
        $this->turno(['inicio_at' => $this->ahora->addYears(2)]);
        $this->turno(['paciente_id' => $this->pacienteCompleto()->id]);
        $filtros = ['profesional' => '  pÉr  ', 'tipo' => 'SEGUIMIENTO', 'estado' => 'PENDIENTE', 'fecha_desde' => '2026-01-01', 'fecha_hasta' => '2026-12-31'];
        $response = $this->actingAs($this->paciente->user)->get($this->url($filtros + [
            'proximos_page' => $proximosPage, 'historicos_page' => $historicosPage, 'paciente_id' => 999, 'desconocido' => 'no',
        ]))->assertOk();
        $filtros['profesional'] = 'pÉr';
        foreach (['proximos' => $proximosPage, 'historicos' => $historicosPage] as $seccion => $pagina) {
            $p = $response->viewData($seccion);
            $this->assertSame(12, $p->total());
            $this->assertSame($pagina, $p->currentPage());
            $this->assertSame(array_slice($ids[$seccion], ($pagina - 1) * 10, 10), $p->pluck('id')->all());
            parse_str(parse_url($p->url(1), PHP_URL_QUERY), $parametros);
            foreach ($filtros as $campo => $valor) {
                $this->assertSame($valor, $parametros[$campo]);
            }
            $this->assertArrayNotHasKey('paciente_id', $parametros);
            $this->assertArrayNotHasKey('desconocido', $parametros);
            $otra = $seccion === 'proximos' ? 'historicos_page' : 'proximos_page';
            $this->assertSame((string) ($seccion === 'proximos' ? $historicosPage : $proximosPage), $parametros[$otra]);
            $this->get($p->url(1))->assertOk()->assertViewHas($seccion, fn ($p) => $p->currentPage() === 1);
        }
    }

    public function test_apply_and_clear_reset_both_pages_and_discard_the_highlight_hint(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $ultimo = $this->turno();
            $this->turno(['inicio_at' => $this->ahora->subDay()]);
        }
        $response = $this->actingAs($this->paciente->user)->get($this->url([
            'tipo' => 'SEGUIMIENTO', 'aplicar' => '1', 'proximos_page' => 2, 'historicos_page' => 2, 'turno' => $ultimo->id,
        ]))->assertOk()->assertViewHas('turnoDestacadoId', null);
        foreach (['proximos', 'historicos'] as $seccion) {
            $this->assertSame(1, $response->viewData($seccion)->currentPage());
            $this->assertStringNotContainsString('aplicar=', $response->viewData($seccion)->url(2));
        }
        $this->get($this->url())->assertOk()->assertViewHas('filtros', [])
            ->assertViewHas('proximos', fn ($p) => $p->currentPage() === 1)
            ->assertViewHas('historicos', fn ($p) => $p->currentPage() === 1);
    }

    public function test_out_of_range_pages_are_adjusted_independently_in_filtered_results(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->turno();
            $this->turno(['inicio_at' => $this->ahora->subDay()]);
        }
        $this->actingAs($this->paciente->user)->get($this->url(['tipo' => 'SEGUIMIENTO', 'proximos_page' => 999, 'historicos_page' => 1]))->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->currentPage() === 2 && $p->count() === 2)
            ->assertViewHas('historicos', fn ($p) => $p->currentPage() === 1 && $p->count() === 10)
            ->assertDontSee('No hay turnos en esta página.');
    }

    public function test_filtered_out_highlight_does_not_change_pages_or_disclose_a_record(): void
    {
        $excluido = $this->turno(['estado' => EstadoTurnoEnum::CANCELADO]);
        $this->turno();
        $this->actingAs($this->paciente->user)->get($this->url(['estado' => 'PENDIENTE', 'turno' => $excluido->id]))->assertOk()
            ->assertViewHas('turnoDestacadoId', null)->assertDontSee('Turno destacado')
            ->assertViewHas('proximos', fn ($p) => $p->currentPage() === 1 && $p->total() === 1)
            ->assertViewHas('historicos', fn ($p) => $p->total() === 0);
    }

    public function test_empty_filtered_results_are_not_presented_as_an_unregistered_patient(): void
    {
        $this->turno();
        $this->actingAs($this->paciente->user)->get($this->url(['profesional' => 'SIN_COINCIDENCIAS']))->assertOk()
            ->assertSee('No hay turnos que coincidan con los criterios seleccionados.')->assertSee('Limpiar filtros')
            ->assertDontSee('Todavía no tenés turnos registrados');
        $this->get($this->url(['profesional' => '   ', 'tipo' => '', 'estado' => '']))->assertOk()->assertViewHas('filtros', [])
            ->assertSee('Todavía no tenés turnos en el historial.');
    }

    public function test_professional_search_never_searches_clinical_or_patient_fields(): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => 'CLINICO_UNICO']);
        $documento = DocumentoClinico::factory()->create([
            'paciente_id' => $this->paciente->id, 'cargado_por_user_id' => $this->paciente->user_id,
            'descripcion' => 'DOCUMENTO_UNICO', 'nombre_original' => 'DOCUMENTO_UNICO.pdf',
        ]);
        DocumentoClinicoOrigenTurno::create(['documento_clinico_id' => $documento->id, 'turno_id' => $turno->id]);
        foreach (['CLINICO_UNICO', 'DOCUMENTO_UNICO', $this->paciente->user->email, $this->kinesiologa->user->dni] as $texto) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->paciente->user)->get($this->url(['profesional' => $texto]))->assertOk()
                ->assertViewHas('proximos', fn ($p) => $p->total() === 0)
                ->assertViewHas('historicos', fn ($p) => $p->total() === 0);
            $sql = implode(' ', array_column(DB::getQueryLog(), 'query'));
            DB::disableQueryLog();
            $this->assertStringNotContainsString('motivo_solicitud', $sql);
            $this->assertStringNotContainsString('documentos_clinicos', $sql);
        }
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_filter_form_has_labels_selection_error_references_and_navigation_without_stale_pages(): void
    {
        $this->kinesiologa->user->update(['name' => 'Ángela']);
        $this->turno();
        $filtros = ['profesional' => 'Áng', 'tipo' => 'SEGUIMIENTO', 'estado' => 'PENDIENTE', 'fecha_desde' => '2026-01-01', 'fecha_hasta' => '2026-12-31'];
        $response = $this->actingAs($this->paciente->user)->get($this->url($filtros))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[@aria-labelledby="filtros-titulo"]')->item(0);
        $this->assertNotNull($form);
        $this->assertSame('GET', $form->getAttribute('method'));
        $this->assertSame($this->url(), $form->getAttribute('action'));
        foreach ($filtros as $campo => $valor) {
            $control = $xpath->query('.//*[@name="'.$campo.'"]', $form)->item(0);
            $this->assertNotNull($control);
            $this->assertSame(1, $xpath->query('.//label[@for="'.$control->getAttribute('id').'"]', $form)->length);
            foreach (explode(' ', $control->getAttribute('aria-describedby')) as $id) {
                $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
            }
            $actual = $control->nodeName === 'select' ? $xpath->query('.//option[@selected]', $control)->item(0)->getAttribute('value') : $control->getAttribute('value');
            $this->assertSame($valor, $actual);
        }
        $this->assertSame(3, $xpath->query('.//select[@name="tipo"]/option', $form)->length);
        $this->assertSame(7, $xpath->query('.//select[@name="estado"]/option', $form)->length);
        $this->assertSame(0, $xpath->query('.//input[@name="proximos_page" or @name="historicos_page" or @name="turno"]', $form)->length);
        $this->assertSame(1, $xpath->query('.//button[@type="submit" and @name="aplicar" and @value="1"]', $form)->length);
        $this->assertSame(1, $xpath->query('.//a[@href="'.$this->url().'" and normalize-space(.)="Limpiar filtros"]', $form)->length);
        $this->assertSame(0, $xpath->query('//*[@tabindex > 0]')->length);
        $this->assertStringContainsString('grid-cols-1', $form->getAttribute('class'));
        $this->assertStringContainsString('sm:grid-cols-2', $form->getAttribute('class'));
    }

    public function test_search_input_is_html_escaped(): void
    {
        $texto = '<script>alert("x")</script>';
        $this->actingAs($this->paciente->user)->get($this->url(['profesional' => $texto]))->assertOk()
            ->assertSee(e($texto), false)->assertDontSee($texto, false);
    }

    #[DataProvider('clasificacionDeTurnos')]
    public function test_owner_reads_detail_of_both_types_in_every_state_and_temporal_section(EstadoTurnoEnum $estado, TipoTurnoEnum $tipo, int $segundos, string $seccion): void
    {
        $inicio = $this->ahora->addSeconds($segundos);
        $turno = $this->turno(['tipo' => $tipo, 'estado' => $estado, 'inicio_at' => $inicio]);
        $antes = $turno->fresh()->getAttributes();
        $response = $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $turno))->assertOk()
            ->assertViewIs('turnos.mis-turnos.show')->assertSee('Detalle del turno')
            ->assertSee($inicio->format('d/m/Y'))->assertSee($inicio->format('H:i').' – '.$inicio->addMinutes(45)->format('H:i').' hs')
            ->assertSee(trim($this->kinesiologa->user->name.' '.$this->kinesiologa->user->apellido));
        $detalle = $response->viewData('detalle');
        $this->assertSame($estado, $detalle['estado']);
        $this->assertSame($tipo, $detalle['tipo']);
        $this->assertSame($seccion, $detalle['seccion']);
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_detail_uses_real_gate_before_rendering_sensitive_fields_and_ignores_client_owner_ids(): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => 'MOTIVO_AJENO', 'clave_idempotencia' => (string) Str::uuid()]);
        $otro = $this->pacienteCompleto();
        $vistas = [];
        Gate::after(function ($user, $capacidad, $resultado, $argumentos) use (&$vistas): void {
            if ($capacidad === 'view' && ($argumentos[0] ?? null) instanceof Turno) {
                $vistas[] = [$user->id, $resultado];
            }
        });
        $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', ['turno' => $turno, 'paciente_id' => $otro->id, 'estado' => 'ATENDIDO']))->assertOk();
        $this->actingAs($otro->user)->get(route('turnos.mis-turnos.show', ['turno' => $turno, 'paciente_id' => $this->paciente->id]))
            ->assertForbidden()->assertDontSee('MOTIVO_AJENO')->assertDontSee($turno->clave_idempotencia);
        $this->assertSame([[$this->paciente->user_id, true], [$otro->user_id, false]], $vistas);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_detail_reuses_guest_verification_profile_and_active_account_middleware(): void
    {
        $turno = $this->turno();
        $url = route('turnos.mis-turnos.show', $turno);
        $this->get($url)->assertRedirect(route('login'));
        $this->paciente->user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->paciente->user)->get($url)->assertRedirect(route('verification.notice'));
        $this->actingAs($this->usuario(RoleEnum::PACIENTE))->get($url)->assertRedirect(route('perfil.completar'));
        $this->paciente->user->forceFill(['email_verified_at' => now(), 'activo' => false])->save();
        $this->actingAs($this->paciente->user)->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('rolesNoPacientes')]
    public function test_detail_does_not_extend_access_to_other_roles(RoleEnum $rol): void
    {
        $turno = $this->turno(['motivo_solicitud' => 'CONTENIDO_PRIVADO']);
        $actor = $rol === RoleEnum::KINESIOLOGO ? $this->kinesiologa->user : $this->usuario($rol);
        $this->actingAs($actor)->get(route('turnos.mis-turnos.show', $turno))->assertForbidden()->assertDontSee('CONTENIDO_PRIVADO');
    }

    public function test_missing_and_invalid_detail_ids_do_not_disclose_data_or_accept_mutations(): void
    {
        $this->actingAs($this->paciente->user);
        foreach (['999999', 'abc', '-1'] as $id) {
            $this->get('/turnos/mis-turnos/'.$id)->assertNotFound();
        }
        $turno = $this->turno();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $metodo) {
            $this->call($metodo, route('turnos.mis-turnos.show', $turno))->assertStatus(405);
        }
        $this->assertSame(EstadoTurnoEnum::PENDIENTE, $turno->fresh()->estado);
    }

    public function test_first_consultation_detail_shows_only_original_documents_associated_with_that_turno(): void
    {
        $disco = Storage::fake(config('documentos_clinicos.disk'));
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => "MOTIVO ORIGINAL\nSegundo renglón", 'clave_idempotencia' => (string) Str::uuid()]);
        $documento = $this->documentoDetalle($turno, ['nombre_original' => 'SOLICITUD_ORIGINAL.pdf', 'descripcion' => 'DESCRIPCIÓN ORIGINAL']);
        $antes = $documento->fresh()->getAttributes();
        $disco->put($documento->ruta_storage, '%PDF-1.4 prueba privada');
        $otraSolicitud = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA]);
        $otroDocumento = $this->documentoDetalle($otraSolicitud, ['nombre_original' => 'OTRO_TURNO.pdf']);
        $ajeno = $this->documentoDetalle($turno, ['paciente_id' => $this->pacienteCompleto()->id, 'nombre_original' => 'OTRO_PACIENTE.pdf']);
        $interno = $this->documentoDetalle($turno, ['origen' => 'CONSULTA_CLINICA', 'nombre_original' => 'INTERNO.pdf', 'descripcion' => 'NOTA CLÍNICA INTERNA']);
        DocumentoClinico::factory()->create(['paciente_id' => $this->paciente->id, 'cargado_por_user_id' => $this->paciente->user_id, 'nombre_original' => 'SIN_ASOCIACION.pdf']);
        $response = $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $turno))->assertOk()
            ->assertSee('MOTIVO ORIGINAL')->assertSee('Segundo renglón')->assertSee('SOLICITUD_ORIGINAL.pdf')->assertSee('DESCRIPCIÓN ORIGINAL')
            ->assertSee(route('documentos-clinicos.download', $documento), false)->assertDontSee($turno->clave_idempotencia)
            ->assertDontSee($documento->ruta_storage)->assertDontSee($otroDocumento->nombre_original)->assertDontSee($ajeno->nombre_original)
            ->assertDontSee($interno->nombre_original)->assertDontSee($interno->descripcion)->assertDontSee('SIN_ASOCIACION.pdf');
        $this->assertCount(1, $response->viewData('detalle')['documentos']);
        $this->assertSame(['nombre', 'descripcion', 'url'], array_keys($response->viewData('detalle')['documentos']->first()));
        $this->assertSame($antes, $documento->fresh()->getAttributes());
        $this->assertDatabaseCount('auditorias', 0);
        $descarga = $this->get(route('documentos-clinicos.download', $documento))->assertOk();
        $this->assertSame('%PDF-1.4 prueba privada', $descarga->streamedContent());
        $this->assertDatabaseCount('auditorias', 1);
        $this->get(route('documentos-clinicos.download', $ajeno))->assertForbidden();
    }

    public function test_document_policy_remains_a_separate_requirement_for_detail_metadata(): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA]);
        $documento = $this->documentoDetalle($turno, ['nombre_original' => 'DENEGADO.pdf', 'descripcion' => 'DESCRIPCIÓN DENEGADA']);
        $this->mock(DocumentoClinicoPolicy::class, function ($mock): void {
            $mock->shouldReceive('download')->once()->andReturnFalse();
        });
        $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $turno))->assertOk()
            ->assertDontSee($documento->nombre_original)->assertDontSee($documento->descripcion)
            ->assertDontSee(route('documentos-clinicos.download', $documento), false);
    }

    public function test_first_consultation_without_optional_original_data_has_honest_empty_states(): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => null]);
        $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $turno))->assertOk()
            ->assertSee('Este turno no tiene un motivo de solicitud registrado.')
            ->assertSee('Este turno no tiene documentos de solicitud disponibles.')
            ->assertDontSee('Solicitado por el paciente');
        $documento = $this->documentoDetalle($turno, ['descripcion' => null]);
        $this->get(route('turnos.mis-turnos.show', $turno))->assertOk()->assertSee('Sin descripción registrada.')->assertSee($documento->nombre_original);
    }

    public function test_followup_does_not_inherit_request_data_or_fabricate_plan_or_session_information(): void
    {
        $primera = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => 'MOTIVO DE PRIMERA']);
        $this->documentoDetalle($primera, ['nombre_original' => 'PRIMERA.pdf']);
        $seguimiento = $this->turno(['motivo_solicitud' => 'NO MOSTRAR EN SEGUIMIENTO']);
        $this->documentoDetalle($seguimiento, ['nombre_original' => 'NO_HEREDAR.pdf']);
        $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $seguimiento))->assertOk()
            ->assertSee('Seguimiento')->assertSee('No hay información de plan o sesión registrada para este turno.')
            ->assertDontSee('MOTIVO DE PRIMERA')->assertDontSee('PRIMERA.pdf')->assertDontSee('NO MOSTRAR EN SEGUIMIENTO')->assertDontSee('NO_HEREDAR.pdf')
            ->assertDontSee('Motivo original')->assertDontSee('Sesión 1')->assertDontSee('/documentos-clinicos/');
    }

    #[DataProvider('seccionesVacias')]
    public function test_detail_navigation_round_trip_preserves_filters_both_pages_and_origin_section(bool $futuro): void
    {
        $this->kinesiologa->user->update(['name' => 'Clara']);
        for ($i = 1; $i <= 12; $i++) {
            $this->turno(['inicio_at' => $this->ahora->addDays($i)]);
            $this->turno(['inicio_at' => $this->ahora->subDays($i)]);
        }
        $filtros = ['profesional' => 'clar', 'tipo' => 'SEGUIMIENTO', 'estado' => 'PENDIENTE', 'fecha_desde' => '2026-01-01', 'fecha_hasta' => '2026-12-31'];
        $response = $this->actingAs($this->paciente->user)->get($this->url($filtros + ['proximos_page' => 2, 'historicos_page' => 2]))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $seccion = $futuro ? 'proximos' : 'historicos';
        $enlace = (new \DOMXPath($dom))->query('//section[@aria-labelledby="'.$seccion.'-titulo"]//article/a[normalize-space(.)="Ver detalle"]')->item(0);
        $this->assertNotNull($enlace);
        $detalle = $this->get($enlace->getAttribute('href'))->assertOk();
        $volver = $detalle->viewData('urlVolver');
        parse_str(parse_url($volver, PHP_URL_QUERY), $parametros);
        $this->assertSame($filtros + ['proximos_page' => '2', 'historicos_page' => '2'], $parametros);
        $this->assertSame($seccion.'-titulo', parse_url($volver, PHP_URL_FRAGMENT));
        $this->get(explode('#', $volver)[0])->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->currentPage() === 2)
            ->assertViewHas('historicos', fn ($p) => $p->currentPage() === 2);
    }

    public function test_detail_back_navigation_uses_only_validated_local_context(): void
    {
        $turno = $this->turno();
        $response = $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', [
            'turno' => $turno, 'paciente_id' => 9999, 'return_to' => 'https://example.com', 'aplicar' => '1',
            'proximos_page' => ['2'], 'historicos_page' => '-1',
        ]))->assertOk();
        $this->assertSame($this->url(['proximos_page' => 1, 'historicos_page' => 1]).'#proximos-titulo', $response->viewData('urlVolver'));
    }

    public function test_detail_preserves_historical_professional_without_exposing_personal_fields_or_actions(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->kinesiologa->user->removeRole(RoleEnum::KINESIOLOGO->value);
        $turno = $this->turno(['inicio_at' => $this->ahora->subDay()]);
        $response = $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $turno))->assertOk()
            ->assertSee($this->kinesiologa->user->name)->assertSee('Historial');
        foreach ([$this->kinesiologa->user->dni, $this->kinesiologa->user->email, $this->kinesiologa->user->telefono, 'Confirmar asistencia', 'Cancelar turno', 'Reprogramar', 'Cambiar estado', 'clave_idempotencia', 'ruta_storage'] as $privado) {
            $response->assertDontSee($privado);
        }
    }

    public function test_detail_markup_is_accessible_and_original_text_is_escaped(): void
    {
        $texto = '<script>alert("privado")</script>';
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => $texto]);
        $this->documentoDetalle($turno, ['nombre_original' => $texto.'.pdf', 'descripcion' => $texto]);
        $response = $this->actingAs($this->paciente->user)->get(route('turnos.mis-turnos.show', $turno))->assertOk()->assertDontSee($texto, false)->assertSee(e($texto), false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//h1[normalize-space(.)="Detalle del turno"]')->length);
        $this->assertSame(0, $xpath->query('//*[@tabindex > 0]')->length);
        foreach ($xpath->query('//main//section[@aria-labelledby]') as $seccion) {
            $this->assertSame(1, $xpath->query('.//h2[@id="'.$seccion->getAttribute('aria-labelledby').'"]', $seccion)->length);
        }
        $volver = $xpath->query('//header//a[normalize-space(.)="Volver a Mis turnos"]')->item(0);
        $this->assertNotNull($volver);
        $this->assertStringContainsString('focus:ring-', $volver->getAttribute('class'));
        $this->assertSame(1, $xpath->query('//main//a[normalize-space(.)="Descargar documento" and @aria-label]')->length);
        $this->assertSame(0, $xpath->query('//main//form')->length);
    }

    private function documentoDetalle(Turno $turno, array $atributos = []): DocumentoClinico
    {
        $documento = DocumentoClinico::factory()->create(array_replace([
            'paciente_id' => $turno->paciente_id, 'cargado_por_user_id' => $this->paciente->user_id,
            'ruta_storage' => config('documentos_clinicos.directorio').'/'.Str::uuid(),
        ], $atributos));
        DocumentoClinicoOrigenTurno::create(['documento_clinico_id' => $documento->id, 'turno_id' => $turno->id]);

        return $documento;
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
