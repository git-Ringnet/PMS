import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  validateHousekeepingInvoiceBlocks,
  validateHousekeepingInvoiceParameters,
} from './housekeepingInvoiceTemplateContract.js'

const columns = [
  'row.STT', 'row.BookingId', 'row.Room', 'row.Guest', 'row.DescriptionServive',
  'row.Product', 'row.TotalAmount', 'row.DiscountAmount', 'row.NetAmount',
  'row.PaymentID', 'row.BillNote', 'row.Username', 'row.Ca',
].map((value) => ({ value, format: ['row.TotalAmount', 'row.DiscountAmount', 'row.NetAmount'].includes(value) ? 'number' : '' }))

const validBlocks = () => ({
  detail: [
    {
      type: 'table', dataSource: 'rows', tableType: 'dynamic', columns,
      groups: [{ field: 'DateGroup' }],
      customRows: [{ className: 'invoice-total-row', cells: [
        { binding: 'totals.TotalAmount' }, { binding: 'totals.DiscountAmount' }, { binding: 'totals.NetAmount' },
      ] }],
    },
    {
      type: 'table', dataSource: 'product_summary', tableType: 'dynamic',
      columns: [{ value: 'item.Product' }, { value: 'item.Quantity' }, { value: 'item.TotalAmount' }],
    },
  ],
})

test('accepts the current HK invoice block contract', () => {
  assert.deepEqual(validateHousekeepingInvoiceBlocks(validBlocks()), [])
})

test('rejects missing numeric format and grouping bindings', () => {
  const blocks = validBlocks()
  blocks.detail[0].columns[6].format = ''
  blocks.detail[0].groups = []
  blocks.detail[0].customRows[0].cells.pop()
  const errors = validateHousekeepingInvoiceBlocks(blocks)
  assert.equal(errors.some((entry) => entry.includes('DateGroup')), true)
  assert.equal(errors.some((entry) => entry.includes('row.TotalAmount') && entry.includes('format')), true)
  assert.equal(errors.some((entry) => entry.includes('totals.NetAmount')), true)
})

test('requires all legacy parameters and validates enum values', () => {
  const errors = validateHousekeepingInvoiceParameters({ p_view_type: 'unknown', p_order_type: 'sideways' })
  assert.equal(errors.length, 9)
  assert.equal(errors.some((entry) => entry.includes('p_view_type')), true)
  assert.equal(errors.some((entry) => entry.includes('p_order_type')), true)
})
