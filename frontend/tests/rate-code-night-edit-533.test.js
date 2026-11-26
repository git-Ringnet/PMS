import test from 'node:test'
import assert from 'node:assert/strict'

import { rateCodeAllowsNightEdit, nightRateLockMessage } from '../src/utils/rate-code-night-edit.js'

test('Dòng 533/535: không dùng mã giá thì được sửa giá từng đêm', () => {
  assert.equal(rateCodeAllowsNightEdit(null), true)
  assert.equal(rateCodeAllowsNightEdit(undefined), true)
})

test('Dòng 533/535: mã giá cố định cho phép đổi giá thì được sửa (STANDARD, CORP)', () => {
  assert.equal(rateCodeAllowsNightEdit({ Ma: 'STANDARD', IsDaily: 0, AllowChangeRate: 1 }), true)
  assert.equal(rateCodeAllowsNightEdit({ Ma: 'CORP', IsDaily: false, AllowChangeRate: true }), true)
})

test('Dòng 533/535: mã giá không cho đổi giá thì khóa (FOC, HU) – trước đây báo thành công rồi tải lại ra giá cũ', () => {
  assert.equal(rateCodeAllowsNightEdit({ Ma: 'FOC', IsDaily: 0, AllowChangeRate: 0 }), false)
  assert.equal(rateCodeAllowsNightEdit({ Ma: 'HU', IsDaily: '0', AllowChangeRate: '0' }), false)
})

test('Dòng 533/535: mã giá theo ngày luôn theo bảng giá khai báo nên khóa', () => {
  assert.equal(rateCodeAllowsNightEdit({ Ma: 'TEST', IsDaily: 1, AllowChangeRate: 0 }), false)
  assert.equal(rateCodeAllowsNightEdit({ Ma: 'DAILY', IsDaily: 1, AllowChangeRate: 1 }), false)
})

test('Dòng 533/535: lời nhắc nêu mã giá', () => {
  assert.match(nightRateLockMessage('FOC'), /FOC/)
  assert.match(nightRateLockMessage('FOC'), /không cho phép sửa giá/)
})
