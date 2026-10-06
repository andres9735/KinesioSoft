<?php

namespace App\Services\Agenda;

use App\Enums\EstadoTurnoEnum;
use App\Models\BloqueoAgenda;
use App\Models\ConfiguracionAgenda;
use App\Models\Kinesiologa;
use App\Models\Turno;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class AgendaQueryService
{
    public function configuracionAplicableEn(
        Kinesiologa $kinesiologa,
        CarbonInterface $fecha
    ): ?ConfiguracionAgenda {
        $configuraciones = $this->configuracionesDe($kinesiologa)
            ->aplicablesEn($fecha)
            ->with(['franjasHorarias' => function ($query): void {
                $query
                    ->orderBy('dia_semana')
                    ->orderBy('hora_inicio')
                    ->orderBy('hora_fin')
                    ->orderBy('id');
            }])
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        if ($configuraciones->count() > 1) {
            throw new LogicException(
                'Existe más de una configuración de agenda aplicable para la kinesióloga y fecha indicadas.'
            );
        }

        return $configuraciones->first();
    }

    public function futurasEn(Kinesiologa $kinesiologa, CarbonInterface $fecha): Builder
    {
        return $this->configuracionesDe($kinesiologa)
            ->futurasDesde($fecha)
            ->orderBy('vigencia_desde')
            ->orderBy('id');
    }

    public function historicasEn(Kinesiologa $kinesiologa, CarbonInterface $fecha): Builder
    {
        return $this->configuracionesDe($kinesiologa)
            ->historicasEn($fecha)
            ->orderByDesc('vigencia_hasta')
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id');
    }

    public function historialConfiguracionesEn(
        Kinesiologa $kinesiologa,
        CarbonInterface $fecha
    ): Builder {
        return $this->configuracionesDe($kinesiologa)
            ->where(function (Builder $query) use ($fecha): void {
                $query
                    ->whereNotNull('anulado_at')
                    ->orWhere(function (Builder $query) use ($fecha): void {
                        $query
                            ->historicasEn($fecha);
                    });
            })
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id');
    }

    public function configuraciones(Kinesiologa $kinesiologa): Builder
    {
        return $this->configuracionesDe($kinesiologa)
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id');
    }

    public function proximaEn(
        Kinesiologa $kinesiologa,
        CarbonInterface $fecha
    ): ?ConfiguracionAgenda {
        return $this->futurasEn($kinesiologa, $fecha)->first();
    }

    public function predecesoraDe(
        Kinesiologa $kinesiologa,
        CarbonInterface $vigenciaDesde,
        ?int $excluirId = null
    ): ?ConfiguracionAgenda {
        return $this->excluirConfiguracion(
            $this->configuracionesDe($kinesiologa)->noAnuladas(),
            $excluirId
        )
            ->whereDate('vigencia_desde', '<', $vigenciaDesde->toDateString())
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id')
            ->first();
    }

    public function sucesoraDe(
        Kinesiologa $kinesiologa,
        CarbonInterface $vigenciaDesde,
        ?int $excluirId = null
    ): ?ConfiguracionAgenda {
        return $this->excluirConfiguracion(
            $this->configuracionesDe($kinesiologa)->noAnuladas(),
            $excluirId
        )
            ->whereDate('vigencia_desde', '>', $vigenciaDesde->toDateString())
            ->orderBy('vigencia_desde')
            ->orderBy('id')
            ->first();
    }

    public function configuracionesSuperpuestasCon(
        Kinesiologa $kinesiologa,
        CarbonInterface $vigenciaDesde,
        ?CarbonInterface $vigenciaHasta,
        ?int $excluirId = null
    ): Builder {
        return $this->excluirConfiguracion(
            $this->configuracionesDe($kinesiologa),
            $excluirId
        )
            ->superpuestasCon($vigenciaDesde, $vigenciaHasta)
            ->orderBy('vigencia_desde')
            ->orderBy('id');
    }

    public function bloqueosSuperpuestosCon(
        Kinesiologa $kinesiologa,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?int $excluirId = null
    ): Builder {
        return $this->excluirBloqueo(
            $this->bloqueosDe($kinesiologa),
            $excluirId
        )
            ->superpuestosCon($inicio, $fin)
            ->orderBy('inicio_at')
            ->orderBy('fin_at')
            ->orderBy('id');
    }

    public function turnosAfectadosPorIntervalo(
        Kinesiologa $kinesiologa,
        CarbonInterface $inicio,
        CarbonInterface $fin
    ): Builder {
        return Turno::query()
            ->where('kinesiologa_id', $kinesiologa->getKey())
            ->whereIn('estado', [
                EstadoTurnoEnum::PENDIENTE->value,
                EstadoTurnoEnum::CONFIRMADO->value,
            ])
            ->where('inicio_at', '<', $fin)
            ->where('fin_at', '>', $inicio)
            ->orderBy('inicio_at')
            ->orderBy('fin_at')
            ->orderBy('id');
    }

    public function bloqueosProximosEn(
        Kinesiologa $kinesiologa,
        CarbonInterface $ahora
    ): Builder {
        return $this->bloqueosDe($kinesiologa)
            ->proximosEn($ahora)
            ->orderBy('inicio_at')
            ->orderBy('id');
    }

    public function bloqueosEnCursoEn(
        Kinesiologa $kinesiologa,
        CarbonInterface $ahora
    ): Builder {
        return $this->bloqueosDe($kinesiologa)
            ->enCursoEn($ahora)
            ->orderBy('inicio_at')
            ->orderBy('id');
    }

    public function bloqueosPasadosEn(
        Kinesiologa $kinesiologa,
        CarbonInterface $ahora
    ): Builder {
        return $this->bloqueosDe($kinesiologa)
            ->pasadosEn($ahora)
            ->orderByDesc('fin_at')
            ->orderByDesc('id');
    }

    public function bloqueosAnulados(Kinesiologa $kinesiologa): Builder
    {
        return $this->bloqueosDe($kinesiologa)
            ->anulados()
            ->orderByDesc('anulado_at')
            ->orderByDesc('id');
    }

    public function bloqueosNoAnulados(Kinesiologa $kinesiologa): Builder
    {
        return $this->bloqueosDe($kinesiologa)
            ->noAnulados()
            ->orderBy('inicio_at')
            ->orderBy('id');
    }

    public function bloqueosTodos(Kinesiologa $kinesiologa): Builder
    {
        return $this->bloqueosDe($kinesiologa)
            ->orderByDesc('inicio_at')
            ->orderByDesc('id');
    }

    private function configuracionesDe(Kinesiologa $kinesiologa): Builder
    {
        return ConfiguracionAgenda::query()
            ->where('kinesiologa_id', $kinesiologa->getKey());
    }

    private function bloqueosDe(Kinesiologa $kinesiologa): Builder
    {
        return BloqueoAgenda::query()
            ->where('kinesiologa_id', $kinesiologa->getKey());
    }

    private function excluirConfiguracion(Builder $query, ?int $excluirId): Builder
    {
        if ($excluirId !== null) {
            $query->whereKeyNot($excluirId);
        }

        return $query;
    }

    private function excluirBloqueo(Builder $query, ?int $excluirId): Builder
    {
        if ($excluirId !== null) {
            $query->whereKeyNot($excluirId);
        }

        return $query;
    }
}
