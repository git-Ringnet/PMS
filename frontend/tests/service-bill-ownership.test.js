import test from 'node:test'
import assert from 'node:assert/strict'
import {
  billBelongsToCurrentRoom,
  isMasterOwnedBill,
  serviceBillCurrentGuestId,
} from '../src/utils/service-bill-ownership.js'

test('bill chưa chuyển vẫn thuộc đúng phòng và khách gốc', () => {
  const bill = {
    Ma: 1,
    RentalRoomId1: 'ROOM-511',
    CustomerId1: 'GUEST-511',
    RentalRoomId2: null,
    CustomerId2: null,
  }

  assert.equal(billBelongsToCurrentRoom(bill, 'ROOM-511'), true)
  assert.equal(serviceBillCurrentGuestId(bill), 'GUEST-511')
  assert.equal(isMasterOwnedBill(bill, { id: 20, master_service_bills: [] }), false)
})

test('bill đã chuyển chỉ thuộc phòng và khách nhận hiện tại', () => {
  const bill = {
    Ma: 2,
    RentalRoomId1: 'ROOM-112',
    CustomerId1: 'GUEST-112',
    RentalRoomId2: 'ROOM-511',
    CustomerId2: 'GUEST-112',
  }

  assert.equal(billBelongsToCurrentRoom(bill, 'ROOM-112'), false)
  assert.equal(billBelongsToCurrentRoom(bill, 'ROOM-511'), true)
  assert.equal(serviceBillCurrentGuestId(bill), 'GUEST-112')
})

test('bill chuyển lên Phiếu Tổng không còn bị nhận là bill phòng gốc', () => {
  const bill = {
    Ma: 3,
    RegisterID2: 20,
    RentalRoomId1: 'ROOM-112',
    CustomerId1: 'GUEST-112',
    RentalRoomId2: null,
    CustomerId2: null,
  }

  assert.equal(isMasterOwnedBill(bill, { id: 20, master_service_bills: [bill] }), true)
  assert.equal(billBelongsToCurrentRoom(bill, 'ROOM-112'), false)
  assert.equal(serviceBillCurrentGuestId(bill), null)
})