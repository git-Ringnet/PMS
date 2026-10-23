function normalizeTime(value) {
  const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/)
  if (!match) return null

  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours > 23 || minutes > 59) return null
  return hours * 60 + minutes
}

export function roomPeriodsOverlap(first, second) {
  if (!first?.arrivalDate || !first?.departureDate || !second?.arrivalDate || !second?.departureDate) return false

  const firstDayUse = Boolean(first.isDayUse) && first.arrivalDate === first.departureDate
  const secondDayUse = Boolean(second.isDayUse) && second.arrivalDate === second.departureDate

  if (firstDayUse && secondDayUse) {
    if (first.arrivalDate !== second.arrivalDate) return false

    const firstArrival = normalizeTime(first.arrivalTime)
    const firstDeparture = normalizeTime(first.departureTime)
    const secondArrival = normalizeTime(second.arrivalTime)
    const secondDeparture = normalizeTime(second.departureTime)
    const hasTimedRanges = firstArrival !== null && firstDeparture !== null
      && secondArrival !== null && secondDeparture !== null
      && firstArrival < firstDeparture && secondArrival < secondDeparture

    // Missing or invalid times remain a full-day conflict for safety.
    return !hasTimedRanges || (firstArrival < secondDeparture && secondArrival < firstDeparture)
  }

  if (firstDayUse) return second.arrivalDate <= first.arrivalDate && first.arrivalDate < second.departureDate
  if (secondDayUse) return first.arrivalDate <= second.arrivalDate && second.arrivalDate < first.departureDate
  return first.arrivalDate < second.departureDate && second.arrivalDate < first.departureDate
}
