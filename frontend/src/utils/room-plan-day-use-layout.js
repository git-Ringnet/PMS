const MIN_VISIBLE_RATIO = 4 / 62

export function parseRoomPlanTimeToMinutes(value) {
  const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/)
  if (!match) return null

  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (!Number.isInteger(hours) || !Number.isInteger(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
    return null
  }

  return hours * 60 + minutes
}

export function resolveRoomPlanDayUseLayout({
  isDayUse = false,
  showNights = false,
  arrivalTime,
  departureTime,
} = {}) {
  if (!isDayUse) return null

  if (showNights) {
    return {
      leftRatio: 0.5,
      widthRatio: 0.5,
      isFallback: false,
    }
  }

  const arrivalMinutes = parseRoomPlanTimeToMinutes(arrivalTime)
  const departureMinutes = parseRoomPlanTimeToMinutes(departureTime)
  if (arrivalMinutes === null || departureMinutes === null || departureMinutes <= arrivalMinutes) {
    return {
      leftRatio: 0.5,
      widthRatio: 0.5,
      isFallback: true,
    }
  }

  const rawLeftRatio = arrivalMinutes / 1440
  const leftRatio = Math.min(rawLeftRatio, 1 - MIN_VISIBLE_RATIO)
  const availableRatio = 1 - leftRatio
  const durationRatio = (departureMinutes - arrivalMinutes) / 1440

  return {
    leftRatio,
    widthRatio: Math.min(Math.max(durationRatio, MIN_VISIBLE_RATIO), availableRatio),
    isFallback: false,
  }
}

