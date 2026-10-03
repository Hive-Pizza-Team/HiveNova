import { useContext, useEffect, useState } from 'react'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost } from '../api/client.js'
import { Cost, GebaeudeThumb } from '../ui/Thumbs.jsx'

export default function QueueYardPage({ resource, idField, title, mode }) {
  const ctx = useContext(GameState)
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const planetId = ctx?.data?.planet?.id

  function load() {
    return apiGet(resource, resource === 'shipyard' ? { planetId, mode: mode || 'fleet' } : { planetId }).then((body) => setData(body.data))
  }

  useEffect(() => {
    load().catch((err) => setMsg(err.message))
  }, [resource, planetId, mode])

  if (!ctx) return null

  async function mutate(action, fields) {
    setMsg('')
    try {
      const body = await apiPost(resource, action, { ...fields, planetId }, ctx.data.csrf)
      setData(body.data)
      setMsg('Queued')
    } catch (err) {
      setMsg(err.message || 'Failed')
    }
  }

  const items = Object.values(data?.items || {})

  return (
    <section className="hn-card">
      <h1>{title}</h1>
      {msg ? <p className="hn-muted">{msg}</p> : null}
      {data?.queue?.length ? (
        <div className="hn-queue">
          <h2>Queue</h2>
          <ul className="hn-queue-list">
            {data.queue.map((q) => (
              <li key={`${q.elementId}-${q.index}`}>
                <GebaeudeThumb id={q.elementId} size={36} />
                <span>
                  {q.name || `#${q.elementId}`}
                  {resource === 'shipyard'
                    ? ` × ${q.count}`
                    : ` → level ${q.level || q.count}`}
                  {' '}
                  ({q.resttime || data.remainingTime}s)
                </span>
              </li>
            ))}
          </ul>
          {resource === 'buildings' ? (
            <button type="button" onClick={() => mutate('cancel', {})}>Cancel current</button>
          ) : null}
        </div>
      ) : null}
      <ul className="hn-itemlist">
        {items.map((item) => (
          <li key={item.id}>
            <div className="hn-item-main">
              <GebaeudeThumb id={item.id} name={item.name} />
              <div>
                <strong>{item.name}</strong>{' '}
                <span className="hn-muted">
                  {resource === 'shipyard'
                    ? `in hangar: ${item.available ?? 0}`
                    : `Level ${item.level ?? 0}`}
                </span>
                <div>
                  <Cost cost={item.cost} />
                </div>
              </div>
            </div>
            {resource === 'shipyard' ? (
              <form
                className="hn-inline"
                onSubmit={(e) => {
                  e.preventDefault()
                  const n = Number(new FormData(e.target).get('n') || 0)
                  mutate('build', { fmenge: { [item.id]: n }, mode: mode || 'fleet' })
                }}
              >
                <input name="n" type="number" min="1" defaultValue="1" />
                <button type="submit" disabled={!item.buyable}>Build</button>
              </form>
            ) : (
              <button type="button" disabled={!item.buyable} onClick={() => mutate('insert', { [idField]: item.id })}>
                Upgrade
              </button>
            )}
          </li>
        ))}
      </ul>
    </section>
  )
}
