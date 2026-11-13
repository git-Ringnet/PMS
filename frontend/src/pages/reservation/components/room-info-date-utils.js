export function isLeapYear(year) {
  const y = parseInt(year, 10)
  if (Number.isNaN(y)) return false
  const fullYear = y < 100 ? (y >= 50 ? 1900 + y : 2000 + y) : y
  return (fullYear % 4 === 0 && fullYear % 100 !== 0) || (fullYear % 400 === 0)
}

export function getMaxDaysInMonth(month, year = null) {
  const m = parseInt(month, 10)
  if (!m || Number.isNaN(m) || m < 1 || m > 12) return 31
  if (m === 2) {
    if (year && (String(year).length === 4 || String(year).length === 2)) {
      return isLeapYear(year) ? 29 : 28
    }
    return 29
  }
  if ([4, 6, 9, 11].includes(m)) {
    return 30
  }
  return 31
}

export function parseYmd(value) {
  if (!value) return null
  if (value instanceof Date) return value
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value))
  if (!match) return null
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  return Number.isNaN(date.getTime()) ? null : date
}

export function toYmd(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function formatShortDate(value) {
  const date = parseYmd(value)
  if (!date) return ''
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = String(date.getFullYear())
  return `${day}/${month}/${year}`
}

export function maskDateInput(value, isDeleting = false, fourDigitYear = true) {
  if (!value) return ''
  let str = String(value)
  const maxYear = fourDigitYear ? 4 : 2

  // Handle single digit followed by slash for day only when typed from scratch e.g. '5/' -> '05/'
  if (!isDeleting && /^\d\/$/.test(str)) {
    str = '0' + str
  }

  // Handle single digit followed by slash for month: only auto-pad if digit is 2..9!
  // If digit is 1 (could be 10, 11, 12) or 0 (could be 01..09), DO NOT auto-pad immediately.
  if (!isDeleting && /^(\d{1,2}\/)([2-9])\//.test(str)) {
    str = str.replace(/^(\d{1,2}\/)([2-9])\//, '$10$2/')
  }

  if (str.includes('/')) {
    const parts = str.split('/')
    let dayRaw = parts[0].replace(/\D/g, '')
    let monthRaw = (parts[1] !== undefined ? parts[1].replace(/\D/g, '') : '')
    let yearRaw = (parts[2] !== undefined ? parts[2].replace(/\D/g, '') : '')

    // If user deleted the slash between day and month (e.g. '1405/1994')
    if (dayRaw.length > 2) {
      monthRaw = dayRaw.slice(2) + monthRaw
      dayRaw = dayRaw.slice(0, 2)
    }

    // If user deleted the slash between month and year (e.g. '14/051994')
    if (monthRaw.length > 2) {
      yearRaw = monthRaw.slice(2) + yearRaw
      monthRaw = monthRaw.slice(0, 2)
    }

    let dayStr = dayRaw.slice(0, 2)
    let monthStr = monthRaw.slice(0, 2)
    let yearStr = yearRaw.slice(0, maxYear)

    // Month validation
    if (!isDeleting && monthStr.length === 1 && monthStr[0] >= '2' && monthStr[0] <= '9' && parts.length === 2 && yearStr.length === 0) {
      monthStr = '0' + monthStr[0]
    } else if (monthStr.length === 2) {
      let m = parseInt(monthStr, 10)
      if (m > 12) monthStr = '12'
      if (m === 0) monthStr = '01'
    }

    // Determine max days for the month & year
    const maxDays = getMaxDaysInMonth(monthStr, yearStr)

    // Day validation based on maxDays
    if (dayStr.length === 2) {
      let d = parseInt(dayStr, 10)
      if (d > maxDays) dayStr = String(maxDays).padStart(2, '0')
      if (d === 0) dayStr = '01'
    }

    let res = dayStr
    if (parts.length > 1 || dayStr.length === 2) {
      res += '/' + monthStr
    }
    if (parts.length > 2 || yearStr.length > 0 || (monthStr.length === 2 && !isDeleting && parts.length === 2)) {
      res += '/' + yearStr
    }
    return res
  }

  // Pure digits without slashes (typing from scratch e.g. 18091996)
  let digits = str.replace(/\D/g, '')
  if (digits.length === 0) return ''
  if (digits.length === 1) return digits

  let dayPart = ''
  let dayNum = parseInt(digits.slice(0, 2), 10)
  if (dayNum > 31) dayNum = 31
  if (dayNum === 0) dayNum = 1
  dayPart = String(dayNum).padStart(2, '0')

  let rest = digits.slice(2)
  if (rest.length === 0) {
    return `${dayPart}/`
  }

  let monthPart = ''
  let yearPart = ''
  const firstMonthChar = rest[0]

  if (!isDeleting && firstMonthChar >= '2' && firstMonthChar <= '9') {
    monthPart = '0' + firstMonthChar
    yearPart = rest.slice(1).slice(0, maxYear)
  } else if (firstMonthChar === '0') {
    if (rest.length === 1) {
      return `${dayPart}/0`
    }
    let secondMonthChar = rest[1]
    if (secondMonthChar === '0') secondMonthChar = '1'
    monthPart = '0' + secondMonthChar
    yearPart = rest.slice(2).slice(0, maxYear)
  } else if (firstMonthChar === '1') {
    if (rest.length === 1) {
      return `${dayPart}/1`
    }
    let monthNum = parseInt(rest.slice(0, 2), 10)
    if (monthNum > 12) monthNum = 12
    monthPart = String(monthNum).padStart(2, '0')
    yearPart = rest.slice(2).slice(0, maxYear)
  }

  if (monthPart) {
    const maxDays = getMaxDaysInMonth(monthPart, yearPart)
    let d = parseInt(dayPart, 10)
    if (d > maxDays) dayPart = String(maxDays).padStart(2, '0')

    if (yearPart) {
      return `${dayPart}/${monthPart}/${yearPart}`
    }
    return `${dayPart}/${monthPart}/`
  }

  return `${dayPart}/`
}

export function parseDmyInput(value) {
  if (!value) return null
  const str = String(value).trim()

  // 1. Check DD/MM/YYYY or D/M/YYYY or DD-MM-YYYY
  const slashMatch = str.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/)
  if (slashMatch) {
    const day = Number(slashMatch[1])
    const month = Number(slashMatch[2])
    const year = Number(slashMatch[3])
    if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
      const date = new Date(year, month - 1, day)
      if (date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
        return toYmd(date)
      }
    }
  }

  // 2. Check DD/MM/YY or D/M/YY (2-digit year)
  const twoDigitYearMatch = str.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2})$/)
  if (twoDigitYearMatch) {
    const day = Number(twoDigitYearMatch[1])
    const month = Number(twoDigitYearMatch[2])
    const yy = Number(twoDigitYearMatch[3])
    const year = yy >= 50 ? 1900 + yy : 2000 + yy
    if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
      const date = new Date(year, month - 1, day)
      if (date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
        return toYmd(date)
      }
    }
  }

  // 3. Check 8 digits DDMMYYYY
  const digits = str.replace(/\D/g, '')
  if (digits.length === 8) {
    const day = Number(digits.slice(0, 2))
    const month = Number(digits.slice(2, 4))
    const year = Number(digits.slice(4))
    if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
      const date = new Date(year, month - 1, day)
      if (date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
        return toYmd(date)
      }
    }
  }

  // 4. Check ISO YYYY-MM-DD
  const isoMatch = str.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/)
  if (isoMatch) {
    const year = Number(isoMatch[1])
    const month = Number(isoMatch[2])
    const day = Number(isoMatch[3])
    if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
      const date = new Date(year, month - 1, day)
      if (date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day) {
        return toYmd(date)
      }
    }
  }

  return null
}

export function normalizeIsoDateOnly(value) {
  if (!value) return ''
  const str = String(value).trim()
  if (!str) return ''

  if (/^\d{4}-\d{2}-\d{2}T/.test(str)) {
    const d = new Date(str)
    if (!Number.isNaN(d.getTime())) {
      const yyyy = d.getFullYear()
      const mm = String(d.getMonth() + 1).padStart(2, '0')
      const dd = String(d.getDate()).padStart(2, '0')
      return `${yyyy}-${mm}-${dd}`
    }
  }

  const cleanStr = str.replace(/\s+\d{1,2}:\d{2}(:\d{2})?.*$/, '').trim()

  const matchIso = cleanStr.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/)
  if (matchIso) {
    const yyyy = matchIso[1]
    const mm = matchIso[2].padStart(2, '0')
    const dd = matchIso[3].padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
  }

  const matchDmy = cleanStr.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/)
  if (matchDmy) {
    const dd = matchDmy[1].padStart(2, '0')
    const mm = matchDmy[2].padStart(2, '0')
    const yyyy = matchDmy[3]
    return `${yyyy}-${mm}-${dd}`
  }

  if (/^\d{8}$/.test(cleanStr)) {
    const dd = cleanStr.substring(0, 2)
    const mm = cleanStr.substring(2, 4)
    const yyyy = cleanStr.substring(4, 8)
    return `${yyyy}-${mm}-${dd}`
  }

  return cleanStr
}

export function formatDateDisplayYY(value) {
  if (!value) return ''
  const str = String(value).trim()
  if (!str) return ''

  if (/^\d{4}-\d{2}-\d{2}T/.test(str)) {
    const d = new Date(str)
    if (!Number.isNaN(d.getTime())) {
      const dd = String(d.getDate()).padStart(2, '0')
      const mm = String(d.getMonth() + 1).padStart(2, '0')
      const yyyy = String(d.getFullYear())
      return `${dd}/${mm}/${yyyy}`
    }
  }

  const cleanStr = str.replace(/\s+\d{1,2}:\d{2}(:\d{2})?.*$/, '').trim()

  const matchIso = cleanStr.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/)
  if (matchIso) {
    const yyyy = matchIso[1]
    const mm = matchIso[2].padStart(2, '0')
    const dd = matchIso[3].padStart(2, '0')
    return `${dd}/${mm}/${yyyy}`
  }

  const matchDmy = cleanStr.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/)
  if (matchDmy) {
    const dd = matchDmy[1].padStart(2, '0')
    const mm = matchDmy[2].padStart(2, '0')
    const yyyy = matchDmy[3]
    return `${dd}/${mm}/${yyyy}`
  }

  const matchDmyShort = cleanStr.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{2})$/)
  if (matchDmyShort) {
    const dd = matchDmyShort[1].padStart(2, '0')
    const mm = matchDmyShort[2].padStart(2, '0')
    const yy = parseInt(matchDmyShort[3], 10)
    const yyyy = yy >= 50 ? 1900 + yy : 2000 + yy
    return `${dd}/${mm}/${yyyy}`
  }

  if (/^\d{8}$/.test(cleanStr)) {
    const dd = cleanStr.substring(0, 2)
    const mm = cleanStr.substring(2, 4)
    const yyyy = cleanStr.substring(4, 8)
    return `${dd}/${mm}/${yyyy}`
  }

  return cleanStr
}
