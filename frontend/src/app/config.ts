export const runtimeConfig: MisakaRuntimeConfig = window.__MISAKA_CONFIG__ ?? {
  apiBase: '/api/v1',
  assetsBase: '/theme/Misaka/assets',
  appName: 'Misaka Network',
  description: 'Reliable global connectivity',
  logo: null,
  version: 'development',
  supportedLocales: ['zh-CN', 'zh-TW', 'en-US', 'ja-JP', 'vi-VN', 'ko-KR', 'ru-RU', 'fa-IR'],
  theme: { primaryColor: '#3155ee' },
  content: {},
  features: {},
}

export let currencySymbol = '¥'
export function setCurrencySymbol(value: unknown): void {
  if (typeof value === 'string' && value.trim()) currencySymbol = value.trim()
}
