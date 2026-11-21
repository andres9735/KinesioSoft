<?php

namespace Tests\Feature\HistoriaClinica;

use App\Actions\HistoriaClinica\ConfirmarRevisionDeclaracionAction;
use App\Models\Auditoria;
use App\Models\ConfirmacionRevisionDeclaracion;
use App\Models\DeclaracionSaludPaciente;
use App\Models\HistoriaClinica;
use App\Models\IncorporacionDeclaracionSalud;
use App\Models\ItemDatosClinicosBase;
use App\Models\Kinesiologa;
use App\Models\Paciente;
use App\Models\RevisionDeclaracionSalud;
use App\Models\Turno;
use App\Models\VersionDatosClinicosBase;
use App\Policies\TurnoPolicy;
use App\Services\HistoriaClinica\ConsultarRevisionDeclaracion;
use App\Services\HistoriaClinica\ValidarDatosAperturaHistoriaClinica as Minimo;
use Database\Seeders\PermisoHistoriaClinicaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class ConfirmarRevisionDeclaracionTest extends TestCase
{
    use CreaPerfilesUc07;
    use RefreshDatabase;

    private Kinesiologa $profesional;

    private Paciente $paciente;

    private Turno $turno;

    private DeclaracionSaludPaciente $declaracion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Queue::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermisoHistoriaClinicaSeeder::class);
        $this->profesional = $this->profesionalUc07();
        $this->profesional->user->givePermissionTo([TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_REVISAR_DECLARACION]);
        $this->paciente = $this->pacienteUc07();
        $this->declaracion = $this->paciente->declaracionesSalud()->sole();
        DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update(['altura_cm' => '170.25']);
        foreach (['ANTECEDENTE' => 'Antecedente personal original', 'CIRUGIA' => 'Cirugía original', 'ALERGIA' => 'Alergia original', 'MEDICACION' => 'Medicación original'] as $tipo => $descripcion) {
            $this->declaracion->items()->create(compact('tipo', 'descripcion'));
        }
        $this->declaracion->items()->create(['tipo' => 'ANTECEDENTE', 'descripcion' => 'Antecedente familiar original']);
        $this->turno = Turno::factory()->for($this->profesional)->for($this->paciente)->create();
        $this->actingAs($this->profesional->user);
    }

    #[DataProvider('recorridos')]
    public function test_confirma_los_tres_recorridos_con_autoria_versionado_trazabilidad_y_estado_del_turno(string $caso): void
    {
        $base = $caso === 'version' ? $this->base() : null;
        $historia = $caso === 'cabecera' ? $this->historia() : $base?->historiaClinica;
        $cabeceraAntes = $historia?->getRawOriginal();
        $originalAntes = $this->declaracion->fresh()->getRawOriginal();
        $itemsAntes = $this->filas('items_declaracion_salud');
        $turnoAntes = $this->turno->fresh()->getRawOriginal();
        $baseAntes = $base?->getRawOriginal();
        $auditoriasAntes = Auditoria::count();
        $entrada = $this->entrada();
        if ($base) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $previa = $this->previa($entrada);
        $comprobante = $previa->viewData('comprobante');
        $this->assertStringNotContainsString('Antecedente personal original', $comprobante);
        $respuesta = $this->post($this->url(), $this->postData($comprobante))->assertOk()->assertSee('Revisión confirmada e incorporada');
        $version = VersionDatosClinicosBase::orderByDesc('numero_version')->firstOrFail();
        $confirmacion = ConfirmacionRevisionDeclaracion::sole();
        $this->assertSame($base ? 2 : 1, $version->numero_version);
        $this->assertSame('REVISION_DECLARACION', $version->origen);
        $this->assertSame('170.25', $version->altura_cm);
        $this->assertNull($version->clave_apertura);
        $this->assertSame($this->profesional->user_id, $confirmacion->autor_user_id);
        $this->assertSame($this->profesional->id, $confirmacion->kinesiologa_id);
        $this->assertSame($this->profesional->especialidades()->first()->id, $confirmacion->especialidad_id);
        $this->assertSame($this->turno->id, $confirmacion->turno_origen_id);
        $this->assertSame(ConfirmacionRevisionDeclaracion::ACCION, $confirmacion->accion);
        $this->assertSame($confirmacion->huella_resultado, $version->huella_solicitud);
        $this->assertSame($previa->viewData('resultado')->huellaResultado, $confirmacion->huella_resultado);
        $this->assertSame($base?->id, $confirmacion->version_base_id);
        $this->assertTrue($version->historiaClinica->activa);
        $this->assertSame($this->paciente->id, $version->historiaClinica->paciente_id);
        $this->assertSame($version->id, $this->declaracion->incorporacion->version->id);
        $this->assertSame($confirmacion->id, $version->incorporacion->confirmacion_revision_id);
        $this->assertDatabaseCount('revisiones_declaracion_salud', 10);
        foreach (Minimo::TIPOS as $tipo => $nombre) {
            $item = $version->items->firstWhere('tipo', $tipo);
            $this->assertNotNull($item);
            $this->assertSame('PENDIENTE_CODIFICACION', $item->estado_codificacion);
            if ($tipo !== 'personales' || ! $base) {
                $this->assertSame('REFERIDO_PACIENTE', $item->procedencia);
            }
        }
        if ($historia) {
            $this->assertSame($cabeceraAntes, $historia->fresh()->getRawOriginal());
            $this->assertSame($historia->id, $version->historia_clinica_id);
        }
        if ($base) {
            $this->assertSame($baseAntes, $base->fresh()->getRawOriginal());
            $this->assertSame('Anamnesis profesional anterior', $version->anamnesis_fisica);
            $this->assertTrue($version->integrantes->sole()->contacto_emergencia_confirmado);
            $this->assertSame('CONTACTO_EMERGENCIA_CONFIRMADO', $version->integrantes->sole()->procedencia);
        }
        $originalDespues = $this->declaracion->fresh()->getRawOriginal();
        $this->assertSame(DeclaracionSaludPaciente::ESTADO_REVISADA, $originalDespues['estado']);
        unset($originalAntes['estado'], $originalDespues['estado']);
        $this->assertSame($originalAntes, $originalDespues);
        $this->assertSame($itemsAntes, $this->filas('items_declaracion_salud'));
        $this->assertSame($turnoAntes, $this->turno->fresh()->getRawOriginal());
        $this->assertSame($auditoriasAntes + ($historia ? 1 : 2), Auditoria::count());
        $this->assertStringNotContainsString('Antecedente personal original', json_encode($this->filas('auditorias')));
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()->assertSee('Revisión de declaración de salud')
            ->assertViewHas('datosBaseDisponibles', true)->assertViewHas('declaracionPendiente', false);
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertNull($this->app['session']->getOldInput('confirmacion'));
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public static function recorridos(): array
    {
        return [['sin historia'], ['cabecera'], ['version']];
    }

    public function test_reintento_identico_es_sin_escrituras_y_otro_comprobante_se_rechaza(): void
    {
        $primero = $this->previa()->viewData('comprobante');
        $otro = $this->previa()->viewData('comprobante');
        $this->post($this->url(), $this->postData($primero))->assertOk();
        $antes = $this->snapshot();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->post($this->url(), $this->postData($primero))->assertOk()->assertSee('Esta confirmación ya fue procesada');
        $this->post($this->url(), $this->postData($otro))->assertStatus(409)->assertSee('Propuesta incompatible');
        $this->assertSame([], $writes);
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_corrige_observa_y_descarta_conservando_originales_justificaciones_y_contacto_explicito(): void
    {
        $entrada = $this->entrada();
        $entrada['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.75']];
        $entrada['dictamenes']['alergias'] = ['dictamen' => 'OBSERVAR', 'justificacion' => 'Requiere aclaración clínica'];
        $entrada['dictamenes']['ocupacion'] = ['dictamen' => 'DESCARTAR', 'justificacion' => 'Dato no admitido'];
        $entrada['complemento_profesional']['bloques']['alergias'] = ['resultado' => 'DESCONOCIDO', 'items' => []];
        $entrada['complemento_profesional']['familia'] = ['resultado' => 'INFORMADO', 'integrantes' => [['vinculo' => 'Madre']],
            'incluir_contacto' => true, 'vinculo_contacto' => 'Hermana'];
        $previa = $this->previa($entrada);
        $this->post($this->url(), $this->postData($previa->viewData('comprobante')))->assertOk();
        $version = VersionDatosClinicosBase::sole();
        $this->assertSame('171.75', $version->altura_cm);
        $this->assertNull($version->ocupacion);
        $this->assertSame('DESCONOCIDO', $version->relevamientos['alergias']);
        $this->assertSame(0, $version->items->where('tipo', 'alergias')->count());
        $this->assertSame('170.25', $this->declaracion->fresh()->altura_cm);
        $this->assertDatabaseHas('revisiones_declaracion_salud', ['bloque' => 'alergias', 'dictamen' => 'OBSERVAR', 'observacion_profesional' => 'Requiere aclaración clínica']);
        $revision = RevisionDeclaracionSalud::where('bloque', 'altura_cm')->sole();
        $this->assertSame(['valor' => '171.75'], $revision->correccion_profesional);
        $this->assertSame('170.25', (string) $revision->datos_originales['valor']);
        $this->assertSame('CORRECCION_PROFESIONAL', $revision->procedencias['origen']);
        $this->assertSame('REFERIDO_PACIENTE', $version->items->first()->procedencia);
        $this->assertDatabaseHas('integrantes_nucleo_familiar_hc', ['nombre_apellido' => null, 'vinculo' => 'Madre', 'contacto_emergencia_confirmado' => false]);
        $this->assertDatabaseHas('integrantes_nucleo_familiar_hc', ['nombre_apellido' => 'Contacto', 'vinculo' => 'Hermana',
            'procedencia' => 'CONTACTO_EMERGENCIA_CONFIRMADO', 'contacto_emergencia_confirmado' => true]);
        $nucleo = collect($previa->viewData('secciones'))->firstWhere('titulo', 'Núcleo familiar');
        $this->assertStringContainsString('Contacto de emergencia confirmado', implode(' ', $nucleo['despues']));
    }

    public function test_sustituciones_solo_cambian_la_nueva_version_y_la_previa_coincide_con_el_guardado(): void
    {
        $base = $this->base();
        $antes = $base->items->map->getAttributes()->all();
        $entrada = $this->entrada();
        unset($entrada['complemento_profesional']['familia']);
        $entrada['sustituciones_base'] = ['personales'];
        $previa = $this->previa($entrada);
        $propuesto = $previa->viewData('resultado')->datosClinicos;
        $this->post($this->url(), $this->postData($previa->viewData('comprobante')))->assertOk();
        $nueva = VersionDatosClinicosBase::orderByDesc('numero_version')->first();
        $this->assertSame($antes, $base->fresh()->items->map->getAttributes()->all());
        $this->assertSame(0, $nueva->items->where('descripcion', 'Antecedente previo')->count());
        foreach ($propuesto['bloques'] as $tipo => $bloque) {
            $this->assertSame($bloque['resultado'], $nueva->relevamientos[$tipo]);
            $this->assertSame($bloque['items'], $nueva->items->where('tipo', $tipo)->map(function ($i) {
                $item = $i->only(['descripcion', 'procedencia', 'estado_codificacion']);
                ksort($item);

                return $item;
            })->values()->all());
        }
    }

    #[DataProvider('cambios')]
    public function test_cambios_despues_de_la_previa_impiden_confirmar_sin_escrituras(string $caso): void
    {
        $base = $caso === 'version' || $caso === 'item_base' ? $this->base() : null;
        $entrada = $this->entrada();
        if ($base) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $comprobante = $this->previa($entrada)->viewData('comprobante');
        match ($caso) {
            'declaracion' => DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update(['altura_cm' => 190]),
            'item_original' => DB::table('items_declaracion_salud')->where('declaracion_salud_id', $this->declaracion->id)->update(['descripcion' => 'Modificación externa']),
            'tipo_original' => DB::table('items_declaracion_salud')->where('declaracion_salud_id', $this->declaracion->id)->update(['tipo' => 'OTRO']),
            'revisada' => DB::table('declaraciones_salud_paciente')->where('id', $this->declaracion->id)->update(['estado' => 'REVISADA']),
            'cabecera' => $this->historia(),
            'inactiva' => $this->historia(false),
            'version' => $base->replicate()->forceFill(['numero_version' => 2, 'clave_apertura' => null, 'created_at' => now()])->save(),
            'item_base' => DB::table('items_datos_clinicos_base')->where('version_datos_clinicos_base_id', $base->id)->update(['descripcion' => 'Otra versión externa']),
            'contacto' => $this->paciente->update(['contacto_emergencia_nombre' => 'Otra persona']),
            'autor' => $this->profesional->user->update(['name' => 'Otro nombre']),
            'especialidad' => $this->profesional->especialidades()->first()->update(['nombre' => 'Otra especialidad']),
            'turno' => $this->turno->forceFill(['inicio_at' => '2026-11-24 10:00:00', 'fin_at' => '2026-11-24 11:00:00'])->save(),
        };
        $antes = $this->snapshot();
        $this->post($this->url(), $this->postData($comprobante))->assertStatus(409);
        $this->assertSame($antes, $this->snapshot());
        $this->assertDatabaseCount('incorporaciones_declaracion_salud', 0);
    }

    public static function cambios(): array
    {
        return array_map(fn ($c) => [$c], ['declaracion', 'item_original', 'tipo_original', 'revisada', 'cabecera', 'inactiva', 'version', 'item_base', 'contacto', 'autor', 'especialidad', 'turno']);
    }

    #[DataProvider('permisos')]
    public function test_revocar_permisos_impide_confirmar_y_no_muestra_clinica(string $permiso): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $this->profesional->user->revokePermissionTo($permiso);
        $antes = $this->snapshot();
        $this->post($this->url(), $this->postData($comprobante))->assertForbidden()->assertSee('Sin autorización')->assertDontSee('Antecedente personal original');
        $this->assertSame($antes, $this->snapshot());
    }

    public static function permisos(): array
    {
        return [[TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA], [TurnoPolicy::PERMISO_REVISAR_DECLARACION]];
    }

    public function test_asignacion_habilitacion_y_paciente_se_revalidan(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $otra = $this->profesionalUc07();
        $otra->user->givePermissionTo([TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_REVISAR_DECLARACION]);
        $this->actingAs($otra->user)->post($this->url(), $this->postData($comprobante))->assertForbidden();
        $this->actingAs($this->profesional->user);
        $otroPaciente = $this->pacienteUc07();
        $this->turno->forceFill(['paciente_id' => $otroPaciente->id])->save();
        $this->post($this->url(), $this->postData($comprobante))->assertStatus(409);
        $this->turno->forceFill(['paciente_id' => $this->paciente->id, 'kinesiologa_id' => $otra->id])->save();
        $this->post($this->url(), $this->postData($comprobante))->assertForbidden();
        $this->turno->forceFill(['kinesiologa_id' => $this->profesional->id])->save();
        $this->profesional->matriculasProfesionales()->update(['activa' => false]);
        $this->expectException(AuthorizationException::class);
        app(ConfirmarRevisionDeclaracionAction::class)->ejecutar($this->profesional->user, $this->turno->id, $this->declaracion->id, $comprobante);
    }

    public function test_revocacion_dentro_de_la_transaccion_es_detectada_con_actor_precargado(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $actor = $this->profesional->user->load('roles', 'permissions');
        $revocado = false;
        DB::listen(function ($query) use (&$revocado): void {
            if (! $revocado && DB::transactionLevel() > 0 && str_starts_with($query->sql, 'select * from "turnos"')) {
                $revocado = true;
                DB::table('model_has_permissions')->where('model_id', $this->profesional->user_id)->delete();
            }
        });
        try {
            app(ConfirmarRevisionDeclaracionAction::class)->ejecutar($actor, $this->turno->id, $this->declaracion->id, $comprobante);
            $this->fail('Se esperaba el rechazo de la revocación.');
        } catch (AuthorizationException) {
            $this->assertTrue($revocado);
            $this->assertDatabaseCount('historias_clinicas', 0);
        }
    }

    public function test_permisos_por_rol_se_releen_aunque_el_cache_conserve_la_concesion(): void
    {
        $rol = $this->profesional->user->roles->sole();
        $rol->givePermissionTo([TurnoPolicy::PERMISO_CONSULTAR_HISTORIA_CLINICA, TurnoPolicy::PERMISO_REVISAR_DECLARACION]);
        $this->profesional->user->permissions()->detach();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $comprobante = $this->previa()->viewData('comprobante');
        DB::table('role_has_permissions')->where('role_id', $rol->id)->delete();
        $this->post($this->url(), $this->postData($comprobante))->assertForbidden();
        $this->assertDatabaseCount('incorporaciones_declaracion_salud', 0);
    }

    #[DataProvider('puntosFallo')]
    public function test_rollback_en_puntos_intermedios_conserva_todo_el_estado(string $modelo, string $evento, bool $conBase): void
    {
        if ($conBase) {
            $this->base();
        }
        $entrada = $this->entrada();
        if ($conBase) {
            unset($entrada['complemento_profesional']['familia']);
        }
        $comprobante = $this->previa($entrada)->viewData('comprobante');
        $antes = $this->snapshot();
        Event::listen('eloquent.'.$evento.': '.$modelo, fn () => throw new RuntimeException('Fallo de prueba después de escritura'));
        try {
            app(ConfirmarRevisionDeclaracionAction::class)->ejecutar($this->profesional->user, $this->turno->id, $this->declaracion->id, $comprobante);
            $this->fail('Se esperaba rollback.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo de prueba después de escritura', $e->getMessage());
        }
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(DeclaracionSaludPaciente::ESTADO_PENDIENTE_REVISION, $this->declaracion->fresh()->estado);
    }

    public static function puntosFallo(): array
    {
        $casos = [];
        foreach ([VersionDatosClinicosBase::class, ItemDatosClinicosBase::class, ConfirmacionRevisionDeclaracion::class,
            RevisionDeclaracionSalud::class, IncorporacionDeclaracionSalud::class, DeclaracionSaludPaciente::class, Auditoria::class] as $modelo) {
            foreach ([false, true] as $conBase) {
                $casos[] = [$modelo, $modelo === DeclaracionSaludPaciente::class ? 'updated' : 'created', $conBase];
            }
        }

        return $casos;
    }

    public function test_errores_tecnicos_no_exponen_sql_comprobante_o_contenido_y_no_flasha_sesion(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        Event::listen('eloquent.creating: '.VersionDatosClinicosBase::class,
            fn () => throw new QueryException('sqlite', 'sql secreto clínico', [], new RuntimeException('contenido secreto')));
        $this->post($this->url(), $this->postData($comprobante))->assertStatus(503)
            ->assertDontSee('contenido secreto')->assertDontSee('sql secreto clínico')->assertDontSee($comprobante);
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->assertNull($this->app['session']->getOldInput('confirmacion'));
    }

    public function test_fallo_inesperado_se_revierte_y_registra_solo_la_clase_sin_contenido_clinico(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $antes = $this->snapshot();
        Log::spy();
        Event::listen('eloquent.created: '.ConfirmacionRevisionDeclaracion::class,
            fn () => throw new RuntimeException('Descripción clínica que nunca debe llegar al log'));
        $this->post($this->url(), $this->postData($comprobante))->assertStatus(500)
            ->assertDontSee('Descripción clínica que nunca debe llegar al log')->assertDontSee($comprobante);
        $this->assertSame($antes, $this->snapshot());
        Log::shouldHaveReceived('error')->once()->with('Fallo en confirmación UC-14 C.', ['tipo' => RuntimeException::class]);
        $this->assertNull($this->app['session']->getOldInput('confirmacion'));
    }

    public function test_comprobante_autenticado_no_admite_alteraciones_ni_resultados_del_navegador(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $antes = $this->snapshot();
        $this->post($this->url(), ['confirmacion' => $comprobante, 'confirmar' => '1', 'propuesta' => 'Otra propuesta'])->assertStatus(422);
        $this->post($this->url(), ['confirmacion' => $comprobante])->assertStatus(422)->assertSee('Confirmación explícita pendiente');
        $this->post($this->url(), $this->postData(substr($comprobante, 0, -8).'alterado'))->assertStatus(409);
        $otroTurno = Turno::factory()->for($this->profesional)->for($this->paciente)->create();
        $this->post(route('historia-clinica.revision.confirmar', ['turno' => $otroTurno->id, 'declaracion' => $this->declaracion->id]), $this->postData($comprobante))->assertStatus(409);
        DB::table('turnos')->where('id', $otroTurno->id)->delete();
        $this->assertSame($antes, $this->snapshot());
        $this->assertNull($this->app['session']->getOldInput('confirmacion'));
    }

    #[DataProvider('resultadosInsuficientes')]
    public function test_revalida_minimo_y_dictamenes_del_sobre_interno_antes_de_escribir(string $caso): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        // Simula un paquete interno defectuoso, no una capacidad del navegador para falsificarlo.
        $sobre = json_decode(Crypt::decryptString($comprobante), true);
        match ($caso) {
            'familia' => $sobre['entrada']['complemento_profesional']['familia']['resultado'] = 'NO_RELEVADO',
            'nota' => $sobre['entrada']['dictamenes']['alergias'] = ['dictamen' => 'DESCARTAR'],
            'clasificacion' => $sobre['entrada']['dictamenes']['antecedentes']['clasificacion'] = [],
            'precision' => $sobre['entrada']['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.755']],
        };
        $this->post($this->url(), $this->postData(Crypt::encryptString(json_encode($sobre))))->assertStatus(422)
            ->assertSee('Información clínica insuficiente');
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public static function resultadosInsuficientes(): array
    {
        return [['familia'], ['nota'], ['clasificacion'], ['precision']];
    }

    public function test_contenido_valido_distinto_del_mostrado_tambien_se_rechaza(): void
    {
        $sobre = json_decode(Crypt::decryptString($this->previa()->viewData('comprobante')), true);
        $sobre['entrada']['dictamenes']['altura_cm'] = ['dictamen' => 'CORREGIR', 'correccion' => ['valor' => '171.75']];
        $this->post($this->url(), $this->postData(Crypt::encryptString(json_encode($sobre))))->assertStatus(409);
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_replay_revalida_autorizacion_y_no_rehabilita_un_original_modificado(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $this->post($this->url(), $this->postData($comprobante))->assertOk();
        DB::table('items_declaracion_salud')->where('declaracion_salud_id', $this->declaracion->id)->update(['descripcion' => 'Cambio externo']);
        $this->post($this->url(), $this->postData($comprobante))->assertStatus(409);
        $this->profesional->user->revokePermissionTo(TurnoPolicy::PERMISO_REVISAR_DECLARACION);
        $this->post($this->url(), $this->postData($comprobante))->assertForbidden();
        $this->assertDatabaseCount('versiones_datos_clinicos_base', 1);
    }

    public function test_replay_historico_no_sustituye_una_version_posterior(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $this->post($this->url(), $this->postData($comprobante))->assertOk();
        $primera = VersionDatosClinicosBase::sole();
        $posterior = $primera->replicate()->forceFill(['numero_version' => 2, 'clave_apertura' => null, 'created_at' => now()]);
        $posterior->save();
        foreach ($primera->items as $item) {
            $posterior->items()->create($item->only(['tipo', 'descripcion', 'procedencia', 'estado_codificacion']));
        }
        $antes = $this->snapshot();
        $this->post($this->url(), $this->postData($comprobante))->assertOk()->assertSee('Esta confirmación ya fue procesada')
            ->assertSee('El expediente puede contener versiones posteriores')->assertViewHas('version', fn ($v) => $v->id === $primera->id);
        $this->assertSame($antes, $this->snapshot());
        $this->get(route('historia-clinica.desde-turno', $this->turno->id))->assertOk()
            ->assertViewHas('versionBase', fn ($v) => $v->id === $posterior->id);
    }

    public function test_cambio_de_recursos_entre_referencia_y_locks_no_toma_otro_mutex(): void
    {
        $comprobante = $this->previa()->viewData('comprobante');
        $otro = $this->pacienteUc07();
        $cambiado = false;
        DB::listen(function ($query) use (&$cambiado, $otro): void {
            if (! $cambiado && str_starts_with($query->sql, 'select "id", "kinesiologa_id", "paciente_id" from "turnos"')) {
                $cambiado = true;
                DB::table('turnos')->where('id', $this->turno->id)->update(['paciente_id' => $otro->id]);
            }
        });
        $this->post($this->url(), $this->postData($comprobante))->assertStatus(409);
        $this->assertTrue($cambiado);
        $this->assertDatabaseCount('historias_clinicas', 0);
        $this->assertDatabaseCount('confirmaciones_revision_declaracion', 0);
    }

    private function historia(bool $activa = true): HistoriaClinica
    {
        $historia = HistoriaClinica::create(['paciente_id' => $this->paciente->id, 'nro_historia_clinica' => 'HC-HISTORICA-C', 'activa' => $activa]);
        $historia->forceFill(['created_at' => '2001-01-02 10:11:12', 'updated_at' => '2001-01-02 10:11:12'])->save();

        return $historia->fresh();
    }

    private function base(): VersionDatosClinicosBase
    {
        $version = VersionDatosClinicosBase::create(['historia_clinica_id' => $this->historia()->id, 'numero_version' => 1,
            'kinesiologa_id' => $this->profesional->id, 'autor_user_id' => $this->profesional->user_id,
            'especialidad_id' => $this->profesional->especialidades()->first()->id, 'turno_origen_id' => $this->turno->id,
            'autor_nombre_snapshot' => 'Profesional anterior', 'especialidad_nombre_snapshot' => 'Especialidad',
            'origen' => 'CARGA_PROFESIONAL_DIRECTA', 'huella_solicitud' => str_repeat('a', 64), 'altura_cm' => '170.25',
            'procedencia_relevamiento' => 'RELEVAMIENTO_PROFESIONAL', 'relevamientos' => [...array_fill_keys(array_keys(Minimo::TIPOS), 'NO_PRESENTA'), 'personales' => 'INFORMADO'],
            'resultado_nucleo_familiar' => 'INFORMADO', 'anamnesis_fisica' => 'Anamnesis profesional anterior', 'created_at' => now()]);
        $version->items()->create(['tipo' => 'personales', 'descripcion' => 'Antecedente previo', 'procedencia' => 'RELEVAMIENTO_PROFESIONAL', 'estado_codificacion' => 'PENDIENTE_CODIFICACION']);
        $version->integrantes()->create(['nombre_apellido' => 'Familiar previo', 'vinculo' => 'Hermana',
            'procedencia' => 'CONTACTO_EMERGENCIA_CONFIRMADO', 'contacto_emergencia_confirmado' => true]);

        return $version->fresh();
    }

    private function entrada(): array
    {
        $dictamenes = array_fill_keys(array_keys(ConsultarRevisionDeclaracion::ETIQUETAS), ['dictamen' => 'VALIDAR']);
        foreach ($this->declaracion->items()->where('tipo', 'ANTECEDENTE')->orderBy('id')->get() as $indice => $item) {
            $dictamenes['antecedentes']['clasificacion'][$item->id] = $indice === 0 ? 'personales' : 'familiares';
        }

        return ['especialidad_id' => $this->profesional->especialidades()->first()->id,
            'procedencia_relevamiento' => 'RELEVAMIENTO_PROFESIONAL', 'dictamenes' => $dictamenes,
            'complemento_profesional' => ['familia' => ['resultado' => 'INFORMACION_NO_DISPONIBLE', 'integrantes' => [], 'incluir_contacto' => false]]];
    }

    private function previa(?array $entrada = null): TestResponse
    {
        $args = ['turno' => $this->turno->id, 'declaracion' => $this->declaracion->id];
        $contexto = $this->get(route('historia-clinica.revision.create', $args))->assertOk()->viewData('contexto');

        return $this->post(route('historia-clinica.revision.preview', $args), [
            'contexto' => $contexto, 'propuesta' => json_encode($entrada ?? $this->entrada(), JSON_THROW_ON_ERROR),
        ])->assertOk();
    }

    private function url(): string
    {
        return route('historia-clinica.revision.confirmar', ['turno' => $this->turno->id, 'declaracion' => $this->declaracion->id]);
    }

    private function postData(string $comprobante): array
    {
        return ['confirmacion' => $comprobante, 'confirmar' => '1'];
    }

    private function filas(string $tabla): array
    {
        return DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
    }

    private function snapshot(): array
    {
        $datos = [];
        foreach (['historias_clinicas', 'versiones_datos_clinicos_base', 'items_datos_clinicos_base', 'integrantes_nucleo_familiar_hc',
            'declaraciones_salud_paciente', 'items_declaracion_salud', 'confirmaciones_revision_declaracion', 'revisiones_declaracion_salud',
            'incorporaciones_declaracion_salud', 'turnos', 'auditorias'] as $tabla) {
            $datos[$tabla] = $this->filas($tabla);
        }

        return $datos;
    }
}
