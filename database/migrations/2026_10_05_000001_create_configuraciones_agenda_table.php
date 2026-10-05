<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_agenda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kinesiologa_id');
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->unsignedSmallInteger('duracion_turno_minutos');
            $table->dateTime('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index([
                'kinesiologa_id',
                'anulado_at',
                'vigencia_desde',
                'vigencia_hasta',
            ]);

            $table->foreign('kinesiologa_id')
                ->references('id')
                ->on('kinesiologas')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_agenda');
    }
};
