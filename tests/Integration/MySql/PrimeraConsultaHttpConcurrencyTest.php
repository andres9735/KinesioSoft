<?php

namespace Tests\Integration\MySql;

use App\Models\Auditoria;
use App\Models\BloqueoAgenda;
use App\Models\DocumentoClinico;
use App\Models\FranjaHorariaAgenda;
use App\Models\Paciente;
use App\Models\Turno;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\MySql\Support\MySqlTestCase;
use Tests\Support\CreaPerfilesUc07;

class PrimeraConsultaHttpConcurrencyTest extends MySqlTestCase
{
    use CreaPerfilesUc07;

    protected function workerPath(): string
    {
        return __DIR__.'/Support/http-worker.php';
    }

    protected function paciente(): Paciente
    {
        return $this->pacienteUc07();
    }

    #[DataProvider('carreras')]
    public function test_http_race_serializes_real_commits_and_keeps_only_winner_multipart(string $caso): void
    {
        $paciente = $this->paciente();
        $profesional = $this->profesional();
        $otroPaciente = $caso === 'mismo_slot' ? $this->paciente() : $paciente;
        $otraProfesional = in_array($caso, ['d01', 'd01_duraciones'], true) ? $this->profesional() : $profesional;
        if ($caso === 'd01_duraciones') {
            $profesional->configuracionesAgenda()->sole()->update(['duracion_turno_minutos' => 45]);
        }
        $uuid = (string) Str::uuid();
        $this->iniciar('uno', $paciente, $profesional, [
            'uuid' => $uuid, 'archivos' => 2, 'pausar' => true, 'pausar_final' => true,
        ]);
        $this->barrera->esperar('uno-antes-insertar');
        $rutas = Storage::disk('documentos_clinicos')->allFiles('originales');
        $this->assertCount(2, $rutas);
        foreach ($rutas as $ruta) {
            $this->assertSame($this->sesiones['uno'], (int) $this->observador
                ->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
        }
        $this->iniciar('dos', $otroPaciente, $otraProfesional, [
            'uuid' => $caso === 'misma_uuid' ? $uuid : (string) Str::uuid(),
            'inicio_at' => $caso === 'd01' ? '2026-10-05 11:00:00' : '2026-10-05 10:00:00',
            'archivos' => 1, 'snapshot' => true,
        ]);
        $this->barrera->esperar('dos-snapshot');
        $this->esperarBloqueo('dos', $this->sesiones['uno']);
        $this->barrera->marcar('uno-continuar');
        $primero = $this->barrera->esperar('uno-finalizada');
        $this->assertTrue($this->procesos['uno']->isRunning());
        foreach ($rutas as $ruta) {
            $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
        }
        $this->barrera->marcar('uno-salir');
        $this->assertSame($primero, $this->resultado('uno'));
        $segundo = $this->resultado('dos');
        $this->assertHttp($primero, 201);
        $this->assertFalse($primero['esReplay']);
        $this->assertHttp($segundo, $caso === 'misma_uuid' ? 200 : 409);
        if ($caso === 'misma_uuid') {
            $this->assertTrue($segundo['esReplay']);
            $this->assertSame($primero['turno_id'], $segundo['turno_id']);
            $this->assertSame($primero['redirect_url'], $segundo['redirect_url']);
        } else {
            $this->assertSame($caso === 'mismo_slot' ? 'slot_no_disponible' : 'primera_consulta_activa', $segundo['code']);
            $this->assertNull($segundo['turno_id']);
            $this->assertNull($segundo['redirect_url']);
        }
        $this->assertNotSame($primero['sesion_http_hash'], $segundo['sesion_http_hash']);
        $this->assertSame(2, $primero['archivos_recibidos']);
        $this->assertSame(1, $segundo['archivos_recibidos']);
        $this->conteos(1, 2);
        $this->assertSame($paciente->id, Turno::sole()->paciente_id);
        $this->assertSame($profesional->id, Turno::sole()->kinesiologa_id);
        $this->assertSame('2026-10-05 10:00:00', Turno::sole()->inicio_at->toDateTimeString());
        $this->assertSame($caso === 'd01_duraciones' ? '2026-10-05 10:45:00' : '2026-10-05 10:30:00', Turno::sole()->fin_at->toDateTimeString());
        $this->assertEqualsCanonicalizing($rutas, Storage::disk('documentos_clinicos')->allFiles('originales'));
        foreach (DocumentoClinico::orderBy('id')->get() as $i => $doc) {
            $contenido = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% uno-".$i."\n%%EOF\n";
            $this->assertSame('uno-'.$i.'.pdf', $doc->nombre_original);
            $this->assertSame('Descripción sintética uno-'.$i.'.', $doc->descripcion);
            $this->assertSame(hash('sha256', $contenido), hash('sha256', Storage::disk('documentos_clinicos')->get($doc->ruta_storage)));
            $this->assertSame(strlen($contenido), $doc->tamanio_bytes);
            $this->assertSame('application/pdf', $doc->mime_type);
            $this->assertSame(Turno::sole()->id, $doc->origenTurno->turno_id);
        }
        $this->auditoriaGanadora($paciente->user_id);
    }

    public static function carreras(): array
    {
        return [
            'dos pacientes mismo slot' => ['mismo_slot'], 'mismo paciente otras profesionales' => ['d01'],
            'mismo inicio otras profesionales con duraciones distintas' => ['d01_duraciones'],
            'misma UUID' => ['misma_uuid'], 'UUID distintas intención incompatible' => ['uuid_distintas'],
        ];
    }

    #[DataProvider('cambiosAgenda')]
    public function test_http_revalidates_committed_agenda_change_after_observed_lock_wait(string $cambio): void
    {
        $paciente = $this->paciente();
        $profesional = $this->profesional();
        DB::beginTransaction();
        DB::table('kinesiologas')->where('id', $profesional->id)->lockForUpdate()->first();
        try {
            $this->iniciar('espera', $paciente, $profesional, ['snapshot' => true, 'archivos' => 1]);
            $this->barrera->esperar('espera-snapshot');
            $this->esperarBloqueo('espera', (int) $this->preflight['sesion']);
            if ($cambio === 'agenda') {
                FranjaHorariaAgenda::firstOrFail()->update(['hora_inicio' => '10:30:00']);
            } else {
                BloqueoAgenda::factory()->for($profesional)->create([
                    'inicio_at' => '2026-10-05 10:00:00', 'fin_at' => '2026-10-05 10:30:00',
                ]);
            }
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $resultado = $this->resultado('espera');
        $this->assertHttp($resultado, 409);
        $this->assertSame('slot_no_disponible', $resultado['code']);
        $this->conteos(0, 0);
    }

    public static function cambiosAgenda(): array
    {
        return [['agenda'], ['bloqueo']];
    }

    public function test_real_http_timeout_after_file_write_compensates_and_explicit_retry_succeeds(): void
    {
        $paciente = $this->paciente();
        $profesional = $this->profesional();
        $uuid = (string) Str::uuid();
        DB::beginTransaction();
        DB::table('auditorias')->lockForUpdate()->get();
        try {
            $this->iniciar('timeout', $paciente, $profesional, [
                'uuid' => $uuid, 'archivos' => 1, 'lock_timeout' => 3, 'pausar_final' => true,
            ]);
            $this->esperarBloqueo('timeout', (int) $this->preflight['sesion']);
            $rutas = Storage::disk('documentos_clinicos')->allFiles('originales');
            $this->assertCount(1, $rutas);
            $resultado = $this->barrera->esperar('timeout-finalizada');
            $this->assertHttp($resultado, 500);
            $this->assertNull($resultado['code']);
            $this->assertSame(1, $resultado['intentos']);
            $this->assertContains(1205, $resultado['codigos_sql']);
            foreach ($rutas as $ruta) {
                $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
            }
            $this->barrera->marcar('timeout-salir');
            $this->resultado('timeout');
        } finally {
            DB::rollBack();
        }
        $this->conteos(0, 0);
        $this->iniciar('retry', $paciente, $profesional, ['uuid' => $uuid, 'archivos' => 1]);
        $this->assertHttp($this->resultado('retry'), 201);
        $this->conteos(1, 1);
    }

    public function test_real_http_deadlock_observes_victim_and_explicit_retry_has_single_aggregate(): void
    {
        $paciente = $this->paciente();
        $profesional = $this->profesional();
        $uuid = (string) Str::uuid();
        DB::beginTransaction();
        DB::table('pacientes')->where('id', $paciente->id)->lockForUpdate()->first();
        $codigoAuxiliar = null;
        try {
            $this->iniciar('deadlock', $paciente, $profesional, ['uuid' => $uuid, 'archivos' => 1]);
            $this->esperarBloqueo('deadlock', (int) $this->preflight['sesion']);
            try {
                DB::table('kinesiologas')->where('id', $profesional->id)->lockForUpdate()->first();
            } catch (QueryException $error) {
                $codigoAuxiliar = (int) ($error->errorInfo[1] ?? 0);
                $this->assertSame(1213, $codigoAuxiliar);
            }
        } finally {
            DB::rollBack();
        }
        $resultado = $this->resultado('deadlock');
        $gano = $resultado['http_status'] === 201;
        $this->assertHttp($resultado, $gano ? 201 : 500);
        // El 1213 del worker se observa con el evento QueryException del handler, sin exponer SQL.
        $this->assertTrue($codigoAuxiliar === 1213 || ($resultado['deadlock_observado'] ?? false));
        $this->diagnostico['deadlock'] = ['codigo_auxiliar' => $codigoAuxiliar, 'worker' => $resultado];
        $this->assertSame(1, $resultado['intentos']);
        $this->conteos($gano ? 1 : 0, $gano ? 1 : 0);
        $this->iniciar('retry', $paciente, $profesional, ['uuid' => $uuid, 'archivos' => 1]);
        $retry = $this->resultado('retry');
        $this->assertHttp($retry, $gano ? 200 : 201);
        $this->assertSame($gano, $retry['esReplay']);
        $this->conteos(1, 1);
    }

    public function test_real_uncertain_http_commit_preserves_description_and_bytes_until_uuid_replay(): void
    {
        $paciente = $this->paciente();
        $profesional = $this->profesional();
        $uuid = (string) Str::uuid();
        $this->iniciar('incierto', $paciente, $profesional, [
            'uuid' => $uuid, 'archivos' => 1, 'commit_incierto' => true,
        ]);
        $incierto = $this->resultado('incierto');
        $this->assertHttp($incierto, 500);
        $this->assertNull($incierto['code']);
        $this->assertNull($incierto['turno_id']);
        $this->assertNull($incierto['redirect_url']);
        $this->conteos(1, 1);
        $turno = Turno::sole();
        $doc = DocumentoClinico::sole();
        $contenido = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% incierto-0\n%%EOF\n";
        $this->assertSame('Descripción sintética incierto-0.', $doc->descripcion);
        $this->assertSame($contenido, Storage::disk('documentos_clinicos')->get($doc->ruta_storage));
        $this->assertSame($turno->id, $doc->origenTurno->turno_id);

        $this->iniciar('retry', $paciente, $profesional, ['uuid' => $uuid, 'archivos' => 2]);
        $replay = $this->resultado('retry');
        $this->assertHttp($replay, 200);
        $this->assertTrue($replay['esReplay']);
        $this->assertSame($turno->id, $replay['turno_id']);
        $this->conteos(1, 1);
        $this->assertSame('Descripción sintética incierto-0.', $doc->fresh()->descripcion);
        $this->assertSame($contenido, Storage::disk('documentos_clinicos')->get($doc->ruta_storage));
        $this->auditoriaGanadora($paciente->user_id);
    }

    private function assertHttp(array $resultado, int $status): void
    {
        $this->assertSame('http', $resultado['status']);
        $this->assertSame($status, $resultado['http_status']);
        $this->assertSame(419, $resultado['csrf_rechazado']);
        $this->assertTrue($resultado['multipart']);
    }

    private function auditoriaGanadora(int $actor): void
    {
        foreach (Auditoria::where('modulo', 'Turnos')->get() as $audit) {
            $this->assertSame($actor, $audit->actor_user_id);
            $this->assertNull($audit->valores_anteriores);
            $this->assertEqualsCanonicalizing(
                $audit->evento === 'turno_primera_consulta_creado'
                    ? ['turno_id', 'paciente_id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at']
                    : ['documento_clinico_id', 'turno_id', 'paciente_id', 'origen', 'estado_validacion', 'tipo_documento_clinico', 'mime_type', 'tamanio_bytes'],
                array_keys($audit->valores_nuevos),
            );
        }
    }
}
