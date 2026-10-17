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

    public function test_patient_can_access_with_both_navigation_entries_and_a_non_interactive_cta(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url());
        $response->assertOk()->assertSee('Todavía no tenés turnos registrados')
            ->assertSee('Solicitar nuevo turno')->assertSee('disabled', false)
            ->assertSee('Podés iniciar una nueva solicitud');
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.$this->url().'"'));
        $this->assertTrue(Route::has('turnos.solicitar.create'));
        $response->assertDontSee('href="'.route('turnos.solicitar.create').'"', false);
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

    public function test_temporal_partition_orders_by_start_and_id_regardless_of_state(): void
    {
        $futuroTarde = $this->turno(['inicio_at' => $this->ahora->addDays(3)]);
        $futuroUno = $this->turno(['inicio_at' => $this->ahora->addSecond(), 'estado' => EstadoTurnoEnum::CANCELADO]);
        $futuroDos = $this->turno(['inicio_at' => $futuroUno->inicio_at]);
        $pasado = $this->turno(['inicio_at' => $this->ahora->subSecond(), 'estado' => EstadoTurnoEnum::PENDIENTE]);
        $exactoUno = $this->turno(['inicio_at' => $this->ahora]);
        $exactoDos = $this->turno(['inicio_at' => $this->ahora]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('proximos', fn ($p) => $p->pluck('id')->all() === [$futuroUno->id, $futuroDos->id, $futuroTarde->id])
            ->assertViewHas('historicos', fn ($p) => $p->pluck('id')->all() === [$exactoDos->id, $exactoUno->id, $pasado->id]);
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

    public function test_visible_fields_and_both_responsive_presentations_exclude_clinical_and_internal_data(): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'motivo_solicitud' => 'MOTIVO PRIVADO', 'clave_idempotencia' => (string) Str::uuid()]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertSee('Primera consulta')->assertSee($this->kinesiologa->user->name.' '.$this->kinesiologa->user->apellido)
            ->assertSee($turno->inicio_at->format('d/m/Y'))->assertSee('12:00 – 12:45')->assertSee('Pendiente')
            ->assertSee('hidden md:block', false)->assertSee('md:hidden', false)->assertSee('<table', false)->assertSee('<article', false)
            ->assertDontSee('MOTIVO PRIVADO')->assertDontSee($turno->clave_idempotencia)
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
            ->assertSee('Próximos turnos')->assertSee('Historial de turnos')->assertSee('Seguimiento')
            ->assertSee($futuro ? 'Todavía no tenés turnos en el historial.' : 'No tenés próximos turnos.')
            ->assertDontSee('Todavía no tenés turnos registrados');
    }

    public static function seccionesVacias(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('estadosCanonicos')]
    public function test_preliminary_d01_follows_every_canonical_state(EstadoTurnoEnum $estado): void
    {
        $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => $estado]);
        $ocupa = in_array($estado, EstadoTurnoEnum::estadosQueReservanAgenda(), true);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('primeraConsultaActiva', $ocupa)
            ->assertSee($ocupa ? 'Ya tenés una Primera consulta futura' : 'Podés iniciar una nueva solicitud');
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
