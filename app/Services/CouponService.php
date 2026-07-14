<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class CouponService
{
    public $coupon;
    public $planId;
    public $userId;
    public $period;

    public function __construct($code, bool $lockForUpdate = true)
    {
        if ($code instanceof Coupon) {
            $this->coupon = $lockForUpdate
                ? Coupon::whereKey($code->getKey())->lockForUpdate()->first()
                : $code;
            return;
        }

        $query = Coupon::where('code', $code);
        $this->coupon = ($lockForUpdate ? $query->lockForUpdate() : $query)->first();
    }

    public function use(Order $order): bool
    {
        $this->setPlanId($order->plan_id);
        $this->setUserId($order->user_id);
        $this->setPeriod($order->period);
        $this->check();
        $order->discount_amount = $this->discountAmount((int) $order->total_amount);
        $order->coupon_limit_deducted = false;
        if ($this->coupon->limit_use !== NULL) {
            $updated = Coupon::whereKey($this->coupon->id)
                ->where('limit_use', '>', 0)
                ->decrement('limit_use');
            if ($updated !== 1) {
                return false;
            }
            $order->coupon_limit_deducted = true;
        }
        return true;
    }

    public function discountAmount(int $totalAmount): int
    {
        $totalAmount = max(0, $totalAmount);
        $value = max(0, (int) $this->coupon->value);
        $discount = match ((int) $this->coupon->type) {
            1 => $value,
            2 => (int) floor($totalAmount * $value / 100),
            default => 0,
        };

        return min($totalAmount, $discount);
    }

    /**
     * @return array{coupon: Coupon, discount_amount: int}|null
     */
    public static function bestSavedFor(
        User $user,
        int $planId,
        string $period,
        int $totalAmount,
    ): ?array {
        return self::savedCandidatesFor($user, $planId, $period, $totalAmount)[0] ?? null;
    }

    /**
     * @return array<int, array{coupon: Coupon, discount_amount: int}>
     */
    public static function savedCandidatesFor(
        User $user,
        int $planId,
        string $period,
        int $totalAmount,
    ): array {
        $candidates = [];

        /** @var Coupon $coupon */
        foreach ($user->savedCoupons()->get() as $coupon) {
            $service = new self($coupon, false);
            $service->setPlanId($planId);
            $service->setUserId($user->id);
            $service->setPeriod($period);

            try {
                $service->check();
            } catch (ApiException) {
                continue;
            }

            $discountAmount = $service->discountAmount($totalAmount);
            if ($discountAmount <= 0) {
                continue;
            }
            $candidates[] = [
                'coupon' => $coupon,
                'discount_amount' => $discountAmount,
            ];
        }

        usort(
            $candidates,
            fn (array $left, array $right): int => $right['discount_amount'] <=> $left['discount_amount'],
        );

        return $candidates;
    }

    public function getId()
    {
        return $this->coupon->id;
    }

    public function getCoupon()
    {
        return $this->coupon;
    }

    public function setPlanId($planId)
    {
        $this->planId = $planId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function setPeriod($period)
    {
        if ($period) {
            $this->period = PlanService::getPeriodKey($period);
        }
    }

    public function checkLimitUseWithUser(): bool
    {
        $usedCount = Order::where('coupon_id', $this->coupon->id)
            ->where('user_id', $this->userId)
            ->whereNotIn('status', [0, 2])
            ->count();
        if ($usedCount >= $this->coupon->limit_use_with_user)
            return false;
        return true;
    }

    public function check()
    {
        if (!$this->coupon || !$this->coupon->show) {
            throw new ApiException(__('Invalid coupon'));
        }
        if ($this->coupon->limit_use <= 0 && $this->coupon->limit_use !== NULL) {
            throw new ApiException(__('This coupon is no longer available'));
        }
        if (time() < $this->coupon->started_at) {
            throw new ApiException(__('This coupon has not yet started'));
        }
        if (time() > $this->coupon->ended_at) {
            throw new ApiException(__('This coupon has expired'));
        }
        if ($this->coupon->limit_plan_ids && $this->planId) {
            if (!in_array($this->planId, $this->coupon->limit_plan_ids)) {
                throw new ApiException(__('The coupon code cannot be used for this subscription'));
            }
        }
        if ($this->coupon->limit_period && $this->period) {
            if (!in_array($this->period, $this->coupon->limit_period)) {
                throw new ApiException(__('The coupon code cannot be used for this period'));
            }
        }
        if ($this->coupon->limit_use_with_user !== NULL && $this->userId) {
            if (!$this->checkLimitUseWithUser()) {
                throw new ApiException(__('The coupon can only be used :limit_use_with_user per person', [
                    'limit_use_with_user' => $this->coupon->limit_use_with_user
                ]));
            }
        }
    }
}
