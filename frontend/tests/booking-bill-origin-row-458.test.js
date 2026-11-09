import test from 'node:test'
import assert from 'node:assert/strict'
import {
  collectRoomOwnedBills,
  distributeServicesByBillOrigin,
  isBillOwnedByRoom,
  mergeRoomServices,
  transferredBillIds,
} from '../src/utils/booking-bill-origin.js'

const roomIds = new Set(['R1', 'R2', 'R3'])
const bill = (Ma, id1, id2) => ({ Ma, RentalRoomId1: id1, RentalRoomId2: id2 })
// Bill do "Chuyển bill" tạo ra luôn có bill âm đi kèm (Pack1 = mã bill dương).
const negativeOf = Ma => ({ Ma: Ma + 1000, Amount: -1, Pack1: String(Ma), Edit: 1, Status: 4, RentalRoomId1: 'x', RentalRoomId2: null })
const transferred = (...ids) => transferredBillIds(ids.map(negativeOf))

test('row 458: bill chuyển sang phòng khác vẫn chỉ thuộc phòng gốc', () => {
  const moved = bill(10, 'R1', 'R3')
  assert.equal(isBillOwnedByRoom(moved, 'R1', roomIds, transferred(10)), true)
  assert.equal(isBillOwnedByRoom(moved, 'R3', roomIds, transferred(10)), false)
})

test('row 458: chuyển nhiều lần (R1 -> R2 -> R3) không làm bill hiện thêm ở phòng nhận', () => {
  const bills = [bill(1, 'R1', 'R3'), bill(2, 'R2', 'R3'), bill(3, 'R3', null)]
  assert.deepEqual(collectRoomOwnedBills(bills, 'R3', roomIds, transferred(1, 2)).map(b => b.Ma), [3])
  assert.deepEqual(collectRoomOwnedBills(bills, 'R1', roomIds, transferred(1, 2)).map(b => b.Ma), [1])
  assert.deepEqual(collectRoomOwnedBills(bills, 'R2', roomIds, transferred(1, 2)).map(b => b.Ma), [2])
})

test('row 458: bill ở master (RentalRoomId2 rỗng) vẫn ở phòng gốc, không trùng lặp', () => {
  const bills = [bill(1, 'R1', null), bill(1, 'R1', null)]
  assert.equal(collectRoomOwnedBills(bills, 'R1', roomIds, transferred(1, 2)).length, 1)
})

test('row 458: bill chuyển từ booking khác sang (phòng gốc ngoài booking) hiện ở phòng nhận', () => {
  const inbound = bill(5, 'OTHER', 'R2')
  assert.equal(isBillOwnedByRoom(inbound, 'R2', roomIds, transferred(5)), true)
})

test('row 458: bill cũ không có phòng gốc thì theo phòng đang giữ', () => {
  assert.equal(isBillOwnedByRoom(bill(6, null, 'R2'), 'R2', roomIds), true)
})

test('row 458: dòng dịch vụ bị copy sang phòng nhận được trả về phòng gốc', () => {
  const rooms = [
    { id: 'R1', services: [] },
    { id: 'R2', services: [{ id: 1, service_bill_id: 10, service_code: 'EP', service_date: '2026-09-10' }] },
  ]
  const result = distributeServicesByBillOrigin(rooms, [bill(10, 'R1', 'R2'), negativeOf(10)])
  assert.equal(result.get('R2').services.length, 0)
  assert.equal(result.get('R1').services.length, 1)
  assert.deepEqual([...result.get('R2').foreignBillIds], ['10'])
})

test('row 458: phòng gốc đã có dòng cùng mã + ngày thì không cộng thêm', () => {
  const dup = { service_code: 'EB', service_date: '2026-09-10' }
  const rooms = [
    { id: 'R1', services: [{ id: 1, service_bill_id: 9, ...dup }] },
    { id: 'R2', services: [{ id: 2, service_bill_id: 10, ...dup }] },
  ]
  const result = distributeServicesByBillOrigin(rooms, [bill(9, 'R1', null), bill(10, 'R1', 'R2'), negativeOf(10)])
  assert.equal(result.get('R1').services.length, 1)
  assert.equal(result.get('R2').services.length, 0)
})

test('row 458: dòng dịch vụ không gắn bill hoặc bill của chính phòng thì giữ nguyên', () => {
  const rooms = [{ id: 'R1', services: [{ id: 1, service_bill_id: null, service_code: 'RM' }, { id: 2, service_bill_id: 3, service_code: 'EB' }] }]
  assert.equal(distributeServicesByBillOrigin(rooms, [bill(3, 'R1', 'R1')]).get('R1').services.length, 2)
})

test('row 458: bill đổi phòng (không có bill âm đi kèm) giữ cách hiển thị cũ ở phòng đang ở', () => {
  const byRoomMove = bill(20, 'R1', 'R2')
  assert.equal(isBillOwnedByRoom(byRoomMove, 'R2', roomIds, new Set()), true)
  assert.equal(isBillOwnedByRoom(byRoomMove, 'R1', roomIds, new Set()), true)
})

test('row 458: dịch vụ đã chuyển về master (xóa mềm) vẫn hiện ở phòng gốc', () => {
  const rooms = [{ id: 'R1', services: [], transferred_out_services: [{ id: 9, service_bill_id: 4, service_code: 'EP', service_date: '2026-09-10' }] }]
  assert.equal(distributeServicesByBillOrigin(rooms, []).get('R1').services.length, 1)
})

test('row 458: dòng đã chuyển đi không hồi sinh khi phòng gốc đã có dòng cùng mã + ngày', () => {
  const same = { service_code: 'EP', service_date: '2026-09-10' }
  const rooms = [{ id: 'R1', services: [{ id: 1, service_bill_id: 3, ...same }], transferred_out_services: [{ id: 9, service_bill_id: 4, ...same }] }]
  assert.equal(distributeServicesByBillOrigin(rooms, []).get('R1').services.length, 1)
})

test('row 458: tải lại dịch vụ của phòng nhận không kéo lại dòng của phòng gốc, vẫn giữ phần đã trả về', () => {
  const room = {
    foreignServiceBillIds: new Set(['10']),
    originExtraServices: [{ id: 9, service_bill_id: 4, service_code: 'EP', service_date: '2026-09-10' }],
  }
  const fresh = [{ id: 1, service_bill_id: 10, service_code: 'EB', service_date: '2026-09-10' }, { id: 2, service_bill_id: null, service_code: 'RM', service_date: '2026-09-11' }]
  assert.deepEqual(mergeRoomServices(room, fresh).map(s => s.id), [2, 9])
})
