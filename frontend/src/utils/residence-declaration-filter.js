export function parseDate(dateStr) {
  if (!dateStr) return null
  const clean = String(dateStr).trim().split('T')[0].split(' ')[0]
  if (clean.includes('-')) {
    const parts = clean.split('-')
    if (parts[0].length === 4) {
      return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
    }
    return new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]))
  }
  if (clean.includes('/')) {
    const parts = clean.split('/')
    if (parts[2].length === 4) {
      return new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]))
    }
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
  }
  return null
}

export function filterResidenceDeclarationRows(rows, selectedDateStr, filters = {}) {
  const targetDate = parseDate(selectedDateStr)
  if (!targetDate) return []
  targetDate.setHours(0, 0, 0, 0)
  const targetTime = targetDate.getTime()

  return rows.filter(r => {
    const arrDate = parseDate(r.arrivalDate || r.ngayDen)
    if (arrDate) arrDate.setHours(0, 0, 0, 0)
    const arrTime = arrDate ? arrDate.getTime() : null

    const depDate = parseDate(r.departureDate || r.ngayDi)
    if (depDate) depDate.setHours(0, 0, 0, 0)
    const depTime = depDate ? depDate.getTime() : null

    const status = Number(r.roomStatus)

    // A. Phòng cũ tình trạng 100 (STATUS_MOVED)
    if (status === 100) {
      if (r.isSameDayMoved) {
        // Vừa check in xong chuyển cùng ngày: không hiển thị phòng cũ (tình trạng 100)
        return false
      }
      if (r.isStayedThenMoved && arrTime === targetTime) {
        // Ngày check-in ban đầu: hiển thị phòng ban đầu
        return true
      }
      return false
    }

    // B. Phòng in-house (status = 1)
    if (status === 1) {
      // B.1. Phòng chuyển tới sau khi đã ở (isStayedThenMoved)
      if (r.isStayedThenMoved) {
        if (arrTime === targetTime) {
          // Ngày xem = ngày chuyển phòng (cũng là ngày in của phòng mới)
          // Chỉ hiển thị khi có tick chọn "Phòng Chuyển" (roomMove)
          return !!filters.roomMove
        }
        if (arrTime && depTime && arrTime < targetTime && targetTime < depTime) {
          // Khách đang ở trong những ngày kế tiếp
          return !!filters.inHouse
        }
        return false
      }

      // B.2. Phòng bình thường hoặc chuyển cùng ngày nhận phòng
      if (arrTime === targetTime) {
        // Ngày đến = ngày cần xem
        return true
      }
      if (arrTime && depTime && arrTime < targetTime && targetTime < depTime) {
        // Khách đang ở: ArrivalDate < ngày xem < CheckoutDate
        return !!filters.inHouse
      }
      return false
    }

    return false
  })
}
