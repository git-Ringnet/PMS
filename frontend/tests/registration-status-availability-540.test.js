import test from 'node:test'
import assert from 'node:assert/strict'
import { isRegistrationStatusWithoutAvailability } from '../src/utils/registration-status.js'

const statuses = [
  { id: 1, booking_status_id: 1, name: 'Guaranteed', is_availability: true },
  { id: 5, booking_status_id: 29, name: 'Waiting', is_availability: false },
  { id: 6, booking_status_id: 30, name: 'Tentative', is_availability: 0 },
  { id: 7, booking_status_id: 31, name: 'Option', is_availability: '1' },
]

test('row 540: tình trạng không ảnh hưởng phòng (is_availability = 0 / false) bị coi là không giữ phòng', () => {
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, 29), true)
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, 30), true)
})

test('row 540: tình trạng có giữ phòng không bị khóa', () => {
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, 1), false)
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, 31), false)
})

test('row 540: không có tình trạng hoặc không tìm thấy thì không khóa', () => {
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, null), false)
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, 999), false)
  assert.equal(isRegistrationStatusWithoutAvailability(undefined, 29), false)
})

test('row 540: tìm theo mã nghiệp vụ (booking_status_id) trước, rồi mới theo id', () => {
  assert.equal(isRegistrationStatusWithoutAvailability(statuses, '29'), true)
  assert.equal(isRegistrationStatusWithoutAvailability([{ id: 29, booking_status_id: 1, is_availability: true }, { id: 2, booking_status_id: 29, is_availability: false }], 29), true)
})
