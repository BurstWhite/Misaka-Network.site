import { describe, expect, it } from 'vitest'
import { displayNodeCode, displayNodeFlag, displayRate, extractRows, parsePlanFeatures, stripMarkup } from '@/shared/catalog'

describe('public catalog helpers', () => {
  it('normalizes array and envelope responses without inventing rows', () => {
    expect(extractRows([{ id: 1 }])).toEqual([{ id: 1 }])
    expect(extractRows({ data: [{ id: 2 }] })).toEqual([{ id: 2 }])
    expect(extractRows({ data: null })).toEqual([])
  })

  it('derives safe node labels from API fields', () => {
    expect(displayNodeCode({ name: 'Tokyo NRT', type: 'vless' })).toBe('NRT')
    expect(displayNodeCode({ name: 'edge', type: 'hysteria' })).toBe('HYST')
    expect(displayRate('1.25')).toBe('1.3×')
    expect(displayRate(null)).toBe('—')
  })

  it.each([
    ['Singapore SG', '/assets/flags/sg.svg'], ['US Edge', '/assets/flags/us.svg'], ['Hong Kong HK', '/assets/flags/hk.svg'],
    ['Hong Kong HKG', '/assets/flags/hk.svg'], ['Tokyo NRT', '/assets/flags/jp.svg'], ['Los Angeles LAX', '/assets/flags/us.svg'],
    ['Singapore SIN', '/assets/flags/sg.svg'], ['Frankfurt FRA', '/assets/flags/de.svg'], ['Unknown ZZ', '/assets/flags/world.svg'], ['No code', '/assets/flags/world.svg'],
  ])('maps node location %s to its flag', (name, flag) => {
    expect(displayNodeFlag({ name })).toBe(flag)
  })

  it('removes markup before using plan content as plain text', () => {
    expect(stripMarkup('<p>  100 GB </p><br>高速')).toBe('100 GB 高速')
  })

  it('reads Xboard plan feature JSON without treating markdown as JSON', () => {
    expect(parsePlanFeatures('[{"support":true,"feature":"流媒体解锁"},{"support":false,"feature":"专线"}]')).toEqual([
      { support: true, feature: '流媒体解锁' },
      { support: false, feature: '专线' },
    ])
    expect(parsePlanFeatures('#### 套餐详情')).toEqual([])
  })
})
