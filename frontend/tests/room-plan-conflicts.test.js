import test from 'node:test'
import assert from 'node:assert/strict'
import { roomPlanStayRange, markRoomPlanConflicts } from '../src/utils/room-plan-conflicts.js'

const bar = (bookingRoomId, code, start, end, extra = {}) => ({
  bookingRoomId,
  code,
  ...extra,
  _range: roomPlanStayRange({ start, end, ...extra }),
})

test('stay range: overnight is [arrival, departure), day-use is its day, same-day without day-use holds nothing', () => {
  assert.deepEqual(roomPlanStayRange({ start: '2026-09-07', end: '2026-09-10' }), { start: '2026-09-07', end: '2026-09-10' })
  assert.deepEqual(roomPlanStayRange({ start: '2026-09-07', end: '2026-09-07', isDayUse: true }), { start: '2026-09-07', end: '2026-09-08' })
  assert.equal(roomPlanStayRange({ start: '2026-09-07', end: '2026-09-07' }), null)
})

test('stay range: lock includes its end day unless it ends before the cut-off time', () => {
  assert.deepEqual(roomPlanStayRange({ start: '2026-09-07', end: '2026-09-09', isLock: true }), { start: '2026-09-07', end: '2026-09-10' })
  assert.deepEqual(roomPlanStayRange({ start: '2026-09-07', end: '2026-09-09', isLock: true, lockEndedEarly: true }), { start: '2026-09-07', end: '2026-09-09' })
})

test('row 395 screenshot: GAL33 (07/09-08/09) and GAL32 (07/09-10/09) on room 304 are both marked and stacked', () => {
  const gal33 = bar('G33', 'GAL33', '2026-09-07', '2026-09-08')
  const gal32 = bar('G32', 'GAL32', '2026-09-07', '2026-09-10')
  markRoomPlanConflicts([gal32, gal33])

  assert.equal(gal32.hasConflict, true)
  assert.equal(gal33.hasConflict, true)
  assert.equal(gal32.conflictLaneCount, 2)
  assert.notEqual(gal32.conflictLane, gal33.conflictLane)
  assert.deepEqual(gal32.conflictWith, ['GAL33'])
  assert.deepEqual(gal33.conflictWith, ['GAL32'])
})

test('back-to-back stays (out 08/09, in 08/09) are not a conflict', () => {
  const a = bar('A', 'GAL1', '2026-09-07', '2026-09-08')
  const b = bar('B', 'GAL2', '2026-09-08', '2026-09-10')
  markRoomPlanConflicts([a, b])
  assert.equal(a.hasConflict, false)
  assert.equal(b.hasConflict, false)
})

test('same-day row without day-use never conflicts; day-use on an occupied night does', () => {
  const stay = bar('S', 'GAL1', '2026-09-07', '2026-09-09')
  const sameDay = bar('N', 'GAL2', '2026-09-08', '2026-09-08')
  const dayUse = bar('D', 'GAL3', '2026-09-08', '2026-09-08', { isDayUse: true })
  markRoomPlanConflicts([stay, sameDay, dayUse])
  assert.equal(sameDay.hasConflict, false)
  assert.equal(dayUse.hasConflict, true)
  assert.equal(stay.hasConflict, true)
})

test('Day Use cùng phòng khác giờ không bị coi là trùng phòng', () => {
  const morning = bar('D1', 'GAL-D1', '2026-09-30', '2026-09-30', {
    isDayUse: true,
    arrival_time: '08:00',
    departure_time: '12:00',
  })
  const afternoon = bar('D2', 'GAL-D2', '2026-09-30', '2026-09-30', {
    isDayUse: true,
    arrival_time: '14:00',
    departure_time: '18:00',
  })

  markRoomPlanConflicts([morning, afternoon])

  assert.equal(morning.hasConflict, false)
  assert.equal(afternoon.hasConflict, false)
  assert.equal(morning.conflictLaneCount, 1)
  assert.equal(afternoon.conflictLaneCount, 1)
})

test('Day Use cùng phòng giao giờ vẫn bị cảnh báo và xếp khác làn', () => {
  const first = bar('D1', 'GAL-D1', '2026-09-30', '2026-09-30', {
    isDayUse: true,
    arrival_time: '08:00',
    departure_time: '12:00',
  })
  const second = bar('D2', 'GAL-D2', '2026-09-30', '2026-09-30', {
    isDayUse: true,
    arrival_time: '11:00',
    departure_time: '15:00',
  })

  markRoomPlanConflicts([first, second])

  assert.equal(first.hasConflict, true)
  assert.equal(second.hasConflict, true)
  assert.equal(first.conflictLaneCount, 2)
  assert.notEqual(first.conflictLane, second.conflictLane)
})

test('Day Use liền giờ đến 24:00 không bị coi là trùng phòng', () => {
  const first = bar('D1', 'GAL-D1', '2026-09-30', '2026-09-30', {
    isDayUse: true,
    arrival_time: '22:00',
    departure_time: '23:00',
  })
  const second = bar('D2', 'GAL-D2', '2026-09-30', '2026-09-30', {
    isDayUse: true,
    arrival_time: '23:00',
    departure_time: '24:00',
  })

  markRoomPlanConflicts([first, second])

  assert.equal(first.hasConflict, false)
  assert.equal(second.hasConflict, false)
})

test('a booking overlapping an OOO lock is marked with the lock label', () => {
  const lock = { code: 'LOCK', type: 'OOO', _range: roomPlanStayRange({ start: '2026-09-09', end: '2026-09-09', isLock: true }) }
  const stay = bar('S', 'GAL5', '2026-09-08', '2026-09-10')
  markRoomPlanConflicts([stay, lock])
  assert.equal(stay.hasConflict, true)
  assert.deepEqual(stay.conflictWith, ['Khóa phòng OOO'])
})

test('non-overlapping bars in a row keep the full-height single lane', () => {
  const a = bar('A', 'GAL1', '2026-09-01', '2026-09-03')
  const b = bar('B', 'GAL2', '2026-09-05', '2026-09-06')
  markRoomPlanConflicts([a, b])
  assert.equal(a.conflictLaneCount, 1)
  assert.equal(b.conflictLaneCount, 1)
})

test('three-way overlap reuses a freed lane', () => {
  const a = bar('A', 'GAL1', '2026-09-01', '2026-09-05')
  const b = bar('B', 'GAL2', '2026-09-02', '2026-09-03')
  const c = bar('C', 'GAL3', '2026-09-03', '2026-09-04')
  markRoomPlanConflicts([a, b, c])
  assert.equal(a.conflictLaneCount, 2)
  assert.equal(b.conflictLane, c.conflictLane)
})
