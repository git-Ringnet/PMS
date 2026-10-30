const toDate = value => String(value || '').slice(0, 10)

/**
 * Phòng thuộc danh sách "Phòng chưa trả" của ngày `date`:
 * - phòng đang ở có ngày đi <= ngày đang xét;
 * - dòng 392: phòng chưa nhận có ngày đến = ngày đi = ngày đang xét (dù có tick Day Use hay không).
 */
export function isPendingDepartureRoom(room, date, normalize = toDate) {
  const day = normalize(date)
  const status = Number(room?.status)
  const departure = normalize(room?.departure_date)

  if (status === 1) return departure <= day
  if (status === 0) return normalize(room?.arrival_date) === day && departure === day
  return false
}
