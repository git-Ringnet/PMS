import test from 'node:test'
import assert from 'node:assert/strict'

import { roomPeriodsOverlap } from '../src/utils/day-use-time-overlap.js'

const dayUse = (arrivalTime, departureTime) => ({
  arrivalDate: '2026-08-30',
  departureDate: '2026-08-30',
  isDayUse: true,
  arrivalTime,
  departureTime,
})

test('Day Use cùng phòng khác giờ không bị coi là trùng', () => {
  assert.equal(roomPeriodsOverlap(dayUse('08:00', '12:00'), dayUse('13:00', '16:00')), false)
})

test('Day Use giao giờ vẫn bị coi là trùng', () => {
  assert.equal(roomPeriodsOverlap(dayUse('08:00', '12:00'), dayUse('11:00', '13:00')), true)
})

test('Day Use chạm giờ trả/nhận không bị coi là trùng', () => {
  assert.equal(roomPeriodsOverlap(dayUse('08:00', '12:00'), dayUse('12:00', '16:00')), false)
})
