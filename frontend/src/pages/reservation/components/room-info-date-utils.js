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

export function maskDateInput(value, isDeleting = false) {
  if (!value) return ''
  let str = String(value)

  if (isDeleting) {
    const digits = str.replace(/\D/g, '').slice(0, 8)
    if (digits.length <= 2) return digits
    if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`
    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
  }

  // Handle single digit followed by slash for day e.g. '5/' -> '05/'
  if (/^\d\//.test(str)) {
    str = '0' + str
  }

  // Handle single digit followed by slash for month e.g. '05/5/' -> '05/05/'
  if (/^(\d{2}\/)(\d)\//.test(str)) {
    str = str.replace(/^(\d{2}\/)(\d)\//, '$10$2/')
  }

  let digits = str.replace(/\D/g, '')
  let dayPart = ''
  if (digits.length >= 2) {
    let dayNum = parseInt(digits.slice(0, 2), 10)
    if (dayNum > 31) dayNum = 31
    if (dayNum === 0) dayNum = 1
    dayPart = String(dayNum).padStart(2, '0')
  } else if (digits.length === 1) {
    return digits
  } else {
    return ''
  }

  let rest = digits.slice(2)
  let monthPart = ''
  let yearPart = ''
  const maxYear = 4

  if (rest.length > 0) {
    const firstMonthChar = rest[0]
    // Nếu số đầu ở phần tháng lớn hơn 1 (2..9) thì tự chuyển thành 0X/ và chuyển sang nhập năm
    if (firstMonthChar >= '2' && firstMonthChar <= '9') {
      monthPart = '0' + firstMonthChar
      yearPart = rest.slice(1).slice(0, maxYear)
    } else if (firstMonthChar === '0') {
      if (rest.length === 1) {
        return `${dayPart}/0`
      } else {
        let secondMonthChar = rest[1]
        if (secondMonthChar === '0') secondMonthChar = '1'
        monthPart = '0' + secondMonthChar
        yearPart = rest.slice(2).slice(0, maxYear)
      }
    } else if (firstMonthChar === '1') {
      if (rest.length === 1) {
        return `${dayPart}/1`
      } else {
        let monthNum = parseInt(rest.slice(0, 2), 10)
        // Rào lại ở tháng chỉ nhập được 12 tháng thôi (nếu > 12 thì giữ 12)
        if (monthNum > 12) monthNum = 12
        monthPart = String(monthNum).padStart(2, '0')
        yearPart = rest.slice(2).slice(0, maxYear)
      }
    }
  } else {
    return `${dayPart}/`
  }

  if (monthPart) {
    if (yearPart) {
      return `${dayPart}/${monthPart}/${yearPart}`
    } else {
      return `${dayPart}/${monthPart}/`
    }
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
