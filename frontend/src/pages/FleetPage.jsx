import { useContext, useEffect, useState } from 'react'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost, coords, formatAmount, formatEta } from '../api/client.js'
import { Cost, GebaeudeThumb } from '../ui/Thumbs.jsx'

export default function FleetPage() {
  const ctx = useContext(GameState)
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const [target, setTarget] = useState({
    galaxy: '',
    system: '',
    planet: '',
    type: '1',
    mission: '1',
    speed: '10',
    metal: '',
    crystal: '',
    deuterium: '',
  })
  const [picked, setPicked] = useState({})
  const planetId = ctx?.data?.planet?.id

  useEffect(() => {
    if (!ctx) return
    const p = ctx.data.planet
    setTarget((t) => ({
      ...t,
      galaxy: t.galaxy || p.galaxy,
      system: t.system || p.system,
      planet: t.planet || p.planet,
    }))
    apiGet('fleet', { planetId }).then((body) => setData(body.data)).catch((err) => setMsg(err.message))
  }, [ctx, planetId])

  if (!ctx || !data) return <p className="hn-muted">Loading fleets…</p>

  const showCargo = ['3', '4', '17'].includes(String(target.mission))

  async function reload() {
    const table = await apiGet('fleet', { planetId })
    setData(table.data)
  }

  async function send(e) {
    e.preventDefault()
    setMsg('')
    const ships = {}
    Object.entries(picked).forEach(([id, n]) => {
      if (Number(n) > 0) ships[id] = Number(n)
    })
    try {
      const body = await apiPost(
        'fleet',
        'send',
        {
          planetId,
          ships,
          galaxy: target.galaxy,
          system: target.system,
          planet: target.planet,
          type: target.type,
          mission: target.mission,
          speed: target.speed,
          metal: target.metal,
          crystal: target.crystal,
          deuterium: target.deuterium,
        },
        ctx.data.csrf,
      )
      setMsg(`Fleet ${body.data.fleetId} launched`)
      setPicked({})
      await reload()
    } catch (err) {
      setMsg(err.message || 'Send failed')
    }
  }

  async function recall(id) {
    await apiPost('fleet', 'recall', { planetId, fleetId: id }, ctx.data.csrf)
    await reload()
  }

  return (
    <div className="hn-fleet">
      <header className="hn-card hn-fleet-status">
        <h1>Fleet</h1>
        <p className="hn-slotbar">
          <span>Flight slots</span>
          <strong>
            {data.slots.used}/{data.slots.max}
          </strong>
          <span className="hn-slotbar-track">
            <span
              className="hn-slotbar-fill"
              style={{ width: `${data.slots.max ? Math.min(100, (100 * data.slots.used) / data.slots.max) : 0}%` }}
            />
          </span>
        </p>
        {msg ? <p className={msg.includes('fail') ? 'hn-error' : 'hn-muted'}>{msg}</p> : null}
      </header>

      <section className="hn-card">
        <h2>In flight</h2>
        {data.fleets.length === 0 ? (
          <p className="hn-muted">No fleets underway.</p>
        ) : (
          <ul className="hn-flight-list">
            {data.fleets.map((f) => (
              <li key={f.id}>
                <div>
                  <strong>{f.missionName || `Mission ${f.mission}`}</strong>
                  <span className={`hn-pill ${f.heading === 'return' ? 'is-return' : 'is-out'}`}>
                    {f.heading === 'return' ? 'Return' : 'Outbound'}
                  </span>
                  <div className="hn-muted">
                    {coords(f.start)} → {coords(f.end)} · {formatEta(f.restSeconds)}
                  </div>
                </div>
                {f.recallable ? (
                  <button type="button" onClick={() => recall(f.id)}>
                    Recall
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="hn-card">
        <h2>Hangar dispatch</h2>
        {data.ships.length === 0 ? (
          <p className="hn-muted">No ships on this body.</p>
        ) : (
          <form className="hn-dispatch" onSubmit={send}>
            <ul className="hn-hangar">
              {data.ships.map((s) => (
                <li key={s.id}>
                  <GebaeudeThumb id={s.id} name={s.name} size={48} />
                  <div>
                    <strong>{s.name}</strong>
                    <div className="hn-muted">Ready {formatAmount(s.count)}</div>
                  </div>
                  <div className="hn-hangar-qty">
                    <input
                      type="number"
                      min="0"
                      max={s.count}
                      value={picked[s.id] || ''}
                      placeholder="0"
                      onChange={(e) => setPicked({ ...picked, [s.id]: e.target.value })}
                    />
                    <button type="button" onClick={() => setPicked({ ...picked, [s.id]: String(s.count) })}>
                      Max
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            <fieldset className="hn-coords">
              <legend>Target</legend>
              <label>
                Galaxy
                <input value={target.galaxy} onChange={(e) => setTarget({ ...target, galaxy: e.target.value })} />
              </label>
              <label>
                System
                <input value={target.system} onChange={(e) => setTarget({ ...target, system: e.target.value })} />
              </label>
              <label>
                Position
                <input value={target.planet} onChange={(e) => setTarget({ ...target, planet: e.target.value })} />
              </label>
              <label>
                Body
                <select value={target.type} onChange={(e) => setTarget({ ...target, type: e.target.value })}>
                  <option value="1">Planet</option>
                  <option value="3">Moon</option>
                </select>
              </label>
            </fieldset>
            <div className="hn-dispatch-meta">
              <label>
                Mission
                <select value={target.mission} onChange={(e) => setTarget({ ...target, mission: e.target.value })}>
                  {(data.missions || []).map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.name}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                Speed
                <select value={target.speed} onChange={(e) => setTarget({ ...target, speed: e.target.value })}>
                  {[10, 9, 8, 7, 6, 5, 4, 3, 2, 1].map((n) => (
                    <option key={n} value={n}>
                      {n * 10}%
                    </option>
                  ))}
                </select>
              </label>
            </div>
            {showCargo ? (
              <fieldset className="hn-coords">
                <legend>Cargo</legend>
                <Cost
                  cost={{
                    901: Number(target.metal) || 0,
                    902: Number(target.crystal) || 0,
                    903: Number(target.deuterium) || 0,
                  }}
                />
                <label>
                  Metal
                  <input value={target.metal} onChange={(e) => setTarget({ ...target, metal: e.target.value })} />
                </label>
                <label>
                  Crystal
                  <input value={target.crystal} onChange={(e) => setTarget({ ...target, crystal: e.target.value })} />
                </label>
                <label>
                  Deuterium
                  <input
                    value={target.deuterium}
                    onChange={(e) => setTarget({ ...target, deuterium: e.target.value })}
                  />
                </label>
              </fieldset>
            ) : null}
            <button type="submit">Launch</button>
          </form>
        )}
      </section>
    </div>
  )
}
