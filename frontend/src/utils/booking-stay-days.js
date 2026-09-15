// Match BookingRoom's current day-use convention: same-day stays count as one day.
export function bookingStayDays(arrival, departure) {
  const parseDay = (value) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null
    const [year, month, day] = value.split('-').map(Number)
    const date = new Date(Date.UTC(year, month - 1, day))
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null
    return date.getTime()
  }
  const start = parseDay(arrival)
  const end = parseDay(departure)
  if (start === null || end === null || end < start) return null
  return Math.max(1, (end - start) / 86400000)
}
