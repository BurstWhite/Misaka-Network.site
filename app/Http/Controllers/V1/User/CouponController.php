<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Plan;
use App\Models\User;
use App\Services\CouponService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function saved(Request $request)
    {
        $coupons = $request->user()->savedCoupons()->get();

        return $this->success(CouponResource::collection($coupons));
    }

    public function save(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $userId = $request->user()->id;
        $coupon = DB::transaction(function () use ($request, $userId) {
            $user = User::lockForUpdate()->findOrFail($userId);
            $couponService = new CouponService(trim((string) $request->input('code')));
            $couponService->setUserId($user->id);
            $couponService->check();

            $coupon = $couponService->getCoupon();
            DB::table('v2_user_coupons')->insertOrIgnore([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'created_at' => time(),
            ]);

            // Keep the legacy single-coupon pointer usable for plugins and old clients.
            $user->forceFill(['saved_coupon_id' => $coupon->id])->saveOrFail();

            return $coupon;
        });

        return $this->success(CouponResource::make($coupon));
    }

    public function remove(Request $request)
    {
        $request->validate([
            'coupon_id' => 'nullable|integer|min:1',
        ]);

        $userId = $request->user()->id;
        DB::transaction(function () use ($request, $userId): void {
            $user = User::lockForUpdate()->findOrFail($userId);
            if ($request->filled('coupon_id')) {
                $couponId = (int) $request->input('coupon_id');
                $user->savedCoupons()->detach($couponId);

                if ((int) $user->saved_coupon_id !== $couponId) {
                    return;
                }
            } else {
                $user->savedCoupons()->detach();
            }

            $nextCouponId = $user->savedCoupons()->value('v2_coupon.id');
            $user->forceFill(['saved_coupon_id' => $nextCouponId])->saveOrFail();
        });

        return $this->success(true);
    }

    public function best(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:v2_plan,id',
            'period' => 'required|string|max:64',
        ]);

        $plan = Plan::findOrFail((int) $request->input('plan_id'));
        $period = PlanService::getPeriodKey((string) $request->input('period'));
        $price = $plan->prices[$period] ?? null;
        if ($price === null) {
            throw new ApiException(__('This payment period cannot be purchased, please choose another period'));
        }

        $best = CouponService::bestSavedFor(
            $request->user(),
            $plan->id,
            $period,
            (int) round((float) $price * 100),
        );
        if ($best === null) {
            return $this->success(null);
        }

        return $this->success([
            ...CouponResource::make($best['coupon'])->resolve($request),
            'discount_amount' => $best['discount_amount'],
        ]);
    }

    public function check(Request $request)
    {
        if (empty($request->input('code'))) {
            return $this->fail([422, __('Coupon cannot be empty')]);
        }
        $couponService = new CouponService($request->input('code'));
        $couponService->setPlanId($request->input('plan_id'));
        $couponService->setUserId($request->user()->id);
        $couponService->setPeriod($request->input('period'));
        $couponService->check();
        return $this->success(CouponResource::make($couponService->getCoupon()));
    }
}
