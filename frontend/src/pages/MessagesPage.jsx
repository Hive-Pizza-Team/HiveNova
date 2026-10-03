import { useContext, useEffect, useState } from 'react'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, combatReportSrc, formatWhen } from '../api/client.js'

const CATEGORY_ORDER = [100, 3, 0, 1, 5, 15, 4, 99, 50]

export default function MessagesPage() {
  const ctx = useContext(GameState)
  const [data, setData] = useState(null)
  const [category, setCategory] = useState(100)
  const [openId, setOpenId] = useState(null)
  const [report, setReport] = useState(null)

  function load(cat) {
    return apiGet('messages', { category: cat, side: 1 }).then((body) => {
      setData(body.data)
      setOpenId(null)
    })
  }

  useEffect(() => {
    load(category).catch(() => {})
  }, [category])

  if (!ctx) return null

  const labels = data?.categories || {}
  const tabs = CATEGORY_ORDER.filter((id) => labels[id] || id === 100)

  function openMail(e) {
    const link = e.target.closest('a')
    if (!link) return
    const src = combatReportSrc(link.getAttribute('href') || '')
    if (!src) return
    e.preventDefault()
    setReport(src)
  }

  return (
    <section className="hn-card hn-mail-deck">
      <h1>Messages</h1>
      <p className="hn-muted">{data?.count || 0} in this folder</p>
      <div className="hn-mail-tabs">
        {tabs.map((id) => (
          <button
            key={id}
            type="button"
            className={Number(category) === Number(id) ? 'is-on' : ''}
            onClick={() => setCategory(id)}
          >
            {labels[id] || (id === 100 ? 'All messages' : `#${id}`)}
          </button>
        ))}
      </div>
      {(data?.messages || []).length === 0 ? (
        <p className="hn-muted">Folder empty.</p>
      ) : (
        <ul className="hn-mail-list">
          {(data?.messages || []).map((m) => {
            const open = openId === m.id
            return (
              <li key={m.id} className={m.unread ? 'is-unread' : ''}>
                <button type="button" className="hn-mail-head" onClick={() => setOpenId(open ? null : m.id)}>
                  <span>
                    <strong>{m.subject || '(no subject)'}</strong>
                    <span className="hn-muted"> {m.from}</span>
                  </span>
                  <time className="hn-muted">{formatWhen(m.time)}</time>
                </button>
                {open ? (
                  <div className="hn-mail-body" onClick={openMail} dangerouslySetInnerHTML={{ __html: m.text }} />
                ) : null}
              </li>
            )
          })}
        </ul>
      )}
      {report ? (
        <div className="hn-modal" onClick={() => setReport(null)}>
          <div className="hn-modal-frame" onClick={(e) => e.stopPropagation()}>
            <header>
              <h2>Combat report</h2>
              <button type="button" onClick={() => setReport(null)}>
                Close
              </button>
            </header>
            <iframe title="Combat report" src={report} />
          </div>
        </div>
      ) : null}
    </section>
  )
}
