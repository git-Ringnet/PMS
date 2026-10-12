import test from 'node:test'
import assert from 'node:assert/strict'
import {
  clearAvailableRoomsDateRange,
  getAvailableRoomsDateRange,
  setAvailableRoomsDateRange
} from '../src/pages/reservation/available-rooms-view-state.js'

test.afterEach(() => {
  clearAvailableRoomsDateRange()
})

test('returns null before a date range is saved', () => {
  assert.equal(getAvailableRoomsDateRange(), null)
})

test('keeps a valid date range while the SPA is running', () => {
  assert.equal(setAvailableRoomsDateRange('2026-10-08', '2026-11-07'), true)
  assert.deepEqual(getAvailableRoomsDateRange(), {
    startDate: '2026-10-08',
    endDate: '2026-11-07'
  })
})

test('rejects invalid dates without replacing the saved range', () => {
  setAvailableRoomsDateRange('2026-10-08', '2026-11-07')

  assert.equal(setAvailableRoomsDateRange('2026-02-30', '2026-11-07'), false)
  assert.equal(setAvailableRoomsDateRange('2026-11-08', '2026-11-07'), false)
  assert.deepEqual(getAvailableRoomsDateRange(), {
    startDate: '2026-10-08',
    endDate: '2026-11-07'
  })
})

test('clears the saved date range', () => {
  setAvailableRoomsDateRange('2026-10-08', '2026-11-07')
  clearAvailableRoomsDateRange()

  assert.equal(getAvailableRoomsDateRange(), null)
})
