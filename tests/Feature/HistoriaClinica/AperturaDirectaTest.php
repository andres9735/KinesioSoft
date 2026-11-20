<?php

namespace Tests\Feature\HistoriaClinica;

use App\Actions\HistoriaClinica\AbrirHistoriaClinicaAction;
use App\Actions\Pacientes\CrearPerfilPacienteAction;
use App\Enums\EstadoTurnoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoTurnoEnum;
use App\Models\Auditoria;
use App\Models\DeclaracionSaludPaciente;
use App\Models\EspecialidadKinesiologia;
use App\Models\HistoriaClinica;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\VersionDatosClinicosBase;
use App\Policies\TurnoPolicy;
use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica;
use Database\Seeders\PermisoHistoriaClinicaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class AperturaDirectaTest extends TestCase
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
        $this->profesional->user->givePermissionTo([
            TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA,
        ]);
        $usuario = $this->usuarioUc07(RoleEnum::PACIENTE);
        $usuario->update(['name' => 'Mariano', 'apellido' => 'Warner', 'email' => 'warnermariano@gmail.com']);
        $this->paciente = app(CrearPerfilPacienteAction::class)->ejecutar($usuario);
        $this->turno = Turno::factory()->for($this->profesional)->for($this->paciente)->create([
            'tipo' => TipoTurnoEnum::PRIMERA_CONSULTA,
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 11:00:00',
        ]);
        $this->turno->forceFill(['estado' => EstadoTurnoEnum::PENDIENTE])->save();
        $this->actingAs($this->profesional->user);
    }

    public function test_abre_activa_con_autoria_procedencia_y_version_recuperable_desde_agenda(): void
    {
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->assertDatabaseCount('declaraciones_salud_paciente', 0);
        $this->assertSame('warnermariano@gmail.com', $this->paciente->user->email);
        $antes = $this->turno->fresh()->getRawOriginal();
        $auditoriasAntes = Auditoria::count();
        $this->get(route('turnos.agenda-atencion.show', $this->turno->id))->assertOk()
            ->assertSee(route('historia-clinica.desde-turno', $this->turno->id), false);
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()->assertSee($this->url(), false);
        $contexto = $this->formulario();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertSessionHasNoErrors()->assertRedirect($this->lectura());
        $historia = HistoriaClinica::sole();
        $version = VersionDatosClinicosBase::sole();
        $this->assertTrue($historia->activa);
        $this->assertSame($this->paciente->id, $historia->paciente_id);
        $this->assertSame('HC-'.str_pad((string) $this->paciente->id, 6, '0', STR_PAD_LEFT), $historia->nro_historia_clinica);
        $this->assertSame($this->profesional->user_id, $version->autor_user_id);
        $this->assertSame($this->profesional->id, $version->kinesiologa_id);
        $this->assertSame($this->datos()['especialidad_id'], $version->especialidad_id);
        $this->assertSame('Especialidad', $version->especialidad_nombre_snapshot);
        $this->assertSame('CARGA_PROFESIONAL_DIRECTA', $version->origen);
        $this->assertSame('REFERIDO_PACIENTE', $version->procedencia_relevamiento);
        $this->assertSame(1, $version->numero_version);
        $this->assertNotNull($version->created_at);
        $this->assertNull($version->peso_kg);
        $this->assertSame('NO_PRESENTA', $version->relevamientos['personales']);
        $this->assertSame($auditoriasAntes + 1, Auditoria::count());
        $this->get($this->lectura())->assertOk()->assertViewHas('datosBaseDisponibles', true)
            ->assertViewHas('recorrido', 'expediente_disponible')->assertSee('Datos de base disponibles')
            ->assertSee('Mariano Warner')->assertSee('Especialidad');
        $this->assertSame($antes, $this->turno->fresh()->getRawOriginal());
        $this->assertDatabaseCount('declaraciones_salud_paciente', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    #[DataProvider('tiposAntecedente')]
    public function test_un_antecedente_presente_pendiente_de_codificacion_permite_activar(string $tipo): void
    {
        $datos = $this->datos();
        $datos['bloques'][$tipo] = ['resultado' => 'INFORMADO', 'items' => [[
            'descripcion' => '  Antecedente referido que requiere precisar equivalencia  ',
            'procedencia' => 'REFERIDO_PACIENTE', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]]];
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('items_datos_clinicos_base', [
            'tipo' => $tipo, 'descripcion' => 'Antecedente referido que requiere precisar equivalencia',
            'procedencia' => 'REFERIDO_PACIENTE', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]);
        $this->assertTrue(HistoriaClinica::sole()->activa);
        $this->get($this->lectura())->assertOk()->assertSee('Pendiente de codificación')
            ->assertSee('Antecedente referido que requiere precisar equivalencia');
    }

    public static function tiposAntecedente(): array
    {
        return array_map(fn ($tipo) => [$tipo], array_keys(ValidarDatosAperturaHistoriaClinica::TIPOS));
    }

    #[DataProvider('bloquesObligatorios')]
    public function test_un_bloque_sin_relevar_o_sin_respuesta_impide_la_apertura(string $tipo, bool $ausente): void
    {
        $datos = $this->datos();
        if ($ausente) {
            unset($datos['bloques'][$tipo]);
        } else {
            $datos['bloques'][$tipo]['resultado'] = 'NO_RELEVADO';
        }
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasErrors();
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->assertDatabaseCount('versiones_datos_clinicos_base', 0);
    }

    public static function bloquesObligatorios(): array
    {
        $casos = [];
        foreach (array_keys(ValidarDatosAperturaHistoriaClinica::TIPOS) as $tipo) {
            $casos[] = [$tipo, false];
            $casos[] = [$tipo, true];
        }

        return $casos;
    }

    public function test_familia_no_disponible_registrada_y_antecedente_desconocido_son_validos(): void
    {
        $datos = $this->datos();
        $datos['familia'] = ['resultado' => 'INFORMACION_NO_DISPONIBLE', 'motivo' => 'No recuerda los datos', 'integrantes' => []];
        $datos['bloques']['familiares']['resultado'] = 'DESCONOCIDO';
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('versiones_datos_clinicos_base', [
            'resultado_nucleo_familiar' => 'INFORMACION_NO_DISPONIBLE', 'motivo_nucleo_familiar' => 'No recuerda los datos',
        ]);
        $this->assertSame('DESCONOCIDO', VersionDatosClinicosBase::sole()->relevamientos['familiares']);
        $this->assertDatabaseCount('items_datos_clinicos_base', 0);
        $this->assertTrue(HistoriaClinica::sole()->activa);
    }

    public function test_familia_no_relevada_impide_abrir(): void
    {
        $datos = $this->datos();
        $datos['familia']['resultado'] = 'NO_RELEVADO';
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasErrors('familia.resultado');
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_solo_vinculo_familiar_es_valido_y_contacto_no_se_incorpora_automaticamente(): void
    {
        $this->paciente->update(['contacto_emergencia_nombre' => 'Persona de emergencia']);
        $datos = $this->datos();
        $datos['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [['nombre_apellido' => '', 'vinculo' => 'Hermana']]];
        $this->get($this->url())->assertOk()->assertSee('Persona de emergencia');
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('integrantes_nucleo_familiar_hc', 1);
        $this->assertDatabaseHas('integrantes_nucleo_familiar_hc', ['nombre_apellido' => null, 'vinculo' => 'Hermana', 'contacto_emergencia_confirmado' => false]);
        $this->get($this->lectura())->assertOk()->assertSee('Nombre no disponible')->assertDontSee('Persona de emergencia');
    }

    public function test_contacto_solo_se_incorpora_con_confirmacion_y_nombre_del_servidor(): void
    {
        $this->paciente->update(['contacto_emergencia_nombre' => 'Contacto original']);
        $contexto = $this->formulario();
        $this->paciente->update(['contacto_emergencia_nombre' => 'Contacto actualizado']);
        $datos = $this->datos();
        $datos['familia'] = ['resultado' => 'INFORMADO', 'incluir_contacto' => '1', 'vinculo_contacto' => 'Padre', 'integrantes' => []];
        $this->post($this->url(), ['contexto' => $contexto, ...$datos])->assertRedirect($this->lectura())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('integrantes_nucleo_familiar_hc', [
            'nombre_apellido' => 'Contacto actualizado', 'vinculo' => 'Padre',
            'procedencia' => 'CONTACTO_EMERGENCIA_CONFIRMADO', 'contacto_emergencia_confirmado' => true,
        ]);
    }

    #[DataProvider('permisos')]
    public function test_se_revalida_el_permiso_al_guardar(string $permiso): void
    {
        $contexto = $this->formulario();
        $this->profesional->user->revokePermissionTo($permiso);
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertForbidden();
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public static function permisos(): array
    {
        return [[TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA], [TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA]];
    }

    public function test_invitado_y_paciente_con_permisos_no_pueden_abrir(): void
    {
        $contexto = $this->formulario();
        $this->app['auth']->forgetGuards();
        $this->get($this->url())->assertRedirect(route('login'));
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertRedirect(route('login'));
        $this->paciente->user->givePermissionTo([TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA]);
        $this->actingAs($this->paciente->user)->get($this->url())->assertForbidden();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertForbidden();
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_revalida_el_permiso_dentro_de_la_transaccion_despues_de_bloquear_el_turno(): void
    {
        $contexto = $this->formulario();
        $revocado = false;
        DB::listen(function ($query) use (&$revocado): void {
            if (! $revocado && DB::transactionLevel() > 0 && str_starts_with($query->sql, 'select * from "turnos"')) {
                $revocado = true;
                $this->profesional->user->revokePermissionTo(TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA);
            }
        });
        try {
            app(AbrirHistoriaClinicaAction::class)->ejecutar($this->profesional->user, $this->turno->id, $contexto, $this->datos());
            $this->fail('La revocación debía impedir el guardado.');
        } catch (AuthorizationException) {
            $this->assertTrue($revocado);
            $this->assertDatabaseCount('historias_clinicas', 0);
        }
    }

    public function test_los_datos_complementarios_disponibles_se_guardan_sin_alterar_el_turno(): void
    {
        $antes = $this->turno->fresh()->getRawOriginal();
        $datos = [...$this->datos(), 'objetivo_salud' => 'Mejorar movilidad', 'peso_kg' => '75.50',
            'altura_cm' => '178', 'ocupacion' => 'Docente', 'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas'];
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$datos])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('versiones_datos_clinicos_base', ['objetivo_salud' => 'Mejorar movilidad', 'peso_kg' => 75.5, 'altura_cm' => 178]);
        $this->assertSame($antes, $this->turno->fresh()->getRawOriginal());
        $this->get($this->lectura())->assertOk()->assertSee('Mejorar movilidad')->assertSee('Docente');
    }

    public function test_otra_profesional_y_turno_reasignado_no_pueden_guardar(): void
    {
        $contexto = $this->formulario();
        $otra = $this->profesionalUc07();
        $otra->user->givePermissionTo([TurnoPolicy::PERMISO_ABRIR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA]);
        $this->actingAs($otra->user)->get($this->url())->assertForbidden();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertForbidden();
        $this->turno->forceFill(['kinesiologa_id' => $otra->id])->save();
        $this->actingAs($this->profesional->user)->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertForbidden();
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_no_guarda_si_la_habilitacion_fue_revocada_despues_de_mostrar_el_formulario(): void
    {
        $contexto = $this->formulario();
        $this->profesional->matriculasProfesionales()->update(['activa' => false]);
        // El middleware deriva al perfil incompleto. La Action debe rechazar
        // también cuando se la invoca sin ese recorrido HTTP.
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertRedirect(route('perfil.kinesiologa.completar'));
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->expectException(AuthorizationException::class);
        app(AbrirHistoriaClinicaAction::class)->ejecutar($this->profesional->user, $this->turno->id, $contexto, $this->datos());
    }

    public function test_contexto_no_se_puede_reutilizar_para_otro_paciente_o_manipular(): void
    {
        $contexto = $this->formulario();
        $otro = app(CrearPerfilPacienteAction::class)->ejecutar($this->usuarioUc07(RoleEnum::PACIENTE));
        $this->turno->forceFill(['paciente_id' => $otro->id])->save();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertRedirect($this->lectura())->assertSessionMissing('_old_input');
        $this->post($this->url(), ['contexto' => 'invalido', ...$this->datos()])->assertRedirect($this->lectura());
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_identidad_y_autoria_se_obtienen_del_turno_y_de_la_sesion(): void
    {
        $this->post($this->url(), [
            'contexto' => $this->formulario(), ...$this->datos(), 'paciente_id' => 99999, 'autor_user_id' => 99999,
            'kinesiologa_id' => 99999, 'activa' => false, 'numero_version' => 99,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->paciente->id, HistoriaClinica::sole()->paciente_id);
        $this->assertSame($this->profesional->user_id, VersionDatosClinicosBase::sole()->autor_user_id);
        $this->assertSame(1, VersionDatosClinicosBase::sole()->numero_version);
    }

    #[DataProvider('especialidadesInvalidas')]
    public function test_especialidad_debe_estar_activa_y_asociada(string $caso): void
    {
        $contexto = $this->formulario();
        $datos = $this->datos();
        if ($caso === 'inactiva') {
            EspecialidadKinesiologia::whereKey($datos['especialidad_id'])->update(['activo' => false]);
        } else {
            $datos['especialidad_id'] = EspecialidadKinesiologia::create(['nombre' => 'Ajena', 'slug' => 'ajena', 'activo' => true])->id;
        }
        $this->post($this->url(), ['contexto' => $contexto, ...$datos])->assertSessionHasErrors('especialidad_id');
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public static function especialidadesInvalidas(): array
    {
        return [['inactiva'], ['ajena']];
    }

    #[DataProvider('declaraciones')]
    public function test_declaracion_aparecida_antes_de_guardar_impide_apertura_y_se_conserva(string $estado, string $recorrido): void
    {
        $contexto = $this->formulario();
        $declaracion = $this->declaracion($estado);
        $antes = $declaracion->fresh()->getRawOriginal();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertRedirect($this->lectura());
        $this->get($this->lectura())->assertOk()->assertViewHas('recorrido', $recorrido);
        $this->get($this->url())->assertRedirect($this->lectura());
        $this->assertSame($antes, $declaracion->fresh()->getRawOriginal());
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public static function declaraciones(): array
    {
        return [
            [DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION, 'revision_c'],
            [DeclaracionSaludPaciente::ESTADO_REVISADA, 'declaracion_revisada_sin_historia'],
        ];
    }

    #[DataProvider('historiasExistentes')]
    public function test_historia_aparecida_no_se_duplica_reactiva_ni_completa(bool $activa): void
    {
        $contexto = $this->formulario();
        $historia = HistoriaClinica::create(['paciente_id' => $this->paciente->id, 'nro_historia_clinica' => 'HC-PREVIA', 'activa' => $activa]);
        $antes = $historia->fresh()->getRawOriginal();
        $this->post($this->url(), ['contexto' => $contexto, ...$this->datos()])->assertRedirect($this->lectura());
        $this->get($this->url())->assertRedirect($this->lectura());
        $this->assertDatabaseCount('historias_clinicas', 1);
        $this->assertDatabaseCount('versiones_datos_clinicos_base', 0);
        $this->assertSame($antes, $historia->fresh()->getRawOriginal());
    }

    public static function historiasExistentes(): array
    {
        return [[true], [false]];
    }

    public function test_doble_envio_recupera_la_misma_version_y_no_acepta_reescribirla(): void
    {
        $contexto = $this->formulario();
        $datos = $this->datos();
        $this->post($this->url(), ['contexto' => $contexto, ...$datos])->assertSessionHasNoErrors();
        $antes = $this->persistido();
        $this->post($this->url(), ['contexto' => $contexto, ...$datos])->assertSessionHasNoErrors();
        $this->assertSame($antes, $this->persistido());
        $datos['objetivo_salud'] = 'No debe reemplazar la versión';
        $this->post($this->url(), ['contexto' => $contexto, ...$datos])->assertRedirect($this->lectura());
        $this->assertSame($antes, $this->persistido());
        $this->assertDatabaseCount('historias_clinicas', 1);
        $this->assertDatabaseCount('versiones_datos_clinicos_base', 1);
    }

    public function test_dos_formularios_distintos_no_abren_dos_expedientes(): void
    {
        $primero = $this->formulario();
        $segundo = $this->formulario();
        $this->post($this->url(), ['contexto' => $primero, ...$this->datos()])->assertSessionHasNoErrors();
        $antes = $this->persistido();
        $this->post($this->url(), ['contexto' => $segundo, ...$this->datos()])->assertRedirect($this->lectura());
        $this->assertSame($antes, $this->persistido());
    }

    public function test_fallo_despues_de_escribir_revierte_cabecera_version_items_familia_y_auditoria(): void
    {
        $datos = $this->datos();
        $datos['bloques']['alergias'] = ['resultado' => 'INFORMADO', 'items' => [[
            'descripcion' => 'Alergia referida', 'procedencia' => 'REFERIDO_PACIENTE', 'estado_codificacion' => 'PENDIENTE_CODIFICACION',
        ]]];
        $datos['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [['vinculo' => 'Hermano']]];
        $contexto = $this->formulario();
        $antes = $this->persistido();
        $evento = 'eloquent.created: '.Auditoria::class;
        Event::listen($evento, function (Auditoria $auditoria): void {
            if ($auditoria->evento === 'historia_clinica_abierta') {
                $this->assertDatabaseCount('historias_clinicas', 1);
                $this->assertDatabaseCount('versiones_datos_clinicos_base', 1);
                $this->assertDatabaseCount('items_datos_clinicos_base', 1);
                $this->assertDatabaseCount('integrantes_nucleo_familiar_hc', 1);
                throw new RuntimeException('Fallo inyectado tras escrituras');
            }
        });
        try {
            app(AbrirHistoriaClinicaAction::class)->ejecutar($this->profesional->user, $this->turno->id, $contexto, $datos);
            $this->fail('Se esperaba el fallo de guardado.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo inyectado tras escrituras', $e->getMessage());
        } finally {
            Event::forget($evento);
        }
        $this->assertSame($antes, $this->persistido());
        $this->post($this->url(), ['contexto' => $contexto, ...$datos])->assertSessionHasNoErrors();
        $this->assertTrue(HistoriaClinica::sole()->activa);
    }

    public function test_las_versiones_profesionales_no_se_editan_en_lugar(): void
    {
        $this->post($this->url(), ['contexto' => $this->formulario(), ...$this->datos()])->assertSessionHasNoErrors();
        $this->expectException(LogicException::class);
        VersionDatosClinicosBase::sole()->update(['objetivo_salud' => 'Sobrescritura']);
    }

    private function datos(): array
    {
        $bloques = [];
        foreach (ValidarDatosAperturaHistoriaClinica::TIPOS as $tipo => $nombre) {
            $bloques[$tipo] = ['resultado' => 'NO_PRESENTA', 'items' => []];
        }

        return [
            'especialidad_id' => $this->profesional->especialidades()->first()->id,
            'procedencia_relevamiento' => 'REFERIDO_PACIENTE', 'confirmar' => '1', 'bloques' => $bloques,
            'familia' => ['resultado' => 'SIN_INTEGRANTES', 'integrantes' => []],
        ];
    }

    private function formulario(): string
    {
        $respuesta = $this->get($this->url())->assertOk()->assertSee('Mariano Warner');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));

        return $respuesta->viewData('contexto');
    }

    private function url(): string
    {
        return route('historia-clinica.apertura.create', $this->turno->id);
    }

    private function lectura(): string
    {
        return route('historia-clinica.desde-turno', $this->turno->id);
    }

    private function persistido(): array
    {
        $datos = [];
        foreach (['turnos', 'historias_clinicas', 'versiones_datos_clinicos_base', 'items_datos_clinicos_base', 'integrantes_nucleo_familiar_hc', 'declaraciones_salud_paciente', 'auditorias'] as $tabla) {
            $datos[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn ($fila) => (array) $fila)->all();
        }

        return $datos;
    }

    private function declaracion(string $estado): DeclaracionSaludPaciente
    {
        return DeclaracionSaludPaciente::create([
            'paciente_id' => $this->paciente->id, 'objetivo_salud' => 'Declaración original', 'peso_kg' => 70,
            'altura_cm' => 170, 'ocupacion' => 'Docente', 'nivel_actividad_fisica' => 'MODERADO',
            'detalle_actividad_fisica' => 'Caminatas', 'estado' => $estado,
        ]);
    }
}
