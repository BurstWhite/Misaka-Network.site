export interface CouponValue {
  type?: number | string
  value?: number | string
}

export function couponDiscountAmount(coupon: CouponValue | null | undefined, price: number | string): number {
  const total = Math.max(0, Math.floor(Number(price) || 0))
  const value = Math.max(0, Number(coupon?.value) || 0)
  const discount = Number(coupon?.type) === 1
    ? value
    : Number(coupon?.type) === 2
      ? Math.floor(total * value / 100)
      : 0

  return Math.min(total, discount)
}
