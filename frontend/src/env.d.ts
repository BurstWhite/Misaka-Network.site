/// <reference types="vite/client" />

interface MisakaRuntimeConfig {
  apiBase: string
  assetsBase: string
  appName: string
  description: string
  logo: string | null
  version: string
  supportedLocales: string[]
  theme: { primaryColor: string; backgroundUrl?: string }
  content: Record<string, string>
  features: Record<string, boolean>
}

interface Window { __MISAKA_CONFIG__?: MisakaRuntimeConfig }
