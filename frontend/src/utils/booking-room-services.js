// Booking 4 — service detail rules shared by the registration screen.

const dateOnly = value => String(value || '').slice(0, 10)

/**
 * A projected service belongs to the stay when its date is inside
 * [arrival, departure). A day-use room (arrival = departure) keeps its
 * arrival date. Rows outside the stay are kept in the database so an
 * extension restores them (Link Hotel / SP2102), but they are not shown.
 */
export function isServiceDateInStay(serviceDate, checkIn, checkOut, isDayUse = false) {
  const date = dateOnly(serviceDate)
  const arrival = dateOnly(checkIn)
  const departure = dateOnly(checkOut)
  if (!date || !arrival || !departure) return true
  if (arrival === departure) return isDayUse ? date === arrival : false
  return date >= arrival && date < departure
}

/**
 * Row 343: the "add extra service" picker only offers services ticked
 * "Hiển thị khi post bill" (is_active). RM - room charge is always offered.
 * Names of services already on a room are still resolved from the full list.
 */
export function isAddableHotelService(service) {
  if (!service) return false
  if (String(service.code || '').trim().toUpperCase() === 'RM') return true
  return service.is_active !== false && service.is_active !== 0 && service.is_active !== '0'
}

export function normalizeChildBreakfastConfig(config) {
  const code = String(config?.service_code || 'BD').trim() || 'BD'
  return {
    serviceCode: code,
    serviceName: config?.service_name || '',
    detailMode: config?.detail_mode !== false,
  }
}

export function isChildBreakfastCode(code, config) {
  const target = normalizeChildBreakfastConfig(config).serviceCode
  return String(code || '').trim().toUpperCase() === target.toUpperCase()
}

/**
 * Service mode keeps projected surcharge rows in booking_room_services and
 * they take display priority over the daily breakfast details.
 */
export function childBreakfastServiceDates(services, config) {
  const normalized = normalizeChildBreakfastConfig(config)
  if (normalized.detailMode) return new Set()
  return new Set((services || [])
    .filter(service => isChildBreakfastCode(service.service_code, config))
    .map(service => dateOnly(service.service_date)))
}

/**
 * Section 9: cancelled rooms list sorting. Default is the most recent
 * cancellation first; empty values always sink to the bottom.
 */
export function sortCancelledRooms(rows, key = 'cancelled_at', direction = 'desc') {
  const factor = direction === 'asc' ? 1 : -1
  return [...(rows || [])].sort((left, right) => {
    const a = left?.[key]
    const b = right?.[key]
    const emptyA = a === null || a === undefined || a === ''
    const emptyB = b === null || b === undefined || b === ''
    if (emptyA || emptyB) return emptyA === emptyB ? 0 : (emptyA ? 1 : -1)
    return String(a).localeCompare(String(b), 'vi', { numeric: true }) * factor
  })
}

/**
 * Description shown in the per-night money detail: the hotel service
 * definition name, the same for past (billed) and future (projected) nights.
 */
export function hotelServiceDisplayName(code, hotelServices, fallback = '') {
  const target = String(code || '').trim().toUpperCase()
  const service = (hotelServices || []).find(item => String(item.code || '').trim().toUpperCase() === target)
  return service?.name || fallback || code || ''
}

/**
 * Row 397: a price edited on the room row only changes the room rate. Nightly
 * prices are kept only for nights that already have a saved room-charge row
 * (booking_room_services); every other night follows the room rate, so the
 * save does not insert nightly rows.
 */
export function keepSavedDailyRoomPrices(dailyRoomPrices, services) {
  if (!dailyRoomPrices) return null
  const savedDates = new Set((services || [])
    .filter(service => service?.id && ['RM', 'ROOM_CHARGE'].includes(String(service.service_code || '').toUpperCase()))
    .map(service => dateOnly(service.service_date)))
  const kept = {}
  Object.keys(dailyRoomPrices).forEach(date => {
    if (savedDates.has(dateOnly(date))) kept[date] = dailyRoomPrices[date]
  })
  return Object.keys(kept).length ? kept : null
}
