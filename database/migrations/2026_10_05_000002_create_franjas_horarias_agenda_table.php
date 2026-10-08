<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franjas_horarias_agenda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('configuracion_agenda_id');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['configuracion_agenda_id', 'dia_semana']);

            $table->foreign('configuracion_agenda_id')
                ->references('id')
                ->on('configuraciones_agenda')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franjas_horarias_agenda');
    }
};
