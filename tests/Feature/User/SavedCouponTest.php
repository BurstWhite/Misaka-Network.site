<?php

namespace Tests\Feature\User;

use App\Exceptions\ApiException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_multiple_coupons_without_duplicates_and_remove_one(): void
    {
        $user = $this->makeUser();
        $first = $this->makeCoupon(['code' => 'ACCOUNT20', 'type' => 2, 'value' => 20]);
        $second = $this->makeCoupon(['code' => 'ACCOUNT10']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/coupon/save', ['code' => $first->code])
            ->assertOk()
            ->assertJsonPath('data.id', $first->id);
        $this->postJson('/api/v1/user/coupon/save', ['code' => $second->code])->assertOk();
        $this->postJson('/api/v1/user/coupon/save', ['code' => $first->code])->assertOk();

        $this->assertDatabaseCount('v2_user_coupons', 2);
        $this->getJson('/api/v1/user/coupon/saved')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', $first->code)
            ->assertJsonPath('data.1.code', $second->code)
            ->assertJsonMissingPath('data.0.pivot');

        $this->postJson('/api/v1/user/coupon/remove', ['coupon_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('data', true);

        $this->assertDatabaseMissing('v2_user_coupons', [
            'user_id' => $user->id,
            'coupon_id' => $first->id,
        ]);
        $this->assertDatabaseHas('v2_user_coupons', [
            'user_id' => $user->id,
            'coupon_id' => $second->id,
        ]);
        $this->assertSame($second->id, $user->fresh()->saved_coupon_id);

        // A missing coupon_id keeps the old clear-all API compatible.
        $this->postJson('/api/v1/user/coupon/remove')->assertOk();
        $this->assertDatabaseCount('v2_user_coupons', 0);
        $this->assertNull($user->fresh()->saved_coupon_id);
    }

    public function test_invalid_coupon_is_not_saved_or_allowed_to_replace_legacy_pointer(): void
    {
        $user = $this->makeUser();
        $savedCoupon = $this->makeCoupon(['code' => 'CURRENT10']);
        $this->makeCoupon([
            'code' => 'EXPIRED10',
            'started_at' => time() - 7200,
            'ended_at' => time() - 3600,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/coupon/save', ['code' => $savedCoupon->code])->assertOk();
        $this->postJson('/api/v1/user/coupon/save', ['code' => 'EXPIRED10'])
            ->assertStatus(400);

        $this->assertDatabaseCount('v2_user_coupons', 1);
        $this->assertSame($savedCoupon->id, $user->fresh()->saved_coupon_id);
    }

    public function test_best_coupon_honours_plan_and_period_restrictions(): void
    {
        $user = $this->makeUser();
        $allowedPlan = $this->makePlan(['prices' => [
            Plan::PERIOD_MONTHLY => 99.99,
            Plan::PERIOD_YEARLY => 199.99,
        ]]);
        $otherPlan = $this->makePlan();
        $coupon = $this->makeCoupon([
            'code' => 'PLAN-YEAR',
            'limit_plan_ids' => [$allowedPlan->id],
            'limit_period' => [Plan::PERIOD_YEARLY],
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/user/coupon/save', ['code' => $coupon->code])->assertOk();

        $this->postJson('/api/v1/user/coupon/best', [
            'plan_id' => $otherPlan->id,
            'period' => 'month_price',
        ])->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/v1/user/coupon/best', [
            'plan_id' => $allowedPlan->id,
            'period' => 'month_price',
        ])->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/v1/user/coupon/best', [
            'plan_id' => $allowedPlan->id,
            'period' => 'year_price',
        ])->assertOk()
            ->assertJsonPath('data.id', $coupon->id)
            ->assertJsonPath('data.discount_amount', 1000);
    }

    public function test_best_coupon_uses_actual_integer_savings_and_stable_save_order(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 99.99]]);
        $percentage = $this->makeCoupon(['code' => 'PERCENT25', 'type' => 2, 'value' => 25]);
        $fixed = $this->makeCoupon(['code' => 'FIXED25', 'type' => 1, 'value' => 2500]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/user/coupon/save', ['code' => $percentage->code])->assertOk();
        $this->postJson('/api/v1/user/coupon/save', ['code' => $fixed->code])->assertOk();

        $this->postJson('/api/v1/user/coupon/best', [
            'plan_id' => $plan->id,
            'period' => 'month_price',
        ])->assertOk()
            ->assertJsonPath('data.id', $fixed->id)
            ->assertJsonPath('data.discount_amount', 2500);

        $tiePlan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 100]]);
        $this->postJson('/api/v1/user/coupon/best', [
            'plan_id' => $tiePlan->id,
            'period' => 'month_price',
        ])->assertOk()
            ->assertJsonPath('data.id', $percentage->id)
            ->assertJsonPath('data.discount_amount', 2500);
    }

    public function test_order_automatically_applies_best_saved_coupon(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 99.99]]);
        $percentage = $this->makeCoupon(['code' => 'AUTO25P', 'type' => 2, 'value' => 25]);
        $fixed = $this->makeCoupon(['code' => 'AUTO25F', 'type' => 1, 'value' => 2500]);
        $this->saveForUser($user, $percentage);
        $this->saveForUser($user, $fixed);

        $order = OrderService::createFromRequest($user, $plan, 'month_price');

        $this->assertSame($fixed->id, $order->coupon_id);
        $this->assertSame(2500, $order->discount_amount);
        $this->assertSame(7499, $order->total_amount);
    }

    public function test_automatic_coupon_keeps_the_saved_coupon_identity_when_codes_are_duplicated(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 30]]);
        $this->makeCoupon(['code' => 'DUPLICATE-CODE', 'value' => 100]);
        $saved = $this->makeCoupon(['code' => 'DUPLICATE-CODE', 'value' => 2000]);
        $this->saveForUser($user, $saved);

        $order = OrderService::createFromRequest($user, $plan, 'month_price');

        $this->assertSame($saved->id, $order->coupon_id);
        $this->assertSame(2000, $order->discount_amount);
        $this->assertSame(1000, $order->total_amount);
    }

    public function test_manual_coupon_overrides_saved_coupon_and_invalid_manual_code_does_not_fallback(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 99.99]]);
        $saved = $this->makeCoupon(['code' => 'SAVED25', 'type' => 1, 'value' => 2500]);
        $manual = $this->makeCoupon(['code' => 'MANUAL10', 'type' => 2, 'value' => 10]);
        $this->saveForUser($user, $saved);

        $order = OrderService::createFromRequest($user, $plan, 'month_price', $manual->code);

        $this->assertSame($manual->id, $order->coupon_id);
        $this->assertSame(999, $order->discount_amount);
        $this->assertSame(9000, $order->total_amount);

        $otherUser = $this->makeUser();
        $this->saveForUser($otherUser, $saved);
        try {
            OrderService::createFromRequest($otherUser, $plan, 'month_price', 'NOT-A-COUPON');
            $this->fail('An invalid manual coupon must fail instead of falling back to a saved coupon.');
        } catch (ApiException) {
            $this->assertDatabaseMissing('v2_order', ['user_id' => $otherUser->id]);
        }
    }

    public function test_coupon_and_vip_discounts_cannot_make_order_total_negative(): void
    {
        $user = $this->makeUser(['discount' => 20]);
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 10]]);
        $coupon = $this->makeCoupon(['code' => 'FULL10', 'type' => 1, 'value' => 1000]);
        $this->saveForUser($user, $coupon);

        $order = OrderService::createFromRequest($user, $plan, 'month_price');

        $this->assertSame(1000, $order->discount_amount);
        $this->assertSame(0, $order->total_amount);
    }

    public function test_auto_selection_skips_a_saved_coupon_that_later_becomes_invalid(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();
        $expired = $this->makeCoupon(['code' => 'EXPIRED-LATER', 'value' => 5000]);
        $valid = $this->makeCoupon(['code' => 'STILL-VALID', 'value' => 1000]);
        $this->saveForUser($user, $expired);
        $this->saveForUser($user, $valid);
        $expired->forceFill(['ended_at' => time() - 1])->save();

        $order = OrderService::createFromRequest($user, $plan, 'month_price');

        $this->assertSame($valid->id, $order->coupon_id);
        $this->assertSame(1000, $order->discount_amount);
    }

    public function test_limited_coupon_decrement_is_atomic_and_transactional(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();
        $coupon = $this->makeCoupon(['code' => 'LAST-ONE', 'limit_use' => 1]);
        $this->saveForUser($user, $coupon);

        OrderService::createFromRequest($user, $plan, 'month_price');
        $this->assertSame(0, $coupon->fresh()->limit_use);

        $otherUser = $this->makeUser();
        try {
            OrderService::createFromRequest($otherUser, $plan, 'month_price', $coupon->code);
            $this->fail('An exhausted coupon must not create an order.');
        } catch (ApiException) {
            $this->assertDatabaseMissing('v2_order', ['user_id' => $otherUser->id]);
            $this->assertSame(0, $coupon->fresh()->limit_use);
        }

        $rollbackCoupon = $this->makeCoupon(['code' => 'ROLLBACK-ONE', 'limit_use' => 1]);
        try {
            DB::transaction(function () use ($otherUser, $plan, $rollbackCoupon): void {
                $order = new Order([
                    'user_id' => $otherUser->id,
                    'plan_id' => $plan->id,
                    'period' => Plan::PERIOD_MONTHLY,
                    'total_amount' => 9999,
                ]);
                $service = new CouponService($rollbackCoupon->code);
                $this->assertTrue($service->use($order));
                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }
        $this->assertSame(1, $rollbackCoupon->fresh()->limit_use);
    }

    public function test_cancelling_an_order_returns_a_limited_coupon_exactly_once(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();
        $coupon = $this->makeCoupon(['code' => 'RETURN-ON-CANCEL', 'limit_use' => 1]);
        $this->saveForUser($user, $coupon);
        $order = OrderService::createFromRequest($user, $plan, 'month_price');

        $this->assertTrue($order->coupon_limit_deducted);
        $this->assertSame(0, $coupon->fresh()->limit_use);

        $service = new OrderService($order);
        $this->assertTrue($service->cancel());
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertFalse($order->fresh()->coupon_limit_deducted);
        $this->assertSame(1, $coupon->fresh()->limit_use);

        $this->assertTrue($service->cancel());
        $this->assertSame(1, $coupon->fresh()->limit_use);
    }

    public function test_a_late_payment_callback_cannot_reopen_a_cancelled_order(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan();
        $order = OrderService::createFromRequest($user, $plan, 'month_price');
        Order::whereKey($order->id)->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertTrue((new OrderService($order))->paid('late-callback'));
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertNull($order->fresh()->callback_no);
    }

    public function test_legacy_saved_coupon_pointer_is_backfilled_by_the_migration(): void
    {
        $user = $this->makeUser();
        $coupon = $this->makeCoupon();
        $user->forceFill(['saved_coupon_id' => $coupon->id])->save();

        $migration = require database_path('migrations/2026_07_14_000003_create_v2_user_coupons_table.php');
        $migration->up();

        $this->assertDatabaseHas('v2_user_coupons', [
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
        ]);
    }

    public function test_exhausted_global_coupon_is_not_automatically_selected(): void
    {
        $firstUser = $this->makeUser();
        $secondUser = $this->makeUser();
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 20]]);
        $coupon = $this->makeCoupon([
            'code' => 'LAST-ONE',
            'value' => 500,
            'limit_use' => 1,
        ]);
        $this->saveForUser($firstUser, $coupon);
        $this->saveForUser($secondUser, $coupon);

        $firstOrder = OrderService::createFromRequest($firstUser, $plan, 'month_price');
        $secondOrder = OrderService::createFromRequest($secondUser, $plan, 'month_price');

        $this->assertSame($coupon->id, $firstOrder->coupon_id);
        $this->assertSame(0, $coupon->fresh()->limit_use);
        $this->assertNull($secondOrder->coupon_id);
        $this->assertSame(2000, $secondOrder->total_amount);
    }

    public function test_per_user_coupon_limit_is_checked_again_when_creating_order(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['prices' => [Plan::PERIOD_MONTHLY => 20]]);
        $coupon = $this->makeCoupon([
            'code' => 'ONCE-PER-USER',
            'value' => 500,
            'limit_use_with_user' => 1,
        ]);
        $this->saveForUser($user, $coupon);

        $usedOrder = OrderService::createFromRequest($user, $plan, 'month_price');
        $usedOrder->forceFill(['status' => Order::STATUS_COMPLETED])->saveOrFail();
        $nextOrder = OrderService::createFromRequest($user, $plan, 'month_price');

        $this->assertSame($coupon->id, $usedOrder->coupon_id);
        $this->assertNull($nextOrder->coupon_id);
        $this->assertSame(2000, $nextOrder->total_amount);
    }

    private function saveForUser(User $user, Coupon $coupon): void
    {
        $user->savedCoupons()->attach($coupon->id, ['created_at' => time()]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'email' => uniqid('coupon-', true) . '@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'created_at' => time(),
            'updated_at' => time(),
        ], $overrides));
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::query()->create(array_merge([
            'group_id' => null,
            'transfer_enable' => 100,
            'name' => uniqid('套餐-', true),
            'speed_limit' => null,
            'show' => true,
            'sort' => 1,
            'renew' => true,
            'content' => null,
            'prices' => [Plan::PERIOD_MONTHLY => 99.99],
            'reset_traffic_method' => null,
            'capacity_limit' => null,
            'sell' => true,
            'device_limit' => null,
        ], $overrides));
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
