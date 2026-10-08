<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos_agenda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kinesiologa_id');
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->string('motivo', 500);
            $table->dateTime('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index([
                'kinesiologa_id',
                'anulado_at',
                'inicio_at',
                'fin_at',
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
        Schema::dropIfExists('bloqueos_agenda');
    }
};
