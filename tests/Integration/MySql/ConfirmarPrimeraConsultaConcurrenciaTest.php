<?php

namespace Tests\Integration\MySql;

use App\Enums\RoleEnum;
use App\Exceptions\Turnos\PrimeraConsultaActivaException;
use App\Exceptions\Turnos\ProfesionalNoElegibleException;
use App\Exceptions\Turnos\SlotNoDisponibleException;
use App\Models\BloqueoAgenda;
use App\Models\FranjaHorariaAgenda;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\MySql\Support\MySqlTestCase;

class ConfirmarPrimeraConsultaConcurrenciaTest extends MySqlTestCase
{
    #[DataProvider('carreras')]
    public function test_serializes_incompatible_intentions_and_observes_committed_state(string $caso): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->profesional();
        $otroPaciente = $caso === 'mismo_slot' ? $this->paciente() : $paciente;
        $otraKinesiologa = $caso === 'mismo_slot' ? $kinesiologa : $this->profesional();
        $uuid = (string) Str::uuid();
        $cantidad = $caso === 'misma_uuid' ? 5 : 2;
        $this->iniciar('uno', $paciente, $kinesiologa, ['uuid' => $uuid, 'archivos' => $cantidad, 'pausar' => true, 'pausar_final' => true]);
        $this->barrera->esperar('uno-antes-insertar');
        foreach (Storage::disk('documentos_clinicos')->allFiles('originales') as $ruta) {
            $this->assertSame($this->sesiones['uno'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
        }
        $this->iniciar('dos', $otroPaciente, $otraKinesiologa, [
            'uuid' => $caso === 'misma_uuid' ? $uuid : (string) Str::uuid(), 'archivos' => 1, 'snapshot' => true,
        ]);
        $this->barrera->esperar('dos-snapshot');
        $this->esperarBloqueo('dos', $this->sesiones['uno']);
        $this->barrera->marcar('uno-continuar');
        $this->barrera->esperar('uno-finalizada');
        $this->assertTrue($this->procesos['uno']->isRunning());
        foreach (Storage::disk('documentos_clinicos')->allFiles('originales') as $ruta) {
            $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
        }
        $this->barrera->marcar('uno-salir');
        $primero = $this->resultado('uno');
        $segundo = $this->resultado('dos');
        $this->assertSame('ok', $primero['status']);
        $this->assertFalse($primero['esReplay']);
        if ($caso === 'misma_uuid') {
            $this->assertSame('ok', $segundo['status']);
            $this->assertTrue($segundo['esReplay']);
            $this->assertSame($primero['turno_id'], $segundo['turno_id']);
        } else {
            $this->assertSame('error', $segundo['status']);
            $this->assertSame($caso === 'mismo_slot' ? SlotNoDisponibleException::class : PrimeraConsultaActivaException::class, $segundo['excepcion']);
        }
        $this->conteos(1, $cantidad);
    }

    public static function carreras(): array
    {
        return [['mismo_slot'], ['d01_entre_profesionales'], ['misma_uuid'], ['uuid_distintas_incompatibles']];
    }

    #[DataProvider('cambiosConcurrentes')]
    public function test_current_reads_after_wait_ignore_an_older_repeatable_read_snapshot(string $cambio): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->profesional();
        DB::beginTransaction();
        DB::table('kinesiologas')->where('id', $kinesiologa->id)->lockForUpdate()->first();
        try {
            $this->iniciar('espera', $paciente, $kinesiologa, ['snapshot' => true, 'archivos' => 1]);
            $this->barrera->esperar('espera-snapshot');
            $this->esperarBloqueo('espera', (int) $this->preflight['sesion']);
            match ($cambio) {
                // Fixture controlado válido: la nueva franja deja de admitir 10:00.
                'agenda' => FranjaHorariaAgenda::query()->firstOrFail()->update(['hora_inicio' => '10:30:00']),
                'bloqueo' => BloqueoAgenda::factory()->for($kinesiologa)->create(['inicio_at' => '2026-10-05 10:00:00', 'fin_at' => '2026-10-05 10:30:00']),
                'usuario' => $kinesiologa->user->update(['activo' => false]),
                'rol' => $kinesiologa->user->removeRole(RoleEnum::KINESIOLOGO->value),
                'matricula' => $kinesiologa->matriculasProfesionales()->update(['activa' => false]),
                'especialidad' => $kinesiologa->especialidades()->detach(),
            };
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $resultado = $this->resultado('espera');
        $this->assertSame('error', $resultado['status']);
        $this->assertSame(in_array($cambio, ['agenda', 'bloqueo'], true) ? SlotNoDisponibleException::class : ProfesionalNoElegibleException::class, $resultado['excepcion']);
        $this->conteos(0, 0);
    }

    public static function cambiosConcurrentes(): array
    {
        return [['agenda'], ['bloqueo'], ['usuario'], ['rol'], ['matricula'], ['especialidad']];
    }

    public function test_real_lock_wait_timeout_rolls_back_and_allows_explicit_retry(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->profesional();
        $uuid = (string) Str::uuid();
        DB::beginTransaction();
        DB::table('kinesiologas')->where('id', $kinesiologa->id)->lockForUpdate()->first();
        try {
            $this->iniciar('timeout', $paciente, $kinesiologa, ['uuid' => $uuid, 'lock_timeout' => 3, 'archivos' => 1]);
            $this->esperarBloqueo('timeout', (int) $this->preflight['sesion']);
            $resultado = $this->resultado('timeout');
            $this->assertSame('error', $resultado['status']);
            $this->assertContains(1205, $resultado['codigos_sql']);
            $this->assertSame(1, $resultado['intentos']);
        } finally {
            DB::rollBack();
        }
        $this->conteos(0, 0);
        $this->iniciar('retry', $paciente, $kinesiologa, ['uuid' => $uuid, 'archivos' => 1]);
        $this->assertSame('ok', $this->resultado('retry')['status']);
        $this->conteos(1, 1);
    }

    public function test_real_deadlock_accepts_observed_victim_without_partial_aggregate_or_automatic_retry(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->profesional();
        $uuid = (string) Str::uuid();
        DB::beginTransaction();
        DB::table('pacientes')->where('id', $paciente->id)->lockForUpdate()->first();
        $codigoAuxiliar = null;
        try {
            $this->iniciar('deadlock', $paciente, $kinesiologa, ['uuid' => $uuid, 'archivos' => 1]);
            // El worker ya tiene kinesióloga y espera paciente: ciclo controlado del harness.
            $this->esperarBloqueo('deadlock', (int) $this->preflight['sesion']);
            try {
                DB::table('kinesiologas')->where('id', $kinesiologa->id)->lockForUpdate()->first();
            } catch (QueryException $error) {
                $codigoAuxiliar = (int) ($error->errorInfo[1] ?? 0);
                $this->assertSame(1213, $codigoAuxiliar);
            }
        } finally {
            DB::rollBack();
        }
        $resultado = $this->resultado('deadlock');
        $this->diagnostico['deadlock'] = ['codigo_auxiliar' => $codigoAuxiliar, 'worker' => $resultado];
        $this->assertTrue($codigoAuxiliar === 1213 || in_array(1213, $resultado['codigos_sql'] ?? [], true), 'Debe observarse un deadlock físico 1213.');
        $this->assertSame(1, $resultado['intentos']);
        $gano = $resultado['status'] === 'ok';
        $this->conteos($gano ? 1 : 0, $gano ? 1 : 0);
        $this->iniciar('retry', $paciente, $kinesiologa, ['uuid' => $uuid, 'archivos' => 1]);
        $reintento = $this->resultado('retry');
        $this->assertSame('ok', $reintento['status']);
        $this->assertSame($gano, $reintento['esReplay']);
        $this->conteos(1, 1);
    }

    public function test_documental_failure_compensates_real_storage_and_releases_real_locks(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->profesional();
        $this->iniciar('rollback', $paciente, $kinesiologa, ['archivos' => 2, 'pausar' => true, 'fallar_documento' => true, 'pausar_final' => true]);
        $this->barrera->esperar('rollback-antes-insertar');
        $rutas = Storage::disk('documentos_clinicos')->allFiles('originales');
        $this->assertCount(2, $rutas);
        foreach ($rutas as $ruta) {
            $this->assertSame($this->sesiones['rollback'], (int) $this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
        }
        $this->barrera->marcar('rollback-continuar');
        $this->assertSame('error', $this->barrera->esperar('rollback-finalizada')['status']);
        $this->assertTrue($this->procesos['rollback']->isRunning());
        foreach ($rutas as $ruta) {
            $nombre = $this->nombreLock($ruta);
            $this->assertSame(1, (int) $this->observador->selectOne('SELECT GET_LOCK(?, 0) AS adquirido', [$nombre])->adquirido);
            $this->assertSame(1, (int) $this->observador->selectOne('SELECT RELEASE_LOCK(?) AS liberado', [$nombre])->liberado);
        }
        $this->barrera->marcar('rollback-salir');
        $this->assertSame('error', $this->resultado('rollback')['status']);
        $this->conteos(0, 0);
    }

    public function test_timeout_after_storage_compensates_and_releases_while_worker_session_is_alive(): void
    {
        $paciente = $this->paciente();
        $kinesiologa = $this->profesional();
        $uuid = (string) Str::uuid();
        DB::beginTransaction();
        DB::table('auditorias')->lockForUpdate()->get();
        try {
            $this->iniciar('timeout', $paciente, $kinesiologa, ['uuid' => $uuid, 'lock_timeout' => 3, 'archivos' => 2, 'pausar_final' => true]);
            $this->esperarBloqueo('timeout', (int) $this->preflight['sesion']);
            $rutas = Storage::disk('documentos_clinicos')->allFiles('originales');
            $this->assertCount(2, $rutas);
            $resultado = $this->barrera->esperar('timeout-finalizada');
            $this->assertSame('error', $resultado['status']);
            $this->assertContains(1205, $resultado['codigos_sql']);
            $this->assertSame(1, $resultado['intentos']);
            $this->assertTrue($this->procesos['timeout']->isRunning());
            foreach ($rutas as $ruta) {
                $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$this->nombreLock($ruta)])->propietario);
            }
            $this->barrera->marcar('timeout-salir');
            $this->resultado('timeout');
        } finally {
            DB::rollBack();
        }
        $this->conteos(0, 0);
        $this->iniciar('retry', $paciente, $kinesiologa, ['uuid' => $uuid, 'archivos' => 2]);
        $this->assertSame('ok', $this->resultado('retry')['status']);
        $this->conteos(1, 2);
    }
}
