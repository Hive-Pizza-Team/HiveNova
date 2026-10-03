import { useContext, useEffect, useState } from 'react'
import { Link } from 'react-router'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost, coords, formatAmount, formatEta } from '../api/client.js'
import { resourceIconSrc } from '../api/assets.js'
import { PlanetThumb, GebaeudeThumb } from '../ui/Thumbs.jsx'

function QueueLine({ label, to, item, yard }) {
  if (!item) {
    return (
      <p className="hn-queue-line">
        <Link to={to}>{label}</Link>
        <span className="hn-muted">idle</span>
      </p>
    )
  }
  return (
    <p className="hn-queue-line">
      <Link to={to}>{label}</Link>
      <GebaeudeThumb id={item.elementId} size={28} />
      <span>
        {item.name || `#${item.elementId}`}
        {yard ? ` × ${item.count}` : item.level ? ` → ${item.level}` : ''}
      </span>
      <span className="hn-muted">{formatEta(item.resttime)}</span>
    </p>
  )
}

export default function OverviewPage() {
  const ctx = useContext(GameState)
  const data = ctx?.data
  const [detail, setDetail] = useState(null)
  const [fleets, setFleets] = useState([])
  const [name, setName] = useState(data?.planet?.name || '')
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)

  const planetId = data?.planet?.id

  useEffect(() => {
    if (!ctx) return
    let cancelled = false
    Promise.all([
      apiGet('overview', { planetId }),
      apiGet('fleet', { planetId }).catch(() => ({ data: { fleets: [] } })),
    ]).then(([ov, fl]) => {
      if (cancelled) return
      setDetail(ov.data)
      if (ov.data?.planet?.name) setName(ov.data.planet.name)
      setFleets(fl.data?.fleets || [])
    }).catch(() => {})
    return () => {
      cancelled = true
    }
  }, [ctx, planetId])

  if (!data) return null

  const planet = detail?.planet || data.planet
  const username = detail?.username || data.user.username
  const queues = detail?.queues || {}
  const colonies = detail?.colonies || data.planets || []
  const moon = detail?.moon
  const res = data.resources || {}
  const typeLabel = Number(planet.type) === 3 ? 'Moon' : 'Planet'

  async function rename(e) {
    e.preventDefault()
    setBusy(true)
    setMsg('')
    try {
      const body = await apiPost(
        'overview',
        'rename',
        { name, planetId: String(data.planet.id) },
        data.csrf,
      )
      setMsg(body.data.message || 'Renamed')
    } catch (err) {
      setMsg(err.message || 'Rename failed')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="hn-overview-page">
      <section className="hn-card hn-overview">
        <div className="hn-overview-art">
          <PlanetThumb
            image={planet.image}
            name={planet.name}
            size={220}
            hq
            tempMin={planet.tempMin}
            tempMax={planet.tempMax}
          />
          {moon ? (
            <button
              type="button"
              className="hn-moon-chip"
              onClick={() => ctx.switchPlanet?.(moon.id)}
            >
              <PlanetThumb image={moon.image} name={moon.name} size={72} />
              <span>{moon.name}</span>
            </button>
          ) : null}
        </div>
        <div>
          <p className="hn-kicker">{typeLabel}</p>
          <h1>{planet.name}</h1>
          <p className="hn-muted">
            Commander {username} ·{' '}
            <Link to={`/galaxy?galaxy=${planet.galaxy}&system=${planet.system}`}>
              {coords(planet)}
            </Link>
          </p>
          <dl className="hn-stats">
            <div>
              <dt>Diameter</dt>
              <dd>{planet.diameter ? `${formatAmount(planet.diameter)} km` : '—'}</dd>
            </div>
            <div>
              <dt>Fields</dt>
              <dd>
                {planet.fieldCurrent ?? '—'}/{planet.fieldMax ?? '—'}
              </dd>
            </div>
            <div>
              <dt>Temperature</dt>
              <dd>
                {planet.tempMin ?? '—'}° to {planet.tempMax ?? '—'}°
              </dd>
            </div>
            <div>
              <dt>Mail</dt>
              <dd>
                <Link to="/messages">{detail?.unreadMessages ?? data.user.unreadMessages ?? 0} unread</Link>
              </dd>
            </div>
            <div>
              <dt>Incoming</dt>
              <dd className={data.attackAlertCount > 0 ? 'hn-alert' : undefined}>
                {data.attackAlertCount} hostile
              </dd>
            </div>
          </dl>
          <div className="hn-overview-res">
            {[901, 902, 903, 911].map((id) => {
              const row = res[id]
              if (!row) return null
              const per = id === 911 ? row.production : row.production
              return (
                <span key={id} className="hn-res">
                  <img src={resourceIconSrc(id)} alt="" width="18" height="18" />
                  {formatAmount(row.current)}
                  {id !== 911 ? (
                    <small className="hn-muted">+{formatAmount(per)}/h</small>
                  ) : (
                    <small className="hn-muted">used {formatAmount(per)}</small>
                  )}
                </span>
              )
            })}
          </div>
          <div className="hn-command-timers">
            <QueueLine label="Buildings" to="/buildings" item={queues.building} />
            <QueueLine label="Research" to="/research" item={queues.research} />
            <QueueLine label="Shipyard" to="/shipyard" item={queues.shipyard} yard />
          </div>
          <form className="hn-form" onSubmit={rename}>
            <label>
              Rename colony
              <input value={name} onChange={(e) => setName(e.target.value)} maxLength={32} />
            </label>
            <button type="submit" disabled={busy}>
              Save
            </button>
          </form>
          {msg ? <p>{msg}</p> : null}
        </div>
      </section>

      <section className="hn-card">
        <h2>Fleets in flight</h2>
        {fleets.length === 0 ? (
          <p className="hn-muted">No fleets underway.</p>
        ) : (
          <ul className="hn-flight-list">
            {fleets.slice(0, 8).map((fl) => (
              <li key={fl.id}>
                <div>
                  <strong>{fl.missionName}</strong>
                  <span className="hn-pill">{fl.heading}</span>
                  <div className="hn-muted">
                    {coords(fl.start)} → {coords(fl.end)}
                  </div>
                </div>
                <span>{formatEta(fl.restSeconds)}</span>
              </li>
            ))}
          </ul>
        )}
        <p>
          <Link to="/fleetTable">Open fleet table</Link>
        </p>
      </section>

      <section className="hn-card">
        <h2>Empire bodies</h2>
        <div className="hn-colony-strip">
          {colonies.map((body) => (
            <button
              type="button"
              key={body.id}
              className={body.id === planet.id ? 'hn-colony active' : 'hn-colony'}
              onClick={() => ctx.switchPlanet?.(body.id)}
            >
              <PlanetThumb image={body.image} name={body.name} size={72} />
              <strong>{body.name}</strong>
              <span className="hn-muted">{coords(body)}</span>
              {body.building ? <span className="hn-muted">{body.building}</span> : null}
            </button>
          ))}
        </div>
        <p>
          <Link to="/empire">Open empire table</Link>
        </p>
      </section>
    </div>
  )
}
