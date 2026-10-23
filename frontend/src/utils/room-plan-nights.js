const DAY_MS = 24 * 60 * 60 * 1000

export function resolveRoomPlanNights({ arrivalDate, departureDate, isDayUse = false } = {}) {
  const arrival = String(arrivalDate || '').slice(0, 10)
  const departure = String(departureDate || '').slice(0, 10)

  // Day Use never occupies an overnight stay, even when it has a room charge.
  if (isDayUse && arrival && arrival === departure) return 0

  const nights = Math.round((new Date(departure) - new Date(arrival)) / DAY_MS)
  return nights || 1
}
