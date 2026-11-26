import test from 'node:test'
import assert from 'node:assert/strict'
import {
  applyStandardStayTimes,
  normalizeStayTime,
  standardCheckInTime,
  standardCheckOutTime,
} from '../src/utils/standard-stay-times.js'
import { buildNoshowRoomEdits } from '../src/utils/booking-room-allocations.js'

// Giờ nhận/trả phòng tiêu chuẩn (Định nghĩa khách sạn › Thông tin khách sạn).

test('normalizeStayTime chuẩn hóa HH:mm và loại giá trị sai', () => {
  assert.equal(normalizeStayTime('9:5'), '09:05')
  assert.equal(normalizeStayTime('13:30:00'), '13:30')
  assert.equal(normalizeStayTime('24:00'), null)
  assert.equal(normalizeStayTime('abc'), null)
  assert.equal(normalizeStayTime(''), null)
})

test('applyStandardStayTimes chỉ đổi khi có khóa, giá trị sai quay về mặc định', () => {
  applyStandardStayTimes({ StandardCheckInTime: '13:00:00', StandardCheckOutTime: '11:00' })
  assert.equal(standardCheckInTime.value, '13:00')
  assert.equal(standardCheckOutTime.value, '11:00')

  // Trang chưa tải xong (object rỗng) không được ghi đè giá trị đã tải.
  applyStandardStayTimes({})
  applyStandardStayTimes(null)
  assert.equal(standardCheckInTime.value, '13:00')
  assert.equal(standardCheckOutTime.value, '11:00')

  applyStandardStayTimes({ StandardCheckInTime: 'sai', StandardCheckOutTime: '' })
  assert.equal(standardCheckInTime.value, '14:00')
  assert.equal(standardCheckOutTime.value, '12:00')
})

test('phòng noshow chưa có giờ dùng giờ chuẩn đã cấu hình khi lưu đăng ký', () => {
  applyStandardStayTimes({ StandardCheckInTime: '15:00', StandardCheckOutTime: '11:30' })
  const [noTime, withTime] = buildNoshowRoomEdits([
    { bookingRoomId: 'G1', status: 4, checkIn: '2026-08-09', checkOut: '2026-08-10' },
    { bookingRoomId: 'G2', status: 4, checkIn: '2026-08-09', checkOut: '2026-08-10', arrivalTime: '09:00', hoursOut: '18:00' },
  ])
  assert.equal(noTime.arrivalTime, '15:00')
  assert.equal(noTime.hoursOut, '11:30')
  assert.equal(withTime.arrivalTime, '09:00')
  assert.equal(withTime.hoursOut, '18:00')
  applyStandardStayTimes({ StandardCheckInTime: '14:00', StandardCheckOutTime: '12:00' })
})
