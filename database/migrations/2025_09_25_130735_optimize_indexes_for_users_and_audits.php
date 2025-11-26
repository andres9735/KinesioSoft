<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Solo queremos ejecutar esto en MySQL/MariaDB, NO en sqlite (tests) */
    private function isMySql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb']);
    }

    /** Helpers */
    private function hasIndex(string $table, string $index): bool
    {
        $res = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        return ! empty($res);
    }

    public function up(): void
    {
        // Si estamos en sqlite (testing), no hacemos nada
        if (! $this->isMySql()) {
            return;
        }

        // --- users ---
        Schema::table('users', function (Blueprint $t) {
            // nombres explícitos para evitar colisiones
        });

        if (! $this->hasIndex('users', 'users_name_index')) {
            Schema::table('users', fn(Blueprint $t) => $t->index('name', 'users_name_index'));
        }
        if (! $this->hasIndex('users', 'users_email_index')) {
            // Solo si NO tenés ya un UNIQUE en email. Si ya es UNIQUE, omití esta línea.
            Schema::table('users', fn(Blueprint $t) => $t->index('email', 'users_email_index'));
        }
        if (! $this->hasIndex('users', 'users_created_at_index')) {
            Schema::table('users', fn(Blueprint $t) => $t->index('created_at', 'users_created_at_index'));
        }

        // --- audits ---
        if (! $this->hasIndex('audits', 'audits_auditable_type_auditable_id_index')) {
            Schema::table(
                'audits',
                fn(Blueprint $t) =>
                $t->index(['auditable_type', 'auditable_id'], 'audits_auditable_type_auditable_id_index')
            );
        }
        if (! $this->hasIndex('audits', 'audits_user_id_index')) {
            Schema::table('audits', fn(Blueprint $t) => $t->index('user_id', 'audits_user_id_index'));
        }
        if (! $this->hasIndex('audits', 'audits_event_index')) {
            Schema::table('audits', fn(Blueprint $t) => $t->index('event', 'audits_event_index'));
        }
        if (! $this->hasIndex('audits', 'audits_created_at_index')) {
            Schema::table('audits', fn(Blueprint $t) => $t->index('created_at', 'audits_created_at_index'));
        }

        // --- spatie pivots (por si faltan) ---
        if (! $this->hasIndex('model_has_roles', 'model_has_roles_model_id_model_type_index')) {
            Schema::table(
                'model_has_roles',
                fn(Blueprint $t) =>
                $t->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index')
            );
        }
        if (! $this->hasIndex('model_has_roles', 'model_has_roles_role_id_index')) {
            Schema::table('model_has_roles', fn(Blueprint $t) => $t->index('role_id', 'model_has_roles_role_id_index'));
        }

        if (! $this->hasIndex('model_has_permissions', 'model_has_permissions_model_id_model_type_index')) {
            Schema::table(
                'model_has_permissions',
                fn(Blueprint $t) =>
                $t->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index')
            );
        }
        if (! $this->hasIndex('model_has_permissions', 'model_has_permissions_permission_id_index')) {
            Schema::table(
                'model_has_permissions',
                fn(Blueprint $t) =>
                $t->index('permission_id', 'model_has_permissions_permission_id_index')
            );
        }

        if (! $this->hasIndex('role_has_permissions', 'role_has_permissions_role_id_permission_id_index')) {
            Schema::table(
                'role_has_permissions',
                fn(Blueprint $t) =>
                $t->index(['role_id', 'permission_id'], 'role_has_permissions_role_id_permission_id_index')
            );
        }
    }

    public function down(): void
    {
        // Igual: en sqlite no hacemos nada
        if (! $this->isMySql()) {
            return;
        }

        // Opcional: dropear solo si existen
        $drop = function (string $table, string $index) {
            if ($this->hasIndex($table, $index)) {
                Schema::table($table, fn(Blueprint $t) => $t->dropIndex($index));
            }
        };

        $drop('users', 'users_name_index');
        $drop('users', 'users_email_index');
        $drop('users', 'users_created_at_index');

        $drop('audits', 'audits_auditable_type_auditable_id_index');
        $drop('audits', 'audits_user_id_index');
        $drop('audits', 'audits_event_index');
        $drop('audits', 'audits_created_at_index');

        $drop('model_has_roles', 'model_has_roles_model_id_model_type_index');
        $drop('model_has_roles', 'model_has_roles_role_id_index');

        $drop('model_has_permissions', 'model_has_permissions_model_id_model_type_index');
        $drop('model_has_permissions', 'model_has_permissions_permission_id_index');

        $drop('role_has_permissions', 'role_has_permissions_role_id_permission_id_index');
    }
};
