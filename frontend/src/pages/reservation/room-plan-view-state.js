const dateRange = {
  startDate: '',
  endDate: ''
}

function isValidYmdDate(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '')
  if (!match) return false

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  const date = new Date(year, month - 1, day)

  return date.getFullYear() === year
    && date.getMonth() === month - 1
    && date.getDate() === day
}

export function getRoomPlanDateRange() {
  if (!isValidYmdDate(dateRange.startDate)
    || !isValidYmdDate(dateRange.endDate)
    || dateRange.startDate > dateRange.endDate) {
    return null
  }

  return { ...dateRange }
}

export function setRoomPlanDateRange(startDate, endDate) {
  if (!isValidYmdDate(startDate) || !isValidYmdDate(endDate) || startDate > endDate) {
    return false
  }

  dateRange.startDate = startDate
  dateRange.endDate = endDate
  return true
}

export function clearRoomPlanDateRange() {
  dateRange.startDate = ''
  dateRange.endDate = ''
}
