import test from 'node:test'
import assert from 'node:assert/strict'
import {
  calculateChargeableServiceTotals,
  isMoveHistoryBillReference,
  mergeMoveHistoryBillReferences,
  normalizeMoveHistoryBillReferences,
} from '../src/utils/booking-room-move-history.js'

test('move history refs map to bill display fields and remain marked read-only', () => {
  const [bill] = normalizeMoveHistoryBillReferences([{
    bill_id: 41,
    date: '2026-10-06',
    service_code: 'RM',
    description: 'Room charge',
    quantity: 1,
    amount: 500,
    source_room_id: 10,
    folio_room_id: 11,
    is_posted_reference: true,
  }])

  assert.equal(bill.Ma, 41)
  assert.equal(bill.ServiceId, 'RM')
  assert.equal(bill.Date, '2026-10-06')
  assert.equal(bill.RentalRoomId1, 10)
  assert.equal(bill.RentalRoomId2, 11)
  assert.equal(bill.from_move_history, true)
})

test('move history merge deduplicates by bill ID against current room references', () => {
  const merged = mergeMoveHistoryBillReferences(
    [{ Ma: 41, ServiceId: 'RM' }],
    [{ bill_id: 41, date: '2026-10-06' }, { bill_id: 42, date: '2026-10-06' }],
    '2026-10-06',
    '2026-10-07',
  )

  assert.deepEqual(merged.map(bill => bill.Ma), [41, 42])
})

test('move history refs are limited to the target stay interval and absent for zero-night stays', () => {
  const sourceHistory = [
    { bill_id: 51, date: '2026-10-05', service_code: 'RM' },
    { bill_id: 52, date: '2026-10-06', service_code: 'RM' },
  ]

  assert.deepEqual(
    mergeMoveHistoryBillReferences([], sourceHistory, '2026-10-06', '2026-10-08').map(bill => bill.Ma),
    [52],
  )
  assert.deepEqual(
    mergeMoveHistoryBillReferences([], sourceHistory, '2026-10-06', '2026-10-06'),
    [],
  )
})

test('move-history bill references are readonly even on the current system date', () => {
  const ref = { service_date: '2026-10-07', from_move_history: true }
  assert.equal(isMoveHistoryBillReference(ref), true)
  assert.equal(isMoveHistoryBillReference({ service_date: '2026-10-07' }), false)
})

test('move-history bill references remain visible but never affect monetary subtotals', () => {
  const totals = calculateChargeableServiceTotals([
    { service_code: 'RM', rate: 500, quantity: 1, from_move_history: true },
    { service_code: 'RM', rate: 100, quantity: 1 },
    { service_code: 'ER', rate: 40, quantity: 1, bill_ref: { from_move_history: true } },
    { service_code: 'EB', rate: 300, quantity: 1, from_move_history: true },
    { service_code: 'EB', rate: 50, quantity: 2 },
    { service_code: 'BF', rate: 75, quantity: 1, from_move_history: true },
    { service_code: 'BF', rate: 25, quantity: 2 },
  ])

  assert.deepEqual(totals, { roomCharge: 100, extraBed: 100, services: 50, total: 250 })
})