<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conexion = Schema::getConnection();
        $driver = $conexion->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new LogicException('Motor no soportado para la revisión de autorización de asistencia.');
        }
        Schema::create('revisiones_enlace_asistencia_turno', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            // Conserva la revisión si desaparece un turno sin enlaces: una reutilización
            // del ID no debe reiniciar su historia. Las FK restrictivas están en el enlace.
            $table->unsignedBigInteger('turno_id')->primary();
            $table->unsignedBigInteger('version_programacion')->default(1);
        });
        // Inicialización administrativa; no altera los atributos del turno existente.
        $conexion->statement('INSERT INTO revisiones_enlace_asistencia_turno (turno_id, version_programacion) SELECT id, 1 FROM turnos');
        foreach ($this->triggers($driver) as $sql) {
            $conexion->unprepared($sql);
        }
        Schema::create('enlaces_asistencia_turno', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('version_programacion');
            $table->uuid('ciclo_emision')->unique('enlace_asistencia_ciclo_unique');
            $table->char('token_hash', 64)->unique('enlace_asistencia_hash_unique');
            $table->string('alcance', 30);
            $table->string('tipo', 30);
            $table->dateTime('inicio_at', 6);
            $table->dateTime('fin_at', 6);
            $table->string('zona_horaria', 64);
            $table->dateTime('emitido_at', 6);
            $table->dateTime('vence_at', 6);
            $table->dateTime('revocado_at', 6)->nullable();
            // Un ciclo ordinario de emisión por turno/revisión; no por intento de correo.
            $table->unique(['turno_id', 'version_programacion'], 'enlace_asistencia_turno_version_unique');
        });
    }

    /** La revisión cambia incluso por SQL directo; confirmar no la consume. */
    private function triggers(string $driver): array
    {
        if ($driver === 'sqlite') {
            return [
                <<<'SQL'
CREATE TRIGGER revision_enlace_no_retrocede
BEFORE UPDATE ON revisiones_enlace_asistencia_turno
WHEN NEW.version_programacion < OLD.version_programacion OR NEW.turno_id IS NOT OLD.turno_id
BEGIN SELECT RAISE(ABORT, 'La revision de autorizacion no puede retroceder'); END
SQL,
                <<<'SQL'
CREATE TRIGGER revision_enlace_no_eliminar BEFORE DELETE ON revisiones_enlace_asistencia_turno
BEGIN SELECT RAISE(ABORT, 'La revision de autorizacion debe conservarse'); END
SQL,
                <<<'SQL'
CREATE TRIGGER turnos_enlace_version_crear AFTER INSERT ON turnos
BEGIN
 INSERT INTO revisiones_enlace_asistencia_turno (turno_id, version_programacion) VALUES (NEW.id, 1)
 ON CONFLICT (turno_id) DO UPDATE SET version_programacion = version_programacion + 1;
END
SQL,
                <<<'SQL'
CREATE TRIGGER turnos_enlace_version_actualizar
AFTER UPDATE OF paciente_id, kinesiologa_id, tipo, inicio_at, fin_at, estado ON turnos
WHEN NEW.paciente_id IS NOT OLD.paciente_id OR NEW.kinesiologa_id IS NOT OLD.kinesiologa_id
 OR NEW.tipo IS NOT OLD.tipo OR NEW.inicio_at IS NOT OLD.inicio_at OR NEW.fin_at IS NOT OLD.fin_at
 OR (NEW.estado IS NOT OLD.estado AND (NEW.estado IS NULL OR NEW.estado NOT IN ('PENDIENTE', 'CONFIRMADO')))
BEGIN
 UPDATE revisiones_enlace_asistencia_turno SET version_programacion = version_programacion + 1
 WHERE turno_id = NEW.id;
END
SQL,
            ];
        }

        return [
            <<<'SQL'
CREATE TRIGGER revision_enlace_no_retrocede BEFORE UPDATE ON revisiones_enlace_asistencia_turno FOR EACH ROW
BEGIN
 IF NEW.version_programacion < OLD.version_programacion OR NOT (NEW.turno_id <=> OLD.turno_id) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La revision de autorizacion no puede retroceder';
 END IF;
END
SQL,
            <<<'SQL'
CREATE TRIGGER revision_enlace_no_eliminar BEFORE DELETE ON revisiones_enlace_asistencia_turno FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La revision de autorizacion debe conservarse'
SQL,
            <<<'SQL'
CREATE TRIGGER turnos_enlace_version_crear AFTER INSERT ON turnos FOR EACH ROW
INSERT INTO revisiones_enlace_asistencia_turno (turno_id, version_programacion) VALUES (NEW.id, 1)
ON DUPLICATE KEY UPDATE version_programacion = version_programacion + 1
SQL,
            <<<'SQL'
CREATE TRIGGER turnos_enlace_version_actualizar AFTER UPDATE ON turnos FOR EACH ROW
BEGIN
 IF
 NOT (NEW.paciente_id <=> OLD.paciente_id) OR NOT (NEW.kinesiologa_id <=> OLD.kinesiologa_id)
 OR NOT (NEW.tipo <=> OLD.tipo) OR NOT (NEW.inicio_at <=> OLD.inicio_at) OR NOT (NEW.fin_at <=> OLD.fin_at)
 OR (NOT (NEW.estado <=> OLD.estado) AND COALESCE(NEW.estado, '') NOT IN ('PENDIENTE', 'CONFIRMADO')) THEN
  UPDATE revisiones_enlace_asistencia_turno SET version_programacion = version_programacion + 1 WHERE turno_id = NEW.id;
 END IF;
END
SQL,
        ];
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_asistencia_turno');
        foreach (['turnos_enlace_version_actualizar', 'turnos_enlace_version_crear', 'revision_enlace_no_retrocede', 'revision_enlace_no_eliminar'] as $trigger) {
            Schema::getConnection()->unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        }
        Schema::dropIfExists('revisiones_enlace_asistencia_turno');
    }
};
