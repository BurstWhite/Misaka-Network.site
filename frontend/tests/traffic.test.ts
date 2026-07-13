import { describe, expect, it } from 'vitest'
import { aggregateTrafficByDay } from '@/shared/traffic'

describe('aggregateTrafficByDay', () => {
  const now = new Date(2026, 6, 13, 12, 30)

  it('returns seven calendar days and aggregates multiple records from one day', () => {
    const result = aggregateTrafficByDay([
      { record_at: Math.floor(new Date(2026, 6, 13, 10).getTime() / 1000), u: 2, d: 3 },
      { record_at: Math.floor(new Date(2026, 6, 13, 18).getTime() / 1000), u: '4', d: '1' },
      { record_at: new Date(2026, 6, 11, 9).getTime(), u: 10, d: 5 },
      { record_at: new Date(2026, 6, 5).getTime(), u: 99, d: 99 },
    ], 7, now)

    expect(result).toHaveLength(7)
    expect(result[0].key).toBe('2026-07-07')
    expect(result[4]).toMatchObject({ key: '2026-07-11', u: 10, d: 5, amount: 15 })
    expect(result[6]).toMatchObject({ key: '2026-07-13', u: 6, d: 4, amount: 10 })
    expect(result[6].label).toBe('7.13')
  })

  it('fills days without records with zeroes and ignores invalid or out-of-range records', () => {
    const result = aggregateTrafficByDay([
      { record_at: 'not-a-date', u: 100, d: 100 },
      { record_at: new Date(2026, 6, 6).getTime() / 1000, u: 20, d: 0 },
      { record_at: new Date(2026, 6, 14).getTime() / 1000, u: 30, d: 0 },
    ], 7, now)

    expect(result[0]).toMatchObject({ key: '2026-07-07', u: 0, d: 0, amount: 0 })
    expect(result.every((day) => day.key !== '2026-07-06')).toBe(true)
    expect(result[6]).toMatchObject({ key: '2026-07-13', amount: 0 })
  })
})
