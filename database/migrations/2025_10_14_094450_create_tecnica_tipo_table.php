<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tecnica_tipo', function (Blueprint $table) {
            $table->id('id_tecnica_tipo');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tecnica_tipo');
    }
};
