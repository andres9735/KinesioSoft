<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('bloques_disponibilidad', 'idx_disp_prof_dia')) {
            Schema::table('bloques_disponibilidad', function (Blueprint $table) {
                $table->index(['profesional_id', 'dia_semana'], 'idx_disp_prof_dia');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('bloques_disponibilidad', 'idx_disp_prof_dia')) {
            Schema::table('bloques_disponibilidad', function (Blueprint $table) {
                $table->dropIndex('idx_disp_prof_dia');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();

        $count = DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->count();

        return $count > 0;
    }
};

