export function sanitizeHtml(value: string): string {
  const allowedTags = new Set(['A', 'BR', 'CODE', 'H2', 'H3', 'H4', 'HR', 'IMG', 'LI', 'P', 'PRE', 'STRONG', 'TABLE', 'TBODY', 'TD', 'TH', 'THEAD', 'TR', 'UL'])
  const blockedTags = new Set(['EMBED', 'FORM', 'IFRAME', 'MATH', 'OBJECT', 'SCRIPT', 'STYLE', 'SVG'])
  const template = document.createElement('template')
  template.innerHTML = value

  for (const element of Array.from(template.content.querySelectorAll('*'))) {
    if (blockedTags.has(element.tagName)) {
      element.remove()
      continue
    }
    if (!allowedTags.has(element.tagName)) {
      element.replaceWith(...Array.from(element.childNodes))
      continue
    }

    for (const attribute of Array.from(element.attributes)) {
      const allowed = (element.tagName === 'A' && ['href', 'title'].includes(attribute.name))
        || (element.tagName === 'IMG' && ['alt', 'src', 'title'].includes(attribute.name))
      if (!allowed) element.removeAttribute(attribute.name)
    }

    if (element instanceof HTMLAnchorElement) {
      if (!isSafeUrl(element.getAttribute('href'), ['http:', 'https:', 'mailto:'])) element.removeAttribute('href')
      element.target = '_blank'
      element.rel = 'noopener noreferrer'
    }
    if (element instanceof HTMLImageElement && !isSafeUrl(element.getAttribute('src'), ['http:', 'https:'])) {
      element.removeAttribute('src')
    }
  }

  return template.innerHTML
}

function isSafeUrl(value: string | null, protocols: string[]): boolean {
  if (!value) return false
  try { return protocols.includes(new URL(value, window.location.origin).protocol) } catch { return false }
}

export function renderRichText(value: unknown, title = ''): string {
  let html = String(value || '').replace(/\r\n?/g, '\n').trim()
  if (title) html = html.replace(new RegExp(`^#\\s+${title.replace(/[.*+?^${}()|[\]\\\\]/g, '\\\\$&')}\\s*\\n?`, 'i'), '')
  html = html.replace(/^###\s+(.+)$/gm, '<h4>$1</h4>').replace(/^##\s+(.+)$/gm, '<h3>$1</h3>').replace(/^#\s+(.+)$/gm, '<h2>$1</h2>')
  html = html.replace(/^```(?:\w+)?\n([\s\S]*?)\n```$/gm, '<pre><code>$1</code></pre>').replace(/^---$/gm, '<hr>')
  html = html.replace(/((?:^\|.*\|(?:\n|$)){2,})/gm, (table) => {
    const rows = table.trim().split('\n').map((row) => row.replace(/^\||\|$/g, '').split('|').map((cell) => cell.trim()))
    if (rows.length < 2 || !rows[1].every((cell) => /^:?-{3,}:?$/.test(cell))) return table
    const head = `<thead><tr>${rows[0].map((cell) => `<th>${cell}</th>`).join('')}</tr></thead>`
    const body = rows.slice(2).map((row) => `<tr>${rows[0].map((_, index) => `<td>${row[index] || ''}</td>`).join('')}</tr>`).join('')
    return `<table>${head}<tbody>${body}</tbody></table>`
  })
  html = html.replace(/^\s*[-*]\s+(.+)$/gm, '<li>$1</li>').replace(/(?:<li>[\s\S]*?<\/li>\s*)+/g, (list) => `<ul>${list}</ul>`)
  html = html.replace(/!\[([^\]]*)\]\(([^\s)]+)(?:\s+"[^"]*")?\)/g, '<img alt="$1" src="$2">').replace(/\[([^\]]+)\]\(([^\s)]+)(?:\s+"[^"]*")?\)/g, '<a href="$2" target="_blank" rel="noreferrer">$1</a>')
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/`([^`]+)`/g, '<code>$1</code>')
  html = html.split(/\n{2,}/).map((block) => /^<(h[234]|ul|pre|hr|img)/i.test(block.trim()) ? block : `<p>${block.replace(/\n/g, '<br>')}</p>`).join('')
  return sanitizeHtml(html)
}
