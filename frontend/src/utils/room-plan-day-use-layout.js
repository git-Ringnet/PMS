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
  showNights = false,
  arrivalTime,
  departureTime,
} = {}) {
  if (!isDayUse) return null

  const arrivalMinutes = parseRoomPlanTimeToMinutes(arrivalTime)
  const departureMinutes = parseRoomPlanTimeToMinutes(departureTime)
  if (arrivalMinutes === null || departureMinutes === null || departureMinutes <= arrivalMinutes) {
    return {
      leftRatio: showNights ? 0 : 0.5,
      widthRatio: 0.5,
      isFallback: true,
    }
  }

  return {
    // Xem đêm dịch booking về nửa đầu của ô ngày, cùng quy tắc với booking
    // thường. Day Use vẫn giữ chiều rộng theo khoảng giờ thực tế.
    leftRatio: showNights ? Math.max(0, (arrivalMinutes / 1440) - 0.5) : arrivalMinutes / 1440,
    widthRatio: (departureMinutes - arrivalMinutes) / 1440,
    isFallback: false,
  }
}

