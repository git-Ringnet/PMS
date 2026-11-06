import test from 'node:test'
import assert from 'node:assert/strict'
import { normalizeShuttleInfo, buildBookingHistoryLocation } from '../src/utils/booking-shuttle-history.js'

test('row 492: danh sách đưa đón rỗng/thiếu giữ rỗng, không tự thêm dòng mặc định', () => {
  assert.deepEqual(normalizeShuttleInfo([]), [])
  assert.deepEqual(normalizeShuttleInfo(undefined), [])
  assert.deepEqual(normalizeShuttleInfo(null), [])
})

test('row 492: có dữ liệu thì sao chép sâu, không dùng chung tham chiếu', () => {
  const source = [{ id: 1, type: 'Đón' }]
  const copy = normalizeShuttleInfo(source)
  copy[0].type = 'Đưa'
  assert.equal(source[0].type, 'Đón')
})

test('row 493: đường dẫn lịch sử lọc theo đăng ký, giữ nguyên path hiện tại', () => {
  assert.deepEqual(buildBookingHistoryLocation('/reservation', 42), { path: '/reservation', query: { tab: 'history', registration_code: '42' } })
})

test('row 493: đăng ký chưa lưu không có đường dẫn', () => {
  assert.equal(buildBookingHistoryLocation('/reservation', null), null)
  assert.equal(buildBookingHistoryLocation('/reservation', ''), null)
})
