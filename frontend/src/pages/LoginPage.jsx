import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { apiGet } from '../api/client.js'
import { planetSrc } from '../api/assets.js'

export default function LoginPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const failed = params.get('code') === '1'
  const [gameName, setGameName] = useState('')

  useEffect(() => {
    let cancelled = false
    apiGet('config')
      .then((body) => {
        if (cancelled) return
        const name = (body.data?.gameName || '').trim()
        if (name) {
          setGameName(name)
          document.title = name
        }
      })
      .catch(() => {})
    apiGet('bootstrap')
      .then(() => {
        if (!cancelled) {
          navigate('/overview', { replace: true })
        }
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [navigate])

  return (
    <div className="hn-login" style={{ backgroundImage: `linear-gradient(180deg, rgba(7,11,20,.45), var(--bg) 70%), url(${planetSrc('gasplanet03')})` }}>
      <div className="hn-login-panel">
        <p className="hn-kicker">{gameName || 'Command deck'}</p>
        <h1>Command deck</h1>
        <p className="hn-muted">Same accounts as the classic game. Hive Keychain still signs on the PHP lobby if you need it.</p>
        {failed ? <p className="hn-error">Wrong username/password. Make sure you register first.</p> : null}
        <form className="hn-form" method="post" action="/index.php?page=login">
          <label>
            Commander
            <input name="username" autoComplete="username" required />
          </label>
          <label>
            Password
            <input name="password" type="password" autoComplete="current-password" required />
          </label>
          <button type="submit">Enter</button>
        </form>
        <p>
          <a href="/index.php">Classic lobby</a>
        </p>
      </div>
    </div>
  )
}
