export function normalizeRateMatrix(period, planCode, roomTypes = [], occupancies = []) {
  if (!period || typeof period !== 'object') return {}

  const normalizedPlanCode = String(planCode || 'DEFAULT')
  const suffixes = roomTypes.flatMap(roomType =>
    occupancies.map(occupancy => `_${roomType.code}_${occupancy}`)
  ).sort((a, b) => b.length - a.length)

  const normalized = {}
  const priorities = {}

  Object.entries(period).forEach(([key, value]) => {
    const suffix = suffixes.find(candidate => key.endsWith(candidate))
    const canonicalKey = suffix ? `${normalizedPlanCode}${suffix}` : key
    const priority = key === canonicalKey ? 2 : 1

    if (priorities[canonicalKey] === undefined || priority >= priorities[canonicalKey]) {
      normalized[canonicalKey] = value
      priorities[canonicalKey] = priority
    }
  })

  return normalized
}
