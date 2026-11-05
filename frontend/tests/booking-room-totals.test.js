import test from 'node:test'
import assert from 'node:assert/strict'
import { summarizeRoomTotals } from '../src/utils/booking-room-totals.js'

const fn = {
  chargeTotal: r => r.charge,
  extraBedQty: r => r.ebQty || 0,
  extraBedTotal: r => r.eb || 0,
  roomTotal: r => r.charge + (r.eb || 0),
}

test('row 482: tổng tiền cộng cả phòng chuyển nhưng không đếm phòng/khách của nó', () => {
  const rooms = [
    { bookingRoomStatus: 1, adults: 2, charge: 2800000 },
    { bookingRoomStatus: 100, adults: 2, charge: 750000, eb: 300000, ebQty: 1 },
  ]

  const s = summarizeRoomTotals(rooms, fn)

  assert.equal(s.total, 3850000)
  assert.equal(s.priceSum, 3550000)
  assert.equal(s.extraBed, 300000)
  assert.equal(s.count, 1)
  assert.equal(s.adults, 2)
  assert.equal(s.extraBedQty, 0)
})

test('row 482: phòng hủy không tính tiền', () => {
  const s = summarizeRoomTotals([{ bookingRoomStatus: 3, charge: 500000 }, { bookingRoomStatus: 0, charge: 100000 }], fn)
  assert.equal(s.total, 100000)
  assert.equal(s.count, 1)
})
