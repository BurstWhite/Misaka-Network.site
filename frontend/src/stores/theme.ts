import { defineStore } from 'pinia'

export type ThemeMode = 'system' | 'light' | 'dark'
export const THEME_KEY = 'misaka.theme-mode'
const media = () => window.matchMedia('(prefers-color-scheme: dark)')

export function getStoredTheme(): ThemeMode {
  const value = localStorage.getItem(THEME_KEY)
  return value === 'light' || value === 'dark' || value === 'system' ? value : 'system'
}

export function resolveTheme(mode: ThemeMode): 'light' | 'dark' {
  return mode === 'system' ? (media().matches ? 'dark' : 'light') : mode
}

export function applyTheme(mode: ThemeMode): void {
  const resolved = resolveTheme(mode)
  document.documentElement.classList.toggle('dark', resolved === 'dark')
  document.documentElement.dataset.theme = resolved
  document.documentElement.style.colorScheme = resolved
}

export function applyStoredTheme(): void { applyTheme(getStoredTheme()) }

export const useThemeStore = defineStore('theme', {
  state: () => ({ mode: getStoredTheme() as ThemeMode, listener: null as ((event: MediaQueryListEvent) => void) | null }),
  getters: { resolved: (state) => resolveTheme(state.mode) },
  actions: {
    setMode(mode: ThemeMode) { this.mode = mode; localStorage.setItem(THEME_KEY, mode); applyTheme(mode) },
    start() {
      if (this.listener) return
      this.listener = () => { if (this.mode === 'system') applyTheme('system') }
      media().addEventListener('change', this.listener)
      applyTheme(this.mode)
    },
  },
})
