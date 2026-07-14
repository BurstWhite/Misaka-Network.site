export type SupportedClient = 'clash' | 'shadowrocket' | 'v2rayn'

function base64Url(value: string): string {
  const bytes = new TextEncoder().encode(value)
  let binary = ''
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

export function clientImportUrl(client: SupportedClient, subscribeUrl: string, name = 'Misaka Subscription'): string {
  const url = encodeURIComponent(subscribeUrl)
  const remark = encodeURIComponent(name)
  return {
    clash: `clash-verge://install-config?name=${remark}&url=${url}`,
    shadowrocket: `shadowrocket://add/sub://${base64Url(subscribeUrl)}?remark=${remark}`,
    v2rayn: `v2rayn://install-config?url=${url}`,
  }[client]
}
