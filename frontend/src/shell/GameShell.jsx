import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { ApiError, apiGet, classicUrl, clearReactCookie, formatAmount } from '../api/client.js'
import { resourceIconSrc } from '../api/assets.js'
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
        const name = (body.data.gameName || '').trim()
        if (name) {
          document.title = name
        }
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
    return <div className="hn-page hn-muted hn-kicker">Loading commander uplink…</div>
  }

  const { data } = state
  const i18n = data.i18n || {}
  const res = data.resources || {}

  async function switchPlanet(id) {
    const body = await apiGet('bootstrap', { planetId: id })
    setState(body)
    const name = (body.data.gameName || '').trim()
    if (name) {
      document.title = name
    }
  }

  return (
    <GameState.Provider value={{ ...state, switchPlanet }}>
      <div className="hn-app">
        <aside className="hn-nav">
          <Link className="hn-brand" to="/overview">{data.gameName || 'HiveNova'}</Link>
          <NavLink to="/overview" className={({ isActive }) => (isActive ? 'active' : undefined)}>
            {i18n.lm_overview || 'Overview'}
          </NavLink>
          <NavLink to="/empire">{i18n.lm_empire || 'Empire'}</NavLink>
          <NavLink to="/buildings">{i18n.lm_buildings || 'Buildings'}</NavLink>
          <NavLink to="/research">{i18n.lm_research || 'Research'}</NavLink>
          <NavLink to="/shipyard">{i18n.lm_shipshard || 'Shipyard'}</NavLink>
          <NavLink to="/fleetTable">{i18n.lm_fleet || 'Fleet'}</NavLink>
          <NavLink to="/galaxy">{i18n.lm_galaxy || 'Galaxy'}</NavLink>
          <NavLink to="/messages">{i18n.lm_messages || 'Messages'}</NavLink>
          {data.user.isStaff ? <a href="/admin.php">{i18n.lm_administration || 'Admin'}</a> : null}
          <a href="/game.php?page=logout">Logout</a>
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
            <select value={data.planet.id} onChange={(e) => switchPlanet(e.target.value)}>
              {data.planets.map((pl) => (
                <option key={pl.id} value={pl.id}>
                  {pl.name} [{pl.galaxy}:{pl.system}:{pl.planet}]
                </option>
              ))}
            </select>
            <span className={data.attackAlertCount > 0 ? 'hn-alert' : ''}>
              inbound {data.attackAlertCount}
            </span>
            {[901, 902, 903, 911, 921].map((id) => {
              const row = res[id]
              const used = id === 911 ? Number(row?.production) || 0 : null
              const label = id === 921 ? 'Pizzabits' : id === 911 ? 'Energy' : row?.name || ''
              return (
                <span key={id} className="hn-res" title={label}>
                  <img src={resourceIconSrc(id)} alt={label} width="18" height="18" />
                  {id === 911
                    ? `${formatAmount(Math.abs(used))} / ${formatAmount(row?.current)}`
                    : formatAmount(row?.current)}
                </span>
              )
            })}
          </header>
          <Outlet />
        </main>
      </div>
    </GameState.Provider>
  )
}
