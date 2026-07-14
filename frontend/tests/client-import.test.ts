import { describe, expect, it } from 'vitest'
import { clientImportUrl } from '@/shared/client-import'

describe('client import links', () => {
  const subscription = 'https://misaka-network.site/sub/~p%40E?token=%7Ep%40E'

  it('uses the Shadowrocket-specific subscription response and canonical Base64 URI', () => {
    const link = clientImportUrl('shadowrocket', subscription, '御坂网络')
    const match = link.match(/^shadowrocket:\/\/add\/sub:\/\/([^?]+)\?remark=(.+)$/)
    expect(match).not.toBeNull()
    const decoded = Buffer.from(match![1], 'base64').toString()
    expect(new URL(decoded).searchParams.get('flag')).toBe('shadowrocket')
    expect(decoded).toBe('https://misaka-network.site/sub/~p%40E?token=%7Ep%40E&flag=shadowrocket')
    expect(match![1]).toBe(Buffer.from(decoded).toString('base64'))
    expect(match![1]).toContain('+')
    expect(match![1]).toContain('/')
    expect(match![1]).toMatch(/=+$/)
    expect(decodeURIComponent(match![2])).toBe('御坂网络')
  })

  it('uses the dedicated Clash Verge scheme with the URL last', () => {
    expect(clientImportUrl('clash', subscription, 'Misaka Network')).toBe(`clash-verge://install-config?name=Misaka%20Network&url=${encodeURIComponent(subscription)}`)
    expect(clientImportUrl('v2rayn', subscription)).toContain(`url=${encodeURIComponent(subscription)}`)
  })

  it('does not parse subscription URLs for clients that accept opaque values', () => {
    expect(() => clientImportUrl('clash', '/relative-subscription')).not.toThrow()
    expect(clientImportUrl('shadowrocket', subscription, '')).toContain('remark=Misaka%20Subscription')
  })
})
