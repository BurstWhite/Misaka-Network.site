export interface TrafficRecord {
  u?: unknown
  d?: unknown
  record_at?: unknown
}

export interface TrafficDay {
  key: string
  label: string
  timestamp: number
  u: number
  d: number
  amount: number
}

function positiveNumber(value: unknown): number {
  const number = Number(value)
  return Number.isFinite(number) && number > 0 ? number : 0
}

function dayStart(value: Date): Date {
  return new Date(value.getFullYear(), value.getMonth(), value.getDate())
}

function dayKey(value: Date): string {
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')
  return `${value.getFullYear()}-${month}-${day}`
}

function dayLabel(value: Date): string {
  return `${value.getMonth() + 1}.${value.getDate()}`
}

export function trafficTimestamp(value: unknown): number | null {
  if (typeof value === 'string' && value.trim() && !/^\d+(\.\d+)?$/.test(value.trim())) {
    const parsed = Date.parse(value)
    return Number.isNaN(parsed) ? null : parsed
  }

  const number = Number(value)
  if (!Number.isFinite(number) || number <= 0) return null
  return number < 1_000_000_000_000 ? number * 1000 : number
}

export function aggregateTrafficByDay(
  records: unknown,
  dayCount = 7,
  now = new Date(),
): TrafficDay[] {
  const count = Math.max(1, Math.floor(dayCount))
  const today = dayStart(now)
  const firstDay = new Date(today)
  firstDay.setDate(firstDay.getDate() - count + 1)

  const days = Array.from({ length: count }, (_, index) => {
    const date = new Date(firstDay)
    date.setDate(firstDay.getDate() + index)
    return {
      key: dayKey(date),
      label: dayLabel(date),
      timestamp: date.getTime(),
      u: 0,
      d: 0,
      amount: 0,
    }
  })
  const byKey = new Map(days.map((day) => [day.key, day]))

  for (const value of Array.isArray(records) ? records : []) {
    if (!value || typeof value !== 'object') continue
    const record = value as TrafficRecord
    const timestamp = trafficTimestamp(record.record_at)
    if (timestamp === null) continue

    const day = byKey.get(dayKey(new Date(timestamp)))
    if (!day) continue
    day.u += positiveNumber(record.u)
    day.d += positiveNumber(record.d)
    day.amount = day.u + day.d
  }

  return days
}
