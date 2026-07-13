import { describe, expect, it } from 'vitest'
import { clientImportUrl } from '@/shared/client-import'

describe('client import links', () => {
  const subscription = 'https://misaka-network.site/sub?token=a+b&mode=full'

  it('uses the Xboard-compatible Shadowrocket subscription URI', () => {
    expect(clientImportUrl('shadowrocket', subscription)).toBe('shadowrocket://add/sub://https%3A%2F%2Fmisaka-network.site%2Fsub%3Ftoken%3Da%2Bb%26mode%3Dfull?remark=Misaka%20Subscription')
  })

  it('encodes desktop client subscription URLs', () => {
    expect(clientImportUrl('clash', subscription)).toContain(`url=${encodeURIComponent(subscription)}`)
    expect(clientImportUrl('v2rayn', subscription)).toContain(`url=${encodeURIComponent(subscription)}`)
  })
})
