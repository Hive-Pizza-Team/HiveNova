import { useEffect, useState } from 'react'
import { gebaeudeSrc, hideBroken, planetSrc, resourceIconSrc } from '../api/assets.js'
import { formatAmount } from '../api/client.js'

export function GebaeudeThumb({ id, name, size = 56 }) {
  return (
    <img
      className="hn-thumb"
      src={gebaeudeSrc(id)}
      alt={name || ''}
      width={size}
      height={size}
      loading="lazy"
      onError={hideBroken}
    />
  )
}

export function PlanetThumb({ image, name, size = 56, hq = false, tempMin, tempMax }) {
  const [src, setSrc] = useState(() => planetSrc(image, { hq, tempMin, tempMax }))

  useEffect(() => {
    setSrc(planetSrc(image, { hq, tempMin, tempMax }))
  }, [image, hq, tempMin, tempMax])

  return (
    <img
      className="hn-thumb hn-thumb-planet"
      src={src}
      alt={name || ''}
      width={size}
      height={size}
      loading="lazy"
      onError={(e) => {
        if (src.includes('_hq')) {
          setSrc(planetSrc(image, { hq: false, tempMin, tempMax }))
          return
        }
        if (src.includes('/hive/')) {
          setSrc(planetSrc(image, { nova: true }))
          return
        }
        hideBroken(e)
      }}
    />
  )
}

export function Cost({ cost }) {
  if (!cost) return null
  const parts = Object.entries(cost).filter(([, v]) => Number(v) > 0)
  if (!parts.length) return null
  return (
    <span className="hn-cost">
      {parts.map(([id, v]) => (
        <span className="hn-res" key={id}>
          <img src={resourceIconSrc(id)} alt="" width="14" height="14" />
          {formatAmount(v)}
        </span>
      ))}
    </span>
  )
}
