import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { apiGet } from '../api/client.js'
import { planetSrc } from '../api/assets.js'

function keychainPresent() {
  return typeof window !== 'undefined' && typeof window.hive_keychain !== 'undefined'
}

export default function LoginPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const failed = params.get('code') === '1'
  const [gameName, setGameName] = useState('')
  const [hiveName, setHiveName] = useState('')
  const [hiveError, setHiveError] = useState('')
  const [hasKeychain, setHasKeychain] = useState(keychainPresent)

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

  useEffect(() => {
    const check = () => setHasKeychain(keychainPresent())
    check()
    const timer = setInterval(check, 400)
    const stop = setTimeout(() => clearInterval(timer), 5000)
    return () => {
      clearInterval(timer)
      clearTimeout(stop)
    }
  }, [])

  function signWithKeychain(e) {
    e.preventDefault()
    setHiveError('')
    const hiveaccount = hiveName.toLowerCase().trim()
    if (!hiveaccount || hiveaccount.length > 16) {
      setHiveError('Enter a valid Hive account name.')
      return
    }
    if (!keychainPresent()) {
      setHiveError('Install the Hive Keychain browser extension first.')
      return
    }
    window.hive_keychain.requestSignBuffer(
      hiveaccount,
      `${hiveaccount} is my account.`,
      'Posting',
      (response) => {
        if (!response?.success) {
          setHiveError(response?.error || 'Keychain rejected the signature.')
          return
        }
        const form = document.getElementById('loginHive')
        if (!form) return
        form.querySelector('[name="username"]').value = hiveaccount
        form.querySelector('[name="password"]').value = response.result
        form.submit()
      },
      null,
      'Moon Login',
    )
  }

  return (
    <div className="hn-login" style={{ backgroundImage: `linear-gradient(180deg, rgba(7,11,20,.45), var(--bg) 70%), url(${planetSrc('gasplanet03')})` }}>
      <div className="hn-login-panel">
        <p className="hn-kicker">{gameName || 'Command deck'}</p>
        <h1>Command deck</h1>
        <p className="hn-muted">Same accounts as the classic game.</p>
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
        <div className="hn-login-hive">
          <p className="hn-kicker">Hive Keychain</p>
          {hasKeychain ? (
            <p className="hn-muted">Sign with the same Hive account linked in Settings.</p>
          ) : (
            <p className="hn-muted">
              Install{' '}
              <a href="https://hive-keychain.com/" target="_blank" rel="noreferrer">
                Hive Keychain
              </a>{' '}
              to sign in here.
            </p>
          )}
          <form id="loginHive" className="hn-form" method="post" action="/index.php?page=login" onSubmit={signWithKeychain}>
            <label>
              Hive account
              <input
                name="username"
                value={hiveName}
                maxLength={16}
                autoComplete="username"
                onChange={(e) => setHiveName(e.target.value)}
              />
            </label>
            <input name="password" type="hidden" />
            <button type="submit" className="hn-keychain">
              <img src="/styles/resource/images/login/keychain-round-logo.svg" alt="" width="22" height="22" />
              Sign with Keychain
            </button>
          </form>
          {hiveError ? <p className="hn-error">{hiveError}</p> : null}
        </div>
        <p>
          <a href="/index.php?page=register">Register</a>
          {' · '}
          <a href="/index.php">Classic lobby</a>
        </p>
      </div>
    </div>
  )
}
