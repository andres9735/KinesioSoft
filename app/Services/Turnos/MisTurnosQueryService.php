<?php

namespace App\Services\Turnos;

use App\Enums\EstadoTurnoEnum;
use App\Models\Paciente;
use App\Services\Agenda\RelojAplicacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class MisTurnosQueryService
{
    private const POR_PAGINA = 10;

    public function __construct(private readonly RelojAplicacion $reloj) {}

    /**
     * @param  array<string, string>  $filtros  Criterios validados por FiltrarMisTurnosRequest.
     * @param  array<string, mixed>  $navegacion
     */
    public function listar(Paciente $paciente, array $filtros, array $navegacion): array
    {
        $ahora = $this->reloj->ahora();
        $base = $this->consultaFiltrada($paciente, $filtros);
        $estadosProximos = [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO];
        $proximosQuery = (clone $base)->whereIn('estado', $estadosProximos)->where('inicio_at', '>', $ahora);
        $historicosQuery = (clone $base)->where(fn (Builder $query) => $query
            ->whereNotIn('estado', $estadosProximos)
            ->orWhere('inicio_at', '<=', $ahora));
        $proximosPage = $this->enteroPositivo($navegacion['proximos_page'] ?? null) ?? 1;
        $historicosPage = $this->enteroPositivo($navegacion['historicos_page'] ?? null) ?? 1;
        $turnoId = $this->enteroPositivo($navegacion['turno'] ?? null);
        $destacado = $turnoId === null ? null : (clone $base)->find($turnoId, ['id', 'estado', 'inicio_at']);
        $destacadoProximo = $destacado === null ? null : in_array($destacado->estado, $estadosProximos, true)
            && $destacado->inicio_at->greaterThan($ahora);

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

        $proximos = $this->paginar($proximosQuery->orderBy('inicio_at')->orderBy('id'), 'proximos_page', $proximosPage);
        $historicos = $this->paginar($historicosQuery->orderByDesc('inicio_at')->orderByDesc('id'), 'historicos_page', $historicosPage);

        // Al navegar la sección destacada se retira la pista para no forzar siempre su página.
        $proximos->appends($filtros + array_filter([
            'historicos_page' => $historicos->currentPage(),
            'turno' => $destacadoProximo === false ? $destacado?->id : null,
        ]));
        $historicos->appends($filtros + array_filter([
            'proximos_page' => $proximos->currentPage(),
            'turno' => $destacadoProximo === true ? $destacado?->id : null,
        ]));

        return ['proximos' => $proximos, 'historicos' => $historicos, 'turnoDestacadoId' => $destacado?->id];
    }

    private function consultaFiltrada(Paciente $paciente, array $filtros): Builder
    {
        $query = $paciente->turnos()->getQuery();
        foreach (['tipo', 'estado'] as $campo) {
            if (isset($filtros[$campo])) {
                $query->where($campo, $filtros[$campo]);
            }
        }
        foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $campo => $comparador) {
            if (isset($filtros[$campo])) {
                // inicio_at se conserva en la zona local de programación del proyecto.
                $query->whereDate('inicio_at', $comparador, $filtros[$campo]);
            }
        }
        if (isset($filtros['profesional'])) {
            $funcion = 'LOWER';
            if ($query->getConnection()->getDriverName() === 'sqlite') {
                // LOWER nativo de SQLite sólo convierte ASCII. Función temporal de esta conexión,
                // sin escrituras, para conservar la búsqueda Unicode también en ese motor.
                $query->getConnection()->getPdo()->sqliteCreateFunction('uc06_minusculas',
                    static fn (?string $valor): ?string => $valor === null ? null : mb_strtolower($valor, 'UTF-8'), 1);
                $funcion = 'uc06_minusculas';
            }
            $patron = '%'.strtr(mb_strtolower($filtros['profesional'], 'UTF-8'), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->whereHas('kinesiologa.user', fn (Builder $user) => $user->where(fn (Builder $nombre) => $nombre
                ->whereRaw("{$funcion}(name) LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("{$funcion}(apellido) LIKE ? ESCAPE '!'", [$patron])));
        }

        return $query;
    }

    private function paginar(Builder $query, string $nombre, int $pagina): LengthAwarePaginator
    {
        $columnas = ['id', 'kinesiologa_id', 'tipo', 'estado', 'inicio_at', 'fin_at'];
        $query->with('kinesiologa.user:id,name,apellido');
        $resultado = $query->paginate(self::POR_PAGINA, $columnas, $nombre, $pagina);
        if ($pagina > $resultado->lastPage()) {
            $resultado = $query->paginate(self::POR_PAGINA, $columnas, $nombre, $resultado->lastPage());
        }

        return $resultado;
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
