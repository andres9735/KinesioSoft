<?php

namespace Tests\Integration\AgendaDiariaMariaDb639;

use App\Actions\Turnos\CancelarTurnoAction;
use App\Actions\Turnos\ConfirmarAsistenciaTurnoAction;
use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Exceptions\Turnos\EntregaEnlaceNoDisponibleException;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntentoRecordatorioTurno;
use App\Models\JornadaGeneracionAgenda;
use App\Models\SobreEntregaEnlace;
use App\Services\Agenda\GenerarAgendaDiariaService;
use App\Services\Turnos\DespacharRecordatorioTurnoService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\GestionarReintentosRecordatorioTurnoService;
use App\Services\Turnos\RegistrarRecordatorioTurnoService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Caso639;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Entorno639;
use Tests\Integration\AgendaDiariaMariaDb639\Support\Guardas639;

/** Preparado para el motor real; no ejecutado durante la preparación del harness. */
class ContratosMotorTest extends Caso639
{
    public function test_instalacion_innodb_claves_triggers_y_sin_referencias_a_otras_bases(): void
    {
        $c = DB::connection();
        $tablas = $c->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
        $nombres = array_column($tablas, 'TABLE_NAME');
        foreach (['turnos', 'revisiones_enlace_asistencia_turno', 'enlaces_asistencia_turno', 'sobres_entrega_enlace',
            'recordatorios_turno', 'intentos_recordatorio_turno', 'ejecuciones_agenda_diaria', 'preparaciones_agenda_diaria', 'jornadas_generacion_agenda'] as $tabla) {
            $this->assertContains($tabla, $nombres);
        }
        foreach ($tablas as $tabla) {
            $this->assertSame('InnoDB', $tabla->ENGINE);
        }
        $this->assertSame([], $c->select('SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_SCHEMA<>DATABASE()'));
        $triggers = array_column($c->select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()'), 'TRIGGER_NAME');
        foreach (['revision_enlace_no_retrocede', 'revision_enlace_no_eliminar', 'turnos_enlace_version_crear', 'turnos_enlace_version_actualizar'] as $trigger) {
            $this->assertContains($trigger, $triggers);
        }
        foreach (['enlaces_asistencia_turno' => 'enlace_asistencia_turno_version_unique',
            'recordatorios_turno' => 'recordatorio_turno_version_unique', 'intentos_recordatorio_turno' => 'intento_recordatorio_numero_unique',
            'jornadas_generacion_agenda' => 'jornada_agenda_fecha_zona_unique', 'preparaciones_agenda_diaria' => 'prep_agenda_ejec_prof_unique'] as $tabla => $indice) {
            $this->assertTrue($c->table('information_schema.STATISTICS')->where('TABLE_SCHEMA', Guardas639::DATABASE)
                ->where('TABLE_NAME', $tabla)->where('INDEX_NAME', $indice)->where('NON_UNIQUE', 0)->exists());
        }
        $aux = Entorno639::independiente('a639_observador');
        $this->assertNotSame(Entorno639::verificar($c)['sesion'], Entorno639::verificar($aux)['sesion']);
    }

    public function test_triggers_directos_y_masivos_impiden_aba_y_manipulacion_de_revision(): void
    {
        $a = $this->turno();
        $b = $this->turno();
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($a->id);
        $revision = EnlaceAsistenciaTurno::versionActualDe($a);
        DB::table('turnos')->whereIn('id', [$a->id, $b->id])->update(['inicio_at' => CarbonImmutable::now()->addHours(16)]);
        DB::table('turnos')->where('id', $a->id)->update(['inicio_at' => $a->getRawOriginal('inicio_at')]);
        $this->assertSame($revision + 2, EnlaceAsistenciaTurno::versionActualDe($a->fresh()));
        $this->assertSame(2, EnlaceAsistenciaTurno::versionActualDe($b->fresh()));
        $this->assertFalse(EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId)->vigentePara($a->fresh(), CarbonImmutable::now()));
        foreach (['update revisiones_enlace_asistencia_turno set version_programacion = 1 where turno_id = ?',
            'delete from revisiones_enlace_asistencia_turno where turno_id = ?',
            'update revisiones_enlace_asistencia_turno set turno_id = turno_id + 999999 where turno_id = ?'] as $sql) {
            try {
                DB::statement($sql, [$a->id]);
                $this->fail('La revisión histórica debe estar protegida.');
            } catch (QueryException $e) {
                $this->assertSame('45000', $e->errorInfo[0]);
            }
        }
    }

    public function test_unicidad_y_fk_reales_del_recordatorio_y_enlace(): void
    {
        $t = $this->turno();
        $r = app(RegistrarRecordatorioTurnoService::class)->registrar($t->id);
        $this->assertSame($r->id, app(RegistrarRecordatorioTurnoService::class)->registrar($t->id)->id);
        $datos = $r->getAttributes();
        unset($datos['id']);
        $datos['identidad'] = (string) Str::uuid();
        try {
            DB::table('recordatorios_turno')->insert($datos);
            $this->fail('Debe impedir dos recordatorios lógicos.');
        } catch (QueryException $e) {
            $this->assertSame(1062, (int) $e->errorInfo[1]);
        }
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($t->id);
        $datos = EnlaceAsistenciaTurno::findOrFail($emitido->enlaceId)->getAttributes();
        unset($datos['id']);
        $datos['turno_id'] = PHP_INT_MAX;
        $datos['token_hash'] = hash('sha256', random_bytes(32));
        $datos['ciclo_emision'] = (string) Str::uuid();
        try {
            DB::table('enlaces_asistencia_turno')->insert($datos);
            $this->fail('Debe impedir un enlace huérfano.');
        } catch (QueryException $e) {
            $this->assertSame(1452, (int) $e->errorInfo[1]);
        }
    }

    public function test_uc06_sin_mutaciones_ajenas_y_rollback_atomico(): void
    {
        $t = $this->turno();
        app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($t->id);
        $antes = $t->getAttributes();
        $version = EnlaceAsistenciaTurno::versionActualDe($t);
        app(ConfirmarAsistenciaTurnoAction::class)->ejecutar($t->paciente->user, $t);
        $this->assertSame($version, EnlaceAsistenciaTurno::versionActualDe($t->fresh()));
        $fallar = true;
        Auditoria::created(function ($a) use (&$fallar): void {
            if ($fallar && $a->evento === 'turno_cancelado') {
                $fallar = false;
                throw new RuntimeException('Fallo sintético de auditoría.');
            }
        });
        try {
            app(CancelarTurnoAction::class)->ejecutar($t->paciente->user, $t);
            $this->fail('La cancelación debe revertirse.');
        } catch (RuntimeException) {
            $this->assertFalse($fallar);
        }
        $this->assertSame('CONFIRMADO', $t->fresh()->estado->value);
        $this->assertSame($version, EnlaceAsistenciaTurno::versionActualDe($t->fresh()));
        $this->assertSame(0, DB::table('intenciones_evaluacion_cupo')->where('turno_id', $t->id)->count());
        app(CancelarTurnoAction::class)->ejecutar($t->paciente->user, $t);
        $replay = app(CancelarTurnoAction::class)->ejecutar($t->paciente->user, $t);
        $this->assertTrue($replay->esReplay);
        $actual = $t->fresh()->getAttributes();
        unset($antes['estado'], $antes['updated_at'], $actual['estado'], $actual['updated_at']);
        $this->assertSame($antes, $actual);
        $this->assertSame(1, DB::table('intenciones_evaluacion_cupo')->where('turno_id', $t->id)->count());
        $this->assertSame(1, Auditoria::where('auditable_id', $t->id)->where('evento', 'turno_cancelado')->count());
    }

    public function test_for_update_retiene_mutex_entre_procesos_y_timeout_no_confirma(): void
    {
        $t = $this->turno();
        $this->iniciar('uno', 'cancelar', ['turno' => $t->id, 'pausar' => true]);
        $this->barrera->esperar('uno-protegido');
        $this->iniciar('dos', 'confirmar', ['turno' => $t->id, 'timeout' => 1]);
        $dos = $this->resultado('dos');
        $this->assertFalse($dos['ok']);
        $this->assertContains(1205, $dos['codigos']);
        $this->barrera->marcar('uno-continuar');
        $this->assertTrue($this->resultado('uno')['ok']);
        $this->assertSame('CANCELADO', $t->fresh()->estado->value);
        $this->assertSame(0, Auditoria::where('auditable_id', $t->id)->where('evento', 'turno_asistencia_confirmada')->count());
    }

    public function test_uc29_jornada_concurrente_idempotente_y_worker_antiguo_sin_titularidad(): void
    {
        $t = $this->turno(1);
        $fecha = $t->inicio_at->toDateString();
        $this->iniciar('uno', 'generar', ['fecha' => $fecha, 'pausar_lectura' => true]);
        $this->barrera->esperar('uno-leyendo');
        $control = JornadaGeneracionAgenda::where('fecha_local', $fecha)->sole();
        $anterior = $control->ejecucion_actual_id;
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(301));
        $this->iniciar('dos', 'generar', ['fecha' => $fecha]);
        $nuevo = $this->resultado('dos');
        $this->assertTrue($nuevo['ok']);
        $this->assertNotSame($anterior, (int) $nuevo['valor']);
        $this->barrera->marcar('uno-continuar');
        $this->assertFalse($this->resultado('uno')['ok']);
        $this->assertSame((int) $nuevo['valor'], (int) $control->fresh()->ejecucion_actual_id);
        $this->assertSame((int) $nuevo['valor'], (int) app(GenerarAgendaDiariaService::class)->ejecutar($t->inicio_at)->id);
        $this->assertSame('PENDIENTE', $t->fresh()->estado->value);
    }

    public function test_emision_concurrente_unica_y_sobre_cifrado_sin_credenciales_en_auditoria(): void
    {
        $t = $this->turno();
        foreach (['uno', 'dos'] as $nombre) {
            $this->iniciar($nombre, 'emitir', ['turno' => $t->id]);
        }
        $uno = $this->resultado('uno');
        $dos = $this->resultado('dos');
        $this->assertTrue($uno['ok'] && $dos['ok']);
        $this->assertSame($uno['valor'], $dos['valor']);
        $this->assertSame(1, EnlaceAsistenciaTurno::where('turno_id', $t->id)->count());
        $e = EnlaceAsistenciaTurno::where('turno_id', $t->id)->sole();
        $sobre = SobreEntregaEnlace::where('enlace_id', $e->id)->sole();
        $this->assertTrue(is_string($sobre->material_cifrado));
        $datos = json_decode(Crypt::decryptString($sobre->material_cifrado), true);
        parse_str((string) parse_url($datos['url'], PHP_URL_QUERY), $query);
        $this->assertTrue($e->coincideConToken($query['token']));
        $persistido = json_encode([$e->getAttributes(), $sobre->getAttributes(), Auditoria::where('auditable_id', $e->id)->get()->toArray()]);
        $this->assertFalse(str_contains($persistido, $query['token']));
        $this->assertFalse(str_contains($persistido, $datos['url']));
    }

    public function test_fallo_cifrado_revierte_emision_sobre_y_auditoria(): void
    {
        $t = $this->turno();
        $audits = Auditoria::count();
        Crypt::shouldReceive('encryptString')->once()->andThrow(new RuntimeException('Fallo sintético'));
        try {
            app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($t->id);
            $this->fail('Debe revertir la emisión.');
        } catch (EntregaEnlaceNoDisponibleException $e) {
            $this->assertNull($e->getPrevious());
        }
        $this->assertSame(0, EnlaceAsistenciaTurno::where('turno_id', $t->id)->count());
        $this->assertSame($audits, Auditoria::count());
        $this->assertSame('PENDIENTE', $t->fresh()->estado->value);
    }

    public function test_recordatorio_e_intento_concurrentes_sin_doble_emision(): void
    {
        $t = $this->turno();
        $this->iniciar('uno', 'registrar', ['turno' => $t->id]);
        $this->iniciar('dos', 'registrar', ['turno' => $t->id]);
        $uno = $this->resultado('uno');
        $dos = $this->resultado('dos');
        $this->assertTrue($uno['ok'] && $dos['ok']);
        $this->assertSame($uno['valor'], $dos['valor']);
        $id = (int) $uno['valor'];
        $identidad = (string) Str::uuid();
        foreach (['tres', 'cuatro'] as $nombre) {
            $this->iniciar($nombre, 'despachar', ['recordatorio' => $id, 'identidad' => $identidad]);
        }
        $tres = $this->resultado('tres');
        $cuatro = $this->resultado('cuatro');
        $this->assertTrue($tres['ok'] || $cuatro['ok']);
        $this->assertSame(1, $tres['transportes'] + $cuatro['transportes']);
        $this->assertSame(1, IntentoRecordatorioTurno::where('recordatorio_id', $id)->count());
        $this->assertSame(Resultado::ACEPTADO, IntentoRecordatorioTurno::where('recordatorio_id', $id)->sole()->resultado);
        $this->assertSame(1, EnlaceAsistenciaTurno::where('turno_id', $t->id)->count());
    }

    public function test_recuperacion_backoff_revocacion_e_incertidumbre_sin_reenvio(): void
    {
        $t = $this->turno();
        $id = $this->recordatorio($t);
        self::transporteSimulado($this->app, Resultado::FALLO_RECUPERABLE);
        app(DespacharRecordatorioTurnoService::class)->despachar($id, (string) Str::uuid());
        $gestion = app(GestionarReintentosRecordatorioTurnoService::class);
        $this->assertNull($gestion->revisar($id));
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
        $plan = $gestion->revisar($id);
        $this->assertSame(2, $plan['numero']);
        $this->assertSame($plan, $gestion->revisar($id));
        $e = EnlaceAsistenciaTurno::where('turno_id', $t->id)->sole();
        $e->forceFill(['revocado_at' => CarbonImmutable::now()])->saveOrFail();
        $this->assertNull($gestion->revisar($id));
        $this->assertSame(1, IntentoRecordatorioTurno::where('recordatorio_id', $id)->count());
        $otro = $this->turno();
        $otroId = $this->recordatorio($otro);
        self::transporteSimulado($this->app, Resultado::INCIERTO);
        $identidad = (string) Str::uuid();
        app()->forgetInstance(DespacharRecordatorioTurnoService::class);
        $servicio = app(DespacharRecordatorioTurnoService::class);
        $this->assertSame(Resultado::INCIERTO, $servicio->despachar($otroId, $identidad));
        $this->assertSame(Resultado::INCIERTO, $servicio->despachar($otroId, $identidad));
        $this->assertNull($gestion->revisar($otroId));
        $this->assertSame(1, IntentoRecordatorioTurno::where('recordatorio_id', $otroId)->count());
        $this->assertNull(IntentoRecordatorioTurno::where('recordatorio_id', $otroId)->sole()->evidencia_entrega);
        $this->assertSame('revocado', app(ValidarEnlaceAsistenciaTurnoService::class)->validarReferencia($e)->estado);
    }

    public function test_reintentos_limitados_y_ventana_no_reiniciable(): void
    {
        $t = $this->turno();
        $id = $this->recordatorio($t);
        self::transporteSimulado($this->app, Resultado::FALLO_RECUPERABLE);
        $gestion = app(GestionarReintentosRecordatorioTurnoService::class);
        $primera = CarbonImmutable::now();
        for ($numero = 1; $numero <= 4; $numero++) {
            $plan = $gestion->revisar($id);
            $this->assertSame($numero, $plan['numero']);
            $this->assertSame(Resultado::FALLO_RECUPERABLE, app(DespacharRecordatorioTurnoService::class)
                ->despachar($id, $plan['identidad'], $numero));
            if ($numero < 4) {
                $this->assertNull($gestion->revisar($id));
                CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds([300, 600, 1200][$numero - 1]));
            }
        }
        $this->assertNull($gestion->revisar($id));
        $this->assertSame(4, IntentoRecordatorioTurno::where('recordatorio_id', $id)->count());
        $otro = $this->turno();
        $otroId = $this->recordatorio($otro);
        app(DespacharRecordatorioTurnoService::class)->despachar($otroId, (string) Str::uuid());
        $fallo = CarbonImmutable::now();
        CarbonImmutable::setTestNow($fallo->addHour()->addMicrosecond());
        $this->assertNull($gestion->revisar($otroId));
        $this->assertSame(1, IntentoRecordatorioTurno::where('recordatorio_id', $otroId)->count());
        $this->assertTrue($primera->lt($fallo));
        $this->assertSame(1, EnlaceAsistenciaTurno::where('turno_id', $t->id)->count());
    }
}
