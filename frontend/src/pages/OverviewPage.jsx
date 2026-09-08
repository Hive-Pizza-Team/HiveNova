import { useContext, useEffect, useState } from 'react'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost } from '../api/client.js'

export default function OverviewPage() {
  const ctx = useContext(GameState)
  const data = ctx?.data
  const [detail, setDetail] = useState(null)
  const [name, setName] = useState(data?.planet?.name || '')
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiGet('overview')
      .then((body) => {
        if (!cancelled) {
          setDetail(body.data)
          if (body.data?.planet?.name) {
            setName(body.data.planet.name)
          }
        }
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [])

  if (!data) return null

  const planet = detail?.planet || data.planet
  const username = detail?.username || data.user.username

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
    <section className="hn-card">
      <h1>{planet.name}</h1>
      <p className="hn-muted">
        Colony of {username} at [{planet.galaxy}:{planet.system}:{planet.planet}]
      </p>
      <dl className="hn-stats">
        <div>
          <dt>Diameter</dt>
          <dd>{planet.diameter || '—'}</dd>
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
            {planet.tempMin ?? '—'} / {planet.tempMax ?? '—'}
          </dd>
        </div>
      </dl>
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
    </section>
  )
}
