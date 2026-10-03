const JSON_HEADERS = { Accept: 'application/json' }

export class ApiError extends Error {
  constructor(error, status, message) {
    super(message || error)
    this.error = error
    this.status = status
  }
}

export function toForm(obj) {
  const params = new URLSearchParams()
  const walk = (value, key) => {
    if (value == null || value === '') return
    if (typeof value === 'object' && !Array.isArray(value)) {
      Object.entries(value).forEach(([k, v]) => walk(v, key ? `${key}[${k}]` : k))
      return
    }
    params.set(key, String(value))
  }
  Object.entries(obj).forEach(([k, v]) => walk(v, k))
  return params
}

async function parse(res) {
  const text = await res.text()
  let body
  try {
    body = JSON.parse(text)
  } catch {
    throw new ApiError('html', res.status, 'Expected JSON from api.php')
  }
  if (!body.ok) {
    throw new ApiError(body.error || 'error', res.status, body.message || '')
  }
  return body
}

export async function apiGet(resource, params = {}) {
  const qs = toForm({ r: resource, ...params })
  const res = await fetch(`/api.php?${qs}`, { credentials: 'same-origin', headers: JSON_HEADERS })
  return parse(res)
}

export async function apiPost(resource, action, fields, csrf) {
  const body = toForm({ r: resource, action, ...fields })
  const res = await fetch('/api.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      ...JSON_HEADERS,
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': csrf,
    },
    body,
  })
  return parse(res)
}

export function classicUrl(page) {
  return `/game.php?page=${encodeURIComponent(page)}&stay=1`
}

export function clearReactCookie() {
  document.cookie = 'hn_ui=;path=/;max-age=0;samesite=lax'
}

export function formatAmount(n) {
  const v = Number(n) || 0
  return Math.floor(v).toLocaleString()
}

export function formatEta(seconds) {
  const s = Math.max(0, Math.floor(Number(seconds) || 0))
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)
  const sec = s % 60
  if (h > 0) return `${h}h ${m}m`
  if (m > 0) return `${m}m ${sec}s`
  return `${sec}s`
}

export function formatWhen(unix) {
  const t = Number(unix) || 0
  if (!t) return ''
  return new Date(t * 1000).toLocaleString()
}

export function coords(c) {
  if (!c) return '—'
  return `[${c.galaxy}:${c.system}:${c.planet}]`
}

export function combatReportSrc(href) {
  try {
    const url = new URL(href, window.location.origin)
    const rid = url.searchParams.get('raport')
    if (!rid) return null
    const path = url.pathname
    if (path.includes('CombatReport.php') || url.searchParams.get('page') === 'raport') {
      return `/game.php?page=raport&raport=${encodeURIComponent(rid)}&stay=1`
    }
  } catch {
    return null
  }
  return null
}
