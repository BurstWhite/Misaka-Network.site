import { describe, expect, it } from 'vitest'; import { bytes,money,orderStatus } from '@/shared/format'
describe('formatters',()=>{it('formats traffic',()=>expect(bytes(1073741824)).toBe('1.00 GB'));it('formats cents',()=>expect(money(6800)).toBe('¥ 68.00'));it('maps order status',()=>expect(orderStatus(3)).toBe('已完成'))})
