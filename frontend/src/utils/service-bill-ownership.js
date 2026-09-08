const hasValue = value => value !== undefined && value !== null && String(value) !== '' && String(value) !== '0'

const roomCandidates = roomOrId => (typeof roomOrId === 'object'
  ? [roomOrId.id, roomOrId.roomId, roomOrId.roomNumber, roomOrId.rawRoom?.room_number]
  : [roomOrId]
).filter(hasValue)

const matchesCandidate = (value, candidates) => hasValue(value)
  && candidates.some(candidate => String(value) === String(candidate))

/** Trả về phòng đang sở hữu bill; bill đã chuyển lên Phiếu Tổng không còn thuộc phòng gốc. */
export function serviceBillCurrentRoomId(bill) {
  if (hasValue(bill?.RentalRoomId2)) return bill.RentalRoomId2
  if (hasValue(bill?.RegisterID2)) return null
  return bill?.RentalRoomId1
}

/** Trả về khách đang sở hữu bill; bill ở Phiếu Tổng không còn gom theo khách gốc. */
export function serviceBillCurrentGuestId(bill) {
  if (hasValue(bill?.CustomerId2)) return bill.CustomerId2
  if (hasValue(bill?.RegisterID2) && !hasValue(bill?.RentalRoomId2)) return null
  return bill?.CustomerId1
}

export function billBelongsToCurrentRoom(bill, roomOrId) {
  const candidates = roomCandidates(roomOrId)
  if (candidates.length === 0) return false
  return matchesCandidate(serviceBillCurrentRoomId(bill), candidates)
}

export function isMasterBillRecord(bill, booking) {
  if (Number(bill?.Edit) === 1) return false
  const bookingId = booking?.id
  const hasCurrentRoom = hasValue(bill?.RentalRoomId2)
  const hasCurrentGuest = hasValue(bill?.CustomerId2)
  const isCurrentMasterOwner = hasValue(bookingId)
    && String(bill?.RegisterID2) === String(bookingId)
    && !hasCurrentRoom
    && !hasCurrentGuest
  const isOriginalMasterBill = !hasValue(bill?.RegisterID2)
    && String(bill?.RegisterId1) === String(bookingId)
    && !hasValue(bill?.RentalRoomId1)
  return isCurrentMasterOwner || isOriginalMasterBill
}

export function isMasterOwnedBill(bill, booking) {
  const billId = bill?.Ma ?? bill?.id
  const masterBills = booking?.master_service_bills || []
  const hasCurrentRoom = hasValue(bill?.RentalRoomId2)
  const hasCurrentGuest = hasValue(bill?.CustomerId2)
  if (!hasCurrentRoom && !hasCurrentGuest && hasValue(billId)
    && masterBills.some(masterBill => String(masterBill?.Ma ?? masterBill?.id) === String(billId))) {
    return true
  }
  return isMasterBillRecord(bill, booking)
}