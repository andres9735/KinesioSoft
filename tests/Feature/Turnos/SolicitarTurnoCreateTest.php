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
        foreach (['Motivo y documentación', 'Elegir profesional', 'Fecha y horario', 'Revisar y confirmar'] as $paso) {
            $response->assertSee($paso);
        }
        $response->assertSee('Paso 1 de 4')->assertSee('aria-label="Pasos de la solicitud"', false)
            ->assertSee(':aria-current=', false)->assertSee('x-show="paso === 1"', false)
            ->assertSee('hidden grid-cols-4', false)->assertSee('md:hidden', false)
            ->assertSee('x-data="solicitarTurno(', false)->assertSee('x-cloak', false);
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
    public function test_d01_entry_follows_canonical_occupying_states_without_generating_a_uuid_when_blocked(EstadoTurnoEnum $estado): void
    {
        $turno = $this->turno(['tipo' => TipoTurnoEnum::PRIMERA_CONSULTA, 'estado' => $estado]);
        $ocupa = in_array($estado, EstadoTurnoEnum::estadosQueReservanAgenda(), true);
        if ($ocupa) {
            Str::createUuidsUsing(static function (): never {
                throw new RuntimeException('No debe generarse UUID en una entrada bloqueada.');
            });
        }
        $response = $this->actingAs($this->paciente->user)->get($this->url());
        if ($ocupa) {
            $response->assertRedirect(route('turnos.mis-turnos.index'))->assertSessionHas('warning');
            $mensaje = session('warning');
            $this->assertStringNotContainsString($this->kinesiologa->user->name, $mensaje);
            $this->assertStringNotContainsString($turno->inicio_at->toDateTimeString(), $mensaje);
            $this->assertStringNotContainsString('Motivo privado', $mensaje);
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
    public function test_only_canonically_eligible_professionals_are_delivered(string $condicion): void
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
        $profesionales = $response->viewData('configuracionWizard')['profesionales'];
        $this->assertSame([['id' => $this->kinesiologa->id, 'nombre' => trim($this->kinesiologa->user->name.' '.$this->kinesiologa->user->apellido)]], $profesionales);
        $response->assertDontSee($otra->user->name);
    }

    public static function condicionesProfesionales(): array
    {
        return array_map(fn ($condicion) => [$condicion], ['activo', 'rol', 'verificado', 'datos_personales', 'titulo', 'matricula', 'especialidad']);
    }

    public function test_professional_order_is_surname_name_id_and_cards_exclude_private_details(): void
    {
        $this->kinesiologa->user->update(['apellido' => 'Alfa', 'name' => 'Bea']);
        $zulu = $this->profesional(['apellido' => 'Zulu', 'name' => 'Ana']);
        $zoe = $this->profesional(['apellido' => 'Alfa', 'name' => 'Zoe']);
        $empate = $this->profesional(['apellido' => 'Alfa', 'name' => 'Bea']);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $this->assertSame([$this->kinesiologa->id, $empate->id, $zoe->id, $zulu->id], array_column($response->viewData('configuracionWizard')['profesionales'], 'id'));
        $response->assertSee('Bea Alfa')->assertSee('type="radio"', false)->assertSee('Seleccionada')
            ->assertDontSee($zulu->user->email)->assertDontSee($zulu->user->dni)
            ->assertDontSee('MP-'.$zulu->user_id)->assertDontSee('titulo_profesional_id')->assertDontSee('paciente_id');
    }

    public function test_empty_professional_list_is_contextual_and_does_not_claim_empty_availability(): void
    {
        $this->kinesiologa->user->update(['activo' => false]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertViewHas('configuracionWizard', fn ($config) => $config['profesionales'] === [])
            ->assertSee('No hay profesionales disponibles para nuevas solicitudes en este momento.')
            ->assertSee('Volver a Mis turnos')->assertDontSee('Sin horarios disponibles');
    }

    public function test_get_does_not_consult_agenda_or_use_blocking_reads(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        foreach (['configuraciones_agenda', 'franjas_horarias_agenda', 'bloqueos_agenda', 'for update', 'insert into', 'update ', 'delete from'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $sql);
        }
    }

    public function test_initial_configuration_is_a_minimal_whitelist_and_document_limits_come_from_policy(): void
    {
        config(['documentos_clinicos.max_archivos' => 2, 'documentos_clinicos.max_bytes_por_archivo' => 6 * 1024 * 1024, 'documentos_clinicos.max_bytes_total' => 12 * 1024 * 1024]);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $config = $response->viewData('configuracionWizard');
        $this->assertSame(['uuid', 'profesionales', 'maxMotivo', 'limites', 'urlMisTurnos', 'urlDisponibilidad', 'urlConfirmacion', 'fechaInicial'], array_keys($config));
        $this->assertSame(route('turnos.solicitar.store'), $config['urlConfirmacion']);
        $this->assertSame(route('turnos.solicitar.disponibilidad'), $config['urlDisponibilidad']);
        $this->assertSame('2026-10-17', $config['fechaInicial']);
        $this->assertSame(['id', 'nombre'], array_keys($config['profesionales'][0]));
        $this->assertSame(2, $config['limites']['maxArchivos']);
        $this->assertSame(6 * 1024 * 1024, $config['limites']['maxBytesPorArchivo']);
        $this->assertSame(12 * 1024 * 1024, $config['limites']['maxBytesTotal']);
        $this->assertSame(config('documentos_clinicos.mime_permitidos'), $config['limites']['mimePermitidos']);
        $response->assertSee('Hasta 2 archivos.')->assertSee('Máximo 6 MiB por archivo y 12 MiB en total.');
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

    public function test_configuration_and_professional_names_are_safely_serialized(): void
    {
        $peligroso = '</script><img src=x onerror=alert(1)>';
        $this->kinesiologa->user->update(['name' => $peligroso]);
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk();
        $response->assertDontSee($peligroso, false)->assertSee(e($peligroso), false);
    }

    public function test_review_has_fetch_confirmation_and_keeps_existing_routes(): void
    {
        $response = $this->actingAs($this->paciente->user)->get($this->url())->assertOk()
            ->assertSee('x-show="paso === 3"', false)->assertSee('x-show="paso === 4"', false)
            ->assertSee('Consultando horarios disponibles…')->assertSee('No hay horarios disponibles entre el')
            ->assertSee("estadoDisponibilidad === 'error'", false)->assertSee('Reintentar')
            ->assertSee('Semana anterior')->assertSee('Semana siguiente')->assertSee('Horarios disponibles')
            ->assertSee(':aria-pressed=', false)->assertSee('Cambiar profesional')->assertSee('Pendiente de selección')
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
        $this->assertSame([
            'turnos.mis-turnos.index', 'turnos.solicitar.create', 'turnos.solicitar.disponibilidad', 'turnos.solicitar.profesionales-disponibles', 'turnos.solicitar.store',
        ], collect(Route::getRoutes())->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'turnos/') && ! str_starts_with($ruta->uri(), 'turnos/agenda'))
            ->map(fn ($ruta) => $ruta->getName())->values()->all());
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
