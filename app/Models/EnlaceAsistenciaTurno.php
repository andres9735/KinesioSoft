<?php

namespace App\Models;

use App\Enums\EstadoTurnoEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

class EnlaceAsistenciaTurno extends Model
{
    public const ALCANCE = 'ASISTENCIA_TURNO';

    protected $table = 'enlaces_asistencia_turno';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'version_programacion' => 'integer', 'inicio_at' => 'immutable_datetime',
            'fin_at' => 'immutable_datetime', 'emitido_at' => 'immutable_datetime',
            'vence_at' => 'immutable_datetime', 'revocado_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $enlace): void {
            $turno = Turno::query()->useWritePdo()->find($enlace->turno_id);
            if ($turno === null || ! $enlace->correspondeA($turno)
                || $enlace->alcance !== self::ALCANCE
                || ! Str::isUuid((string) $enlace->ciclo_emision)
                || ! preg_match('/\A[0-9a-f]{64}\z/', (string) $enlace->token_hash)
                || $enlace->revocado_at !== null
                || ! in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)
                || ! $enlace->instante('emitido_at')->lt($enlace->instante('vence_at'))
                || ! $enlace->instante('vence_at')->equalTo($enlace->instante('inicio_at'))
                || ! $enlace->instante('inicio_at')->lt($enlace->instante('fin_at'))) {
                throw new InvalidArgumentException('El enlace requiere una asociación, alcance y vigencia coherentes.');
            }
        });
        static::updating(function (self $enlace): void {
            if (array_diff(array_keys($enlace->getDirty()), ['revocado_at']) !== []
                || $enlace->getRawOriginal('revocado_at') !== null
                || $enlace->revocado_at === null
                || $enlace->instante('revocado_at')->lt($enlace->instante('emitido_at'))) {
                throw new LogicException('El enlace es histórico; sólo admite revocación irreversible.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('El enlace conserva su trazabilidad.');
        });
    }

    /** Primitiva interna; no constituye el resolvedor público ni autoriza una Action. */
    public function coincideConToken(#[SensitiveParameter] string $token): bool
    {
        return preg_match('/\A[0-9a-f]{64}\z/', $token) === 1
            && hash_equals((string) $this->token_hash, hash('sha256', $token));
    }

    /** El consumidor debe suministrar el turno actual, bajo los locks cuando corresponda. */
    public function vigentePara(Turno $turno, CarbonInterface $ahora): bool
    {
        return $this->exists && $this->alcance === self::ALCANCE && $this->revocado_at === null
            && $this->correspondeA($turno)
            && in_array($turno->estado, [EstadoTurnoEnum::PENDIENTE, EstadoTurnoEnum::CONFIRMADO], true)
            && $ahora->greaterThanOrEqualTo($this->instante('emitido_at'))
            && $ahora->lessThan($this->instante('vence_at'));
    }

    /** Lectura actual de la revisión auxiliar, después del lock del turno al operar. */
    public static function versionActualDe(Turno $turno): int
    {
        $version = $turno->getConnection()->table('revisiones_enlace_asistencia_turno')
            ->useWritePdo()->where('turno_id', $turno->getKey())->lockForUpdate()->value('version_programacion');
        if ($version === null || (int) $version < 1) {
            // No reconstruir una revisión ausente: podría rehabilitar un enlace histórico.
            throw new LogicException('No se pudo verificar la revisión de autorización del turno.');
        }

        return (int) $version;
    }

    private function correspondeA(Turno $turno): bool
    {
        $datos = $turno->getAttributes();
        if (! $turno->exists || (int) $this->turno_id !== (int) $turno->id
            || (int) $this->paciente_id !== (int) $turno->paciente_id
            || (int) $this->kinesiologa_id !== (int) $turno->kinesiologa_id
            || $this->tipo !== $turno->getRawOriginal('tipo')
            || (int) $this->version_programacion < 1
            || (int) $this->version_programacion !== self::versionActualDe($turno)) {
            return false;
        }
        foreach (['inicio_at', 'fin_at'] as $campo) {
            if (! is_string($datos[$campo] ?? null)
                || ! $this->instante($campo)->equalTo(CarbonImmutable::parse($datos[$campo], $this->zona_horaria))) {
                return false;
            }
        }

        return true;
    }

    private function instante(string $campo): CarbonImmutable
    {
        $valor = $this->getAttributes()[$campo] ?? null;
        if (! is_string($valor) || $valor === '' || ! is_string($this->zona_horaria) || $this->zona_horaria === '') {
            throw new InvalidArgumentException('El enlace requiere contexto temporal completo.');
        }

        return CarbonImmutable::parse($valor, $this->zona_horaria);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function kinesiologa(): BelongsTo
    {
        return $this->belongsTo(Kinesiologa::class);
    }
}
