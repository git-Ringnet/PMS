export function resolveRoomPlanQuickBookingUnitPrice({
  configuredRoomPrice,
  rateCode,
  canOverrideRateCode = false,
  formRate,
  useConfiguredPricePerRoom = false,
} = {}) {
  if ((rateCode && !canOverrideRateCode) || useConfiguredPricePerRoom) {
    return Number(configuredRoomPrice) || 0
  }

  return Number(formRate) || 0
}
