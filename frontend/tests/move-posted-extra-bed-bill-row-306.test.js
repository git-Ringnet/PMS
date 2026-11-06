import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { findMoveHistoryExtraBedBill, findExtraBedBillsOutsideStay, extraBedBillRow } from '../src/utils/booking-room-extra-bed-bills.js'

// Dòng 306 (note 27/10): phòng có EB, post trước tiền phòng + EB của đêm 16 rồi chuyển phòng ngay ngày 16.
// Chi tiết dịch vụ từng đêm của phòng cũ (0 đêm sau chuyển) và phòng mới đều phải hiện bill EB của đêm đã post.

const dateOf = bill => String(bill.Date).slice(0, 10)
const bill = (over = {}) => ({ Ma: 1, ServiceId: 'EB', Date: '2026-08-16', Quantity: 1, Amount: 300000, Edit: 0, Status: 1, RentalRoomId1: 'OLD', RentalRoomId2: null, ...over })

test('row 306: a posted EB bill referenced from the previous room is found for the night it was posted', () => {
  const history = bill({ Ma: 7, from_move_history: true })
  assert.equal(findMoveHistoryExtraBedBill([bill({ Ma: 9, Date: '2026-08-17', from_move_history: true }), history], '2026-08-16', dateOf, new Set()), history)
})

test('row 306: only move-history references count for the new room (own plan rows keep their existing display)', () => {
  assert.equal(findMoveHistoryExtraBedBill([bill({ Ma: 7 })], '2026-08-16', dateOf, new Set()), undefined)
})

test('row 306: reversed, adjusted, already handled or non-EB bills are ignored', () => {
  const cases = [
    bill({ Ma: 1, from_move_history: true, Edit: 1 }),
    bill({ Ma: 2, from_move_history: true, Status: 3 }),
    bill({ Ma: 3, from_move_history: true, Status: 4 }),
    bill({ Ma: 4, from_move_history: true, ServiceId: 'RM' }),
  ]
  assert.equal(findMoveHistoryExtraBedBill(cases, '2026-08-16', dateOf, new Set()), undefined)
  assert.equal(findMoveHistoryExtraBedBill([bill({ Ma: 5, from_move_history: true })], '2026-08-16', dateOf, new Set(['5'])), undefined)
})

test('row 306: the old room with zero nights lists the EB bill posted on a night outside its stay', () => {
  const posted = bill({ Ma: 11 })
  assert.deepEqual(findExtraBedBillsOutsideStay([posted], new Set(), dateOf, new Set()), [posted])
})

test('row 306: EB bills inside the stay nights or already handled are not duplicated by the sweep', () => {
  const inside = bill({ Ma: 12, Date: '2026-08-17' })
  const handled = bill({ Ma: 13, Date: '2026-08-20' })
  assert.deepEqual(findExtraBedBillsOutsideStay([inside, handled], new Set(['2026-08-17']), dateOf, new Set(['13'])), [])
})

test('row 306: a posted EB bill row keeps its bill reference and shows GIT when posted to the booking', () => {
  const atMaster = extraBedBillRow(bill({ Ma: 21, Quantity: 2, Amount: 600000 }), '2026-08-16')
  assert.equal(atMaster.service_code, 'EB')
  assert.equal(atMaster.quantity, 2)
  assert.equal(atMaster.rate, 300000)
  assert.equal(atMaster.is_room, false)
  assert.equal(atMaster.from_bill, true)
  assert.equal(atMaster.bill_ref.Ma, 21)

  const atRoom = extraBedBillRow(bill({ Ma: 22, RentalRoomId2: 'OLD' }), '2026-08-16')
  assert.equal(atRoom.is_room, true)
})

test('row 306: history EB rows are flagged so they are not counted twice in the totals', () => {
  const row = extraBedBillRow(bill({ Ma: 31, from_move_history: true }), '2026-08-16')
  assert.equal(row.from_move_history, true)
})

test('row 306: the booking page shows history EB bills and sweeps EB bills outside the stay', () => {
  const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
  assert.match(source, /findMoveHistoryExtraBedBill\(/)
  assert.match(source, /findExtraBedBillsOutsideStay\(/)
  assert.match(source, /extraBedBillRow\(/)
  // Đêm quá khứ lấy bill EB của phòng trước cũng phải được đánh dấu lịch sử (không cộng trùng tổng).
  assert.match(source, /from_move_history:\s*!!ebBill\.from_move_history/)
})
