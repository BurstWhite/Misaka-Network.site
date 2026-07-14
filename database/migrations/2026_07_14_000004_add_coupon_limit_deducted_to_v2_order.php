<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_order') || Schema::hasColumn('v2_order', 'coupon_limit_deducted')) {
            return;
        }

        Schema::table('v2_order', function (Blueprint $table): void {
            $table->boolean('coupon_limit_deducted')->default(false)->after('coupon_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('v2_order') || !Schema::hasColumn('v2_order', 'coupon_limit_deducted')) {
            return;
        }

        Schema::table('v2_order', function (Blueprint $table): void {
            $table->dropColumn('coupon_limit_deducted');
        });
    }
};
