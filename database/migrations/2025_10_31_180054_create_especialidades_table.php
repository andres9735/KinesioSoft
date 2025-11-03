<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidades');
    }
};
