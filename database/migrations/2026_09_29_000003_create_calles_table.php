<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calles', function (Blueprint $table): void {
            $table->id();
            $table->char('georef_id', 15)->unique();
            $table->foreignId('localidad_id');
            $table->string('nombre', 150);
            $table->timestamps();

            $table->index(['localidad_id', 'nombre']);

            $table->foreign('localidad_id')
                ->references('id')
                ->on('localidades')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calles');
    }
};
