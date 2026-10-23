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

export function calculateRoomPlanRoomAmounts(room, booking, systemDate) {
  const today = dateOnly(systemDate || new Date())
  const arrival = dateOnly(room.arrival_date || booking.arrival_date)
  const departure = dateOnly(room.departure_date || booking.departure_date)
  const isStayDate = value => {
    const date = dateOnly(value)
    if (!date) return true
    return (!arrival || date >= arrival) && (!departure || date < departure)
  }

  const setupBillIds = new Set((room.services || [])
    .map(service => service.service_bill_id ?? service.serviceBillId)
    .filter(value => value !== null && value !== undefined && value !== '')
    .map(String))

  const roomBills = [
    ...(room.service_bills || room.serviceBills || []),
    ...(room.current_service_bills || room.currentServiceBills || []),
    ...(booking.master_service_bills || []).filter(bill => String(bill.RentalRoomId1 || '') === String(room.id))
  ].filter((bill, index, bills) => {
    const billId = bill.Ma ?? bill.id
    return billId === null || billId === undefined || billId === ''
      || index === bills.findIndex(candidate => String(candidate.Ma ?? candidate.id ?? '') === String(billId))
  })

  const historicalBills = roomBills.filter(bill => {
    const billId = String(bill.Ma ?? bill.id ?? '')
    const billCode = String(bill.ServiceId || '').toUpperCase()
    return Number(bill.Edit) !== 1
      && (['RM', 'EB', 'BD'].includes(billCode) || setupBillIds.has(billId))
  })

  const historicalRoomCharge = historicalBills
    .filter(bill => ['RM', 'ROOM_CHARGE'].includes(String(bill.ServiceId || '').toUpperCase()))
    .reduce((sum, bill) => sum + (Number(bill.Amount) || 0), 0)

  const historicalServiceCharge = historicalBills
    .filter(bill => !['RM', 'ROOM_CHARGE'].includes(String(bill.ServiceId || '').toUpperCase()))
    .reduce((sum, bill) => sum + (Number(bill.Amount) || 0), 0)

  const unpostedServices = (room.services || []).filter(service => (
    isStayDate(service.service_date)
    && Number(service.is_posted) !== 1
    && !service.service_bill_id
    && !service.housekeeping_service_bill_id
  ))

  const fullNights = Math.max(1, Math.round((new Date(departure) - new Date(arrival)) / (1000 * 60 * 60 * 24)) || Number(booking.num_of_days) || 1)

  const unpostedRoomCharge = unpostedServices
    .filter(isRoomCharge)
    .reduce((sum, service) => sum + amountOf(service), 0)

  const roomCharge = (historicalRoomCharge + unpostedRoomCharge) > 0
    ? (historicalRoomCharge + unpostedRoomCharge)
    : (Number(room.rate) || 0) * fullNights

  const unpostedExtraBed = unpostedServices
    .filter(service => codeOf(service) === 'EB')
    .reduce((sum, service) => sum + amountOf(service), 0)
  const hasDailyExtraBedSetup = unpostedServices.some(service => codeOf(service) === 'EB')

  const hasExtraBedBill = historicalBills.some(bill => String(bill.ServiceId || '').toUpperCase() === 'EB')

  const extraBedAmount = (hasDailyExtraBedSetup || hasExtraBedBill)
    ? unpostedExtraBed
    : (Number(room.extra_bed_qty) || 0) * (Number(room.extra_bed_rate) || 0) * fullNights

  const childBreakfastAmount = (room.children || [])
    .flatMap(child => child.breakfast_details || child.breakfastDetails || [])
    .filter(detail => Number(detail.is_extra_charge) === 1
      && Number(detail.breakfast) === 1
      && isStayDate(detail.service_date))
    .reduce((sum, detail) => sum + (Number(detail.amount) || 0), 0)

  return {
    roomCharge,
    serviceCharge: historicalServiceCharge + unpostedServices
      .filter(service => !isRoomCharge(service) && !isChildBreakfast(service) && codeOf(service) !== 'EB')
      .reduce((sum, service) => sum + amountOf(service), 0)
      + extraBedAmount
      + childBreakfastAmount
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
