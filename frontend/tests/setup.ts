import { vi } from 'vitest'
const storage = new Map<string, string>()
Object.assign(globalThis, {
  localStorage: { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key), clear: () => storage.clear() },
  document: { cookie: '', createElement: () => ({ content: { firstChild: null } }), documentElement: { className: '', classList: { values: new Set<string>(), toggle(name: string, active: boolean) { if (active) this.values.add(name); else this.values.delete(name) }, contains(name: string) { return this.values.has(name) } }, dataset: {}, style: {} } },
  window: globalThis,
  matchMedia: vi.fn().mockImplementation((query) => ({ matches: false, media: query, addEventListener: vi.fn(), removeEventListener: vi.fn() })),
})
