<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            // PK personalizada
            $table->id('id_turno'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            // ====== Relaciones ======
            // Profesional (usuarios con rol Kinesiologa). FK -> users.id
            $table->foreignId('profesional_id')
                ->constrained('users')       // referencia a tabla users, columna id (por defecto)
                ->cascadeOnUpdate()
                ->restrictOnDelete();        // evita borrar usuario con turnos

            // Paciente (también en users por ahora). FK -> users.id
            $table->foreignId('paciente_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Consultorio (opcional). FK -> consultorio.id_consultorio
            $table->foreignId('id_consultorio')
                ->nullable()
                ->constrained('consultorio', 'id_consultorio') // nombre de tabla + PK real
                ->cascadeOnUpdate()
                ->nullOnDelete();           // si se elimina el consultorio, se pone NULL

            // ====== Datos del turno ======
            $table->date('fecha');          // día del turno (sin hora)
            $table->time('hora_desde');     // hora de inicio
            $table->time('hora_hasta');     // hora de fin

            // Estado del turno: pendiente | confirmado | cancelado (usamos string para flexibilidad)
            $table->string('estado', 20)->default('pendiente');

            // Observación / Motivo opcional
            $table->string('motivo', 255)->nullable();

            $table->timestamps();

            // ====== Índices / Uniques ======
            // Evita doble booking exacto para el mismo profesional, misma fecha y misma franja
            $table->unique(
                ['profesional_id', 'fecha', 'hora_desde', 'hora_hasta'],
                'turnos_prof_fecha_horas_unique'
            );

            // Índices para consultas frecuentes
            $table->index('fecha');
            $table->index('profesional_id');
            $table->index('paciente_id');

            // (Opcional) Si usás mucho por consultorio:
            // $table->index('id_consultorio');

            // (Opcional) CHECK a nivel DB (MySQL 8+):
            // Asegura que hora_hasta > hora_desde (si tu motor/versión lo soporta)
            // $table->check('hora_hasta > hora_desde');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
