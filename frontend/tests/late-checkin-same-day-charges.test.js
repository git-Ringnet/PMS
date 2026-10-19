import test from 'node:test'
import assert from 'node:assert/strict'

function resolveNights(room) {
  const checkIn = room.checkIn
  const checkOut = room.checkOut
  const isSameDay = !!checkIn && !!checkOut && (checkIn === checkOut)
  const isDayUse = !!room.hourly && isSameDay
  const rawNightCount = Number(room.nights)
  return isSameDay
    ? (isDayUse ? 1 : 0)
    : (Number.isFinite(rawNightCount) ? Math.max(0, rawNightCount) : 1)
}

function simulateRoomDisplayServices(room) {
  const list = []
  const nights = resolveNights(room)
  const checkIn = room.checkIn

  if (checkIn && nights > 0) {
    for (let i = 0; i < nights; i++) {
      const parts = checkIn.split('-')
      const curr = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
      curr.setDate(curr.getDate() + i)
      const dStr = `${curr.getFullYear()}-${String(curr.getMonth() + 1).padStart(2, '0')}-${String(curr.getDate()).padStart(2, '0')}`
      list.push({
        id: `room-charge-${room.id}-${i}`,
        service_date: dStr,
        service_name: 'Dịch vụ phòng nghỉ',
        quantity: 1,
        rate: Number(room.price) || 0,
        is_room: true
      })
    }
  }

  // Quét các hóa đơn ngoài dải ngày lưu trú (hoặc đã post từ noshow trước đó)
  ;(room.serviceBills || []).forEach(sb => {
    list.push({
      id: `service-bill-${sb.Ma || sb.id}`,
      service_date: sb.ChargeDate || sb.service_date,
      service_name: sb.DescriptionServive || 'Dịch vụ phòng nghỉ - Phòng 806',
      quantity: Number(sb.Quantity) || 1,
      rate: Number(sb.Amount) || 0,
      is_room: true,
      from_bill: true
    })
  })

  return list
}

function calculateTotal(services) {
  return services.reduce((sum, s) => sum + (Number(s.rate) * (Number(s.quantity) || 1)), 0)
}

test('late check-in 1 night noshow room (in 02/09, out 02/09) with hourly = false only displays night 1 noshow bill (540,000 VND)', () => {
  const room = {
    id: 101,
    bookingRoomId: 'BR-806',
    checkIn: '2026-09-02',
    checkOut: '2026-09-02',
    nights: 0,
    hourly: false,
    price: 540000,
    serviceBills: [
      { Ma: 991, ServiceId: 'RM', Quantity: 1, Amount: 540000, ChargeDate: '2026-09-01', DescriptionServive: 'Dịch vụ phòng nghỉ - Phòng 806' }
    ]
  }

  const services = simulateRoomDisplayServices(room)
  assert.equal(services.length, 1)
  assert.equal(services[0].service_date, '2026-09-01')
  assert.equal(services[0].rate, 540000)
  assert.equal(calculateTotal(services), 540000)
})

test('late check-in room (in 02/09, out 02/09) when hourly = true displays day 2 room charge AND night 1 noshow bill (1,080,000 VND)', () => {
  const room = {
    id: 101,
    bookingRoomId: 'BR-806',
    checkIn: '2026-09-02',
    checkOut: '2026-09-02',
    nights: 0,
    hourly: true,
    price: 540000,
    serviceBills: [
      { Ma: 991, ServiceId: 'RM', Quantity: 1, Amount: 540000, ChargeDate: '2026-09-01', DescriptionServive: 'Dịch vụ phòng nghỉ - Phòng 806' }
    ]
  }

  const services = simulateRoomDisplayServices(room)
  assert.equal(services.length, 2)
  assert.equal(services[0].service_date, '2026-09-02')
  assert.equal(services[0].rate, 540000)
  assert.equal(services[1].service_date, '2026-09-01')
  assert.equal(services[1].rate, 540000)
  assert.equal(calculateTotal(services), 1080000)
})

test('normal overnight room (in 01/09, out 03/09, 2 nights) still calculates 2 nights normally', () => {
  const room = {
    id: 102,
    checkIn: '2026-09-01',
    checkOut: '2026-09-03',
    nights: 2,
    hourly: false,
    price: 500000,
    serviceBills: []
  }

  const services = simulateRoomDisplayServices(room)
  assert.equal(services.length, 2)
  assert.equal(calculateTotal(services), 1000000)
})
