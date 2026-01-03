<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluacion_funcional', function (Blueprint $table) {
            $table->text('motivo_consulta')->nullable()->after('texto');
            $table->string('limitacion_funcional', 30)->nullable()->after('eva_dolor');
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion_funcional', function (Blueprint $table) {
            $table->dropColumn(['motivo_consulta', 'limitacion_funcional']);
        });
    }
};
