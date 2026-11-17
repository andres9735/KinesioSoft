<?php

namespace Tests\Feature\Turnos;

use App\Actions\Agenda\RegistrarBloqueoAgendaAction;
use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Actions\Turnos\AceptarOfertaAdelantoAction;
use App\Actions\Turnos\CancelarTurnoAction;
use App\Enums\EstadoTurnoEnum;
use App\Exceptions\Turnos\AccesoEnlaceRechazadoException;
use App\Http\Middleware\ProtegerAccesoOfertaAdelanto;
use App\Jobs\RevisarCicloOfertaAdelantoJob;
use App\Models\Auditoria;
use App\Models\BloqueoAgenda;
use App\Models\DespachoOfertaAdelanto;
use App\Models\EnlaceAsistenciaTurno;
use App\Models\IntencionEvaluacionCupo;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\SalidaOfertaAdelanto;
use App\Models\Turno;
use App\Services\Turnos\AccesoEnlaceAsistencia;
use App\Services\Turnos\AutorizacionNavegadorEnlaceService;
use App\Services\Turnos\CerrarOfertaAdelantoService;
use App\Services\Turnos\ContinuarCupoAdelantoService;
use App\Services\Turnos\DisponibilidadPrimeraConsultaService;
use App\Services\Turnos\EmitirEnlaceAsistenciaTurnoService;
use App\Services\Turnos\ProcesarIntencionCupoService;
use App\Services\Turnos\ResolverAsistenciaEnlaceService;
use App\Services\Turnos\ValidarEnlaceAsistenciaTurnoService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreaPerfilesUc07;
use Tests\TestCase;

class OfertaAdelantoAceptacionTest extends TestCase
{
    use CreaPerfilesUc07;
    use DatabaseMigrations;

    private Kinesiologa $profesional;

    private Turno $candidato;

    private OfertaAdelanto $oferta;

    private DespachoOfertaAdelanto $despacho;

    private string $url;

    private string $cookie;

    private array $autorizacion;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:' || extension_loaded('pdo_mysql')) {
            throw new LogicException('Estas pruebas requieren SQLite efímero sin PDO MySQL.');
        }
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://consultorio.example.test']);
        Mail::fake();
        Queue::fake();
        $this->ahora('2026-11-15 10:00:00');
        $this->profesional = $this->profesionalUc07();
        $this->candidato = Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-23 10:00:00', 'fin_at' => '2026-11-23 10:30:00', 'motivo_solicitud' => 'MOTIVO PRIVADO',
        ])->fresh();
        $paciente = $this->pacienteUc07();
        $cancelado = Turno::factory()->for($this->profesional)->for($paciente)->create([
            'tipo' => 'SEGUIMIENTO', 'estado' => 'CONFIRMADO',
            'inicio_at' => '2026-11-16 10:15:00', 'fin_at' => '2026-11-16 11:00:00',
        ]);
        app(CancelarTurnoAction::class)->ejecutar($paciente->user, $cancelado);
        $this->ahora('2026-11-15 12:00:00');
        app(ProcesarIntencionCupoService::class)->procesar(IntencionEvaluacionCupo::query()->sole()->id);
        $this->oferta = OfertaAdelanto::query()->sole();
        $this->despacho = DespachoOfertaAdelanto::query()->sole();
        $this->url = json_decode(Crypt::decryptString($this->despacho->material_cifrado), true)['url'];
        $this->autorizar();
    }

    protected function tearDown(): void
    {
        Event::forget('eloquent.creating: '.Auditoria::class);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('estadosElegibles')]
    public function test_traslada_el_mismo_turno_y_conserva_estado_duracion_y_datos(string $estado): void
    {
        $this->candidato->estado = EstadoTurnoEnum::from($estado);
        $this->candidato->saveOrFail();
        $antes = $this->candidato->fresh();
        $this->assertNotContains('10:00:00', $this->horas('2026-11-23'));
        $this->postDecision('aceptar')->assertOk()->assertSee('Se confirmó el cambio de horario')->assertDontSee('MOTIVO PRIVADO');
        $actual = $this->candidato->fresh();
        foreach (['id', 'paciente_id', 'kinesiologa_id', 'tipo', 'motivo_solicitud', 'estado', 'clave_idempotencia', 'created_at'] as $campo) {
            $this->assertSame($antes->getRawOriginal($campo), $actual->getRawOriginal($campo));
        }
        $this->assertSame('2026-11-16 10:15:00', $actual->inicio_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-16 10:45:00', $actual->fin_at->format('Y-m-d H:i:s'));
        $this->assertSame($antes->inicio_at->diffInSeconds($antes->fin_at), $actual->inicio_at->diffInSeconds($actual->fin_at));
        $this->assertContains('10:00:00', $this->horas('2026-11-23'));
        $this->assertFalse($this->oferta->fresh()->reservaVigenteEn(now()));
        $salida = SalidaOfertaAdelanto::query()->sole();
        $this->assertSame($estado, $salida->estado_turno_aceptado);
        $this->assertSame('no_corresponde', $salida->continuacion);
        $this->assertDatabaseCount('turnos', 2);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->assertDatabaseCount('ofertas_adelanto', 1);
        $this->assertSame(1, Auditoria::query()->where('evento', 'turno_adelantado_por_oferta')->count());
        $this->assertNull($this->despacho->fresh()->material_cifrado);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public static function estadosElegibles(): array
    {
        return [['PENDIENTE'], ['CONFIRMADO']];
    }

    public function test_declara_locks_actuales_y_el_prefijo_canonico_sin_abrir_mysql(): void
    {
        $conexion = DB::connection();
        $registro = new class($conexion) extends SQLiteGrammar
        {
            public array $lecturas = [];

            public function compileSelect(QueryBuilder $query)
            {
                if ($query->lock === true) {
                    $this->lecturas[] = ['tabla' => $query->from, 'nivel' => $query->getConnection()->transactionLevel(),
                        'sql' => (new MySqlGrammar($query->getConnection()))->compileSelect(clone $query)];
                }

                return parent::compileSelect($query);
            }
        };
        $conexion->setQueryGrammar($registro);
        app(AceptarOfertaAdelantoAction::class)->ejecutar($this->oferta->id, $this->autorizacion);
        $this->assertSame(['kinesiologas', 'pacientes', 'turnos'], array_slice(array_column($registro->lecturas, 'tabla'), 0, 3));
        foreach (['ofertas_adelanto', 'despachos_oferta_adelanto', 'salidas_oferta_adelanto', 'intenciones_evaluacion_cupo', 'configuraciones_agenda', 'franjas_horarias_agenda', 'bloqueos_agenda', 'revisiones_enlace_asistencia_turno'] as $tabla) {
            $this->assertContains($tabla, array_column($registro->lecturas, 'tabla'));
        }
        foreach ($registro->lecturas as $lectura) {
            // La preparación durable de confirmación reaccede a los mismos locks
            // dentro de una transacción anidada, sin liberar el prefijo exterior.
            $this->assertContains($lectura['nivel'], [1, 2]);
            $this->assertStringEndsWith('for update', $lectura['sql']);
        }
    }

    public function test_get_head_y_get_del_endpoint_no_aceptan(): void
    {
        $antes = $this->snapshot();
        $this->get($this->url)->assertOk()->assertSee('Aceptar adelanto')->assertSee('method="post"', false);
        $this->head($this->url)->assertOk();
        $this->get('https://consultorio.example.test/turnos/adelanto/aceptar')->assertStatus(405);
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('entradasInvalidas')]
    public function test_post_invalido_no_mueve_ni_registra(string $caso, int $status): void
    {
        $antes = $this->snapshot();
        $datos = ['_csrf_oferta' => $this->autorizacion['csrf'], 'accion' => 'aceptar'];
        $headers = ['Origin' => 'https://consultorio.example.test'];
        if ($caso === 'cookie') {
            $this->cookie = 'invalida';
        }
        if ($caso === 'csrf') {
            $datos['_csrf_oferta'] = 'incorrecto';
        }
        if ($caso === 'accion') {
            $datos['accion'] = 'rechazar';
        }
        if ($caso === 'origen') {
            $headers['Origin'] = 'https://otro.example.test';
        }
        if ($caso === 'destinatario') {
            $datosCookie = $this->autorizacion;
            $datosCookie['paciente']++;
            $this->cookie = Crypt::encryptString(json_encode($datosCookie));
        }
        $this->withUnencryptedCookies([ProtegerAccesoOfertaAdelanto::COOKIE => $this->cookie])
            ->post('https://consultorio.example.test/turnos/adelanto/aceptar', $datos, $headers)->assertStatus($status);
        $this->assertSame($antes, $this->snapshot());
    }

    public static function entradasInvalidas(): array
    {
        return [['cookie', 403], ['csrf', 419], ['accion', 419], ['origen', 419], ['destinatario', 403]];
    }

    public function test_revalida_autorizacion_bajo_locks(): void
    {
        $antes = $this->snapshot();
        $datos = $this->autorizacion;
        $datos['despacho']++;
        try {
            app(AceptarOfertaAdelantoAction::class)->ejecutar($this->oferta->id, $datos);
            $this->fail('Debió rechazar la asociación.');
        } catch (AuthorizationException) {
            $this->assertSame($antes, $this->snapshot());
        }
    }

    public function test_vencimiento_exacto_y_vencimiento_durante_las_lecturas(): void
    {
        $this->ahora('2026-11-15 13:59:59');
        $this->autorizar();
        DB::listen(function ($evento): void {
            if (str_starts_with($evento->sql, 'select ') && str_contains($evento->sql, 'from "bloqueos_agenda"')) {
                CarbonImmutable::setTestNow($this->oferta->vence_at);
            }
        });
        $antes = $this->candidato->getRawOriginal();
        $this->postDecision('aceptar')->assertStatus(410)->assertSee('La oferta venció');
        $this->assertSame(OfertaAdelanto::VENCIDA, SalidaOfertaAdelanto::query()->sole()->resultado);
        $this->assertSame($antes, $this->candidato->fresh()->getRawOriginal());
    }

    #[DataProvider('limitesVencimiento')]
    public function test_aceptacion_antes_y_en_el_instante_exacto_del_vencimiento(string $instante, bool $acepta): void
    {
        $this->ahora('2026-11-15 13:59:00');
        $this->autorizar();
        $this->ahora($instante);
        $this->postDecision('aceptar')->assertStatus($acepta ? 200 : 410);
        $this->assertSame($acepta ? OfertaAdelanto::ACEPTADA : OfertaAdelanto::VENCIDA, SalidaOfertaAdelanto::query()->sole()->resultado);
        $this->assertSame($acepta ? '2026-11-16' : '2026-11-23', $this->candidato->fresh()->inicio_at->toDateString());
    }

    public static function limitesVencimiento(): array
    {
        return [['2026-11-15 13:59:59.999999', true], ['2026-11-15 14:00:00', false]];
    }

    public function test_autorizacion_que_vence_al_adquirir_locks_no_produce_escrituras(): void
    {
        $antes = $this->snapshot();
        DB::listen(function ($evento): void {
            if (str_starts_with($evento->sql, 'select ') && str_contains($evento->sql, 'from "pacientes"')) {
                $this->ahora('2026-11-15 12:15:00');
            }
        });
        $this->postDecision('aceptar')->assertForbidden();
        $this->assertSame($antes, $this->snapshot());
    }

    #[DataProvider('cambiosOriginal')]
    public function test_original_modificado_cierra_sin_trasladarlo(string $caso): void
    {
        $cambios = match ($caso) {
            'horario' => ['inicio_at' => '2026-11-23 11:00:00', 'fin_at' => '2026-11-23 11:30:00'],
            'profesional' => ['kinesiologa_id' => $this->profesionalUc07()->id],
            'paciente' => ['paciente_id' => $this->pacienteUc07()->id],
            default => ['estado' => $caso],
        };
        DB::table('turnos')->where('id', $this->candidato->id)->update($cambios);
        $antes = $this->candidato->fresh()->getRawOriginal();
        $this->postDecision('aceptar')->assertStatus(410)->assertSee('El turno original cambió');
        $this->assertSame(OfertaAdelanto::ORIGINAL_MODIFICADO, $this->oferta->fresh()->estado);
        $this->assertSame($antes, $this->candidato->fresh()->getRawOriginal());
        $this->assertSame('pendiente', SalidaOfertaAdelanto::query()->sole()->continuacion);
    }

    public static function cambiosOriginal(): array
    {
        return [['CANCELADO'], ['EN_ATENCION'], ['ATENDIDO'], ['AUSENTE'], ['horario'], ['profesional'], ['paciente']];
    }

    #[DataProvider('incompatibilidades')]
    public function test_destino_incompatible_o_no_verificable_cierra(string $caso): void
    {
        if ($caso === 'bloqueo') {
            BloqueoAgenda::factory()->for($this->profesional)->create(['inicio_at' => '2026-11-16 10:20:00', 'fin_at' => '2026-11-16 10:25:00']);
        } elseif ($caso === 'paciente') {
            Turno::factory()->for($this->profesionalUc07())->for($this->candidato->paciente)->create([
                'tipo' => 'SEGUIMIENTO', 'estado' => 'CONFIRMADO', 'inicio_at' => '2026-11-16 10:30:00', 'fin_at' => '2026-11-16 11:00:00',
            ]);
        } elseif ($caso === 'franja') {
            DB::table('franjas_horarias_agenda')->update(['hora_fin' => '10:30:00']);
        } elseif ($caso === 'ambigua') {
            $config = $this->profesional->configuracionesAgenda()->first()->getRawOriginal();
            unset($config['id']);
            DB::table('configuraciones_agenda')->insert($config);
        } else {
            $this->profesional->configuracionesAgenda()->update(['anulado_at' => now(), 'motivo_anulacion' => 'Prueba']);
        }
        $antes = $this->candidato->getRawOriginal();
        $this->postDecision('aceptar')->assertStatus(410)->assertSee('No reprogramamos tu turno');
        $this->assertSame(OfertaAdelanto::DESTINO_INCOMPATIBLE, $this->oferta->fresh()->estado);
        $this->assertSame($antes, $this->candidato->fresh()->getRawOriginal());
    }

    public static function incompatibilidades(): array
    {
        return [['bloqueo'], ['paciente'], ['franja'], ['ambigua'], ['anulada']];
    }

    #[DataProvider('perdidasDeReserva')]
    public function test_reserva_perdida_u_ocupacion_impide_aceptar(string $caso): void
    {
        if ($caso === 'reserva') {
            DB::table('ofertas_adelanto')->where('id', $this->oferta->id)->update(['cerrada_at' => now()]);
        } elseif ($caso === 'ocupacion') {
            Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
                'estado' => 'CONFIRMADO', 'inicio_at' => '2026-11-16 10:50:00', 'fin_at' => '2026-11-16 11:10:00',
            ]);
        } else {
            // Dato defensivo: simula una reserva incompatible creada fuera del protocolo.
            $otra = $this->oferta->getRawOriginal();
            unset($otra['id']);
            $otra['paciente_id'] = $this->pacienteUc07()->id;
            DB::table('ofertas_adelanto')->insert($otra);
        }
        $antes = $this->candidato->getRawOriginal();
        $this->postDecision('aceptar')->assertStatus(410);
        $this->assertSame(OfertaAdelanto::CUPO_NO_DISPONIBLE, $this->oferta->fresh()->estado);
        $this->assertSame($antes, $this->candidato->fresh()->getRawOriginal());
    }

    public static function perdidasDeReserva(): array
    {
        return [['reserva'], ['ocupacion'], ['otra']];
    }

    public function test_aceptacion_repetida_y_jobs_tardios_no_mueven_ni_continuan_otra_vez(): void
    {
        $this->postDecision('aceptar')->assertOk();
        $antes = $this->snapshot();
        $this->postDecision('aceptar')->assertOk()->assertSee('Aceptaste el adelanto');
        $this->postDecision('rechazar')->assertOk()->assertSee('Aceptaste el adelanto');
        $this->ciclo();
        $this->assertSame($antes, $this->snapshot());
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('ofertas_adelanto', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        DB::table('turnos')->where('id', $this->candidato->id)->update(['estado' => 'CANCELADO']);
        $posterior = $this->snapshot();
        $this->postDecision('aceptar')->assertOk()->assertSee('Aceptaste el adelanto');
        $this->assertSame($posterior, $this->snapshot());
        $this->assertSame('CONFIRMADO', SalidaOfertaAdelanto::query()->sole()->estado_turno_aceptado);
    }

    public function test_rechazo_primero_impide_aceptacion_posterior(): void
    {
        $this->postDecision('rechazar')->assertOk();
        $antes = $this->snapshot();
        $this->postDecision('aceptar')->assertOk()->assertSee('La oferta quedó rechazada');
        $this->assertSame($antes, $this->snapshot());
        $this->assertSame(OfertaAdelanto::RECHAZADA, SalidaOfertaAdelanto::query()->sole()->resultado);
    }

    public function test_enlace_de_asistencia_anterior_pierde_validez_al_mover(): void
    {
        $emitido = app(EmitirEnlaceAsistenciaTurnoService::class)->emitir($this->candidato->id);
        parse_str(parse_url($emitido->urlParaCorreo(), PHP_URL_QUERY), $datos);
        $validador = app(ValidarEnlaceAsistenciaTurnoService::class);
        $this->assertSame(AccesoEnlaceAsistencia::VALIDO, $validador->validar($datos['token'])->estado);
        $version = EnlaceAsistenciaTurno::versionActualDe($this->candidato);
        [$autorizacionAnterior] = app(AutorizacionNavegadorEnlaceService::class)->crear(EnlaceAsistenciaTurno::query()->findOrFail($emitido->enlaceId));
        $this->postDecision('aceptar')->assertOk();
        $this->assertSame($version + 1, EnlaceAsistenciaTurno::versionActualDe($this->candidato->fresh()));
        $this->assertSame(AccesoEnlaceAsistencia::PROGRAMACION_MODIFICADA, $validador->validar($datos['token'])->estado);
        $this->assertDatabaseCount('enlaces_asistencia_turno', 1);
        $antes = $this->snapshot();
        try {
            app(ResolverAsistenciaEnlaceService::class)->ejecutar($autorizacionAnterior, 'confirmar', $autorizacionAnterior['operaciones']['confirmar']);
            $this->fail('La autorización anterior no puede confirmar el turno trasladado.');
        } catch (AccesoEnlaceRechazadoException) {
            $this->assertSame($antes, $this->snapshot());
        }
    }

    public function test_original_cambiado_reutiliza_continuacion_y_evaluacion_con_otro_paciente(): void
    {
        $otro = Turno::factory()->for($this->profesional)->for($this->pacienteUc07())->create([
            'tipo' => 'PRIMERA_CONSULTA', 'estado' => 'PENDIENTE', 'inicio_at' => '2026-11-22 10:00:00', 'fin_at' => '2026-11-22 10:30:00',
        ]);
        DB::table('turnos')->where('id', $this->candidato->id)->update(['estado' => 'CANCELADO']);
        $this->postDecision('aceptar')->assertStatus(410);
        Queue::assertPushed(RevisarCicloOfertaAdelantoJob::class, 1);
        $this->ciclo();
        $siguiente = OfertaAdelanto::query()->where('id', '!=', $this->oferta->id)->sole();
        $this->assertSame($otro->id, $siguiente->turno_id);
        $this->assertSame($this->oferta->intencion_evaluacion_cupo_id, $siguiente->intencion_evaluacion_cupo_id);
        $this->assertDatabaseCount('evaluaciones_adelanto', 1);
        $this->assertDatabaseCount('intenciones_evaluacion_cupo', 1);
        $this->ciclo();
        $this->assertDatabaseCount('ofertas_adelanto', 2);
    }

    public function test_fallo_despues_de_escribir_revierte_turno_oferta_reserva_revision_y_auditoria(): void
    {
        $antes = $this->snapshot();
        Auditoria::creating(function (Auditoria $auditoria): void {
            if ($auditoria->evento === 'turno_adelantado_por_oferta') {
                $this->assertSame('10:15:00', $this->candidato->fresh()->inicio_at->format('H:i:s'));
                $this->assertSame(OfertaAdelanto::ACEPTADA, $this->oferta->fresh()->estado);
                $this->assertDatabaseCount('salidas_oferta_adelanto', 1);
                throw new RuntimeException('Fallo posterior a las escrituras');
            }
        });
        $this->postDecision('aceptar')->assertStatus(503)->assertSee('No pudimos completar el cambio');
        $this->assertSame($antes, $this->snapshot());
        $this->assertTrue($this->oferta->fresh()->reservaVigenteEn(now()));
        Queue::assertNothingPushed();
        Event::forget('eloquent.creating: '.Auditoria::class);
        $this->postDecision('aceptar')->assertOk();
    }

    #[DataProvider('ordenesBloqueo')]
    public function test_ca_r11_en_ambos_ordenes_funcionales(bool $aceptarPrimero): void
    {
        if ($aceptarPrimero) {
            $this->postDecision('aceptar')->assertOk();
        }
        $actor = $this->profesional->user;
        $datos = ['inicio_at' => '2026-11-16 10:20:00', 'fin_at' => '2026-11-16 10:25:00', 'motivo' => 'Bloqueo de prueba'];
        $revision = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $datos);
        $this->assertSame($aceptarPrimero ? 1 : 0, $revision['cantidad_turnos_afectados']);
        app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $datos + ['snapshot' => $revision['snapshot']]);
        if ($aceptarPrimero) {
            $this->assertSame($this->candidato->id, $revision['turnos_afectados'][0]['id']);
            $this->assertSame('10:15:00', $this->candidato->fresh()->inicio_at->format('H:i:s'));
            $this->assertSame(EstadoTurnoEnum::CONFIRMADO, $this->candidato->fresh()->estado);
            $this->assertSame(OfertaAdelanto::ACEPTADA, $this->oferta->fresh()->estado);
        } else {
            $this->postDecision('aceptar')->assertStatus(410)->assertSee('No reprogramamos tu turno');
            $this->assertSame('2026-11-23', $this->candidato->fresh()->inicio_at->toDateString());
            $this->assertSame(OfertaAdelanto::CERRADA_AGENDA, $this->oferta->fresh()->estado);
        }
        $this->assertFalse($this->oferta->fresh()->reservaVigenteEn(now()));
    }

    public static function ordenesBloqueo(): array
    {
        return [[false], [true]];
    }

    private function autorizar(): void
    {
        $respuesta = $this->get($this->url)->assertOk();
        $this->cookie = $respuesta->headers->getCookies()[0]->getValue();
        $this->autorizacion = json_decode(Crypt::decryptString($this->cookie), true);
    }

    private function postDecision(string $accion)
    {
        return $this->withUnencryptedCookies([ProtegerAccesoOfertaAdelanto::COOKIE => $this->cookie])
            ->post('https://consultorio.example.test/turnos/adelanto/'.$accion, [
                '_csrf_oferta' => $this->autorizacion['csrf'], 'accion' => $accion,
            ], ['Origin' => 'https://consultorio.example.test']);
    }

    private function ahora(string $fecha): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($fecha, config('app.timezone')));
    }

    private function horas(string $fecha): array
    {
        $dia = CarbonImmutable::parse($fecha, config('app.timezone'));

        return array_map(fn ($slot): string => $slot['inicio_at']->format('H:i:s'), app(DisponibilidadPrimeraConsultaService::class)->consultarDisponibilidad($this->profesional, $dia, $dia));
    }

    private function snapshot(): array
    {
        return array_map(fn ($tabla): string => DB::table($tabla)->orderBy($tabla === 'revisiones_enlace_asistencia_turno' ? 'turno_id' : 'id')->get()->toJson(), [
            'turnos', 'ofertas_adelanto', 'despachos_oferta_adelanto', 'salidas_oferta_adelanto', 'auditorias', 'intenciones_evaluacion_cupo', 'revisiones_enlace_asistencia_turno',
        ]);
    }

    private function ciclo(): void
    {
        (new RevisarCicloOfertaAdelantoJob($this->oferta->id))->handle(app(CerrarOfertaAdelantoService::class), app(ContinuarCupoAdelantoService::class));
    }
}
