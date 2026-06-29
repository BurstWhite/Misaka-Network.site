<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            if (!Schema::hasColumn('v2_order', 'manual_status')) {
                $table->tinyInteger('manual_status')->default(0)->comment('0无1待人工处理2已人工处理');
            }
            if (!Schema::hasColumn('v2_order', 'manual_submitted_at')) {
                $table->integer('manual_submitted_at')->nullable()->comment('人工提交时间');
            }
            if (!Schema::hasColumn('v2_order', 'manual_handled_at')) {
                $table->integer('manual_handled_at')->nullable()->comment('人工处理时间');
            }
            if (!Schema::hasColumn('v2_order', 'manual_handled_by')) {
                $table->integer('manual_handled_by')->nullable()->comment('人工处理管理员ID');
            }
        });
    }

    public function down(): void
    {
        Schema::table('v2_order', function (Blueprint $table) {
            foreach (['manual_status', 'manual_submitted_at', 'manual_handled_at', 'manual_handled_by'] as $column) {
                if (Schema::hasColumn('v2_order', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
