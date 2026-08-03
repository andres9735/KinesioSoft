<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kinesiologa_especialidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kinesiologa_id')->constrained('kinesiologas')->cascadeOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades_kinesiologia')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['kinesiologa_id', 'especialidad_id'],
                'uq_kinesiologa_especialidad'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kinesiologa_especialidad');
    }
};
