<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('diagnostico_funcional', function (Blueprint $table) {
            $table->id('id_diagnostico_funcional');
            $table->string('nombre', 120)->unique();
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostico_funcional');
    }
};
