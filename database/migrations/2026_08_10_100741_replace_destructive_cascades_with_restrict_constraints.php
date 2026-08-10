<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reemplaza cascadas destructivas del dominio por restricciones
     * que preservan la información histórica.
     */
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table(
            'matriculas_profesionales_kinesiologia',
            function (Blueprint $table) {
                $table->dropForeign(['kinesiologa_id']);
            }
        );

        Schema::table(
            'matriculas_profesionales_kinesiologia',
            function (Blueprint $table) {
                $table->foreign('kinesiologa_id')
                    ->references('id')
                    ->on('kinesiologas')
                    ->restrictOnDelete();
            }
        );

        Schema::table(
            'kinesiologa_especialidad',
            function (Blueprint $table) {
                $table->dropForeign(['kinesiologa_id']);
            }
        );

        Schema::table(
            'kinesiologa_especialidad',
            function (Blueprint $table) {
                $table->foreign('kinesiologa_id')
                    ->references('id')
                    ->on('kinesiologas')
                    ->restrictOnDelete();
            }
        );
    }

    /**
     * Restaura las cascadas originales.
     */
    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('kinesiologas', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table(
            'matriculas_profesionales_kinesiologia',
            function (Blueprint $table) {
                $table->dropForeign(['kinesiologa_id']);
            }
        );

        Schema::table(
            'matriculas_profesionales_kinesiologia',
            function (Blueprint $table) {
                $table->foreign('kinesiologa_id')
                    ->references('id')
                    ->on('kinesiologas')
                    ->cascadeOnDelete();
            }
        );

        Schema::table(
            'kinesiologa_especialidad',
            function (Blueprint $table) {
                $table->dropForeign(['kinesiologa_id']);
            }
        );

        Schema::table(
            'kinesiologa_especialidad',
            function (Blueprint $table) {
                $table->foreign('kinesiologa_id')
                    ->references('id')
                    ->on('kinesiologas')
                    ->cascadeOnDelete();
            }
        );
    }
};
