import { useEffect, useState } from 'react'
import { Outlet, useNavigate } from 'react-router'
import { ApiError, apiGet, classicUrl, clearReactCookie } from '../api/client.js'
import { GameState } from './GameState.jsx'

function fireCron(ids) {
  if (!Array.isArray(ids)) return
  ids.forEach((id) => {
    const img = new Image()
    img.src = `/cronjob.php?cronjobID=${encodeURIComponent(id)}`
  })
}

export default function GameShell() {
  const navigate = useNavigate()
  const [state, setState] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    let cancelled = false
    apiGet('bootstrap')
      .then((body) => {
        if (cancelled) return
        setState(body)
        fireCron(body.data.cronjobs)
      })
      .catch((err) => {
        if (cancelled) return
        if (err instanceof ApiError && err.status === 401) {
          navigate('/', { replace: true })
          return
        }
        setError(err.message || 'bootstrap failed')
      })
    return () => {
      cancelled = true
    }
  }, [navigate])

  if (error) {
    return (
      <div className="hn-page">
        <p className="hn-error">{error}</p>
        <a href={classicUrl('overview')}>Open classic UI</a>
      </div>
    )
  }

  if (!state) {
    return <div className="hn-page hn-muted">Loading commander uplink…</div>
  }

  const { data } = state
  const i18n = data.i18n || {}

  return (
    <GameState.Provider value={state}>
      <div className="hn-app">
        <aside className="hn-nav">
          <a className="hn-brand" href="/react/overview">HiveNova</a>
          <a href="/react/overview">{i18n.lm_overview || 'Overview'}</a>
          <a href={classicUrl('buildings')}>{i18n.lm_buildings || 'Buildings'}</a>
          <a href={classicUrl('research')}>{i18n.lm_research || 'Research'}</a>
          <a href={classicUrl('shipyard')}>{i18n.lm_shipshard || 'Shipyard'}</a>
          <a href={classicUrl('fleetTable')}>{i18n.lm_fleet || 'Fleet'}</a>
          <a href={classicUrl('galaxy')}>{i18n.lm_galaxy || 'Galaxy'}</a>
          <a href={classicUrl('messages')}>{i18n.lm_messages || 'Messages'}</a>
          {data.user.isStaff ? <a href="/admin.php">{i18n.lm_administration || 'Admin'}</a> : null}
          <button
            type="button"
            className="hn-linkish"
            onClick={() => {
              clearReactCookie()
              window.location.href = classicUrl('overview')
            }}
          >
            {i18n.hn_classic_ui || 'Classic version'}
          </button>
        </aside>
        <main className="hn-main">
          <header className="hn-top">
            <strong>{data.user.username}</strong>
            <span>
              {data.planet.name} [{data.planet.galaxy}:{data.planet.system}:{data.planet.planet}]
            </span>
            <span className={data.attackAlertCount > 0 ? 'hn-alert' : ''}>
              inbound {data.attackAlertCount}
            </span>
          </header>
          <Outlet />
        </main>
      </div>
    </GameState.Provider>
  )
}
