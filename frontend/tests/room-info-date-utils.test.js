import test from 'node:test'
import assert from 'node:assert/strict'
import {
  formatShortDate,
  maskDateInput,
  parseDmyInput,
} from '../src/pages/reservation/components/room-info-date-utils.js'

test('adds separators while entering an eight-digit date', () => {
  assert.equal(maskDateInput('18091996'), '18/09/1996')
})

test('converts an eight-digit date to the API format', () => {
  assert.equal(parseDmyInput('18/09/1996'), '1996-09-18')
})

test('accepts the six-digit ddmmyy format', () => {
  assert.equal(parseDmyInput('180926'), '2026-09-18')
})

test('rejects an impossible calendar date', () => {
  assert.equal(parseDmyInput('31022026'), null)
})

test('shows saved dates in dd/mm/yy format', () => {
  assert.equal(formatShortDate('1996-09-18'), '18/09/96')
})
