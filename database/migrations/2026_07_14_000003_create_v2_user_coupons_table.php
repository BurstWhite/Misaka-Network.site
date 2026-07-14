<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_user_coupons')) {
            Schema::create('v2_user_coupons', function (Blueprint $table): void {
                $table->integer('user_id');
                $table->integer('coupon_id');
                $table->integer('created_at');

                $table->unique(['user_id', 'coupon_id']);
                $table->foreign('user_id')->references('id')->on('v2_user')->cascadeOnDelete();
                $table->foreign('coupon_id')->references('id')->on('v2_coupon')->cascadeOnDelete();
            });
        }

        if (!Schema::hasColumn('v2_user', 'saved_coupon_id')) {
            return;
        }

        DB::table('v2_user')
            ->whereNotNull('saved_coupon_id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                $createdAt = time();
                $rows = $users->map(fn ($user): array => [
                    'user_id' => $user->id,
                    'coupon_id' => $user->saved_coupon_id,
                    'created_at' => $createdAt,
                ])->all();

                DB::table('v2_user_coupons')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_user_coupons');
    }
};
