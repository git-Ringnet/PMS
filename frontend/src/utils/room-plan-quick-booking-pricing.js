export function normalizeRoomPlanQuickBookingRateCode(rateCode) {
  const normalized = String(rateCode ?? '').trim()
  return normalized === '' || normalized === 'Vui lòng chọn giá phòng' ? null : normalized
}

export function resolveRoomPlanQuickBookingSelectedRateCode({ rateCode, rateCodes = [] } = {}) {
  const normalized = normalizeRoomPlanQuickBookingRateCode(rateCode)
  if (!normalized) return null

  const selected = rateCodes.find(item => String(item?.Ma ?? item?.code ?? '').trim() === normalized)
  return selected ? normalized : null
}

export function resolveRoomPlanQuickBookingUnitPrice({
  configuredRoomPrice,
  rateCode,
  formRate,
  hasManualRate = false,
} = {}) {
  // Rate 0 trên form có thể chỉ biểu thị các phòng được chọn có giá cấu hình
  // khác nhau. Chỉ giá do người dùng thực sự nhập mới được dùng chung.
  if (normalizeRoomPlanQuickBookingRateCode(rateCode) || !hasManualRate) {
    return Number(configuredRoomPrice) || 0
  }

  return Number(formRate) || 0
}

export function resolveRoomPlanQuickBookingRateMode({ unitRates = [], rateCode } = {}) {
  const normalizedRates = unitRates.map(rate => Number(rate) || 0)
  const firstRate = normalizedRates[0] || 0
  const hasDifferentRates = normalizedRates.some(rate => rate !== firstRate)

  return {
    formRate: hasDifferentRates ? 0 : firstRate,
    useConfiguredPricePerRoom: Boolean(normalizeRoomPlanQuickBookingRateCode(rateCode)) && hasDifferentRates,
  }
}
