// Bill giường phụ (EB) đã post trong chi tiết dịch vụ từng đêm của phòng (checklist dòng 306).
// Phòng đã chuyển có 0 đêm sau ngày chuyển, phòng mới chỉ có bill đã post ở phòng cũ (tham chiếu lịch sử),
// nên cần hiện riêng các bill EB này thay vì chỉ duyệt theo từng đêm của phòng.

const normalizeId = bill => String(bill?.Ma ?? bill?.id ?? '')

function isLiveExtraBedBill(bill) {
  return String(bill?.ServiceId || '').toUpperCase() === 'EB'
    && Number(bill.Edit) !== 1
    && ![3, 4].includes(Number(bill.Status))
}

/** Bill EB đã post ở phòng trước (tham chiếu lịch sử khi chuyển phòng) của đúng đêm `dateStr`. */
export function findMoveHistoryExtraBedBill(serviceBills, dateStr, dateOf, handledIds) {
  return (Array.isArray(serviceBills) ? serviceBills : []).find(bill =>
    bill?.from_move_history
    && isLiveExtraBedBill(bill)
    && dateOf(bill) === dateStr
    && !handledIds.has(normalizeId(bill)),
  )
}

/** Bill EB còn hiệu lực có ngày nằm ngoài các đêm lưu trú của phòng (vd phòng đã chuyển, 0 đêm). */
export function findExtraBedBillsOutsideStay(serviceBills, stayNights, dateOf, handledIds) {
  return (Array.isArray(serviceBills) ? serviceBills : []).filter(bill =>
    isLiveExtraBedBill(bill)
    && !stayNights.has(dateOf(bill))
    && !handledIds.has(normalizeId(bill)),
  )
}

/** Dòng hiển thị cho một bill EB đã post: bill gửi về Master (không có phòng đích) hiển thị GIT. */
export function extraBedBillRow(bill, dateStr) {
  const quantity = Number(bill.Quantity) || 1
  const amount = Number(bill.Amount) || 0
  const folioRoom = bill.RentalRoomId2
  return {
    id: `service-bill-eb-${normalizeId(bill)}`,
    service_date: dateStr,
    service_name: bill.DescriptionServive || 'Giường phụ (Extra Bed)',
    service_code: 'EB',
    quantity,
    rate: quantity > 0 ? amount / quantity : amount,
    is_room: !!folioRoom && String(folioRoom) !== '0',
    from_bill: true,
    from_move_history: !!bill.from_move_history,
    bill_ref: bill,
  }
}
