import { describe, expect, it } from 'vitest'
import { clientImportUrl } from '@/shared/client-import'

describe('client import links', () => {
  const subscription = 'https://misaka-network.site/sub?token=a+b&mode=full'

  it('uses a Base64URL Shadowrocket subscription URI and site remark', () => {
    const link = clientImportUrl('shadowrocket', subscription, '御坂网络')
    const match = link.match(/^shadowrocket:\/\/add\/sub:\/\/([^?]+)\?remark=(.+)$/)
    expect(match).not.toBeNull()
    expect(Buffer.from(match![1], 'base64url').toString()).toBe(subscription)
    expect(decodeURIComponent(match![2])).toBe('御坂网络')
  })

  it('uses the dedicated Clash Verge scheme with the URL last', () => {
    expect(clientImportUrl('clash', subscription, 'Misaka Network')).toBe(`clash-verge://install-config?name=Misaka%20Network&url=${encodeURIComponent(subscription)}`)
    expect(clientImportUrl('v2rayn', subscription)).toContain(`url=${encodeURIComponent(subscription)}`)
  })
})
