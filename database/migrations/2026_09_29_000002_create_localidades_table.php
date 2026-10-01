<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localidades', function (Blueprint $table): void {
            $table->id();
            $table->char('georef_id', 8)->unique();
            $table->foreignId('provincia_id');
            $table->string('nombre', 150);
            $table->string('departamento_nombre', 100);
            $table->timestamps();

            $table->index(['provincia_id', 'nombre']);

            $table->foreign('provincia_id')
                ->references('id')
                ->on('provincias')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localidades');
    }
};
