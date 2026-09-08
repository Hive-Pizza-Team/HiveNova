const JSON_HEADERS = { Accept: 'application/json' }

export class ApiError extends Error {
  constructor(error, status, message) {
    super(message || error)
    this.error = error
    this.status = status
  }
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
  const qs = new URLSearchParams({ r: resource, ...params })
  const res = await fetch(`/api.php?${qs}`, { credentials: 'same-origin', headers: JSON_HEADERS })
  return parse(res)
}

export async function apiPost(resource, action, fields, csrf) {
  const body = new URLSearchParams({ r: resource, action, ...fields })
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
