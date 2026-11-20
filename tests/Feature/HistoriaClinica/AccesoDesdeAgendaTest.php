<?php

namespace Tests\Feature\HistoriaClinica;

use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Policies\TurnoPolicy;
use App\Services\HistoriaClinica\ConsultarExpedienteDesdeTurnoService;
use Database\Seeders\PermisoHistoriaClinicaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class AccesoDesdeAgendaTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Paciente $paciente;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Queue::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermisoHistoriaClinicaSeeder::class);
        $this->profesional = $this->profesionalUc07();
        $this->profesional->user->givePermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA);
        $this->paciente = Paciente::create(['user_id' => $this->usuarioUc07(RoleEnum::PACIENTE)->id, 'activo' => true]);
        $this->turno = Turno::factory()->for($this->profesional)->for($this->paciente)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00',
        ]);
    }

    public function test_el_acceso_desde_el_detalle_y_la_ruta_directa_requieren_permiso(): void
    {
        $this->actingAs($this->profesional->user);
        $this->get(route('turnos.agenda-atencion.show', $this->turno->id))->assertOk()
            ->assertSee(route('historia-clinica.desde-turno', $this->turno->id), false);
        $this->get($this->url())->assertOk()->assertViewHas('paciente', fn ($paciente) => $paciente->id === $this->paciente->id);

        $this->profesional->user->revokePermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA);
        $this->get(route('turnos.agenda-atencion.show', $this->turno->id))->assertOk()
            ->assertDontSee('Acceder a la historia clínica');
        $this->get($this->url())->assertForbidden();
    }

    public function test_guest_no_puede_acceder_y_la_ruta_solo_admite_lecturas(): void
    {
        $ruta = Route::getRoutes()->getByName('historia-clinica.desde-turno');
        $this->assertSame(['GET', 'HEAD'], $ruta->methods());
        $this->get($this->url())->assertRedirect(route('login'));
        $this->actingAs($this->profesional->user)->post($this->url())->assertStatus(405);
    }

    #[DataProvider('rolesSinAccesoClinico')]
    public function test_un_rol_no_profesional_no_obtiene_acceso_aunque_tenga_permiso(RoleEnum $rol): void
    {
        $actor = $this->usuarioUc07($rol);
        $actor->givePermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA);
        $this->actingAs($actor)->get($this->url())->assertForbidden()->assertDontSee($this->paciente->user->name);
    }

    public static function rolesSinAccesoClinico(): array
    {
        return [[RoleEnum::PACIENTE], [RoleEnum::ADMINISTRADOR], [RoleEnum::SUPERADMIN]];
    }

    public function test_otra_profesional_con_permiso_no_accede_y_la_reasignacion_revoca_el_acceso(): void
    {
        $otra = $this->profesionalUc07();
        $otra->user->givePermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA);
        $this->actingAs($otra->user)->get($this->url())->assertForbidden();

        $this->actingAs($this->profesional->user);
        $this->get($this->url())->assertOk();
        $this->turno->forceFill(['kinesiologa_id' => $otra->id])->save();
        $this->get($this->url())->assertForbidden();
    }

    #[DataProvider('revocaciones')]
    public function test_el_servicio_relee_la_habilitacion_y_el_permiso_aunque_el_actor_este_precargado(string $caso): void
    {
        $actor = $this->profesional->user;
        $actor->load('roles', 'permissions', 'kinesiologa.matriculasProfesionales', 'kinesiologa.especialidades');
        match ($caso) {
            'cuenta inactiva' => DB::table('users')->where('id', $actor->id)->update(['activo' => false]),
            'correo no verificado' => DB::table('users')->where('id', $actor->id)->update(['email_verified_at' => null]),
            'datos personales incompletos' => DB::table('users')->where('id', $actor->id)->update(['telefono' => null]),
            'sin título' => DB::table('kinesiologas')->where('id', $this->profesional->id)->update(['titulo_profesional_id' => null]),
            'sin matrícula activa' => $this->profesional->matriculasProfesionales()->update(['activa' => false]),
            'sin especialidad' => $this->profesional->especialidades()->detach(),
            'sin rol profesional' => $actor->roles()->detach(),
            'sin permiso' => $actor->permissions()->detach(),
        };

        $this->expectException(AuthorizationException::class);
        app(ConsultarExpedienteDesdeTurnoService::class)->consultar($actor, $this->turno->id);
    }

    public static function revocaciones(): array
    {
        return array_map(fn ($caso) => [$caso], [
            'cuenta inactiva', 'correo no verificado', 'datos personales incompletos', 'sin título',
            'sin matrícula activa', 'sin especialidad', 'sin rol profesional', 'sin permiso',
        ]);
    }

    public function test_el_rechazo_por_asignacion_precede_a_la_lectura_del_expediente_y_las_declaraciones(): void
    {
        $otra = $this->profesionalUc07();
        $otra->user->givePermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA);
        $lecturasClinicas = [];
        DB::listen(function (QueryExecuted $query) use (&$lecturasClinicas): void {
            if (str_contains($query->sql, 'historias_clinicas') || str_contains($query->sql, 'declaraciones_salud_paciente')) {
                $lecturasClinicas[] = $query->sql;
            }
        });

        $this->actingAs($otra->user)->get($this->url())->assertForbidden();
        $this->assertSame([], $lecturasClinicas);
    }

    public function test_relee_el_paciente_del_turno_y_no_una_relacion_anterior(): void
    {
        $anterior = $this->historia();
        $this->turno->load('paciente.historiaClinica');
        $actual = $this->pacienteUc07();
        $historiaActual = HistoriaClinica::create([
            'paciente_id' => $actual->id, 'nro_historia_clinica' => 'HC-PACIENTE-ACTUAL', 'activa' => true,
        ]);
        $this->turno->forceFill(['paciente_id' => $actual->id])->save();

        $this->actingAs($this->profesional->user)->get($this->url())->assertOk()
            ->assertViewHas('paciente', fn ($paciente) => $paciente->id === $actual->id)
            ->assertViewHas('historia', fn ($historia) => $historia->id === $historiaActual->id)
            ->assertDontSee($anterior->nro_historia_clinica);
    }

    public function test_no_toma_el_paciente_ni_la_disponibilidad_de_base_de_la_peticion(): void
    {
        $ajeno = $this->pacienteUc07();
        $historia = $this->historia();
        HistoriaClinica::create(['paciente_id' => $ajeno->id, 'nro_historia_clinica' => 'HC-AJENA-SECRETA', 'activa' => true]);
        $this->actingAs($this->profesional->user)->get($this->url().'?paciente_id='.$ajeno->id.'&datosBaseDisponibles=1')
            ->assertOk()->assertViewHas('historia', fn ($hc) => $hc->id === $historia->id)
            ->assertViewHas('datosBaseDisponibles', null)->assertViewHas('recorrido', 'verificar_datos_base')
            ->assertDontSee('HC-AJENA-SECRETA');
    }

    #[DataProvider('situacionesPersistidas')]
    public function test_recupera_el_expediente_e_indica_el_recorrido_sin_escrituras(
        ?bool $activa,
        array $estados,
        string $recorrido,
        bool $aviso,
    ): void {
        $historia = $activa === null ? null : $this->historia($activa);
        foreach ($estados as $estado) {
            $this->declaracion($estado);
        }
        $antes = $this->contenidoClinico();
        $escrituras = [];
        DB::listen(function (QueryExecuted $query) use (&$escrituras): void {
            if (preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query->sql)) {
                $escrituras[] = $query->sql;
            }
        });
        $this->actingAs($this->profesional->user);
        $respuesta = $this->get($this->url());
        $respuesta->assertOk()->assertViewHas('recorrido', $recorrido)
            ->assertViewHas('declaracionPendiente', $aviso)
            ->assertViewHas('historia', fn ($hc) => $hc?->id === $historia?->id)
            ->assertSee('Recorrido siguiente')->assertDontSee('dato declarado no incorporado');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        if ($aviso) {
            $respuesta->assertSee('role="alert"', false)->assertSee('Declaración de salud pendiente de revisión');
        } else {
            $respuesta->assertDontSee('Declaración de salud pendiente de revisión');
        }
        $this->head($this->url())->assertOk();
        $this->assertSame($antes, $this->contenidoClinico());
        $this->assertSame([], $escrituras);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public static function situacionesPersistidas(): array
    {
        $pendiente = DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION;
        $revisada = DeclaracionSaludPaciente::ESTADO_REVISADA;

        return [
            'sin historia ni declaración' => [null, [], 'apertura_a', false],
            'sin historia con pendiente' => [null, [$pendiente], 'revision_c', true],
            'activa sin versión digital' => [true, [], 'verificar_datos_base', false],
            'histórica y pendiente' => [true, [$pendiente], 'verificar_datos_base', true],
            'revisada no demuestra base disponible' => [true, [$revisada], 'verificar_datos_base', false],
            'inactiva' => [false, [], 'historia_inactiva', false],
            'inactiva y pendiente' => [false, [$pendiente], 'historia_inactiva', true],
            'revisada sin historia' => [null, [$revisada], 'declaracion_revisada_sin_historia', false],
            'inconsistencia con otra pendiente' => [null, [$revisada, $pendiente], 'declaracion_revisada_sin_historia', true],
        ];
    }

    #[DataProvider('estadosTurno')]
    public function test_consultar_no_inicia_atencion_ni_altera_el_estado_del_turno(EstadoTurnoEnum $estado): void
    {
        $this->turno->forceFill(['estado' => $estado])->save();
        $this->historia();
        $declaracion = $this->declaracion(DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION);
        $antes = $this->contenidoClinico();
        $this->actingAs($this->profesional->user)->get($this->url())->assertOk();
        $this->assertSame($estado, $this->turno->fresh()->estado);
        $this->assertSame(DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION, $declaracion->fresh()->estado);
        $this->assertSame($antes, $this->contenidoClinico());
    }

    public static function estadosTurno(): array
    {
        return array_map(fn ($estado) => [$estado], EstadoTurnoEnum::cases());
    }

    public function test_error_de_recuperacion_no_expone_detalles_tecnicos_y_permite_reintentar(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'historias_clinicas')) {
                throw new QueryException('sqlite', 'SQL PRIVADO', [], new RuntimeException('detalle privado'));
            }
        });
        $this->actingAs($this->profesional->user)->get($this->url())->assertStatus(503)
            ->assertSee('pendiente de verificar')->assertSee('Reintentar')
            ->assertDontSee('SQL PRIVADO')->assertDontSee('detalle privado');
    }

    public function test_registrar_el_permiso_es_idempotente_y_no_lo_otorga_a_otras_profesionales(): void
    {
        $otra = $this->profesionalUc07();
        $this->seed(PermisoHistoriaClinicaSeeder::class);
        $this->assertFalse($otra->user->checkPermissionTo(TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, 'web'));
        $this->assertDatabaseCount('permissions', 1);
    }

    private function url(): string
    {
        return route('historia-clinica.desde-turno', $this->turno->id);
    }

    private function historia(bool $activa = true): HistoriaClinica
    {
        return HistoriaClinica::create([
            'paciente_id' => $this->paciente->id, 'nro_historia_clinica' => 'HC-HISTORICA-PRUEBA', 'activa' => $activa,
        ]);
    }

    private function declaracion(string $estado): DeclaracionSaludPaciente
    {
        return DeclaracionSaludPaciente::create([
            'paciente_id' => $this->paciente->id, 'objetivo_salud' => 'dato declarado no incorporado',
            'peso_kg' => 70, 'altura_cm' => 170, 'ocupacion' => 'Docente',
            'nivel_actividad_fisica' => 'MODERADO', 'detalle_actividad_fisica' => 'Caminatas', 'estado' => $estado,
        ]);
    }

    private function contenidoClinico(): array
    {
        $datos = [];
        foreach (['turnos', 'pacientes', 'historias_clinicas', 'declaraciones_salud_paciente', 'items_declaracion_salud', 'auditorias'] as $tabla) {
            $datos[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $datos;
    }
}
