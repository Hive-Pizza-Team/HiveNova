import { useContext, useEffect, useState } from 'react'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost } from '../api/client.js'
import { PlanetThumb } from '../ui/Thumbs.jsx'

export default function GalaxyPage() {
  const ctx = useContext(GameState)
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const p = ctx?.data?.planet
  const [coords, setCoords] = useState({ galaxy: p?.galaxy || 1, system: p?.system || 1 })

  function load(g, s) {
    return apiGet('galaxy', { galaxy: g, system: s, planetId: p?.id }).then((body) => setData(body.data))
  }

  useEffect(() => {
    if (!p) return
    load(coords.galaxy, coords.system).catch((err) => setMsg(err.message))
  }, [p?.id])

  if (!ctx) return null

  async function spy(planetId) {
    setMsg('')
    try {
      const body = await apiPost('galaxy', 'spy', { planetId: p.id, targetPlanetId: planetId }, ctx.data.csrf)
      setMsg(body.data.message || 'Probes launched')
    } catch (err) {
      setMsg(err.message || 'Spy failed')
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
      {msg ? <p>{msg}</p> : null}
      <table className="hn-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Planet</th>
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
              <td>{slot.username || ''}</td>
              <td>
                {slot.canSpy ? (
                  <button type="button" onClick={() => spy(slot.planetId)}>Spy</button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}
