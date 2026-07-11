import { beforeEach, describe, expect, it } from 'vitest'
import { applyTheme, getStoredTheme, resolveTheme, THEME_KEY } from '@/stores/theme'

describe('three-state theme', () => {
  beforeEach(() => { localStorage.clear(); document.documentElement.className = ''; (document.documentElement.classList as any).values.clear() })
  it('defaults to system', () => expect(getStoredTheme()).toBe('system'))
  it('keeps explicit light and dark choices', () => { localStorage.setItem(THEME_KEY, 'dark'); expect(getStoredTheme()).toBe('dark'); localStorage.setItem(THEME_KEY, 'light'); expect(getStoredTheme()).toBe('light') })
  it('applies the resolved class and color scheme', () => { applyTheme('dark'); expect(document.documentElement.classList.contains('dark')).toBe(true); expect(document.documentElement.style.colorScheme).toBe('dark'); applyTheme('light'); expect(document.documentElement.classList.contains('dark')).toBe(false) })
  it('resolves system without changing the stored mode', () => { expect(resolveTheme('system')).toBe('light'); expect(getStoredTheme()).toBe('system') })
})
