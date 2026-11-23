const DAY_MS = 24 * 60 * 60 * 1000

const utcDay = value => {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''))
  return match ? Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null
}

/** Dòng 508 (note 22/11): chọn từ ngày đến ngày là các ngày được charge, tính cả hai đầu. */
export function noshowChargeNights(dateFrom, dateTo) {
  const from = utcDay(dateFrom)
  const to = utcDay(dateTo)
  if (from === null || to === null || to < from) return 0
  return Math.round((to - from) / DAY_MS) + 1
}
