export function resolveRoomPlanQuickBookingUnitPrice({
  configuredRoomPrice,
  rateCode,
  formRate,
} = {}) {
  if (rateCode) {
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
    useConfiguredPricePerRoom: Boolean(rateCode) && hasDifferentRates,
  }
}
