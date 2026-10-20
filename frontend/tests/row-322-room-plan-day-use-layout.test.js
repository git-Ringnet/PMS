import test from 'node:test'
import assert from 'node:assert/strict'

import { resolveRoomPlanDayUseLayout } from '../src/utils/room-plan-day-use-layout.js'
import { markRoomPlanConflicts, roomPlanStayRange } from '../src/utils/room-plan-conflicts.js'

const closeTo = (actual, expected) => {
  assert.ok(Math.abs(actual - expected) < 0.000001, `Expected ${actual} to equal ${expected}`)
}

test('Dòng 322: Xem đêm hiển thị Day Use ở nửa sau của đúng ô ngày', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    showNights: true,
    arrivalTime: '08:00',
    departureTime: '12:00',
  })

  assert.deepEqual(layout, { leftRatio: 0.5, widthRatio: 0.5, isFallback: false })
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

test('Dòng 322: khoảng giờ gần trọn ngày không vượt khỏi ô', () => {
  const layout = resolveRoomPlanDayUseLayout({
    isDayUse: true,
    arrivalTime: '00:00',
    departureTime: '23:59',
  })

  assert.equal(layout.leftRatio, 0)
  assert.ok(layout.widthRatio < 1)
  assert.ok(layout.leftRatio + layout.widthRatio <= 1)
})

test('Dòng 322: giờ thiếu hoặc giờ đi không sau giờ đến dùng fallback nửa ô', () => {
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

test('Dòng 322: nhiều lượt Day Use cùng phòng vẫn được chia thành nhiều line', () => {
  const first = {
    bookingRoomId: 1,
    code: 'GAL322-1',
    _range: roomPlanStayRange({ start: '2026-09-30', end: '2026-09-30', isDayUse: true }),
  }
  const second = {
    bookingRoomId: 2,
    code: 'GAL322-2',
    _range: roomPlanStayRange({ start: '2026-09-30', end: '2026-09-30', isDayUse: true }),
  }

  markRoomPlanConflicts([first, second])

  assert.equal(first.conflictLaneCount, 2)
  assert.equal(second.conflictLaneCount, 2)
  assert.notEqual(first.conflictLane, second.conflictLane)
})

