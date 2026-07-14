<?php

namespace Tests\Feature\User;

use App\Models\Coupon;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_fetch_and_remove_coupon(): void
    {
        $user = $this->makeUser();
        $coupon = $this->makeCoupon(['code' => 'ACCOUNT20', 'type' => 2, 'value' => 20]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/coupon/save', ['code' => 'ACCOUNT20'])
            ->assertOk()
            ->assertJsonPath('data.id', $coupon->id)
            ->assertJsonPath('data.code', 'ACCOUNT20');

        $this->assertDatabaseHas('v2_user', [
            'id' => $user->id,
            'saved_coupon_id' => $coupon->id,
        ]);

        $this->getJson('/api/v1/user/coupon/saved')
            ->assertOk()
            ->assertJsonPath('data.name', $coupon->name)
            ->assertJsonPath('data.value', 20);

        $this->postJson('/api/v1/user/coupon/remove')
            ->assertOk()
            ->assertJsonPath('data', true);

        $this->assertNull($user->fresh()->saved_coupon_id);
        $this->getJson('/api/v1/user/coupon/saved')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_invalid_coupon_does_not_replace_saved_coupon(): void
    {
        $user = $this->makeUser();
        $savedCoupon = $this->makeCoupon(['code' => 'CURRENT10']);
        $this->makeCoupon([
            'code' => 'EXPIRED10',
            'started_at' => time() - 7200,
            'ended_at' => time() - 3600,
        ]);
        $user->forceFill(['saved_coupon_id' => $savedCoupon->id])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/coupon/save', ['code' => 'EXPIRED10'])
            ->assertStatus(400);

        $this->assertSame($savedCoupon->id, $user->fresh()->saved_coupon_id);
    }

    public function test_saved_coupon_is_still_checked_against_selected_plan(): void
    {
        $user = $this->makeUser();
        $coupon = $this->makeCoupon([
            'code' => 'PLANONLY',
            'limit_plan_ids' => [7],
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/coupon/save', ['code' => 'PLANONLY'])
            ->assertOk();

        $this->postJson('/api/v1/user/coupon/check', [
            'code' => 'PLANONLY',
            'plan_id' => 8,
            'period' => 'month_price',
        ])->assertStatus(400);

        $this->postJson('/api/v1/user/coupon/check', [
            'code' => 'PLANONLY',
            'plan_id' => 7,
            'period' => 'month_price',
        ])->assertOk()->assertJsonPath('data.id', $coupon->id);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => uniqid('coupon-', true) . '@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'code' => uniqid('COUPON'),
            'name' => '账号优惠券',
            'type' => 1,
            'value' => 1000,
            'show' => true,
            'limit_use' => null,
            'limit_use_with_user' => null,
            'limit_plan_ids' => null,
            'limit_period' => null,
            'started_at' => time() - 3600,
            'ended_at' => time() + 3600,
            'created_at' => time(),
            'updated_at' => time(),
        ], $overrides));
    }
}
