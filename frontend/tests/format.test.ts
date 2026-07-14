import { describe, expect, it } from 'vitest'
import { bytes, date, money, orderStatus, shortDate } from '@/shared/format'

describe('formatters', () => {
  it('formats traffic', () => expect(bytes(1073741824)).toBe('1.00 GB'))
  it('formats cents', () => expect(money(6800)).toBe('¥ 68.00'))
  it('maps order status', () => expect(orderStatus(3)).toBe('已完成'))
  it('formats compact chart dates', () => expect(shortDate(new Date(2026, 4, 8).getTime())).toBe('5.8'))
  it('formats Unix seconds supplied as strings or milliseconds', () => {
    expect(date('1780500000', true)).toBe(date(1780500000000, true))
    expect(date('1780500000', true)).not.toBe('1780500000')
  })
})
