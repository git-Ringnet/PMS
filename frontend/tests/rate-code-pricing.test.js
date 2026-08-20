import test from 'node:test'
import assert from 'node:assert/strict'

import {
  buildRateCodeDailyPrices,
  normalizeRateDate,
  resolveRateCodePrice,
} from '../src/utils/rate-code-pricing.js'

test('resolves a configured price by room class id', () => {
  const price = resolveRateCodePrice([{
    Ma: 'BAR',
    rate_plans: [{ Code: 'DEFAULT', Period: { 9: 1250000 } }],
  }], { rateCode: 'BAR', roomClassId: 9, roomClassCode: 'JST', date: '2026-08-20' })

  assert.equal(price, 1250000)
})

test('keeps a configured zero price for FOC rate codes', () => {
  const price = resolveRateCodePrice([{
    Ma: 'FOC',
    Value: 500000,
    rate_plans: [{ Code: 'DEFAULT', Period: { 9: 0 } }],
  }], { rateCode: 'FOC', roomClassId: 9, roomClassCode: 'JST', date: '2026-08-20' })

  assert.equal(price, 0)
})

test('uses the daily mapping plan for the selected arrival date', () => {
  const price = resolveRateCodePrice([{
    Ma: 'DAILY',
    rate_plans: [
      { Code: 'DEFAULT', Period: { 9: 900000 } },
      { Code: 'WEEKEND', Period: { WEEKEND_JST_Double: 1500000 } },
    ],
    daily_mappings: [{ Date: '2026-08-22T00:00:00.000000Z', Code: 'WEEKEND' }],
  }], { rateCode: 'DAILY', roomClassId: null, roomClassCode: 'JST', date: '2026-08-22' })

  assert.equal(price, 1500000)
})

test('supports legacy keys prefixed by the rate code', () => {
  const price = resolveRateCodePrice([{
    Ma: 'OLD',
    rate_plans: [{ Code: 'DEFAULT', Period: JSON.stringify({ OLD_SUP_Double: 1100000 }) }],
  }], { rateCode: 'OLD', roomClassCode: 'SUP', date: '2026-08-20' })

  assert.equal(price, 1100000)
})

test('returns null when neither a class price nor fallback exists', () => {
  const price = resolveRateCodePrice([{
    Ma: 'BAR',
    rate_plans: [{ Code: 'DEFAULT', Period: { DEFAULT_SUP_Double: 1000000 } }],
  }], { rateCode: 'BAR', roomClassCode: 'JST', date: '2026-08-20' })

  assert.equal(price, null)
})

test('normalizes UTC rate mappings to the hotel business date', () => {
  assert.equal(normalizeRateDate('2026-08-09T17:00:00.000000Z'), '2026-08-10')
})

test('builds a separate configured price for every stay night', () => {
  const prices = buildRateCodeDailyPrices([{
    Ma: 'TEST1',
    rate_plans: [
      { Code: 'TEST1', Period: { TEST1_SUPD_Double: 1 } },
      { Code: 'TEST2', Period: { TEST2_SUPD_Double: 11 } },
    ],
    daily_mappings: [
      { Date: '2026-08-08T17:00:00.000000Z', Code: 'TEST1' },
      { Date: '2026-08-09T17:00:00.000000Z', Code: 'TEST2' },
      { Date: '2026-08-10T17:00:00.000000Z', Code: 'TEST2' },
      { Date: '2026-08-11T17:00:00.000000Z', Code: 'TEST1' },
      { Date: '2026-08-12T17:00:00.000000Z', Code: 'TEST1' },
    ],
  }], {
    rateCode: 'TEST1',
    roomClassCode: 'SUPD',
    arrivalDate: '2026-08-09',
    departureDate: '2026-08-14',
  })

  assert.deepEqual(prices, {
    '2026-08-09': 1,
    '2026-08-10': 11,
    '2026-08-11': 11,
    '2026-08-12': 1,
    '2026-08-13': 1,
  })
})
