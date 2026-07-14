import { runtimeConfig } from '@/app/config'

export function content(key: string, fallback: string): string {
  const value = runtimeConfig.content?.[key]
  return typeof value === 'string' && value.trim() ? value : fallback
}
