import test from 'node:test'
import assert from 'node:assert/strict'
import { excludeBillsBeforeMoveIn } from '../src/utils/booking-bill-origin.js'
import { findMovedRoomChildBreakfastBills } from '../src/utils/booking-room-move-history.js'

const roomIds = new Set(['607', '707'])
const dateOf = bill => bill.Date
const bill = (Ma, Date, RentalRoomId1, RentalRoomId2, ServiceId = 'BD') => ({ Ma, Date, RentalRoomId1, RentalRoomId2, ServiceId, Edit: 0, Status: 1 })
const isBreakfast = item => item.ServiceId === 'BD'

test('row 307: bill đêm trước ngày chuyển không hiện ở phòng mới', () => {
  const bills = [bill(1, '2026-08-31', '607', '707'), bill(2, '2026-09-01', '607', '707'), bill(3, '2026-09-02', '707', '707')]
  assert.deepEqual(excludeBillsBeforeMoveIn(bills, '707', '2026-09-02', roomIds, new Set(), dateOf).map(item => item.Ma), [3])
})

test('row 307: bill đúng ngày chuyển và bill do thao tác Chuyển bill vẫn giữ nguyên', () => {
  const bills = [bill(1, '2026-09-02', '607', '707'), bill(2, '2026-09-01', '607', '707')]
  assert.deepEqual(excludeBillsBeforeMoveIn(bills, '707', '2026-09-02', roomIds, new Set(['2']), dateOf).map(item => item.Ma), [1, 2])
})

test('row 307: bill có phòng gốc ở booking khác không bị loại', () => {
  const bills = [bill(1, '2026-09-01', 'OTHER', '707')]
  assert.deepEqual(excludeBillsBeforeMoveIn(bills, '707', '2026-09-02', roomIds, new Set(), dateOf).map(item => item.Ma), [1])
})

test('row 307: phòng cũ đã chuyển hiện bill ăn sáng trẻ dù trẻ đã sang phòng mới', () => {
  const bills = [bill(1, '2026-08-31', '607', '707'), bill(2, '2026-09-01', '607', '707'), bill(3, '2026-09-01', '607', '707', 'EB')]
  assert.deepEqual(findMovedRoomChildBreakfastBills(bills, isBreakfast, new Set()).map(item => item.Ma), [1, 2])
})

test('row 307: không lặp bill đã hiện và bỏ bill đã hủy', () => {
  const bills = [bill(1, '2026-08-31', '607', '707'), { ...bill(2, '2026-09-01', '607', '707'), Status: 3 }, { ...bill(3, '2026-09-01', '607', '707'), Edit: 1 }]
  assert.deepEqual(findMovedRoomChildBreakfastBills(bills, isBreakfast, new Set(['1'])), [])
})
