import assert from 'node:assert/strict'
import test from 'node:test'
import {
  isHeaderDayUseTransition,
  synchronizeHeaderDayUseRooms,
} from '../src/utils/booking-header-day-use.js'

test('header 1 night to 0 nights explicitly becomes Day-use', () => {
  assert.equal(isHeaderDayUseTransition({
    nights: 0,
    checkIn: '2026-08-31',
    checkOut: '2026-08-31',
  }), true)
})

test('a normal header stay does not become Day-use', () => {
  assert.equal(isHeaderDayUseTransition({
    nights: 1,
    checkIn: '2026-08-31',
    checkOut: '2026-09-01',
  }), false)
})

test('header transition changes only eligible reservation rooms', () => {
  const rooms = [
    { id: 'booked', hourly: false, status: 0 },
    { id: 'checked-in', hourly: false, status: 1 },
    { id: 'cancelled', hourly: false, status: 3 },
  ]

  synchronizeHeaderDayUseRooms(rooms, true, room => room.status === 0)

  assert.equal(rooms[0].hourly, true)
  assert.equal(rooms[1].hourly, false)
  assert.equal(rooms[2].hourly, false)

  synchronizeHeaderDayUseRooms(rooms, false, room => room.status === 0)
  assert.equal(rooms[0].hourly, false)
})
