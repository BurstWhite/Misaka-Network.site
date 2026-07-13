<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('v2_user') && !Schema::hasColumn('v2_user', 'invite_code')) {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->string('invite_code', 32)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('v2_user') && Schema::hasColumn('v2_user', 'invite_code')) {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->dropColumn('invite_code');
            });
        }
    }
};
