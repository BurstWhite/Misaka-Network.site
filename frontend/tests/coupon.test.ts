import { describe, expect, it } from 'vitest'
import { couponDiscountAmount } from '@/shared/coupon'

describe('couponDiscountAmount', () => {
  it('uses cents for fixed discounts and caps them at the plan price', () => {
    expect(couponDiscountAmount({ type: 1, value: 1_500 }, 10_000)).toBe(1_500)
    expect(couponDiscountAmount({ type: 1, value: 15_000 }, 10_000)).toBe(10_000)
  })

  it('calculates percentage discounts in cents', () => {
    expect(couponDiscountAmount({ type: 2, value: 25 }, 9_999)).toBe(2_499)
  })

  it('does not apply malformed or negative discounts', () => {
    expect(couponDiscountAmount({ type: 3, value: 5_000 }, 10_000)).toBe(0)
    expect(couponDiscountAmount({ type: 1, value: -500 }, 10_000)).toBe(0)
  })
})
