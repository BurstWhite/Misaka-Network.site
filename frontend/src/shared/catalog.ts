export interface PublicNode {
  id: string | number
  type?: string | null
  name?: string | null
  rate?: number | string | null
  tags?: string[] | null
  is_online?: boolean | number | null
  last_check_at?: number | string | null
}

export interface PublicPlan {
  id: string | number
  name?: string | null
  tags?: string[] | null
  content?: string | null
  month_price?: number | string | null
  year_price?: number | string | null
  transfer_enable?: number | string | null
  speed_limit?: number | string | null
  device_limit?: number | string | null
  capacity_limit?: number | string | null
}

export function extractRows<T>(value: unknown): T[] {
  if (Array.isArray(value)) return value as T[]
  if (value && typeof value === 'object' && Array.isArray((value as { data?: unknown }).data)) {
    return (value as { data: T[] }).data
  }
  return []
}

export function displayNodeCode(node: Pick<PublicNode, 'name' | 'type'>): string {
  const match = String(node?.name || '').match(/\b(HKG|NRT|LAX|SIN|FRA|[A-Z]{2})\b/i)
  return match?.[1]?.toUpperCase() || String(node?.type || 'NODE').slice(0, 4).toUpperCase()
}

export function displayRate(rate: PublicNode['rate']): string {
  if (rate === null || rate === undefined || rate === '') return '—'
  const value = Number(rate)
  return Number.isFinite(value) ? value.toFixed(1) + '×' : '—'
}

export function stripMarkup(value: unknown): string {
  return String(value || '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}
