<?php

namespace Tests\Integration\MySql\Support;

use App\Enums\DiaSemanaEnum;
use App\Enums\RoleEnum;
use App\Models\Auditoria;
use App\Models\ColegioEmisor;
use App\Models\ConfiguracionAgenda;
use App\Models\DocumentoClinico;
use App\Models\DocumentoClinicoOrigenTurno;
use App\Models\EspecialidadKinesiologia;
use App\Models\FranjaHorariaAgenda;
use App\Models\Jurisdiccion;
use App\Models\Kinesiologa;
use App\Models\MatriculaProfesionalKinesiologia;
use App\Models\Paciente;
use App\Models\TituloProfesional;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

abstract class MySqlTestCase extends TestCase
{
    protected string $escenario;

    protected Barrera $barrera;

    protected Connection $observador;

    protected array $preflight;

    protected array $procesos = [];

    protected array $sesiones = [];

    protected array $locks = [];

    protected array $resultados = [];

    protected array $esperas = [];

    protected array $diagnostico = [];

    public function createApplication()
    {
        return EntornoMariaDb::aplicacion();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Ningún trait de migraciones: la guarda real se ejecuta ANTES del reset.
        $this->preflight = EntornoMariaDb::verificar(DB::connection());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]));
        // La preparación puede renovar el PDO; registrar la sesión que realmente usará el escenario.
        $this->preflight = EntornoMariaDb::verificar(DB::connection());
        DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
        $this->observador = EntornoMariaDb::independiente('uc07_observador');
        $this->assertNotSame((int) $this->preflight['sesion'], (int) $this->observador->selectOne('SELECT CONNECTION_ID() AS id')->id);
        $this->escenario = bin2hex(random_bytes(16));
        EntornoMariaDb::configurarStorage($this->escenario);
        $this->barrera = new Barrera(storage_path('framework/testing/uc07-mysql/'.$this->escenario.'/barreras'));
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop(1);
                }
            }
            foreach ($this->procesos as $proceso) {
                $this->assertFalse($proceso->isRunning(), 'No debe quedar un worker activo.');
            }
            if (isset($this->observador)) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                foreach ($this->sesiones as $id) {
                    $this->assertSame(0, (int) $this->observador->selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE ID = ?', [$id])->n);
                }
                $ids = [...$this->sesiones, (int) $this->preflight['sesion']];
                foreach ($ids as $id) {
                    $this->assertTrue(Barrera::hasta(fn () => (int) $this->observador->selectOne('SELECT COUNT(*) AS n FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = ?', [$id])->n === 0, 3, 150000));
                }
                foreach (Storage::disk('documentos_clinicos')->allFiles('originales') as $ruta) {
                    $this->nombreLock($ruta);
                }
                foreach ($this->locks as $nombre) {
                    $this->assertNull($this->observador->selectOne('SELECT IS_USED_LOCK(?) AS propietario', [$nombre])->propietario);
                }
                $this->registrarEvidencia();
                $this->observador->disconnect();
            }
            if (isset($this->escenario)) {
                $base = realpath(storage_path('framework/testing/uc07-mysql'));
                $ruta = realpath(storage_path('framework/testing/uc07-mysql/'.$this->escenario));
                if ($base !== false && $ruta !== false && ! is_link($ruta)
                    && str_starts_with(strtolower(str_replace('\\', '/', $ruta)), strtolower(str_replace('\\', '/', $base)).'/')) {
                    (new Filesystem)->deleteDirectory($ruta);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function paciente(): Paciente
    {
        return Paciente::create(['user_id' => User::factory()->create()->id, 'activo' => true]);
    }

    protected function profesional(): Kinesiologa
    {
        $user = User::factory()->create(['dni' => fake()->unique()->numerify('########'), 'fecha_nacimiento' => '1990-01-01', 'sexo' => 'femenino', 'telefono' => '3764123456', 'activo' => true]);
        $user->assignRole(Role::findOrCreate(RoleEnum::KINESIOLOGO->value, 'web'));
        $titulo = TituloProfesional::firstOrCreate(['slug' => 'titulo-mysql'], ['nombre' => 'Título fixture', 'activo' => true]);
        $especialidad = EspecialidadKinesiologia::firstOrCreate(['slug' => 'especialidad-mysql'], ['nombre' => 'Especialidad fixture', 'activo' => true]);
        $jurisdiccion = Jurisdiccion::firstOrCreate(['slug' => 'jurisdiccion-mysql'], ['nombre' => 'Jurisdicción fixture', 'activo' => true]);
        $colegio = ColegioEmisor::firstOrCreate(['sigla' => 'MYSQL'], ['nombre' => 'Colegio fixture', 'jurisdiccion_id' => $jurisdiccion->id, 'activo' => true]);
        $kinesiologa = Kinesiologa::create(['user_id' => $user->id, 'titulo_profesional_id' => $titulo->id]);
        MatriculaProfesionalKinesiologia::create(['kinesiologa_id' => $kinesiologa->id, 'nro_matricula_profesional' => 'MP-'.$user->id, 'jurisdiccion_id' => $jurisdiccion->id, 'colegio_emisor_id' => $colegio->id, 'activa' => true]);
        $kinesiologa->especialidades()->attach($especialidad);
        $configuracion = ConfiguracionAgenda::factory()->for($kinesiologa)->create(['vigencia_desde' => '2026-10-01', 'duracion_turno_minutos' => 30]);
        FranjaHorariaAgenda::factory()->for($configuracion, 'configuracionAgenda')->create(['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00']);

        return $kinesiologa;
    }

    protected function iniciar(string $nombre, Paciente $paciente, Kinesiologa $kinesiologa, array $opciones = []): Process
    {
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
            'UC07_MYSQL_DATABASE' => config('database.connections.mysql.database'),
            'UC07_MYSQL_HOST' => config('database.connections.mysql.host'),
            'UC07_MYSQL_PORT' => (string) config('database.connections.mysql.port'),
            'UC07_MYSQL_USERNAME' => config('database.connections.mysql.username'),
            'UC07_MYSQL_PASSWORD' => config('database.connections.mysql.password'),
            'UC07_MYSQL_AUTORIZADO' => getenv('UC07_MYSQL_AUTORIZADO'),
        ];
        $entrada = $opciones + ['escenario' => $this->escenario, 'worker' => $nombre, 'actor' => $paciente->user_id, 'kinesiologa' => $kinesiologa->id, 'uuid' => (string) Str::uuid()];
        $proceso = new Process([PHP_BINARY, $this->workerPath()], base_path(), $env, json_encode($entrada, JSON_THROW_ON_ERROR), 35);
        $proceso->start();
        $this->procesos[$nombre] = $proceso;
        $listo = $this->barrera->esperar($nombre.'-listo');
        $this->assertNotContains((int) $listo['sesion'], [...$this->sesiones, (int) $this->preflight['sesion']]);
        $this->assertSame('REPEATABLE-READ', $listo['aislamiento']);
        $this->sesiones[$nombre] = (int) $listo['sesion'];
        $this->barrera->marcar($nombre.'-iniciar');

        return $proceso;
    }

    protected function workerPath(): string
    {
        return __DIR__.'/worker.php';
    }

    protected function esperarBloqueo(string $worker, int $propietario): void
    {
        $solicitante = $this->sesiones[$worker];
        $traza = [];
        $espera = Barrera::hasta(function () use ($worker, $solicitante, $propietario, &$traza) {
            if (! $this->procesos[$worker]->isRunning()) {
                $sesionActual = DB::connection()->selectOne('SELECT CONNECTION_ID() AS id', [], false)->id;
                $transacciones = $this->observador->select('SELECT trx_mysql_thread_id AS sesion, trx_state AS estado FROM information_schema.INNODB_TRX');
                throw new \RuntimeException('El worker terminó antes de observar su espera: '.$this->procesos[$worker]->getOutput().' contexto='.json_encode(['esperado' => $propietario, 'actual_padre' => $sesionActual, 'transacciones' => $transacciones, 'traza' => $traza]));
            }
            $espera = $this->observador->selectOne(
                'SELECT r.trx_mysql_thread_id AS solicitante, b.trx_mysql_thread_id AS propietario FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=? AND r.trx_state=?',
                [$solicitante, $propietario, 'LOCK WAIT'],
            );
            $traza[] = $espera;
            $traza = array_slice($traza, -3);

            return $espera;
            // MariaDB 10.4.32 trx0i_s.cc CACHE_MIN_IDLE_TIME_NS=100000000:
            // el polling debe dejar >100 ms sin leer para permitir renovar la caché InnoDB.
        }, 8, 150000);
        $this->assertSame($solicitante, (int) $espera->solicitante);
        $this->assertSame($propietario, (int) $espera->propietario);
        $this->esperas[] = ['solicitante' => $solicitante, 'propietario' => $propietario, 'observado_at' => microtime(true)];
    }

    protected function resultado(string $worker): array
    {
        $proceso = $this->procesos[$worker];
        $proceso->wait();
        $this->assertContains($proceso->getExitCode(), [0, 2], 'Worker terminó fuera del protocolo estructurado.');
        $resultado = json_decode(trim($proceso->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->sesiones[$worker], $resultado['sesion']);
        $this->assertLessThanOrEqual(1, $resultado['intentos']);
        $this->resultados[$worker] = $resultado;

        return $resultado;
    }

    private function registrarEvidencia(): void
    {
        $run = getenv('UC07_MYSQL_RUN_ID') ?: '';
        if ($run === '') {
            return;
        }
        if (! preg_match('/\A[a-f0-9]{32}\z/', $run)) {
            throw new \RuntimeException('Identificador de evidencia inválido.');
        }
        $datos = [
            'test' => $this->nameWithDataSet(), 'preflight' => $this->preflight,
            'observador' => (int) $this->observador->selectOne('SELECT CONNECTION_ID() AS id')->id,
            'workers' => $this->sesiones, 'esperas' => $this->esperas, 'resultados' => $this->resultados,
            'diagnostico' => $this->diagnostico,
            'conteos' => [
                'turnos' => Turno::count(), 'documentos' => DocumentoClinico::count(),
                'asociaciones' => DocumentoClinicoOrigenTurno::count(),
                'auditorias_turno' => Auditoria::where('evento', 'turno_primera_consulta_creado')->count(),
                'auditorias_documento' => Auditoria::where('evento', 'documento_clinico_cargado')->count(),
                'archivos' => count(Storage::disk('documentos_clinicos')->allFiles('originales')),
            ],
        ];
        file_put_contents(storage_path('framework/testing/uc07-mysql/evidencia-'.$run.'.jsonl'), json_encode($datos, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    protected function conteos(int $turnos, int $documentos): void
    {
        $this->assertSame($turnos, Turno::count());
        $this->assertSame($documentos, DocumentoClinico::count());
        $this->assertSame($documentos, DocumentoClinicoOrigenTurno::count());
        $this->assertSame($turnos, Auditoria::where('evento', 'turno_primera_consulta_creado')->count());
        $this->assertSame($documentos, Auditoria::where('evento', 'documento_clinico_cargado')->count());
        $this->assertCount($documentos, Storage::disk('documentos_clinicos')->allFiles('originales'));
    }

    protected function nombreLock(string $ruta): string
    {
        $nombre = 'ks-doc:'.substr(hash('sha256', config('app.name').'|'.DB::connection()->getDatabaseName().'|'.$ruta), 0, 56);
        $this->locks[$nombre] = $nombre;

        return $nombre;
    }
}
