import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { ApiError, apiGet, classicUrl, clearReactCookie, formatAmount } from '../api/client.js'
import { resourceIconSrc } from '../api/assets.js'
import { MODULE, hasModule } from '../api/modules.js'
import { GameState } from './GameState.jsx'

const POLL_MS = 20000
const TICK_MS = 1000

function fireCron(ids) {
  if (!Array.isArray(ids)) return
  ids.forEach((id) => {
    const img = new Image()
    img.src = `/cronjob.php?cronjobID=${encodeURIComponent(id)}`
  })
}

function applyTitle(name) {
  const trimmed = (name || '').trim()
  if (trimmed) document.title = trimmed
}

function tickResources(resources) {
  if (!resources) return resources
  const next = { ...resources }
  ;[901, 902, 903].forEach((id) => {
    const row = next[id]
    if (!row) return
    const production = Number(row.production) || 0
    const max = Number(row.max)
    let current = (Number(row.current) || 0) + production / 3600
    if (Number.isFinite(max) && max > 0) {
      current = Math.min(max, current)
    }
    next[id] = { ...row, current }
  })
  return next
}

function NavGroup({ label, children }) {
  const items = Array.isArray(children) ? children.filter(Boolean) : children
  if (!items || (Array.isArray(items) && items.length === 0)) return null
  return (
    <div className="hn-nav-group">
      <p className="hn-nav-kicker">{label}</p>
      {items}
    </div>
  )
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
        applyTitle(body.data.gameName)
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

  useEffect(() => {
    if (!state) return undefined
    let cancelled = false
    const poll = () => {
      if (cancelled || document.hidden) return
      apiGet('alerts')
        .then((body) => {
          if (cancelled) return
          setState((prev) => {
            if (!prev) return prev
            const count = Number(body.data?.count)
            const unread = Number(body.data?.unreadMessages)
            return {
              ...prev,
              data: {
                ...prev.data,
                attackAlertCount: Number.isFinite(count) ? count : prev.data.attackAlertCount,
                user: {
                  ...prev.data.user,
                  unreadMessages: Number.isFinite(unread) ? unread : prev.data.user.unreadMessages,
                },
              },
            }
          })
        })
        .catch(() => {})
    }
    poll()
    const timer = setInterval(poll, POLL_MS)
    return () => {
      cancelled = true
      clearInterval(timer)
    }
  }, [state ? 'ready' : ''])

  useEffect(() => {
    if (!state) return undefined
    const timer = setInterval(() => {
      if (document.hidden) return
      setState((prev) => {
        if (!prev?.data?.resources) return prev
        return { ...prev, data: { ...prev.data, resources: tickResources(prev.data.resources) } }
      })
    }, TICK_MS)
    return () => clearInterval(timer)
  }, [state ? 'ready' : ''])

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
  const modules = data.modules || []
  const unread = data.user.unreadMessages || 0

  async function switchPlanet(id) {
    const body = await apiGet('bootstrap', { planetId: id })
    setState(body)
    applyTitle(body.data.gameName)
  }

  async function reload() {
    const body = await apiGet('bootstrap', { planetId: data.planet.id })
    setState(body)
    applyTitle(body.data.gameName)
  }

  return (
    <GameState.Provider value={{ ...state, switchPlanet, reload }}>
      <div className="hn-app">
        <aside className="hn-nav">
          <Link className="hn-brand" to="/overview">{data.gameName || 'Command'}</Link>
          <NavGroup label="Command">
            <NavLink to="/overview" className={({ isActive }) => (isActive ? 'active' : undefined)}>
              {i18n.lm_overview || 'Overview'}
            </NavLink>
            {hasModule(modules, MODULE.IMPERIUM) ? (
              <NavLink to="/empire">{i18n.lm_empire || 'Empire'}</NavLink>
            ) : null}
          </NavGroup>
          <NavGroup label="Yard">
            {hasModule(modules, MODULE.BUILDING) ? (
              <NavLink to="/buildings">{i18n.lm_buildings || 'Buildings'}</NavLink>
            ) : null}
            {hasModule(modules, MODULE.RESEARCH) ? (
              <NavLink to="/research">{i18n.lm_research || 'Research'}</NavLink>
            ) : null}
            {hasModule(modules, MODULE.SHIPYARD_FLEET) ? (
              <NavLink to="/shipyard">{i18n.lm_shipshard || 'Shipyard'}</NavLink>
            ) : null}
            {hasModule(modules, MODULE.SHIPYARD_DEFENSIVE) ? (
              <NavLink to="/defense">{i18n.lm_defenses || 'Defenses'}</NavLink>
            ) : null}
            {hasModule(modules, MODULE.RESOURCES) ? (
              <a href={classicUrl('resources')}>{i18n.lm_resources || 'Resources'}</a>
            ) : null}
            {hasModule(modules, MODULE.OFFICIER) ? (
              <a href={classicUrl('officier')}>{i18n.lm_officiers || 'Officers'}</a>
            ) : null}
            {hasModule(modules, MODULE.TRADER) ? (
              <NavLink to="/trader">{i18n.lm_trader || 'Market'}</NavLink>
            ) : null}
          </NavGroup>
          <NavGroup label="Ops">
            {hasModule(modules, MODULE.FLEET_TABLE) ? (
              <NavLink to="/fleetTable">{i18n.lm_fleet || 'Fleet'}</NavLink>
            ) : null}
            {hasModule(modules, MODULE.GALAXY) ? (
              <NavLink to="/galaxy">{i18n.lm_galaxy || 'Galaxy'}</NavLink>
            ) : null}
            {hasModule(modules, MODULE.MESSAGES) ? (
              <NavLink to="/messages">
                {i18n.lm_messages || 'Messages'}
                {unread > 0 ? <span className="hn-nav-count">{unread}</span> : null}
              </NavLink>
            ) : null}
          </NavGroup>
          <NavGroup label="Intel">
            {hasModule(modules, MODULE.ALLIANCE) ? (
              <a href={classicUrl('alliance')}>{i18n.lm_alliance || 'Alliance'}</a>
            ) : null}
            {hasModule(modules, MODULE.STATISTICS) ? (
              <a href={classicUrl('statistics')}>{i18n.lm_statistics || 'Statistics'}</a>
            ) : null}
            {hasModule(modules, MODULE.SUPPORT) ? (
              <a href={classicUrl('ticket')}>{i18n.lm_support || 'Tickets'}</a>
            ) : null}
            <a href={classicUrl('settings')}>{i18n.lm_options || 'Settings'}</a>
          </NavGroup>
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
