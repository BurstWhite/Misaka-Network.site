<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure legacy installations have the user remarks column.
     *
     * The original create migration already defines this column, but it is
     * skipped when v2_user exists. This migration therefore only adds it when
     * upgrading a database created from an older schema.
     */
    public function up(): void
    {
        if (!Schema::hasTable('v2_user') || Schema::hasColumn('v2_user', 'remarks')) {
            return;
        }

        Schema::table('v2_user', function (Blueprint $table): void {
            $table->text('remarks')->nullable()->after('expired_at')->comment('管理员备注');
        });
    }

    /**
     * Do not remove the column on rollback: it may have been present before
     * this compatibility migration ran, and dropping it could destroy notes.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
