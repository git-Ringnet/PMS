function toPrice(value) {
  if (value === undefined || value === null || value === '') return null

  const price = Number(value)
  return Number.isFinite(price) && price >= 0 ? price : null
}

function parsePeriod(period) {
  if (!period) return null
  if (typeof period !== 'string') return period

  try {
    return JSON.parse(period)
  } catch {
    return null
  }
}

export function normalizeRateDate(value) {
  if (!value) return ''

  const raw = String(value)
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw

  const parsed = new Date(raw)
  if (Number.isNaN(parsed.getTime())) return raw.slice(0, 10)

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Bangkok',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(parsed)
  const values = Object.fromEntries(parts.map(part => [part.type, part.value]))

  return `${values.year}-${values.month}-${values.day}`
}

function addDays(dateKey, days) {
  const [year, month, day] = String(dateKey).split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day + days))
  return date.toISOString().slice(0, 10)
}

export function resolveRateCodePrice(rateCodes, {
  rateCode,
  roomClassCode,
  date,
  roomClassId,
}) {
  const normalizedRateCode = String(rateCode || '')
  const rateCodeData = (rateCodes || []).find(item =>
    String(item.Ma ?? item.code ?? '') === normalizedRateCode
  )
  if (!rateCodeData) return null

  const fallbackPrice = toPrice(rateCodeData.Value ?? rateCodeData.Gia ?? rateCodeData.price)
  const plans = rateCodeData.ratePlans || rateCodeData.rate_plans || []
  if (!Array.isArray(plans) || plans.length === 0) return fallbackPrice

  const mappings = rateCodeData.dailyMappings || rateCodeData.daily_mappings || []
  const normalizedDate = normalizeRateDate(date)
  const mapping = normalizedDate && Array.isArray(mappings)
    ? mappings.find(item => normalizeRateDate(item.Date) === normalizedDate)
    : null
  const activePlanCode = String(mapping?.Code || 'DEFAULT')

  const plan = plans.find(item => String(item.Code) === activePlanCode)
    || plans.find(item => String(item.Code) === 'DEFAULT')
    || plans[0]
  const period = parsePeriod(plan?.Period)
  if (!period || typeof period !== 'object') return fallbackPrice

  if (roomClassId !== undefined && roomClassId !== null && roomClassId !== '') {
    const idPrice = toPrice(period[String(roomClassId)])
    if (idPrice !== null) return idPrice
  }

  const normalizedRoomClassCode = String(roomClassCode || '').trim()
  if (normalizedRoomClassCode) {
    const planCode = String(plan?.Code || 'DEFAULT')
    const preferredKeys = [
      `${planCode}_${normalizedRoomClassCode}_Double`,
      `${normalizedRateCode}_${normalizedRoomClassCode}_Double`,
    ]

    for (const key of preferredKeys) {
      const price = toPrice(period[key])
      if (price !== null) return price
    }

    const compatibleKey = Object.keys(period).find(key =>
      key.startsWith(`${planCode}_${normalizedRoomClassCode}_`)
      || key.startsWith(`${normalizedRateCode}_${normalizedRoomClassCode}_`)
      || key.includes(`_${normalizedRoomClassCode}_`)
    )
    const compatiblePrice = compatibleKey ? toPrice(period[compatibleKey]) : null
    if (compatiblePrice !== null) return compatiblePrice
  }

  return fallbackPrice
}

export function buildRateCodeDailyPrices(rateCodes, {
  rateCode,
  roomClassCode,
  arrivalDate,
  departureDate,
  roomClassId,
}) {
  const start = normalizeRateDate(arrivalDate)
  const end = normalizeRateDate(departureDate)
  if (!start || !end || start >= end) return {}

  const prices = {}
  let current = start
  let guard = 0

  while (current < end && guard < 3660) {
    const price = resolveRateCodePrice(rateCodes, {
      rateCode,
      roomClassCode,
      date: current,
      roomClassId,
    })
    if (price !== null) prices[current] = price
    current = addDays(current, 1)
    guard += 1
  }

  return prices
}
