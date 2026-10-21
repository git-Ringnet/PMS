import test from 'node:test'
import assert from 'node:assert/strict'

import { resolveRoomPlanQuickBookingUnitPrice } from '../src/utils/room-plan-quick-booking-pricing.js'

test('Kế hoạch phòng: phòng khác dạng dùng giá chuẩn riêng khi Rate hiển thị 0', () => {
  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 850000,
    formRate: 0,
    useConfiguredPricePerRoom: true,
  }), 850000)
  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 1150000,
    formRate: 0,
    useConfiguredPricePerRoom: true,
  }), 1150000)
})

test('Kế hoạch phòng: Rate Code không cho đổi giá vẫn lấy giá từng phòng', () => {
  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 950000,
    rateCode: 'BAR',
    canOverrideRateCode: false,
    formRate: 0,
  }), 950000)
})

test('Kế hoạch phòng: giá người dùng nhập tay vẫn được ưu tiên', () => {
  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 850000,
    rateCode: 'CORP',
    canOverrideRateCode: true,
    formRate: 720000,
  }), 720000)
})
