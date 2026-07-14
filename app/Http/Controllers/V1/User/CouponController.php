<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function saved(Request $request)
    {
        $coupon = $request->user()->savedCoupon()->first();

        return $this->success($coupon ? CouponResource::make($coupon) : null);
    }

    public function save(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $couponService = new CouponService(trim((string) $request->input('code')));
        $couponService->setUserId($request->user()->id);
        $couponService->check();

        $coupon = $couponService->getCoupon();
        $request->user()->forceFill(['saved_coupon_id' => $coupon->id])->saveOrFail();

        return $this->success(CouponResource::make($coupon));
    }

    public function remove(Request $request)
    {
        $request->user()->forceFill(['saved_coupon_id' => null])->saveOrFail();

        return $this->success(true);
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
