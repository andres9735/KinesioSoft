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
        Schema::table('users', function (Blueprint $table) {
            $table->string('apellido')->nullable()->after('name');
            $table->string('dni')->nullable()->unique()->after('email');
            $table->date('fecha_nacimiento')->nullable()->after('dni');
            $table->string('sexo')->nullable()->after('fecha_nacimiento');
            $table->string('telefono')->nullable()->after('sexo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['dni']);
            $table->dropColumn([
                'apellido',
                'dni',
                'fecha_nacimiento',
                'sexo',
                'telefono',
            ]);
        });
    }
};
