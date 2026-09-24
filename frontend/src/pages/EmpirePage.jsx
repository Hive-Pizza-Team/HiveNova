import { useContext, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, coords, formatAmount } from '../api/client.js'
import { resourceIconSrc } from '../api/assets.js'
import { PlanetThumb } from '../ui/Thumbs.jsx'

function sum(bodies, key) {
  return bodies.reduce((n, b) => n + (Number(b[key]) || 0), 0)
}

export default function EmpirePage() {
  const ctx = useContext(GameState)
  const [bodies, setBodies] = useState([])
  const [msg, setMsg] = useState('')
  const planetId = ctx?.data?.planet?.id

  useEffect(() => {
    if (!ctx) return
    apiGet('catalog', { kind: 'empire', planetId })
      .then((body) => setBodies(body.data?.bodies || []))
      .catch((err) => setMsg(err.message || 'Empire uplink failed'))
  }, [ctx, planetId])

  const totals = useMemo(
    () => ({
      metal: sum(bodies, 'metal'),
      crystal: sum(bodies, 'crystal'),
      deuterium: sum(bodies, 'deuterium'),
      metalPerHour: sum(bodies, 'metalPerHour'),
      crystalPerHour: sum(bodies, 'crystalPerHour'),
      deuteriumPerHour: sum(bodies, 'deuteriumPerHour'),
    }),
    [bodies],
  )

  if (!ctx) return null

  return (
    <section className="hn-card hn-empire">
      <h1>Empire</h1>
      <p className="hn-muted">{bodies.length} bodies · click a name to jump there</p>
      {msg ? <p className="hn-error">{msg}</p> : null}
      <div className="hn-table-wrap">
        <table className="hn-table hn-empire-table">
          <thead>
            <tr>
              <th>Body</th>
              <th>Coords</th>
              <th>Fields</th>
              <th>
                <img src={resourceIconSrc(901)} alt="" width="16" height="16" /> Metal
              </th>
              <th>
                <img src={resourceIconSrc(902)} alt="" width="16" height="16" /> Crystal
              </th>
              <th>
                <img src={resourceIconSrc(903)} alt="" width="16" height="16" /> Deut
              </th>
              <th>
                <img src={resourceIconSrc(911)} alt="" width="16" height="16" /> Energy
              </th>
            </tr>
          </thead>
          <tbody>
            <tr className="hn-empire-total">
              <td colSpan={3}>Total</td>
              <td>
                {formatAmount(totals.metal)}
                <small className="hn-muted"> +{formatAmount(totals.metalPerHour)}/h</small>
              </td>
              <td>
                {formatAmount(totals.crystal)}
                <small className="hn-muted"> +{formatAmount(totals.crystalPerHour)}/h</small>
              </td>
              <td>
                {formatAmount(totals.deuterium)}
                <small className="hn-muted"> +{formatAmount(totals.deuteriumPerHour)}/h</small>
              </td>
              <td>—</td>
            </tr>
            {bodies.map((body) => (
              <tr key={body.id} className={body.id === planetId ? 'hn-row-active' : undefined}>
                <td>
                  <button type="button" className="hn-empire-jump" onClick={() => ctx.switchPlanet?.(body.id)}>
                    <PlanetThumb image={body.image} name={body.name} size={40} />
                    <span>
                      {body.name}
                      <small className="hn-muted">{Number(body.type) === 3 ? ' moon' : ' planet'}</small>
                    </span>
                  </button>
                </td>
                <td>
                  <Link to={`/galaxy?galaxy=${body.galaxy}&system=${body.system}`}>{coords(body)}</Link>
                </td>
                <td>
                  {body.fields}/{body.fieldMax}
                </td>
                <td>
                  {formatAmount(body.metal)}
                  <small className="hn-muted"> +{formatAmount(body.metalPerHour)}/h</small>
                </td>
                <td>
                  {formatAmount(body.crystal)}
                  <small className="hn-muted"> +{formatAmount(body.crystalPerHour)}/h</small>
                </td>
                <td>
                  {formatAmount(body.deuterium)}
                  <small className="hn-muted"> +{formatAmount(body.deuteriumPerHour)}/h</small>
                </td>
                <td>{formatAmount(body.energy)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
