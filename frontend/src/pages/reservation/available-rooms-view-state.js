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
  const date = new Date(Date.UTC(year, month - 1, day))

  return date.getUTCFullYear() === year
    && date.getUTCMonth() === month - 1
    && date.getUTCDate() === day
}

export function getAvailableRoomsDateRange() {
  if (!isValidYmdDate(dateRange.startDate)
    || !isValidYmdDate(dateRange.endDate)
    || dateRange.startDate > dateRange.endDate) {
    return null
  }

  return { ...dateRange }
}

export function setAvailableRoomsDateRange(startDate, endDate) {
  if (!isValidYmdDate(startDate) || !isValidYmdDate(endDate) || startDate > endDate) {
    return false
  }

  dateRange.startDate = startDate
  dateRange.endDate = endDate
  return true
}

export function clearAvailableRoomsDateRange() {
  dateRange.startDate = ''
  dateRange.endDate = ''
}
