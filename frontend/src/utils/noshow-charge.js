const DAY_MS = 24 * 60 * 60 * 1000

const utcDay = value => {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''))
  return match ? Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null
}

const isoDay = time => new Date(time).toISOString().slice(0, 10)

/** Dòng 508: tiền tự động = giá đã thiết lập của từng ngày được chọn, ngày chưa có giá lấy giá phòng. */
export function noshowAutoAmount(dateFrom, dateTo, roomRate, dailyPrices) {
  const rate = Number(roomRate) || 0
  const nights = noshowChargeNights(dateFrom, dateTo)
  if (nights === 0) return rate
  const start = utcDay(dateFrom)
  let total = 0
  for (let index = 0; index < nights; index++) {
    const price = dailyPrices?.[isoDay(start + index * DAY_MS)]
    total += price !== undefined && price !== null && (Number(price) > 0 || rate > 0) ? Number(price) : rate
  }
  return total
}

/** Dòng 508 (note 22/11): chọn từ ngày đến ngày là các ngày được charge, tính cả hai đầu. */
export function noshowChargeNights(dateFrom, dateTo) {
  const from = utcDay(dateFrom)
  const to = utcDay(dateTo)
  if (from === null || to === null || to < from) return 0
  return Math.round((to - from) / DAY_MS) + 1
}
