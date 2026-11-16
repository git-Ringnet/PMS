import test from 'node:test'
import assert from 'node:assert/strict'
import { formatMonthDisplay, parseMonthInput } from '../src/utils/month-input.js'

test('hiển thị YYYY-MM thành mm/yyyy', () => {
  assert.equal(formatMonthDisplay('2026-11'), '11/2026')
  assert.equal(formatMonthDisplay(''), '')
  assert.equal(formatMonthDisplay(null), '')
})

test('gõ tay mm/yyyy được chấp nhận', () => {
  assert.equal(parseMonthInput('11/2026'), '2026-11')
  assert.equal(parseMonthInput('1/2026'), '2026-01')
  assert.equal(parseMonthInput('01-2026'), '2026-01')
  assert.equal(parseMonthInput('112026'), '2026-11')
})

test('gõ sai định dạng bị từ chối', () => {
  assert.equal(parseMonthInput('13/2026'), null)
  assert.equal(parseMonthInput('00/2026'), null)
  assert.equal(parseMonthInput('abc'), null)
  assert.equal(parseMonthInput('11/26'), null)
  assert.equal(parseMonthInput(''), null)
})
