<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('padecimiento_tipo', function (Blueprint $table) {
            $table->id('id_padecimiento_tipo');
            $table->string('nombre', 100)->unique();
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padecimiento_tipo');
    }
};
