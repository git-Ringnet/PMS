import test from 'node:test'
import assert from 'node:assert/strict'

import { resolveRoomPlanNights } from '../src/utils/room-plan-nights.js'

test('Day Use cùng ngày hiển thị 0 đêm trong Kế hoạch phòng', () => {
  assert.equal(resolveRoomPlanNights({
    arrivalDate: '2026-08-30',
    departureDate: '2026-08-30',
    isDayUse: true,
  }), 0)
})

test('booking không phải Day Use cùng ngày giữ hành vi cũ là 1 đêm', () => {
  assert.equal(resolveRoomPlanNights({
    arrivalDate: '2026-08-30',
    departureDate: '2026-08-30',
    isDayUse: false,
  }), 1)
})

test('booking qua đêm giữ số đêm thực tế', () => {
  assert.equal(resolveRoomPlanNights({
    arrivalDate: '2026-08-30',
    departureDate: '2026-09-02',
    isDayUse: true,
  }), 3)
})
