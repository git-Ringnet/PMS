import assert from 'node:assert/strict'
import test from 'node:test'
import { bookingStayDays } from '../src/utils/booking-stay-days.js'

test('shortening a stay drops the departure night', () => {
  assert.equal(bookingStayDays('2026-09-09', '2026-09-12'), 3)
  assert.equal(bookingStayDays('2026-09-09', '2026-09-11'), 2)
})

test('stay days handle month/year boundaries and leap years', () => {
  assert.equal(bookingStayDays('2026-12-31', '2027-01-02'), 2)
  assert.equal(bookingStayDays('2028-02-28', '2028-03-01'), 2)
  assert.equal(bookingStayDays('2026-02-28', '2026-03-01'), 1)
})

test('day use preserves the current one-day convention; invalid periods remain invalid', () => {
  assert.equal(bookingStayDays('2026-09-09', '2026-09-09'), 1)
  assert.equal(bookingStayDays('2026-09-12', '2026-09-11'), null)
  assert.equal(bookingStayDays('', '2026-09-11'), null)
  assert.equal(bookingStayDays('2026-02-30', '2026-03-03'), null)
})
