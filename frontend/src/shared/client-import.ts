export type SupportedClient = 'clash' | 'shadowrocket' | 'v2rayn'

export function clientImportUrl(client: SupportedClient, subscribeUrl: string, name = 'Misaka Subscription'): string {
  const url = encodeURIComponent(subscribeUrl)
  const remark = encodeURIComponent(name)
  return {
    clash: `clash://install-config?url=${url}&name=${remark}`,
    shadowrocket: `shadowrocket://add/sub://${url}?remark=${remark}`,
    v2rayn: `v2rayn://install-config?url=${url}`,
  }[client]
}
