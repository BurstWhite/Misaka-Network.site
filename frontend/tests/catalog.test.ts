import { describe, expect, it } from 'vitest'
import { displayNodeCode, displayRate, extractRows, stripMarkup } from '@/shared/catalog'

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

  it('removes markup before using plan content as plain text', () => {
    expect(stripMarkup('<p>  100 GB </p><br>高速')).toBe('100 GB 高速')
  })
})
