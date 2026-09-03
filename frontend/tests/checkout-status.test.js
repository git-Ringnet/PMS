import test from 'node:test'
import assert from 'node:assert/strict'

import { isCheckedOutRecord } from '../src/utils/checkout-status.js'

test('inhouse room stays current when CheckoutDate is only the planned departure date', () => {
  assert.equal(isCheckedOutRecord({ status: 1, CheckoutDate: '2026-08-11' }), false)
})

test('checked-out room is detected from explicit status without a legacy date', () => {
  assert.equal(isCheckedOutRecord({ status: 2 }), true)
  assert.equal(isCheckedOutRecord({ status: 'checked_out' }), true)
})

test('booked room is not checked out when it has a planned legacy CheckoutDate', () => {
  assert.equal(isCheckedOutRecord({ status: 0, checkout_date: '2026-08-12' }), false)
})

test('legacy records without status still fall back to CheckoutDate', () => {
  assert.equal(isCheckedOutRecord({ CheckoutDate: '2026-08-12' }), true)
  assert.equal(isCheckedOutRecord({}), false)
})
