import test from 'node:test'
import assert from 'node:assert/strict'

import { countActivityEntries, findMostActiveProduct } from '../src/utils/inventory-activity.js'

const items = [
  { product_id: 1, product_name: 'Coca Cola' },
  { product_id: 2, product_name: 'Mirinda' },
  { product_id: 3, product_name: 'Sting' },
]

test('row 439: each non-zero receive/export/transfer cell counts as one occurrence', () => {
  assert.equal(countActivityEntries({
    '2026-09-02': { receive: 25, export: 2, transfer: 20 },
    '2026-09-03': { receive: 0, export: 2, transfer: 0 },
  }), 4)
  assert.equal(countActivityEntries(undefined), 0)
})

test('row 439: most active product is chosen by occurrences, not by total quantity', () => {
  const logsMap = {
    // Coca: 1 lượt nhưng số lượng lớn (186)
    1: { '2026-09-02': { receive: 186, export: 0, transfer: 0 } },
    // Mirinda: 3 lượt, số lượng nhỏ
    2: { '2026-09-02': { export: 1 }, '2026-09-03': { export: 1 }, '2026-09-04': { transfer: 1 } },
  }
  assert.deepEqual(findMostActiveProduct(items, logsMap), { name: 'Mirinda', count: 3 })
})

test('row 439: no activity means no most active product', () => {
  assert.deepEqual(findMostActiveProduct(items, {}), { name: '', count: 0 })
})

test('row 439: values from the API as strings are still counted', () => {
  assert.equal(countActivityEntries({ '2026-09-02': { receive: '5', export: '0', transfer: null } }), 1)
})
