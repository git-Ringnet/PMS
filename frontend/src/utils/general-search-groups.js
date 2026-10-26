// Tìm kiếm chung - tab Đăng ký: gộp phòng của booking theo loại phòng/ngày/mã giá (sp_034).

export function getBookingRoomGroups(rooms) {
  if (!rooms || !rooms.length) return []
  const map = new Map()
  for (const r of rooms) {
    const rType = r.room_class?.code || r.room_class?.name || '—'
    const arr = r.arrival_date || ''
    const dep = r.departure_date || ''
    const rateCode = r.rate_code || '—'
    const rateVal = Number(r.rate || 0)
    const key = `${rType}|${arr}|${dep}|${rateCode}|${rateVal}`

    if (!map.has(key)) {
      map.set(key, {
        room_type: rType,
        room_count: 0,
        adults: 0,
        babies: 0,
        children: 0,
        arrival_date: arr,
        departure_date: dep,
        rate_code: rateCode,
        rate: rateVal,
        service_total: 0
      })
    }
    const g = map.get(key)
    g.room_count += 1
    g.adults += Number(r.adults || 0)
    g.babies += Number(r.babies || 0)
    g.children += Number(r.children || 0)
    // Dòng 409: tiền từng phòng tính như màn hình Booking (room_total từ API).
    g.service_total += Number(r.room_total ?? r.service_total ?? 0)
  }
  return Array.from(map.values())
}

export function getSubtableTotals(groups) {
  return groups.reduce((acc, g) => {
    acc.rooms += g.room_count
    acc.adults += g.adults
    acc.babies += g.babies
    acc.children += g.children
    acc.total += g.service_total
    return acc
  }, { rooms: 0, adults: 0, babies: 0, children: 0, total: 0 })
}
