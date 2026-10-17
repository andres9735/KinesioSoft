<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\Agenda\RelojAplicacion;
use App\Services\Turnos\ElegibilidadPrimeraConsultaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MisTurnosController extends Controller
{
    private const POR_PAGINA = 10;

    public function index(Request $request, RelojAplicacion $reloj, ElegibilidadPrimeraConsultaService $elegibilidad): View
    {
        $paciente = $request->user()->paciente()->first();
        abort_unless($paciente instanceof Paciente, 403);

        $ahora = $reloj->ahora();
        $base = $paciente->turnos()->getQuery();
        $proximosQuery = (clone $base)->where('inicio_at', '>', $ahora);
        $historicosQuery = (clone $base)->where('inicio_at', '<=', $ahora);
        $proximosPage = $this->enteroPositivo($request->query('proximos_page')) ?? 1;
        $historicosPage = $this->enteroPositivo($request->query('historicos_page')) ?? 1;
        $turnoId = $this->enteroPositivo($request->query('turno'));
        $destacado = $turnoId === null ? null : (clone $base)->find($turnoId, ['id', 'inicio_at']);
        $destacadoProximo = $destacado?->inicio_at->greaterThan($ahora);

        if ($destacado !== null) {
            $query = $destacadoProximo ? clone $proximosQuery : clone $historicosQuery;
            $comparador = $destacadoProximo ? '<' : '>';
            $anteriores = $query->where(function (Builder $query) use ($destacado, $comparador): void {
                $query->where('inicio_at', $comparador, $destacado->inicio_at)
                    ->orWhere(fn (Builder $empate) => $empate
                        ->where('inicio_at', $destacado->inicio_at)
                        ->where('id', $comparador, $destacado->id));
            })->count();
            $pagina = intdiv($anteriores, self::POR_PAGINA) + 1;
            if ($destacadoProximo) {
                $proximosPage = $pagina;
            } else {
                $historicosPage = $pagina;
            }
        }

        $columnas = ['id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at'];
        $proximos = $proximosQuery->with('kinesiologa.user:id,name,apellido')
            ->orderBy('inicio_at')->orderBy('id')
            ->paginate(self::POR_PAGINA, $columnas, 'proximos_page', $proximosPage);
        $historicos = $historicosQuery->with('kinesiologa.user:id,name,apellido')
            ->orderByDesc('inicio_at')->orderByDesc('id')
            ->paginate(self::POR_PAGINA, $columnas, 'historicos_page', $historicosPage);

        // Al navegar la sección destacada se retira la pista para no forzar siempre su página.
        $proximos->appends(array_filter([
            'historicos_page' => $historicosPage,
            'turno' => $destacadoProximo === false ? $destacado?->id : null,
        ]));
        $historicos->appends(array_filter([
            'proximos_page' => $proximosPage,
            'turno' => $destacadoProximo === true ? $destacado?->id : null,
        ]));

        return view('turnos.mis-turnos.index', [
            'proximos' => $proximos,
            'historicos' => $historicos,
            'turnoDestacadoId' => $destacado?->id,
            'primeraConsultaActiva' => $elegibilidad->tienePrimeraConsultaFuturaActiva($paciente),
        ]);
    }

    private function enteroPositivo(mixed $valor): ?int
    {
        if (! is_string($valor)) {
            return null;
        }

        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $entero === false ? null : $entero;
    }
}
