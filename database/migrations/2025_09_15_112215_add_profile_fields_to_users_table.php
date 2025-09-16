<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('dni', 30)->nullable()->unique()->after('phone');
            $table->string('address')->nullable()->after('dni');
            $table->string('specialty')->nullable()->after('address'); // para Kinesióloga
            $table->boolean('is_active')->default(true)->after('specialty');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index(['is_active']);
            $table->index(['last_login_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['users_is_active_index']);
            $table->dropIndex(['users_last_login_at_index']);
            $table->dropColumn(['phone', 'dni', 'address', 'specialty', 'is_active', 'last_login_at']);
        });
    }
};

