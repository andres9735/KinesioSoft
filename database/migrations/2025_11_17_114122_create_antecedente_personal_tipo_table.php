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
        Schema::create('antecedente_personal_tipo', function (Blueprint $table) {
            $table->id('antecedente_personal_tipo_id');
            $table->string('nombre')->unique(); // “Patológico”, “Quirúrgico”, etc.
            $table->string('slug')->unique();   // “patologico”, “quirurgico”
            $table->boolean('activo')->default(true);
            $table->unsignedTinyInteger('orden')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('antecedente_personal_tipo');
    }
};
