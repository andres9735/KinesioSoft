<?php

use App\Actions\Agenda\AnularConfiguracionAgendaFuturaAction;
use App\Actions\Agenda\RegistrarBloqueoAgendaAction;
use App\Actions\Agenda\RevisarBloqueoAgendaAction;
use App\Actions\Turnos\AceptarOfertaAdelantoAction;
use App\Actions\Turnos\ActivarOfertaAdelantoAction;
use App\Actions\Turnos\ConfirmarPrimeraConsultaAction;
use App\Exceptions\Agenda\SnapshotAgendaDesactualizadoException;
use App\Mail\ConfirmacionAdelantoMail;
use App\Models\Auditoria;
use App\Models\DespachoConfirmacionAdelanto;
use App\Models\Kinesiologa;
use App\Models\OfertaAdelanto;
use App\Models\User;
use App\Services\Agenda\AgendaSnapshotService;
use App\Services\Turnos\CerrarOfertaAdelantoService;
use App\Services\Turnos\DespacharConfirmacionAdelantoService;
use App\Services\Turnos\PrepararConfirmacionAdelantoService;
use App\Services\Turnos\ProcesarIntencionCupoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Integration\AdelantosMariaDb09\Support\Entorno639;
use Tests\Integration\MySql\Support\Barrera;

require dirname(__DIR__, 3).'/vendor/autoload.php';

try {
    $app = Entorno639::aplicacion();
    $in = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $raiz = realpath(base_path('storage/framework/testing/adelantos-mariadb-09/'.Entorno639::perfil()['RUN']));
    $ruta = realpath($in['barrera'] ?? '');
    if (! $raiz || ! $ruta || ! str_starts_with(str_replace('\\', '/', $ruta).'/', str_replace('\\', '/', $raiz).'/') || ! preg_match('/\A[a-z]+\z/', $in['nombre'] ?? '')) {
        throw new RuntimeException;
    }
    $b = new Barrera($ruta);
    $n = $in['nombre'];
    CarbonImmutable::setTestNow(CarbonImmutable::parse($in['ahora']));
    $s = Entorno639::verificar(DB::connection());
    DB::statement('SET SESSION innodb_lock_wait_timeout=8');
    $wait = null;
    $first = true;
    DB::connection()->beforeExecuting(function ($sql) use ($b, $n): void {
        if (preg_match('/from `kinesiologas`.*for update/i', $sql)) {
            $b->marcar($n.'-intentando');
        }
    });
    DB::listen(function ($e) use ($b, $n, $in, &$wait, &$first): void {
        if ($first && preg_match('/from `kinesiologas`.*for update/i', $e->sql)) {
            $first = false;
            $wait = $e->time;
            if ($in['pausar'] ?? false) {
                $b->marcar($n.'-protegido');
                $b->esperar($n.'-continuar');
            }
        }
    });
    if ($in['fallar'] ?? false) {
        Auditoria::creating(function ($a): void {
            if ($a->evento === 'turno_adelantado_por_oferta') {
                throw new RuntimeException('Fallo inyectado después de escrituras');
            }
        });
    }
    $b->marcar($n.'-listo');
    $b->esperar($n.'-iniciar');
    $valor = match ($in['operacion']) {
        'procesar' => app(ProcesarIntencionCupoService::class)->procesar($in['intencion'])->id,
        'activar' => app(ActivarOfertaAdelantoAction::class)->ejecutar($in['intencion'], $in['turno'])?->id,
        'aceptar' => app(AceptarOfertaAdelantoAction::class)->ejecutar($in['oferta'], $in['auth'])->resultado,
        'rechazar' => app(CerrarOfertaAdelantoService::class)->cerrar($in['oferta'], OfertaAdelanto::RECHAZADA, $in['auth'])?->resultado,
        'vencer' => app(CerrarOfertaAdelantoService::class)->cerrar($in['oferta'], OfertaAdelanto::VENCIDA)?->resultado,
        'anular_configuracion' => (function () use ($in) {
            $k = Kinesiologa::findOrFail($in['k']);
            $c = $k->configuracionesAgenda()->sole();
            $c->load('franjasHorarias');
            $snapshot = app(AgendaSnapshotService::class)->generarParaConfiguracion($c);

            return app(AnularConfiguracionAgendaFuturaAction::class)->ejecutar($k->user, $c, ['snapshot' => $snapshot, 'motivo_anulacion' => 'Prueba aislada'])->id;
        })(),
        'preparar_confirmacion' => app(PrepararConfirmacionAdelantoService::class)->preparar($in['salida'])->id,
        'despachar_confirmacion' => (function () use ($in) {
            Mail::fake();
            app(DespacharConfirmacionAdelantoService::class)->despachar($in['despacho']);

            return ['estado' => DespachoConfirmacionAdelanto::findOrFail($in['despacho'])->estado, 'transportes' => Mail::sent(ConfirmacionAdelantoMail::class)->count()];
        })(),
        'alta' => app(ConfirmarPrimeraConsultaAction::class)->ejecutar(User::findOrFail($in['actor']), $in['uuid'], $in['k'], CarbonImmutable::parse($in['inicio']), 'Prueba ficticia aislada')->turno->id,
        'bloquear' => (function () use ($in) {
            $actor = Kinesiologa::findOrFail($in['k'])->user;
            $datos = ['inicio_at' => '2026-11-16 10:20:00', 'fin_at' => '2026-11-16 10:25:00', 'motivo' => 'Prueba aislada'];
            $rev = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $datos);
            try {
                app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $datos + ['snapshot' => $rev['snapshot']]);
            } catch (SnapshotAgendaDesactualizadoException) {
                // El recorrido profesional exige revisar y confirmar el aviso actualizado.
                $rev = app(RevisarBloqueoAgendaAction::class)->ejecutar($actor, $datos);
                $b = new Barrera($in['barrera']);
                $b->marcar($in['nombre'].'-snapshot-desactualizado');
                app(RegistrarBloqueoAgendaAction::class)->ejecutar($actor, $datos + ['snapshot' => $rev['snapshot']]);
            }

            return $rev['cantidad_turnos_afectados'];
        })(),
    };
    echo json_encode(['ok' => true, 'valor' => $valor, 'sesion' => $s['sesion'], 'pid' => getmypid(), 'espera_ms' => $wait]), PHP_EOL;
} catch (Throwable $e) {
    $codes = [];
    for ($t = $e; $t; $t = $t->getPrevious()) {
        if ($t instanceof PDOException) {
            $codes[] = $t->errorInfo[1] ?? null;
        }
    }
    echo json_encode(['ok' => false, 'clase' => $e::class, 'codigos' => $codes, 'sesion' => $s['sesion'] ?? null, 'pid' => getmypid(), 'espera_ms' => $wait ?? null]), PHP_EOL;
    exit(2);
}
