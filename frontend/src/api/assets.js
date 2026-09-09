export const THEME = '/styles/theme/hive/'
export const NOVA_THEME = '/styles/theme/nova/'

const FAMILY_TEMP = {
  trocken: [120, 260],
  wuesten: [120, 260],
  dschjungel: [50, 110],
  normaltemp: [-10, 80],
  wasser: [-10, 60],
  eis: [-130, -10],
}

function pad2(n) {
  return String(n).padStart(2, '0')
}

/** Map nova 01–10 names onto hive 01–05 portraits. */
export function hivePlanetKey(image, tempMin, tempMax) {
  const raw = image || 'dschjungelplanet01'
  const m = raw.match(/^(trocken|wuesten|dschjungel|normaltemp|wasser|eis)planet(\d+)$/)
  if (!m) return raw
  const family = m[1]
  const n = Number(m[2])
  if (n >= 1 && n <= 5) return raw
  if (Number.isFinite(tempMin) && Number.isFinite(tempMax)) {
    const [lo, hi] = FAMILY_TEMP[family]
    const avg = Math.floor((tempMin + tempMax) / 2)
    const span = hi - lo
    const t = Math.max(0, Math.min(1, (avg - lo) / span))
    let bucket = Math.floor(t * 5)
    if (bucket >= 5) bucket = 4
    return `${family}planet${pad2(5 - bucket)}`
  }
  return `${family}planet${pad2(Math.min(5, Math.max(1, Math.ceil((n * 5) / 10))))}`
}

export function gebaeudeSrc(id) {
  return `${THEME}gebaeude/${id}.gif`
}

export function planetFileSrc(image, { hq = false, nova = false } = {}) {
  const key = image || 'dschjungelplanet01'
  const root = nova ? NOVA_THEME : THEME
  return `${root}planeten/${key}${hq ? '_hq' : ''}.jpg`
}

export function planetSrc(image, { hq = false, nova = false, tempMin, tempMax } = {}) {
  const key = nova ? image || 'dschjungelplanet01' : hivePlanetKey(image, tempMin, tempMax)
  return planetFileSrc(key, { hq, nova })
}

export function resourceIconSrc(resourceId) {
  const names = {
    901: 'metal',
    902: 'crystal',
    903: 'deuterium',
    911: 'energy',
    921: 'darkmatter',
  }
  const id = Number(resourceId)
  return `${THEME}images/${names[id] || 'metal'}.gif`
}

export function hideBroken(e) {
  e.currentTarget.style.visibility = 'hidden'
}
