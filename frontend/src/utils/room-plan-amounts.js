const dateOnly = value => String(value || '').slice(0, 10)

const codeOf = service => String(
  service.service_code ?? service.serviceCode ?? service.ServiceId ?? ''
).toUpperCase()

const amountOf = service => {
  const quantity = Number(service.quantity ?? service.Quantity ?? 1) || 1
  const rate = Number(service.rate ?? service.price ?? service.Amount ?? service.amount ?? 0) || 0
  return Number(service.total_amount ?? service.totalAmount ?? '') || (rate * quantity)
}

const isRoomCharge = service => ['RM', 'ROOM_CHARGE'].includes(codeOf(service))

const isChildBreakfast = service => codeOf(service) === 'BD'
  || String(service.note ?? service.Note ?? '').toLowerCase().includes('phụ thu ăn sáng trẻ em')

export function calculateRoomPlanRoomAmounts(room, booking, systemDate) {
  const today = dateOnly(systemDate || new Date())
  const arrival = dateOnly(room.arrival_date || booking.arrival_date)
  const departure = dateOnly(room.departure_date || booking.departure_date)
  const isProjectedDate = value => {
    const date = dateOnly(value)
    return date >= today && (!departure || date < departure)
  }

  const setupBillIds = new Set((room.services || [])
    .map(service => service.service_bill_id ?? service.serviceBillId)
    .filter(value => value !== null && value !== undefined && value !== '')
    .map(String))

  const roomBills = [
    ...(room.service_bills || room.serviceBills || []),
    ...(room.current_service_bills || room.currentServiceBills || []),
    ...(booking.master_service_bills || []).filter(bill => String(bill.RentalRoomId1 || '') === String(room.id))
  ]

  const historicalBills = roomBills.filter(bill => {
    const billId = String(bill.Ma ?? bill.id ?? '')
    const billCode = String(bill.ServiceId || '').toUpperCase()
    return Number(bill.Edit) !== 1
      && dateOnly(bill.Date) < today
      && (['RM', 'EB', 'BD'].includes(billCode) || setupBillIds.has(billId))
  })

  const historicalRoomCharge = historicalBills
    .filter(bill => ['RM', 'ROOM_CHARGE'].includes(String(bill.ServiceId || '').toUpperCase()))
    .reduce((sum, bill) => sum + (Number(bill.Amount) || 0), 0)

  const historicalServiceCharge = historicalBills
    .filter(bill => !['RM', 'ROOM_CHARGE'].includes(String(bill.ServiceId || '').toUpperCase()))
    .reduce((sum, bill) => sum + (Number(bill.Amount) || 0), 0)

  const projectedServices = (room.services || []).filter(service => (
    isProjectedDate(service.service_date)
    && Number(service.is_posted) !== 1
    && !service.service_bill_id
    && !service.housekeeping_service_bill_id
  ))

  const projectedNights = Math.max(0, Math.round((new Date(departure) - new Date(today)) / (1000 * 60 * 60 * 24)))
  const fullNights = Math.max(1, Math.round((new Date(departure) - new Date(arrival)) / (1000 * 60 * 60 * 24)) || Number(booking.num_of_days) || 1)

  const roomCharge = projectedServices
    .filter(isRoomCharge)
    .reduce((sum, service) => sum + amountOf(service), 0)
    || (Number(room.rate) || 0) * (today > arrival ? projectedNights : fullNights)

  const hasProjectedExtraBed = projectedServices.some(service => codeOf(service) === 'EB')
  const extraBedAmount = hasProjectedExtraBed
    ? 0
    : (Number(room.extra_bed_qty) || 0) * (Number(room.extra_bed_rate) || 0) * (today > arrival ? projectedNights : fullNights)

  const childBreakfastAmount = (room.children || [])
    .flatMap(child => child.breakfast_details || child.breakfastDetails || [])
    .filter(detail => Number(detail.is_extra_charge) === 1
      && Number(detail.breakfast) === 1
      && isProjectedDate(detail.service_date))
    .reduce((sum, detail) => sum + (Number(detail.amount) || 0), 0)

  return {
    roomCharge: historicalRoomCharge + roomCharge,
    serviceCharge: historicalServiceCharge + projectedServices
      .filter(service => !isRoomCharge(service) && !isChildBreakfast(service))
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
