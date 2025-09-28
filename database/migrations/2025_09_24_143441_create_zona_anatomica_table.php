<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zona_anatomica', function (Blueprint $table) {
            $table->id('id_zona_anatomica');
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('codigo', 50)->nullable();
            $table->boolean('requiere_lateralidad')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id_zona_anatomica')
                ->on('zona_anatomica')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['parent_id', 'nombre']); // evita duplicados bajo el mismo padre
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zona_anatomica');
    }
};
