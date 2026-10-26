import test from 'node:test'
import assert from 'node:assert/strict'

import { getBookingRoomGroups, getSubtableTotals } from '../src/utils/general-search-groups.js'
import { createLatestRequestGuard } from '../src/utils/latest-request.js'

const room = overrides => ({
  room_class: { code: 'SUPTR' }, arrival_date: '2026-09-04', departure_date: '2026-09-05',
  rate_code: null, rate: 600000, adults: 3, children: 0, babies: 0, room_total: 600000, ...overrides,
})

test('row 409: subtable counts babies separately from children', () => {
  const groups = getBookingRoomGroups([room({ babies: 1 }), room({ children: 2 })])
  assert.equal(groups.length, 1)
  assert.equal(groups[0].children, 2)
  assert.equal(groups[0].babies, 1)
  assert.deepEqual(getSubtableTotals(groups), { rooms: 2, adults: 6, babies: 1, children: 2, total: 1200000 })
})

test('row 409: subtable money uses the Booking-screen room_total', () => {
  const groups = getBookingRoomGroups([room({ room_total: 600000, service_total: 9999999 })])
  assert.equal(groups[0].service_total, 600000)
})

test('row 409: GAL46 - only the one active room is grouped (API already hides the cancelled room)', () => {
  const totals = getSubtableTotals(getBookingRoomGroups([room({ babies: 1 })]))
  assert.deepEqual(totals, { rooms: 1, adults: 3, babies: 1, children: 0, total: 600000 })
})

test('row 411: a slower older search response cannot overwrite the latest one', async () => {
  const guard = createLatestRequestGuard()
  let shown = null
  const search = (term, delay, rows) => {
    const id = guard.next()
    return new Promise(resolve => setTimeout(resolve, delay)).then(() => {
      if (guard.isLatest(id)) shown = { term, rows }
    })
  }

  // Mở modal (tìm "" - chậm, trả nhiều booking) rồi gõ "20" (nhanh, chỉ GAL20).
  await Promise.all([
    search('', 30, ['GAL51', 'GAL50', 'GAL49']),
    search('20', 5, ['GAL20']),
  ])
  assert.deepEqual(shown, { term: '20', rows: ['GAL20'] })
})

test('row 411: unselecting every status invalidates searches still in flight', () => {
  const guard = createLatestRequestGuard()
  const inFlight = guard.next()
  guard.next() // bỏ chọn hết tình trạng -> kết quả rỗng
  assert.equal(guard.isLatest(inFlight), false)
})
