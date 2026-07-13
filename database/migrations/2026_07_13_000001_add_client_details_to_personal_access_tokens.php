<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            if (!Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('abilities');
            }
            if (!Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $columns = array_filter(['ip_address', 'user_agent'], fn (string $column): bool => Schema::hasColumn('personal_access_tokens', $column));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
