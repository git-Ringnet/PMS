import test from 'node:test'
import assert from 'node:assert/strict'

import {
  resolveRoomPlanQuickBookingRateMode,
  resolveRoomPlanQuickBookingUnitPrice,
} from '../src/utils/room-plan-quick-booking-pricing.js'

test('Kế hoạch phòng: nhiều giá chuẩn hiển thị 0 và dùng giá nhập tay chung', () => {
  assert.deepEqual(resolveRoomPlanQuickBookingRateMode({
    unitRates: [850000, 1150000],
    rateCode: '',
  }), { formRate: 0, useConfiguredPricePerRoom: false })

  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 850000,
    formRate: 0,
  }), 0)
  assert.equal(resolveRoomPlanQuickBookingUnitPrice({ formRate: 720000 }), 720000)
})

test('Kế hoạch phòng: Rate Code luôn lấy giá theo từng phòng và khóa giá nhập', () => {
  assert.deepEqual(resolveRoomPlanQuickBookingRateMode({
    unitRates: [950000, 1050000],
    rateCode: 'BAR',
  }), { formRate: 0, useConfiguredPricePerRoom: true })

  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 950000,
    rateCode: 'BAR',
    formRate: 720000,
  }), 950000)
})

test('Kế hoạch phòng: giá chuẩn 0 là giá hợp lệ, không fallback giá cứng', () => {
  assert.equal(resolveRoomPlanQuickBookingUnitPrice({
    configuredRoomPrice: 890000,
    formRate: 0,
  }), 0)
})
