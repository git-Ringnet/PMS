import test from 'node:test'
import assert from 'node:assert/strict'

import { normalizeRateMatrix } from '../src/utils/rate-matrix.js'

const roomTypes = [{ code: 'SUPD' }, { code: 'JST' }]
const occupancies = ['Double', 'King']

test('keeps canonical plan matrix keys unchanged', () => {
  const result = normalizeRateMatrix(
    { TEST2_mini_SUPD_Double: '300000' },
    'TEST2_mini',
    roomTypes,
    occupancies
  )

  assert.deepEqual(result, { TEST2_mini_SUPD_Double: '300000' })
})

test('removes duplicated plan fragments from polluted keys', () => {
  const result = normalizeRateMatrix(
    { TEST2_mini_mini_SUPD_Double: '300000' },
    'TEST2_mini',
    roomTypes,
    occupancies
  )

  assert.deepEqual(result, { TEST2_mini_SUPD_Double: '300000' })
})

test('normalizes legacy rate-code-prefixed keys to the selected plan', () => {
  const result = normalizeRateMatrix(
    { TEST2_SUPD_Double: '300000' },
    'TEST2_mini',
    roomTypes,
    occupancies
  )

  assert.deepEqual(result, { TEST2_mini_SUPD_Double: '300000' })
})

test('prefers an existing canonical value over a polluted duplicate', () => {
  const result = normalizeRateMatrix(
    {
      TEST2_mini_SUPD_Double: '600000',
      TEST2_mini_mini_SUPD_Double: '300000',
    },
    'TEST2_mini',
    roomTypes,
    occupancies
  )

  assert.deepEqual(result, { TEST2_mini_SUPD_Double: '600000' })
})

test('preserves unknown legacy keys without inferring a mapping', () => {
  const result = normalizeRateMatrix(
    { 9: '1200000', UNKNOWN_KEY: '500000' },
    'TEST2_mini',
    roomTypes,
    occupancies
  )

  assert.deepEqual(result, { 9: '1200000', UNKNOWN_KEY: '500000' })
})
