export function parseRoomPlanTimeToMinutes(value) {
  const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/)
  if (!match) return null

  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (!Number.isInteger(hours) || !Number.isInteger(minutes) || hours < 0 || hours > 24 || minutes < 0 || minutes > 59) {
    return null
  }

  if (hours === 24) return minutes === 0 ? 1440 : null

  return hours * 60 + minutes
}

export function resolveRoomPlanDayUseLayout({
  isDayUse = false,
  arrivalTime,
  departureTime,
} = {}) {
  if (!isDayUse) return null

  const arrivalMinutes = parseRoomPlanTimeToMinutes(arrivalTime)
  const departureMinutes = parseRoomPlanTimeToMinutes(departureTime)
  if (arrivalMinutes === null || departureMinutes === null || departureMinutes <= arrivalMinutes) {
    return {
      leftRatio: 0.5,
      widthRatio: 0.5,
      isFallback: true,
    }
  }

  return {
    leftRatio: arrivalMinutes / 1440,
    widthRatio: (departureMinutes - arrivalMinutes) / 1440,
    isFallback: false,
  }
}

