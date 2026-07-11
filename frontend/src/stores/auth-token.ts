const TOKEN_KEY = 'misaka.access_token'

function cookieToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)access_token=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function parseLegacy(raw: string | null): string {
  if (!raw) return ''
  try {
    const parsed = JSON.parse(raw)
    if (typeof parsed === 'string') return parsed
    if (typeof parsed?.value === 'string') {
      if (parsed.expire && Number(parsed.expire) < Date.now()) return ''
      return parsed.value
    }
  } catch { return raw }
  return ''
}

export function readToken(): string {
  const canonical = localStorage.getItem(TOKEN_KEY)
  if (canonical) return canonical
  const legacy = cookieToken() || parseLegacy(localStorage.getItem('Vue_Naive_access_token')) || localStorage.getItem('access_token') || ''
  if (legacy) localStorage.setItem(TOKEN_KEY, legacy)
  return legacy
}

export function saveToken(value: string): void { localStorage.setItem(TOKEN_KEY, value) }
export function clearToken(): void { localStorage.removeItem(TOKEN_KEY) }
