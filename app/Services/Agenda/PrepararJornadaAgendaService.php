<?php

namespace App\Services\Agenda;

use App\Models\Kinesiologa;
use App\Services\Turnos\ElegibilidadKinesiologaService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/** UC–29 interno: no requiere sesión ni publica información mediante HTTP. */
final class PrepararJornadaAgendaService
{
    public function __construct(
        private AgendaAtencionQueryService $consulta,
        private ElegibilidadKinesiologaService $elegibilidad,
        private RelojAplicacion $reloj,
    ) {}

    /** @return Collection<int, Kinesiologa> */
    public function profesionalesHabilitadas(): Collection
    {
        return $this->elegibilidad->listarElegibles()->sortBy('id')->values();
    }

    /**
     * Sin fecha explícita usa hoy local; una fecha explícita es civil, como la lectura
     * compartida. Permite al futuro consumidor recuperar la jornada que corresponda.
     * Los errores se propagan: nunca equivalen a una jornada válida sin citas.
     */
    public function preparar(Kinesiologa $kinesiologa, ?CarbonInterface $fecha = null): JornadaAgendaPreparada
    {
        $actual = Kinesiologa::query()->with([
            'user.roles', 'user.kinesiologa.matriculasProfesionales', 'user.kinesiologa.especialidades',
        ])->find($kinesiologa->getKey());
        if ($actual === null || ! $this->elegibilidad->esElegible($actual)) {
            throw new DomainException('La profesional no está habilitada para preparar su jornada.');
        }
        $zona = (string) config('app.timezone');
        $jornada = CarbonImmutable::createFromFormat('!Y-m-d', ($fecha ?? $this->reloj->hoy())->toDateString(), $zona);

        return new JornadaAgendaPreparada(
            (int) $actual->getKey(), $jornada, $zona,
            $this->consulta->informacionDePeriodo($actual, $jornada, $jornada),
        );
    }
}
