<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 0) (Opcional pero recomendado) Si hubiera duplicados por id_consulta, limpiar:
        // Nos quedamos con el registro más nuevo (id_eval_func más alto) por cada id_consulta.
        $duplicates = DB::table('evaluacion_funcional')
            ->select('id_consulta', DB::raw('COUNT(*) as c'))
            ->whereNotNull('id_consulta')
            ->groupBy('id_consulta')
            ->having('c', '>', 1)
            ->pluck('id_consulta');

        foreach ($duplicates as $idConsulta) {
            $ids = DB::table('evaluacion_funcional')
                ->where('id_consulta', $idConsulta)
                ->orderByDesc('id_eval_func')
                ->pluck('id_eval_func')
                ->all();

            array_shift($ids); // deja el más nuevo

            if (! empty($ids)) {
                DB::table('evaluacion_funcional')
                    ->whereIn('id_eval_func', $ids)
                    ->delete();
            }
        }

        // 1) Limpiar filas que romperían el NOT NULL
        DB::table('evaluacion_funcional')
            ->whereNull('id_consulta')
            ->delete();

        // 2) NOT NULL + UNIQUE + FK (en ese orden)
        Schema::table('evaluacion_funcional', function (Blueprint $table) {
            // Asegura NOT NULL
            $table->unsignedBigInteger('id_consulta')->nullable(false)->change();

            // Primero UNIQUE (crea índice)
            $table->unique('id_consulta', 'u_eval_func_id_consulta');

            // Después FK
            $table->foreign('id_consulta', 'fk_eval_func_id_consulta')
                ->references('id_consulta')
                ->on('consultas')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluacion_funcional', function (Blueprint $table) {
            // Drop FK por nombre (más seguro si le pusiste nombre)
            $table->dropForeign('fk_eval_func_id_consulta');

            // Drop unique por nombre
            $table->dropUnique('u_eval_func_id_consulta');

            // Volver a nullable (si querés volver atrás)
            $table->unsignedBigInteger('id_consulta')->nullable()->change();
        });
    }
};
