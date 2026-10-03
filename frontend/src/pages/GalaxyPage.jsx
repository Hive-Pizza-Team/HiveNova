import { useContext, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost, formatAmount } from '../api/client.js'
import { PlanetThumb } from '../ui/Thumbs.jsx'

function fleetHref(coords, slot, extra) {
  const q = new URLSearchParams({
    galaxy: String(coords.galaxy),
    system: String(coords.system),
    planet: String(slot.position),
    ...extra,
  })
  return `/fleetTable?${q}`
}

export default function GalaxyPage() {
  const ctx = useContext(GameState)
  const [params] = useSearchParams()
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const p = ctx?.data?.planet
  const [coords, setCoords] = useState({
    galaxy: params.get('galaxy') || p?.galaxy || 1,
    system: params.get('system') || p?.system || 1,
  })

  function load(g, s) {
    return apiGet('galaxy', { galaxy: g, system: s, planetId: p?.id }).then((body) => setData(body.data))
  }

  useEffect(() => {
    if (!p) return
    const g = params.get('galaxy') || p.galaxy
    const s = params.get('system') || p.system
    setCoords({ galaxy: g, system: s })
    load(g, s).catch((err) => setMsg(err.message))
  }, [p?.id, params])

  if (!ctx) return null

  async function act(action, fields) {
    setMsg('')
    try {
      const body = await apiPost('galaxy', action, { planetId: p.id, ...fields }, ctx.data.csrf)
      setMsg(body.data.message || 'Fleet launched')
      await load(coords.galaxy, coords.system)
    } catch (err) {
      setMsg(err.message || 'Action failed')
    }
  }

  return (
    <section className="hn-card">
      <h1>Galaxy</h1>
      <form
        className="hn-inline"
        onSubmit={(e) => {
          e.preventDefault()
          load(coords.galaxy, coords.system).catch((err) => setMsg(err.message))
        }}
      >
        <input value={coords.galaxy} onChange={(e) => setCoords({ ...coords, galaxy: e.target.value })} />
        <input value={coords.system} onChange={(e) => setCoords({ ...coords, system: e.target.value })} />
        <button type="submit">Scan</button>
      </form>
      {msg ? <p className={msg.toLowerCase().includes('fail') || msg.toLowerCase().includes('no ') ? 'hn-error' : 'hn-muted'}>{msg}</p> : null}
      <div className="hn-table-wrap">
        <table className="hn-table hn-galaxy-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Planet</th>
              <th>Moon</th>
              <th>Debris</th>
              <th>Player</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {(data?.slots || []).map((slot) => (
              <tr key={slot.position}>
                <td>{slot.position}</td>
                <td>
                  {slot.empty ? (
                    slot.image ? <PlanetThumb image={slot.image} name="" size={28} /> : '—'
                  ) : (
                    <span className="hn-inline">
                      <PlanetThumb image={slot.image} name={slot.name} size={32} />
                      {slot.name}
                    </span>
                  )}
                </td>
                <td>
                  {slot.moon ? (
                    <span className="hn-inline">
                      <PlanetThumb image={slot.moon.image} name={slot.moon.name} size={24} />
                      {slot.moon.name}
                    </span>
                  ) : (
                    '—'
                  )}
                </td>
                <td>
                  {slot.debris && (slot.debris.metal > 0 || slot.debris.crystal > 0) ? (
                    <span className="hn-muted">
                      {formatAmount(slot.debris.metal)} / {formatAmount(slot.debris.crystal)}
                    </span>
                  ) : (
                    '—'
                  )}
                </td>
                <td>
                  {slot.username || ''}
                  {slot.alliance ? <span className="hn-pill">{slot.alliance}</span> : null}
                  {slot.lastActivity ? <div className="hn-muted">{slot.lastActivity}</div> : null}
                </td>
                <td>
                  <div className="hn-galaxy-actions">
                    {slot.canColonize ? (
                      <button
                        type="button"
                        onClick={() => act('colonize', { galaxy: coords.galaxy, system: coords.system, planet: slot.position })}
                      >
                        Colonize
                      </button>
                    ) : null}
                    {slot.canSpy ? (
                      <button type="button" onClick={() => act('spy', { targetPlanetId: slot.planetId })}>
                        Spy
                      </button>
                    ) : null}
                    {slot.moon && slot.canSpy ? (
                      <button type="button" onClick={() => act('spy', { targetPlanetId: slot.moon.id })}>
                        Spy moon
                      </button>
                    ) : null}
                    {slot.canRecycle ? (
                      <button type="button" onClick={() => act('recycle', { targetPlanetId: slot.planetId })}>
                        Recycle
                      </button>
                    ) : null}
                    {slot.canAttack ? (
                      <Link className="hn-mini" to={fleetHref(coords, slot, { type: '1', mission: '1' })}>
                        Attack
                      </Link>
                    ) : null}
                    {slot.moon && slot.canAttack ? (
                      <Link className="hn-mini" to={fleetHref(coords, slot, { type: '3', mission: '1' })}>
                        Attack moon
                      </Link>
                    ) : null}
                    {slot.canTransport ? (
                      <Link className="hn-mini" to={fleetHref(coords, slot, { type: '1', mission: '3' })}>
                        Transport
                      </Link>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
