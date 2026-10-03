import { useParams } from 'react-router'
import { classicUrl } from '../api/client.js'

export default function StubPage() {
  const { page } = useParams()
  const safe = (page || 'overview').replace(/[^a-zA-Z0-9]/g, '')
  return (
    <section className="hn-card">
      <h1>Not ported yet</h1>
      <p className="hn-muted">
        <code>{safe}</code> still runs on the classic UI during dual-run.
      </p>
      <p>
        <a href={classicUrl(safe)}>Open {safe} in classic</a>
      </p>
    </section>
  )
}
