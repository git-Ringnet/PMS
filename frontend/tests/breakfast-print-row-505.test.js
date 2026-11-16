import test from 'node:test'
import assert from 'node:assert/strict'
import { DEFAULT_COUPON_CONFIG, buildCouponPreviewHtml, initialSelectedKeys, resolveCouponConfig } from '../src/utils/breakfast-print.js'

const coupons = [
  { booking_info: 'NV1-An', room_number: '412', date: '2026-09-06', guest_name: 'A' },
  { booking_info: 'NV1-An', room_number: '412', date: '2026-09-06', guest_name: 'A' }
]

test('row 505: không chọn sẵn tất cả các phòng, chỉ chọn khi mở từ Room Map', () => {
  const rows = [{ id: 'a_1' }, { id: 'b_1' }]
  assert.deepEqual(initialSelectedKeys(rows, false), [])
  assert.deepEqual(initialSelectedKeys(rows, true), ['a_1', 'b_1'])
})

test('row 505: mặc định 2 phiếu mỗi hàng', () => {
  assert.equal(DEFAULT_COUPON_CONFIG.columns, 2)
  assert.equal(resolveCouponConfig(null).columns, 2)
  assert.equal(resolveCouponConfig({ columns: 3 }).columns, 3, 'cấu hình người dùng đã lưu vẫn được tôn trọng')
  const html = buildCouponPreviewHtml({ coupons })
  assert.match(html, /grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/)
})

test('row 505: xem trước khổ A4 dọc, có nút In và không tự bật hộp thoại in', () => {
  const html = buildCouponPreviewHtml({ coupons })
  assert.match(html, /@page \{ size: A4 portrait/)
  assert.match(html, /width: 210mm/)
  assert.match(html, /onclick="window\.print\(\)"/)
  assert.doesNotMatch(html, /setTimeout|<script/)
})
