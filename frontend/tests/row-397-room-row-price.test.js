import test from 'node:test'
import assert from 'node:assert/strict'
import { keepSavedDailyRoomPrices } from '../src/utils/booking-room-services.js'

test('row 397: row price edit drops unsaved nightly prices so no nightly rows are inserted', () => {
  // Rate-code prices filled in on the client for 3 nights, none saved yet.
  const daily = { '2026-09-01': 500000, '2026-09-02': 500000, '2026-09-03': 500000 }
  assert.equal(keepSavedDailyRoomPrices(daily, []), null)
})

test('row 397: nights with a saved room-charge row keep their own price', () => {
  const daily = { '2026-09-01': 450000, '2026-09-02': 500000 }
  const services = [
    { id: 11, service_code: 'RM', service_date: '2026-09-01T00:00:00.000000Z', rate: 450000 },
    { id: 12, service_code: 'SPA', service_date: '2026-09-02', rate: 99000 },
  ]
  assert.deepEqual(keepSavedDailyRoomPrices(daily, services), { '2026-09-01': 450000 })
})

test('row 397: an unsaved service row (no id) does not count as saved', () => {
  const daily = { '2026-09-01': 450000 }
  const services = [{ service_code: 'RM', service_date: '2026-09-01', rate: 450000 }]
  assert.equal(keepSavedDailyRoomPrices(daily, services), null)
})

test('row 397: empty input stays empty', () => {
  assert.equal(keepSavedDailyRoomPrices(null, []), null)
})
