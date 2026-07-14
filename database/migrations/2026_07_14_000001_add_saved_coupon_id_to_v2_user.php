<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_user') || Schema::hasColumn('v2_user', 'saved_coupon_id')) {
            return;
        }

        Schema::table('v2_user', function (Blueprint $table): void {
            $table->integer('saved_coupon_id')->nullable()->after('discount');
            $table->foreign('saved_coupon_id')
                ->references('id')
                ->on('v2_coupon')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('v2_user') || !Schema::hasColumn('v2_user', 'saved_coupon_id')) {
            return;
        }

        Schema::table('v2_user', function (Blueprint $table): void {
            $table->dropForeign(['saved_coupon_id']);
            $table->dropColumn('saved_coupon_id');
        });
    }
};
