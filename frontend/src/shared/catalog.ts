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

export interface PlanContentFeature {
  feature: string
  support: boolean
}

export function extractRows<T>(value: unknown): T[] {
  if (Array.isArray(value)) return value as T[]
  if (value && typeof value === 'object' && Array.isArray((value as { data?: unknown }).data)) {
    return (value as { data: T[] }).data
  }
  return []
}

function nodeLocationCode(name: PublicNode['name']): string | undefined {
  return String(name || '').match(/\b(HKG|NRT|LAX|SIN|FRA|[A-Z]{2})\b/i)?.[1]?.toUpperCase()
}

export function displayNodeCode(node: Pick<PublicNode, 'name' | 'type'>): string {
  return nodeLocationCode(node?.name) || String(node?.type || 'NODE').slice(0, 4).toUpperCase()
}

const nodeFlags: Record<string, string> = {
  HK: 'hk', HKG: 'hk',
  JP: 'jp', NRT: 'jp',
  US: 'us', LAX: 'us',
  SG: 'sg', SIN: 'sg',
  DE: 'de', FRA: 'de',
}

export function displayNodeFlag(node: Pick<PublicNode, 'name'>): string {
  const code = nodeLocationCode(node?.name)
  const flag = code ? nodeFlags[code] : undefined
  return `/assets/flags/${flag || 'world'}.svg`
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

export function parsePlanFeatures(value: unknown): PlanContentFeature[] {
  if (typeof value !== 'string') return []
  try {
    const rows = JSON.parse(value)
    if (!Array.isArray(rows)) return []
    return rows
      .filter((row): row is { feature: unknown, support?: unknown } => row && typeof row === 'object' && String(row.feature || '').trim().length > 0)
      .map((row) => ({ feature: String(row.feature).trim(), support: Boolean(row.support) }))
  } catch {
    return []
  }
}
