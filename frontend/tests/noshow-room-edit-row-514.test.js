import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { buildNoshowRoomEdits, isNoshowRoom } from '../src/utils/booking-room-allocations.js'

const rooms = [
  { roomClassId: 7, bookingRoomId: 'G1', bookingRoomStatus: 0, checkIn: '2026-11-06', checkOut: '2026-11-08' },
  {
    roomClassId: 7, bookingRoomId: 'G2', bookingRoomStatus: 4, roomNumber: '102', checkIn: '2026-11-07', checkOut: '2026-11-08',
    price: 1200000, adults: 2, babies: 0, children: 1, guestName: 'Khach tre', rateCode: 'BAR', breakfast: true,
    extraBedQty: 1, extraBedPrice: 300000, arrivalTime: '14:00', hoursOut: '12:00',
  },
  { roomClassId: 7, bookingRoomId: 'G3', bookingRoomStatus: 3 },
  { roomClassId: 7 },
]

// Dòng 514: phòng noshow phải được gửi lên khi lưu (trước đây bị loại hẳn khỏi payload).
test('row 514: only persisted no-show rooms are sent as noshow_rooms', () => {
  assert.equal(isNoshowRoom(rooms[1]), true)
  assert.equal(isNoshowRoom(rooms[0]), false)
  assert.equal(isNoshowRoom(rooms[3]), false)
  const edits = buildNoshowRoomEdits(rooms)
  assert.equal(edits.length, 1)
  assert.deepEqual(edits[0], {
    bookingRoomId: 'G2', arrivalDate: '2026-11-07', departureDate: '2026-11-08', roomNumber: '102',
    price: 1200000, adults: 2, babies: 0, children: 1, guestName: 'Khach tre', rateCode: 'BAR', breakfast: true,
    extraBedQty: 1, extraBedPrice: 300000, arrivalTime: '14:00', hoursOut: '12:00',
  })
})

test('row 514: the booking save payload carries noshow_rooms and noshow rows recompute nights', () => {
  const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
  assert.match(source, /noshow_rooms:\s+buildNoshowRoomEdits\(tab\.rooms\)/)
  const inline = source.match(/async function handleRowDateChangeInline\(room\) \{([\s\S]*?)\n\}/)[1]
  assert.match(inline, /isNoshowRoom\(room\)/)
})
