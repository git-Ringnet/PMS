import assert from 'node:assert/strict'
import test from 'node:test'

import { addLocalDays, localMonthRange } from '../src/utils/report-date-range.js'

test('report date presets preserve the local business date', () => {
  assert.equal(addLocalDays('2026-08-09', -1), '2026-08-08')
  assert.equal(addLocalDays('2026-08-09', 1), '2026-08-10')
})

test('report month preset returns the complete local month', () => {
  assert.deepEqual(localMonthRange('2026-08-09'), ['2026-08-01', '2026-08-31'])
  assert.deepEqual(localMonthRange('2028-02-09'), ['2028-02-01', '2028-02-29'])
})
