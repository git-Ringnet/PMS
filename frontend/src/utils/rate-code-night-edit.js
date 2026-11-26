/**
 * Dòng 533/535: sửa giá từng đêm ở chi tiết phòng (dấu +).
 * Mã giá theo ngày, hoặc mã giá không bật "cho phép đổi giá", luôn lấy giá theo bảng giá khai báo
 * (cùng quy tắc BookingController::applyConfiguredRateCodePricing), nên không cho sửa tay giá từng đêm.
 * Trước đây vẫn cho sửa, báo thành công nhưng tải lại hiện giá theo mã giá.
 */
function flag(value) {
  return value === true || value === 1 || value === '1'
}

export function rateCodeAllowsNightEdit(rateCode) {
  if (!rateCode) return true
  return flag(rateCode.AllowChangeRate) && !flag(rateCode.IsDaily)
}

export function nightRateLockMessage(rateCodeMa) {
  return `Mã giá ${rateCodeMa} không cho phép sửa giá từng đêm, giá lấy theo bảng giá của mã giá.`
}
