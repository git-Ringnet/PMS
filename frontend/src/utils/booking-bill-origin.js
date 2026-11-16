/**
 * Dòng 458: màn Đăng ký hiển thị tiền theo PHÒNG GỐC của dịch vụ/bill.
 *
 * Khi chuyển bill ở màn Hóa đơn (master -> phòng, phòng -> phòng, phòng -> master) hệ thống tạo bill mới
 * với RentalRoomId2 = nơi đang giữ bill nhưng RentalRoomId1 vẫn là phòng phát sinh gốc, đồng thời copy dòng
 * booking_room_services sang phòng nhận. Nếu cả phòng gốc lẫn phòng nhận cùng hiện bill/dịch vụ đó thì tiền bị
 * nhân đôi và đổ hết về phòng nhận. Các hàm dưới đây giữ mỗi bill/dịch vụ ở đúng một phòng: phòng gốc.
 */

const idOf = value => {
  if (value === null || value === undefined) return ''
  const text = String(value).trim()
  return text === '' || text === '0' ? '' : text
}

export const billOriginRoomId = bill => idOf(bill?.RentalRoomId1 ?? bill?.rentalRoomId1)
export const billHolderRoomId = bill => idOf(bill?.RentalRoomId2 ?? bill?.rentalRoomId2)
const billKey = bill => idOf(bill?.Ma ?? bill?.id)

/**
 * Bill được tạo ra bởi thao tác "Chuyển bill/Tập hợp dịch vụ" luôn đi kèm một bill âm (Pack1 = mã bill dương).
 * Chỉ các bill này mới áp quy tắc "hiển thị ở phòng gốc"; bill di chuyển theo đổi phòng (không có bill âm)
 * giữ nguyên cách hiển thị cũ ở phòng đang ở.
 */
export function transferredBillIds(bills) {
  const ids = new Set()
  for (const bill of Array.isArray(bills) ? bills : []) {
    const pack = idOf(bill?.Pack1 ?? bill?.pack1)
    if (pack && Number(bill?.Amount) < 0) ids.add(pack)
  }
  return ids
}

/**
 * Bill thuộc về phòng khi phòng đó là phòng gốc (RentalRoomId1).
 * Bill đang nằm ở phòng này nhưng phòng gốc không thuộc booking hiện tại (chuyển từ booking khác sang)
 * thì chỉ còn phòng này để hiển thị.
 */
export function isBillOwnedByRoom(bill, roomId, bookingRoomIds, transferredIds = null) {
  const room = idOf(roomId)
  if (!room || !bill) return false
  const origin = billOriginRoomId(bill)
  if (transferredIds && !transferredIds.has(billKey(bill))) {
    return origin === room || billHolderRoomId(bill) === room
  }
  if (origin) {
    if (origin === room) return true
    return billHolderRoomId(bill) === room && !bookingRoomIds?.has(origin)
  }
  return billHolderRoomId(bill) === room
}

/** Lọc + bỏ trùng danh sách bill của một phòng theo quy tắc phòng gốc. */
export function collectRoomOwnedBills(rawBills, roomId, bookingRoomIds, transferredIds = null) {
  const seen = new Set()
  const result = []
  for (const bill of Array.isArray(rawBills) ? rawBills : []) {
    if (!isBillOwnedByRoom(bill, roomId, bookingRoomIds, transferredIds)) continue
    const key = billKey(bill)
    if (key) {
      if (seen.has(key)) continue
      seen.add(key)
    }
    result.push(bill)
  }
  return result
}

const sameServiceSlot = (a, b) =>
  String(a?.service_code || '').toUpperCase() === String(b?.service_code || '').toUpperCase()
  && String(a?.service_date || '').slice(0, 10) === String(b?.service_date || '').slice(0, 10)

const appendMissingSlots = (target, additions) => {
  for (const service of additions) {
    if (!target.some(existing => sameServiceSlot(existing, service))) target.push(service)
  }
}

/**
 * Trả Map<roomId, { services, extras, foreignBillIds }>:
 * - services: dòng của phòng gốc = dòng đang nằm ở phòng + extras;
 * - extras: dòng bị copy sang phòng khác (đã trả về phòng gốc) và dòng đã chuyển đi (xóa mềm) mà phòng này là gốc;
 *   không cộng thêm khi phòng đã có dòng cùng mã + ngày;
 * - foreignBillIds: bill của các dòng nằm ở phòng này nhưng thuộc phòng gốc khác (phải loại khi tải lại dịch vụ của phòng).
 */
export function distributeServicesByBillOrigin(bookingRooms, bills) {
  const rooms = Array.isArray(bookingRooms) ? bookingRooms : []
  const roomIds = new Set(rooms.map(room => idOf(room?.id)))
  const transferred = transferredBillIds(bills)
  const originByBill = new Map()
  for (const bill of Array.isArray(bills) ? bills : []) {
    const origin = billOriginRoomId(bill)
    const key = billKey(bill)
    if (key && origin && roomIds.has(origin) && transferred.has(key)) originByBill.set(key, origin)
  }

  const result = new Map(rooms.map(room => [idOf(room?.id), { own: [], extras: [], foreignBillIds: new Set() }]))
  for (const room of rooms) {
    const holder = idOf(room?.id)
    const slot = result.get(holder)
    for (const service of Array.isArray(room?.services) ? room.services : []) {
      const billId = idOf(service?.service_bill_id)
      const origin = billId ? originByBill.get(billId) : ''
      if (origin && origin !== holder) {
        slot.foreignBillIds.add(billId)
        result.get(origin)?.extras.push(service)
      } else {
        slot.own.push(service)
      }
    }
    for (const service of Array.isArray(room?.transferred_out_services) ? room.transferred_out_services : []) {
      slot.extras.push(service)
    }
  }

  for (const slot of result.values()) {
    const extras = []
    appendMissingSlots(extras, slot.extras)
    slot.extras = extras.filter(service => !slot.own.some(existing => sameServiceSlot(existing, service)))
    slot.services = [...slot.own, ...slot.extras]
  }
  return result
}

/**
 * Tải lại dịch vụ của một phòng từ API: bỏ dòng thuộc phòng gốc khác, giữ lại phần đã trả về phòng gốc.
 */
export function mergeRoomServices(room, freshServices) {
  const foreign = room?.foreignServiceBillIds
  const own = (Array.isArray(freshServices) ? freshServices : [])
    .filter(service => !(foreign && service?.service_bill_id && foreign.has(idOf(service.service_bill_id))))
  const extras = Array.isArray(room?.originExtraServices) ? room.originExtraServices : []
  return [...own, ...extras.filter(service => !own.some(existing => sameServiceSlot(existing, service)))]
}
