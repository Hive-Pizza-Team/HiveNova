import { useContext, useEffect, useMemo, useState } from 'react'
import { GameState } from '../shell/GameState.jsx'
import { apiGet, apiPost, formatAmount } from '../api/client.js'
import { resourceIconSrc } from '../api/assets.js'

const RESOURCE_IDS = [901, 902, 903]

function itemById(items, id) {
  return items.find((row) => Number(row.id) === Number(id))
}

export default function MarketPage() {
  const ctx = useContext(GameState)
  const i18n = ctx?.data?.i18n || {}
  const [detail, setDetail] = useState(null)
  const [sellId, setSellId] = useState(null)
  const [want, setWant] = useState({})
  const [msg, setMsg] = useState('')
  const [ok, setOk] = useState(false)
  const [busy, setBusy] = useState(false)
  const planetId = ctx?.data?.planet?.id

  useEffect(() => {
    if (!ctx) return undefined
    let cancelled = false
    apiGet('catalog', { kind: 'trader', planetId })
      .then((body) => {
        if (!cancelled) setDetail(body.data)
      })
      .catch((err) => {
        if (!cancelled) setMsg(err.message || 'Market uplink failed')
      })
    return () => {
      cancelled = true
    }
  }, [planetId])

  const items = detail?.items || []
  const sell = itemById(items, sellId)
  const buyRows = useMemo(
    () => items.filter((row) => Number(row.id) !== Number(sellId)),
    [items, sellId],
  )

  const spent = useMemo(() => {
    if (!sell) return 0
    return buyRows.reduce((sum, row) => {
      const qty = Number(want[row.id]) || 0
      const rate = Number(sell.rates?.[row.id]) || 0
      return sum + qty * rate
    }, 0)
  }, [sell, buyRows, want])

  const stock = Number(sell?.amount) || 0
  const enough = spent > 0 && spent <= stock
  const canCall = Boolean(detail?.canCall)

  function pick(id) {
    setSellId(id)
    setWant({})
    setMsg('')
    setOk(false)
  }

  function setQty(id, value) {
    setWant((prev) => ({ ...prev, [id]: value }))
  }

  function fillMax(id) {
    if (!sell) return
    const rate = Number(sell.rates?.[id]) || 0
    if (rate <= 0) return
    const others = buyRows.reduce((sum, row) => {
      if (Number(row.id) === Number(id)) return sum
      return sum + (Number(want[row.id]) || 0) * (Number(sell.rates?.[row.id]) || 0)
    }, 0)
    const remaining = Math.max(0, stock - others)
    setQty(id, String(Math.floor(remaining / rate)))
  }

  async function submit(e) {
    e.preventDefault()
    if (!sell || !enough || !canCall || busy) return
    setBusy(true)
    setMsg('')
    setOk(false)
    const trade = {}
    buyRows.forEach((row) => {
      const qty = Math.max(0, Math.floor(Number(want[row.id]) || 0))
      if (qty > 0) trade[row.id] = qty
    })
    try {
      const body = await apiPost(
        'catalog',
        'trade',
        { resource: sell.id, trade, planetId, kind: 'trader' },
        ctx.data.csrf,
      )
      setWant({})
      setOk(true)
      setMsg(body.data?.message || i18n.tr_exchange_done || 'Trade successful')
      if (typeof ctx.reload === 'function') {
        await ctx.reload()
      }
      const fresh = await apiGet('catalog', { kind: 'trader', planetId })
      setDetail({ ...fresh.data, message: body.data?.message })
    } catch (err) {
      setMsg(err.message || i18n.tr_exchange_error || 'Trade failed')
    } finally {
      setBusy(false)
    }
  }

  if (!ctx) return null

  const cost = Number(detail?.cost) || 0
  const quota = RESOURCE_IDS.map((id) => {
    const row = itemById(items, id)
    const vsDeut = Number(detail?.rates?.[id]?.[903]) || 0
    return `${row?.name || id} ${vsDeut}`
  }).join(' / ')

  return (
    <section className="hn-card hn-market">
      <h1>{i18n.lm_trader || 'Market'}</h1>
      <p className="hn-muted">{i18n.tr_call_trader_who_buys || 'Call a merchant who purchases'}</p>
      {msg ? <p className={ok ? 'hn-ok' : 'hn-error'}>{msg}</p> : null}
      {!canCall && detail ? (
        <p className="hn-error">
          {(i18n.tr_not_enought || "Don't have enough %s.").replace('%s', 'Pizzabits')}
        </p>
      ) : null}
      <p>
        {(i18n.tr_cost_dm_trader || 'You have to pay the merchant %s %s!')
          .replace('%s', formatAmount(cost))
          .replace('%s', 'Pizzabits')}
      </p>
      <p className="hn-muted">
        {i18n.tr_exchange_quota || 'The exchange rate is'} {quota}
      </p>
      <div className="hn-market-picks">
        {items.map((row) => (
          <button
            key={row.id}
            type="button"
            className={Number(sellId) === Number(row.id) ? 'hn-market-pick is-on' : 'hn-market-pick'}
            onClick={() => pick(row.id)}
            disabled={!canCall}
          >
            <img src={resourceIconSrc(row.id)} alt="" width="52" height="32" />
            <strong>{row.name || `#${row.id}`}</strong>
            <span className="hn-muted">{formatAmount(row.amount)}</span>
          </button>
        ))}
      </div>
      {sell ? (
        <form className="hn-form hn-market-form" onSubmit={submit}>
          <h2>
            {i18n.tr_sell || 'Selling'} {sell.name}
          </h2>
          <table className="hn-table">
            <thead>
              <tr>
                <th>{i18n.tr_resource || 'Resource'}</th>
                <th>{i18n.tr_amount || 'Amount'}</th>
                <th>{i18n.tr_quota_exchange || 'Fee'}</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>{sell.name}</td>
                <td className={spent > stock ? 'hn-error' : ''}>{formatAmount(spent)}</td>
                <td>1</td>
              </tr>
              {buyRows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <label htmlFor={`trade-${row.id}`}>
                      <img src={resourceIconSrc(row.id)} alt="" width="18" height="18" /> {row.name}
                    </label>
                  </td>
                  <td>
                    <span className="hn-hangar-qty">
                      <input
                        id={`trade-${row.id}`}
                        inputMode="numeric"
                        value={want[row.id] ?? ''}
                        placeholder="0"
                        onChange={(e) => setQty(row.id, e.target.value.replace(/[^\d]/g, ''))}
                      />
                      <button type="button" onClick={() => fillMax(row.id)}>
                        max
                      </button>
                    </span>
                  </td>
                  <td>{sell.rates?.[row.id]}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <button type="submit" disabled={!enough || !canCall || busy}>
            {i18n.tr_exchange || 'Trade'}
          </button>
        </form>
      ) : (
        <p className="hn-muted">{i18n.tr_call_trader || 'Call a merchant'}</p>
      )}
    </section>
  )
}
