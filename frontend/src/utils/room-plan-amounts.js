const dateOnly = value => String(value || '').slice(0, 10)

const codeOf = service => String(
  service.service_code ?? service.serviceCode ?? service.ServiceId ?? ''
).toUpperCase()

const amountOf = service => {
  const rawQuantity = service.quantity ?? service.Quantity
  const quantity = rawQuantity === null || rawQuantity === undefined || rawQuantity === ''
    ? 1
    : (Number(rawQuantity) || 0)
  const rate = Number(service.rate ?? service.price ?? service.Amount ?? service.amount ?? 0) || 0
  const rawTotalAmount = service.total_amount ?? service.totalAmount
  if (rawTotalAmount !== null && rawTotalAmount !== undefined && rawTotalAmount !== '') {
    const totalAmount = Number(rawTotalAmount)
    if (Number.isFinite(totalAmount)) return totalAmount
  }
  return rate * quantity
}

const isRoomCharge = service => ['RM', 'ROOM_CHARGE'].includes(codeOf(service))

const isChildBreakfast = service => codeOf(service) === 'BD'
  || String(service.note ?? service.Note ?? '').toLowerCase().includes('phụ thu ăn sáng trẻ em')

const addDays = (date, days) => {
  const [year, month, day] = date.split('-').map(Number)
  const value = new Date(Date.UTC(year, month - 1, day + days))
  return value.toISOString().slice(0, 10)
}

const stayDatesOf = (room, booking) => {
  const arrival = dateOnly(room.arrival_date || booking?.arrival_date)
  const departure = dateOnly(room.departure_date || booking?.departure_date)
  if (!arrival) return []

  if (arrival === departure) {
    return room.is_day_use ?? booking?.is_day_use ? [arrival] : []
  }
  if (!departure || departure < arrival) return []

  const dates = []
  for (let date = arrival; date < departure; date = addDays(date, 1)) {
    dates.push(date)
  }
  return dates
}

const isActivePayment = payment => Number(payment?.edit_flag ?? 0) === 0
  && !payment?.deleted_at
  && Number(payment?.status) !== 3

const isTransferInPayment = payment => String(payment?.description ?? '')
  .trim()
  .toUpperCase()
  .startsWith('[TRANSFER IN')

const paymentKey = (payment, index) => {
  const id = payment?.id ?? payment?.paymentId ?? payment?.legacy_id
  return id !== null && id !== undefined && id !== '' ? `id:${id}` : `row:${index}`
}

/** Mutually exclusive, de-duplicated totals for the Room Plan tooltip. */
export function calculateRoomPlanPaymentAmounts(booking) {
  const seen = new Set()
  const payments = (booking?.payments || []).filter((payment, index) => {
    const key = paymentKey(payment, index)
    if (seen.has(key)) return false
    seen.add(key)
    return isActivePayment(payment) && !isTransferInPayment(payment)
  })

  return payments.reduce((totals, payment) => {
    const amount = Number(payment.amount) || 0
    const isPaid = Number(payment.status) === 2
    const isUnusedDeposit = String(payment.pack2 || '').toUpperCase() === 'DPR'
      && String(payment.pack4 || '').toUpperCase() !== 'AP'
      && !payment.payment_id
      && !payment.paymentId
      && !isPaid

    if (isUnusedDeposit) totals.depositAmount += amount
    if (isPaid) totals.paidAmount += amount
    return totals
  }, { depositAmount: 0, paidAmount: 0 })
}

const noteText = value => typeof value === 'string' ? value.trim() : ''

const requestNote = value => {
  if (typeof value === 'string') return value.trim()
  if (!Array.isArray(value)) return ''
  return value
    .map(item => noteText(item?.special_request?.name)
      || noteText(item?.specialRequest?.name)
      || noteText(item?.name)
      || noteText(item?.note))
    .filter(Boolean)
    .join(', ')
}

export function resolveRoomPlanBookingNote(booking, room) {
  return noteText(booking?.note)
    || requestNote(booking?.special_requests ?? booking?.specialRequests)
    || noteText(room?.note)
    || requestNote(room?.special_requests ?? room?.specialRequests)
}

export function calculateRoomPlanRoomAmounts(room, booking) {
  const stayDates = stayDatesOf(room, booking)
  const stayDateSet = new Set(stayDates)
  const isStayDate = value => {
    const date = dateOnly(value)
    return !date || stayDateSet.has(date)
  }

  // Room Plan is a registration projection.  Checkout/folio bills can be
  // loaded alongside a booking for payment display, but never participate in
  // this total.  Every amount below comes from the registration setup of the
  // same booking room (or its child-breakfast setup).
  const setupServices = (room.services || []).filter(service => isStayDate(service.service_date))
  const roomChargeServices = setupServices.filter(isRoomCharge)
  const configuredRoomDates = new Set(roomChargeServices
    .map(service => dateOnly(service.service_date))
    .filter(Boolean))

  const configuredRoomCharge = roomChargeServices
    .reduce((sum, service) => sum + amountOf(service), 0)
  const fallbackRoomCharge = stayDates
    .filter(date => !configuredRoomDates.has(date))
    .reduce(sum => sum + (Number(room.rate) || 0), 0)
  const roomCharge = configuredRoomCharge + fallbackRoomCharge

  const childBreakfastDetails = (room.children || [])
    .flatMap(child => child.breakfast_details || child.breakfastDetails || [])
    .filter(detail => Number(detail.is_extra_charge) === 1
      && Number(detail.breakfast) === 1
      && isStayDate(detail.service_date))
  const childBreakfastDates = new Set(childBreakfastDetails
    .map(detail => dateOnly(detail.service_date))
    .filter(Boolean))
  const childBreakfastAmount = childBreakfastDetails
    .reduce((sum, detail) => sum + (Number(detail.amount) || 0), 0)

  const setupServiceAmount = setupServices
    .filter(service => !isRoomCharge(service))
    // Chi tiết ăn sáng trẻ em là snapshot chi tiết hơn của cùng ngày; tránh
    // cộng lại dòng BD được tạo đồng thời trong setup dịch vụ.
    .filter(service => !isChildBreakfast(service) || !childBreakfastDates.has(dateOnly(service.service_date)))
    .reduce((sum, service) => sum + amountOf(service), 0)

  const hasDailyExtraBedSetup = setupServices.some(service => codeOf(service) === 'EB')
  const fallbackExtraBedAmount = hasDailyExtraBedSetup
    ? 0
    : stayDates.length * (Number(room.extra_bed_qty) || 0) * (Number(room.extra_bed_rate) || 0)

  return {
    roomCharge,
    serviceCharge: setupServiceAmount + childBreakfastAmount + fallbackExtraBedAmount
  }
}

export function calculateRoomPlanBookingAmounts(booking, systemDate) {
  return (booking.booking_rooms || [])
    .filter(room => Number(room.status) !== 3)
    .map(room => calculateRoomPlanRoomAmounts(room, booking, systemDate))
    .reduce((totals, amounts) => ({
      roomCharge: totals.roomCharge + amounts.roomCharge,
      serviceCharge: totals.serviceCharge + amounts.serviceCharge
    }), { roomCharge: 0, serviceCharge: 0 })
}
