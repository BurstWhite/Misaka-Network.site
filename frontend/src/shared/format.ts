import { currencySymbol } from '@/app/config'

export function bytes(value: number): string { if (!value) return '0 GB'; const gb = value / 1024 ** 3; return gb >= 1024 ? `${(gb / 1024).toFixed(2)} TB` : `${gb.toFixed(2)} GB` }
export function money(value: number | string | undefined, symbol = currencySymbol): string { const number = Number(value || 0) / 100; return `${symbol} ${number.toFixed(2)}` }
export function date(value: number | string | undefined, time = false): string { if (!value) return '—'; const stamp = typeof value === 'number' && value < 1e12 ? value * 1000 : value; const parsed = new Date(stamp); if (Number.isNaN(parsed.getTime())) return String(value); return new Intl.DateTimeFormat('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', ...(time ? { hour: '2-digit', minute: '2-digit' } : {}) }).format(parsed) }
export function orderStatus(value: number): string { return ['待支付', '开通中', '已取消', '已完成', '已折抵'][Number(value)] || '未知' }
export const periods: Record<string, string> = { month_price: '月付', quarter_price: '季付', half_year_price: '半年付', year_price: '年付', two_year_price: '两年付', three_year_price: '三年付', onetime_price: '一次性', reset_price: '流量重置包' }
