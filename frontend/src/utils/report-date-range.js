const parseLocalDate = (dateString) => {
  const [year, month, day] = String(dateString).split('-').map(Number)
  return new Date(year, month - 1, day)
}

export const formatLocalDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export const addLocalDays = (dateString, days) => {
  const date = parseLocalDate(dateString)
  date.setDate(date.getDate() + days)
  return formatLocalDate(date)
}

export const localMonthRange = (dateString) => {
  const date = parseLocalDate(dateString)
  const start = new Date(date.getFullYear(), date.getMonth(), 1)
  const end = new Date(date.getFullYear(), date.getMonth() + 1, 0)
  return [formatLocalDate(start), formatLocalDate(end)]
}

export const localQuarterRange = (dateString, offset = 0) => {
  const date = parseLocalDate(dateString)
  const quarterStartMonth = Math.floor(date.getMonth() / 3) * 3 + (offset * 3)
  const start = new Date(date.getFullYear(), quarterStartMonth, 1)
  const end = new Date(start.getFullYear(), start.getMonth() + 3, 0)
  return [formatLocalDate(start), formatLocalDate(end)]
}

export const localYearRange = (dateString, offset = 0) => {
  const date = parseLocalDate(dateString)
  const year = date.getFullYear() + offset
  return [
    formatLocalDate(new Date(year, 0, 1)),
    formatLocalDate(new Date(year, 12, 0))
  ]
}
