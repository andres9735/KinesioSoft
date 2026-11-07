<?php

namespace Tests\Feature\Services\Turnos;

use App\Enums\ResultadoIntentoRecordatorioEnum as Resultado;
use App\Jobs\DespacharRecordatorioTurnoJob;
use App\Models\Auditoria;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntentoRecordatorioTurno;
use App\Models\RecordatorioTurno;
use App\Models\SobreEntregaEnlace;
use App\Models\Turno;
use App\Services\Turnos\AccesoEnlaceAsistencia;
use App\Services\Turnos\DespacharRecordatorioTurnoService;
use App\Services\Turnos\PrepararContenidoRecordatorioTurnoService;
use App\Services\Turnos\SeleccionarRecordatoriosTurnoService;
use App\Services\Turnos\TransportarRecordatorioTurnoService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;
use Throwable;

/** Ensayos locales de 6.36; no certifican MariaDB, producción ni recepción del paciente. */
class RecordatoriosUc27CertificacionTest extends TestCase
{
    use CreaPerfilesUc07;

    protected function setUp(): void
    {
        parent::setUp();
        $raiz = str_replace('\\', '/', (string) getenv('LARAVEL_STORAGE_PATH'));
        $dentro = fn ($ruta) => $raiz !== '' && str_starts_with(str_replace('\\', '/', (string) $ruta), $raiz.'/');
        $handlers = app('log')->channel()->getLogger()->getHandlers();
        if (! app()->environment('testing') || app()->configurationIsCached()
            || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || \PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('pdo_mysql')
            || ! $dentro(storage_path('framework')) || ! $dentro(config('view.compiled'))
            || count($handlers) !== 1 || ! $handlers[0] instanceof NullHandler) {
            throw new LogicException('La certificación requiere el perfil SQLite aislado y NullHandler.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires', 'app.url' => 'https://consultorio.example.test']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Group('mailpit-local')]
    public function test_smtp_real_mailpit_con_job_persistido_y_sin_credenciales_en_registros(): void
    {
        if (getenv('UC27_MAILPIT_SMTP_REAL') !== '1') {
            $this->markTestSkipped('Mailpit requiere activación explícita del ensayo local.');
        }
        $this->api('/info');
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => [
            'transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1025,
            'scheme' => null, 'url' => null, 'username' => null, 'password' => null,
        ], 'mail.from.address' => 'consultorio-uc27@example.test', 'mail.from.name' => 'Consultorio sintético UC27',
            'queue.default' => 'database', 'queue.connections.database.connection' => 'sqlite']);
        $this->assertSame(TransportarRecordatorioTurnoService::class, get_class(app(TransportarRecordatorioTurnoService::class)));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('127.0.0.1', config('mail.mailers.smtp.host'));
        $this->assertSame(1025, config('mail.mailers.smtp.port'));
        $profesional = $this->profesionalUc07();
        $profesional->user->update(['name' => 'Profesional <b>UC27</b>', 'apellido' => 'Sintética']);
        $paciente = $this->pacienteUc07();
        $destinatario = 'uc27-'.Str::uuid().'@example.test';
        $paciente->user->update(['email' => $destinatario, 'name' => 'Paciente sintético UC27']);
        $turno = Turno::factory()->for($profesional)->for($paciente)->create([
            'tipo' => 'PRIMERA_CONSULTA', 'inicio_at' => '2026-11-07 10:00:00', 'fin_at' => '2026-11-07 10:30:00',
            'motivo_solicitud' => 'CLINICO-SINTETICO-NO-ENVIAR',
        ])->fresh();
        $antes = $turno->getAttributes();
        $id = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar()[0];
        app(PrepararContenidoRecordatorioTurnoService::class)->preparar($id);
        $cifrado = SobreEntregaEnlace::sole()->getRawOriginal('material_cifrado');
        $cola = app('queue')->connection('database');
        $cola->push(new DespacharRecordatorioTurnoJob($id, (string) Str::uuid()));
        $payload = DB::table('jobs')->sole()->payload;
        $comando = json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['data']['command'];
        $inicio = hrtime(true);
        $pendiente = $cola->pop();
        $this->assertNotNull($pendiente);
        $pendiente->fire();
        $duracionMs = (hrtime(true) - $inicio) / 1e6;
        $this->assertSame(Resultado::ACEPTADO, IntentoRecordatorioTurno::sole()->resultado);
        $this->assertNull(IntentoRecordatorioTurno::sole()->evidencia_entrega);
        $this->assertSame('PROCESADO', RecordatorioTurno::sole()->estado->value);
        $this->assertSame('CERRADO', SobreEntregaEnlace::sole()->estado);
        $this->assertNull(SobreEntregaEnlace::sole()->material_cifrado);
        $this->assertDatabaseCount('jobs', 0);

        // Buscar sólo nuestra dirección sintética; nunca vaciar ni borrar mensajes de la bandeja.
        $busqueda = '/search?query='.rawurlencode('to:'.$destinatario);
        $lista = $this->api($busqueda);
        for ($i = 0; ($lista['total'] ?? 0) === 0 && $i < 10; $i++) {
            usleep(100000);
            $lista = $this->api($busqueda);
        }
        $this->assertSame(1, $lista['total'] ?? 0);
        $mensajeId = $lista['messages'][0]['ID'] ?? null;
        $this->assertTrue(is_string($mensajeId) && preg_match('/\A[\w-]+\z/', $mensajeId) === 1);
        $mensaje = $this->api('/message/'.$mensajeId);
        $this->assertSame($destinatario, $mensaje['To'][0]['Address'] ?? null);
        $this->assertSame('consultorio-uc27@example.test', $mensaje['From']['Address'] ?? null);
        $this->assertSame('Recordatorio de turno — KinesioSoft', $mensaje['Subject'] ?? null);
        $html = $mensaje['HTML'] ?? '';
        foreach (['07/11/2026', '10:00', 'Primera consulta', 'Pendiente', '&lt;b&gt;UC27&lt;/b&gt;',
            'Confirmar asistencia', 'Cancelar turno'] as $dato) {
            $this->assertTrue(str_contains($html, $dato), 'Falta un dato administrativo esperado.');
        }
        foreach (['CLINICO-SINTETICO-NO-ENVIAR', '<b>UC27</b>', '<script', '<img', $destinatario] as $excluido) {
            $this->assertFalse(str_contains($html, $excluido), 'El contenido incluye información excluida.');
        }
        $this->assertEmpty($mensaje['Attachments'] ?? []);
        $this->assertTrue((bool) preg_match('/href="([^"]+\?token=([a-f0-9]{64}))"/', $html, $credencial), 'Falta un acceso seguro autorizado.');
        $enlace = EnlaceAsistenciaTurno::sole();
        $this->assertTrue($enlace->coincideConToken($credencial[2]));
        $this->assertSame(AccesoEnlaceAsistencia::VALIDO, app(ValidarEnlaceAsistenciaTurnoService::class)->validar($credencial[2])->estado);
        foreach ([$payload, $cifrado, Auditoria::all()->toJson(), DB::table('recordatorios_turno')->get()->toJson(),
            DB::table('intentos_recordatorio_turno')->get()->toJson(), DB::table('enlaces_asistencia_turno')->get()->toJson()] as $ordinario) {
            $this->assertFalse(str_contains($ordinario, $credencial[2]), 'Se encontró una credencial fuera del mensaje autorizado.');
            $this->assertFalse(str_contains($ordinario, $credencial[1]), 'Se encontró una URL sensible fuera del mensaje autorizado.');
        }
        foreach (glob(storage_path('logs/*')) ?: [] as $log) {
            if (is_file($log)) {
                $this->assertFalse(str_contains(file_get_contents($log), $credencial[2]), 'El log contiene una credencial.');
            }
        }
        // Repetición a través de la misma cola persistente: no significa un segundo transporte.
        $cola->push(unserialize($comando));
        $cola->pop()->fire();
        $this->assertSame(1, $this->api($busqueda)['total'] ?? 0);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $this->assertDatabaseCount('intentos_recordatorio_turno', 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame($antes, $turno->fresh()->getAttributes());
        fwrite(STDOUT, PHP_EOL.'UC27_SMTP_REAL '.json_encode(['aceptacion' => true, 'captura_mailpit' => true,
            'mensajes_propios' => 1, 'intentos' => 1, 'despacho_ms' => round($duracionMs, 3),
            'payload_bytes' => strlen($payload), 'entrega_paciente_verificada' => false], JSON_THROW_ON_ERROR).PHP_EOL);
    }

    private function api(string $ruta): array
    {
        try {
            $contexto = stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 0]]);
            $respuesta = @file_get_contents('http://127.0.0.1:8025/api/v1'.$ruta, false, $contexto);
            if ($respuesta === false) {
                throw new RuntimeException;
            }

            return json_decode($respuesta, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('No pudo verificarse la API local de Mailpit.');
        }
    }

    #[DataProvider('cargas')]
    public function test_medicion_local_sin_umbrales_inventados(int $cantidad): void
    {
        $profesionales = [$this->profesionalUc07(), $this->profesionalUc07()];
        $elegibles = intdiv($cantidad, 3);
        for ($i = 0; $i < $cantidad; $i++) {
            $indice = $i % 2;
            $inicio = CarbonImmutable::now()->addHours($i < $elegibles ? 2 : 72)->addMinutes(intdiv($i, 2) * 30);
            Turno::factory()->for($profesionales[$indice])->for($this->pacienteUc07())->create([
                'tipo' => $i % 2 === 0 ? 'PRIMERA_CONSULTA' : 'SEGUIMIENTO',
                'estado' => $i % 2 === 0 ? 'PENDIENTE' : 'CONFIRMADO',
                'inicio_at' => $inicio->format('Y-m-d H:i:s'), 'fin_at' => $inicio->addMinutes(30)->format('Y-m-d H:i:s'),
            ]);
        }
        $fase = 'seleccion';
        $consultas = [];
        $selects = [];
        // Nunca capturar bindings, respuestas SMTP ni material cifrado/descifrado.
        DB::listen(function (QueryExecuted $evento) use (&$fase, &$consultas, &$selects): void {
            $consultas[$fase] = ($consultas[$fase] ?? 0) + 1;
            if (preg_match('/^select .*?from ["`]?([a-z_]+)/i', $evento->sql, $tabla)) {
                $selects[$fase][$tabla[1]] = ($selects[$fase][$tabla[1]] ?? 0) + 1;
            }
        });
        $inicio = hrtime(true);
        $ids = app(SeleccionarRecordatoriosTurnoService::class)->seleccionar();
        $seleccionMs = (hrtime(true) - $inicio) / 1e6;
        $this->assertCount($elegibles, $ids);
        $fase = 'preparacion';
        $inicio = hrtime(true);
        foreach ($ids as $id) {
            app(PrepararContenidoRecordatorioTurnoService::class)->preparar($id);
        }
        $preparacionMs = (hrtime(true) - $inicio) / 1e6;
        $this->instance(TransportarRecordatorioTurnoService::class, Mockery::mock(TransportarRecordatorioTurnoService::class)
            ->shouldReceive('enviar')->times($elegibles)->andReturnUsing(function (Closure $contenido): Resultado {
                $contenido()->render();

                return Resultado::ACEPTADO;
            })->getMock());
        $fase = 'despacho_simulado';
        $inicio = hrtime(true);
        foreach ($ids as $id) {
            app(DespacharRecordatorioTurnoService::class)->despachar($id, (string) Str::uuid());
        }
        $despachoMs = (hrtime(true) - $inicio) / 1e6;
        $metricas = ['turnos' => $cantidad, 'elegibles' => $elegibles, 'seleccion_ms' => round($seleccionMs, 3),
            'preparacion_ms' => round($preparacionMs, 3), 'despacho_simulado_ms' => round($despachoMs, 3),
            'consultas' => $consultas, 'selects_por_tabla' => $selects,
            'pico_proceso_mib' => round(memory_get_peak_usage(true) / 1048576, 2), 'fixtures_fuera_de_medicion' => true];
        // Desactivar el contador antes de las consultas de verificación.
        $fase = 'verificacion';
        $this->assertSame($elegibles, RecordatorioTurno::where('estado', 'PROCESADO')->count());
        $this->assertDatabaseCount('intentos_recordatorio_turno', $elegibles);
        $this->assertSame(0, SobreEntregaEnlace::whereNotNull('material_cifrado')->count());
        fwrite(STDOUT, PHP_EOL.'UC27_METRICA '.json_encode($metricas, JSON_THROW_ON_ERROR).PHP_EOL);
    }

    public static function cargas(): array
    {
        return [[30], [150]];
    }
}
