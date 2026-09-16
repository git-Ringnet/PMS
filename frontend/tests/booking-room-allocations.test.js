import test from 'node:test'
import assert from 'node:assert/strict'
import {
  allocationCandidateRooms,
  countAllocatableRooms,
  countProtectedAllocationRooms,
  isEditableAllocationRoom,
  normalizeAllocationQuantity,
} from '../src/utils/booking-room-allocations.js'

test('allocation quantities exclude cancelled, no-show and moved history', () => {
  const rooms = [
    { roomClassId: 7, bookingRoomId: 'G0000001', bookingRoomStatus: 0 },
    { roomClassId: 7, bookingRoomId: 'G0000002', bookingRoomStatus: 1 },
    { roomClassId: 7, bookingRoomId: 'G0000003', bookingRoomStatus: 2 },
    { roomClassId: 7, bookingRoomId: 'G0000004', bookingRoomStatus: 3 },
    { roomClassId: 7, bookingRoomId: 'G0000005', bookingRoomStatus: 4 },
    { roomClassId: 7, bookingRoomId: 'G0000006', bookingRoomStatus: 100 },
    { roomClassId: 7 },
  ]

  assert.equal(countAllocatableRooms(rooms, 7), 4)
  assert.equal(countProtectedAllocationRooms(rooms, 7), 2)
  assert.equal(allocationCandidateRooms(rooms, 7).length, 4)
  assert.equal(isEditableAllocationRoom(rooms[0]), true)
  assert.equal(isEditableAllocationRoom(rooms[1]), false)
  assert.equal(isEditableAllocationRoom(rooms[3]), false)
})

test('quantity normalization keeps allocation targets integral and non-negative', () => {
  assert.equal(normalizeAllocationQuantity(5.8), 5)
  assert.equal(normalizeAllocationQuantity('3'), 3)
  assert.equal(normalizeAllocationQuantity(-1), 0)
  assert.equal(normalizeAllocationQuantity('invalid'), 0)
})
