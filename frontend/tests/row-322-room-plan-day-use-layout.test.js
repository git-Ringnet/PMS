import test from 'node:test'
import assert from 'node:assert/strict'

import { resolveRoomPlanDayUseLayout } from '../src/utils/room-plan-day-use-layout.js'
import { markRoomPlanConflicts, roomPlanStayRange } from '../src/utils/room-plan-conflicts.js'

const closeTo = (actual, expected) => {
  assert.ok(Math.abs(actual - expected) < 0.000001, `Expected ${actual} to equal ${expected}`)
}

test('Dòng 322: Xem đêm dịch Day Use như booking thường nhưng giữ độ rộng theo giờ', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    showNights: true,
    arrivalTime: '08:00',
    departureTime: '12:00',
  })

  closeTo(layout.leftRatio, 0)
  closeTo(layout.widthRatio, 4 / 24)
  assert.equal(layout.isFallback, false)
})

test('Dòng 322: Xem ngày giữ vị trí giờ thực tế của Day Use', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    showNights: false,
    arrivalTime: '08:00',
    departureTime: '12:00',
  })

  closeTo(layout.leftRatio, 8 / 24)
  closeTo(layout.widthRatio, 4 / 24)
})

test('Dòng 322: 01:00-02:00 nằm đúng khoảng một giờ trong ô', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    showNights: false,
    arrivalTime: '01:00',
    departureTime: '02:00',
  })

  closeTo(layout.leftRatio, 1 / 24)
  closeTo(layout.widthRatio, 1 / 24)
  closeTo(layout.leftRatio + layout.widthRatio, 2 / 24)
})

test('Dòng 322: Xem đêm dịch Day Use sau 12 giờ sang trái nửa ô', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    showNights: true,
    arrivalTime: '14:00',
    departureTime: '20:00',
  })

  closeTo(layout.leftRatio, 2 / 24)
  closeTo(layout.widthRatio, 6 / 24)
})

test('Dòng 322: Xem ngày 08:00-12:00 chiếm đúng 4 giờ trong ô ngày', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    arrivalTime: '08:00',
    departureTime: '12:00',
  })

  closeTo(layout.leftRatio, 8 / 24)
  closeTo(layout.widthRatio, 4 / 24)
  assert.equal(layout.isFallback, false)
})

test('Dòng 322: Xem ngày 14:00-20:00 chiếm đúng 6 giờ trong ô ngày', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    arrivalTime: '14:00:00',
    departureTime: '20:00:00',
  })

  closeTo(layout.leftRatio, 14 / 24)
  closeTo(layout.widthRatio, 6 / 24)
})

test('Dòng 322: 23:00-24:00 nằm đúng giờ cuối cùng của ô', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    arrivalTime: '23:00',
    departureTime: '24:00',
  })

  closeTo(layout.leftRatio, 23 / 24)
  closeTo(layout.widthRatio, 1 / 24)
  closeTo(layout.leftRatio + layout.widthRatio, 1)
})

test('Dòng 322: giờ thiếu hoặc giờ đi không sau giờ đến dùng fallback nửa ô', () => {
  assert.deepEqual(
    resolveRoomPlanDayUseLayout({ isDayUse: true, showNights: true, arrivalTime: '14:00', departureTime: '12:00' }),
    { leftRatio: 0, widthRatio: 0.5, isFallback: true },
  )
  assert.deepEqual(
    resolveRoomPlanDayUseLayout({ isDayUse: true, arrivalTime: '14:00', departureTime: '12:00' }),
    { leftRatio: 0.5, widthRatio: 0.5, isFallback: true },
  )
  assert.deepEqual(
    resolveRoomPlanDayUseLayout({ isDayUse: true, arrivalTime: '', departureTime: '' }),
    { leftRatio: 0.5, widthRatio: 0.5, isFallback: true },
  )
})

test('Dòng 322: booking không bật Day Use không nhận layout mới', () => {
  assert.equal(resolveRoomPlanDayUseLayout({
    isDayUse: false,
    arrivalTime: '08:00',
    departureTime: '12:00',
  }), null)
})

test('Dòng 322: nhiều lượt Day Use khác giờ cùng phòng không bị coi là trùng', () => {
  const first = {
    bookingRoomId: 1,
    code: 'GAL322-1',
    isDayUse: true,
    arrival_time: '08:00',
    departure_time: '12:00',
    _range: roomPlanStayRange({ start: '2026-09-30', end: '2026-09-30', isDayUse: true }),
  }
  const second = {
    bookingRoomId: 2,
    code: 'GAL322-2',
    isDayUse: true,
    arrival_time: '14:00',
    departure_time: '18:00',
    _range: roomPlanStayRange({ start: '2026-09-30', end: '2026-09-30', isDayUse: true }),
  }

  markRoomPlanConflicts([first, second])

  assert.equal(first.hasConflict, false)
  assert.equal(second.hasConflict, false)
  assert.equal(first.conflictLaneCount, 1)
  assert.equal(second.conflictLaneCount, 1)
})

