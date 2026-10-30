// Dòng 351 (note 29/10): quy tắc dịch vụ bổ sung ở màn hình đăng ký.

/**
 * Các dòng cần gửi khi lưu "Thêm dịch vụ bổ sung": chỉ THÊM dịch vụ vừa chọn vào
 * các ngày đã tick (trong giai đoạn ở, từ ngày hệ thống trở đi). Không đụng dịch vụ khác.
 */
export function buildAddServicePayloads({ items, checkedDates, roomDates, today, skip = () => false }) {
  const payloads = []
  for (const item of items || []) {
    if (skip(item)) continue
    for (const d of checkedDates || []) {
      if (!(roomDates || []).includes(d) || d < today) continue
      payloads.push({
        service_code: item.service_code,
        service_name: item.service_name,
        service_date: d,
        quantity: item.quantity,
        rate: item.rate,
        is_room: item.is_room ? 1 : 0
      })
    }
  }
  return payloads
}

const isPostedRow = s => Number(s?.is_posted) === 1 || !!s?.service_bill_id

/**
 * Dịch vụ đã post và dòng kế hoạch (giá sửa sau khi post) cùng mã/ngày hiển thị thành MỘT dòng.
 * - Dòng kế hoạch có dòng đã post cùng ngày: { hidden: true } (đã gộp vào dòng đã post).
 * - Dòng đã post có dòng kế hoạch: { pendingPlan } để hiện giá mới kèm số tiền bill đã chạy.
 */
export function resolvePostedServicePlan(svc, services, dateOf) {
  const date = dateOf(svc)
  const sameDay = s => s !== svc && s.service_code === svc.service_code && dateOf(s) === date
  if (!isPostedRow(svc)) {
    return { hidden: (services || []).some(s => sameDay(s) && isPostedRow(s)), pendingPlan: null }
  }
  return { hidden: false, pendingPlan: (services || []).find(s => sameDay(s) && !isPostedRow(s)) || null }
}
