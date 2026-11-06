<?php

namespace App\Services\Turnos;

/** Resultado de lectura: nunca contiene la credencial, modelos ni datos clínicos. */
final readonly class AccesoEnlaceAsistencia
{
    public const VALIDO = 'valido';

    public const INVALIDO = 'invalido';

    public const VENCIDO = 'vencido';

    public const REVOCADO = 'revocado';

    public const PROGRAMACION_MODIFICADA = 'programacion_modificada';

    public const ESTADO_INCOMPATIBLE = 'estado_incompatible';

    public function __construct(
        public string $estado,
        public array $cita = [],
        public bool $puedeConfirmar = false,
        public bool $puedeCancelar = false,
    ) {}
}
