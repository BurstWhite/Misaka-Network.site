export type SupportedClient = 'clash' | 'shadowrocket' | 'v2rayn'

function base64(value: string): string {
  const bytes = new TextEncoder().encode(value)
  let binary = ''
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary)
}

export function clientImportUrl(client: SupportedClient, subscribeUrl: string, name = 'Misaka Subscription'): string {
  const url = encodeURIComponent(subscribeUrl)
  const remark = encodeURIComponent(name || 'Misaka Subscription')

  if (client === 'shadowrocket') {
    const shadowrocketUrl = new URL(subscribeUrl)
    shadowrocketUrl.searchParams.set('flag', 'shadowrocket')
    return `shadowrocket://add/sub://${base64(shadowrocketUrl.toString())}?remark=${remark}`
  }

  return client === 'clash'
    ? `clash-verge://install-config?name=${remark}&url=${url}`
    : `v2rayn://install-config?url=${url}`
}
