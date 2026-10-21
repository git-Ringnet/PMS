import test from 'node:test'
import assert from 'node:assert/strict'

import {
  clearCheckoutSelection,
  loadCheckoutSelection,
  saveCheckoutSelection,
} from '../src/utils/checkout-selection-storage.js'

const createStorage = (initial = {}) => {
  const values = new Map(Object.entries(initial))
  return {
    getItem: key => values.has(key) ? values.get(key) : null,
    setItem: (key, value) => values.set(key, String(value)),
    removeItem: key => values.delete(key),
  }
}

test('Dòng 346: lưu và khôi phục đúng Booking, phòng, khách và phạm vi Hóa đơn', () => {
  const storage = createStorage()
  const context = { branchId: 2, userId: 15 }

  saveCheckoutSelection(storage, {
    bookingId: 346,
    roomId: 81,
    guestId: 92,
    registerScope: 'old',
  }, context)

  assert.deepEqual(loadCheckoutSelection(storage, context), {
    bookingId: '346',
    roomId: '81',
    guestId: '92',
    registerScope: 'old',
  })
})

test('Dòng 346: Booking Master được khôi phục khi phòng và khách để trống', () => {
  const storage = createStorage()
  const context = { branchId: 'HKT1', userId: '7' }

  saveCheckoutSelection(storage, { bookingId: 'GAL346', registerScope: 'current' }, context)

  assert.deepEqual(loadCheckoutSelection(storage, context), {
    bookingId: 'GAL346',
    roomId: null,
    guestId: null,
    registerScope: 'current',
  })
})

test('Dòng 346: không khôi phục lựa chọn từ chi nhánh khác', () => {
  const storage = createStorage()
  saveCheckoutSelection(storage, { bookingId: 346 }, { branchId: 1, userId: 7 })

  assert.equal(loadCheckoutSelection(storage, { branchId: 2, userId: 7 }), null)
  assert.equal(loadCheckoutSelection(storage, { branchId: 1, userId: 7 }), null)
})

test('Dòng 346: không khôi phục lựa chọn từ người dùng khác', () => {
  const storage = createStorage()
  saveCheckoutSelection(storage, { bookingId: 346 }, { branchId: 1, userId: 7 })

  assert.equal(loadCheckoutSelection(storage, { branchId: 1, userId: 8 }), null)
})

test('Dòng 346: dữ liệu session hỏng bị xóa an toàn', () => {
  const storage = createStorage({ pms_checkout_selection_v1: '{invalid-json' })

  assert.equal(loadCheckoutSelection(storage, { branchId: 1, userId: 7 }), null)
  assert.equal(storage.getItem('pms_checkout_selection_v1'), null)
})

test('Dòng 346: xóa lựa chọn loại bỏ cả khóa mới và các khóa cũ', () => {
  const storage = createStorage({
    pms_checkout_selection_v1: '{}',
    pms_checkout_last_booking_id: '346',
    pms_checkout_last_room_id: '81',
    pms_checkout_last_guest_id: '92',
  })

  clearCheckoutSelection(storage)

  assert.equal(storage.getItem('pms_checkout_selection_v1'), null)
  assert.equal(storage.getItem('pms_checkout_last_booking_id'), null)
  assert.equal(storage.getItem('pms_checkout_last_room_id'), null)
  assert.equal(storage.getItem('pms_checkout_last_guest_id'), null)
})
