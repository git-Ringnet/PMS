import assert from 'node:assert/strict'
import test from 'node:test'

import {
  applyDetailBreakfastField,
  applyParentBreakfastField,
  normalizeBreakfastAmount,
} from '../src/utils/child-breakfast-amounts.js'

function makeChild() {
  return {
    id: 'T1',
    age_group: 'child',
    amount: 123456,
    breakfast: true,
    is_free: false,
    is_extra_charge: true,
    is_room: true,
    breakfast_details: [
      {
        id: 1,
        amount: 123456,
        breakfast: true,
        is_free: false,
        is_extra_charge: true,
        is_room: true,
      },
    ],
  }
}

test('parent amount edits propagate the entered amount to every day', () => {
  const child = makeChild()

  applyParentBreakfastField(child, 'amount', '987,654')

  assert.equal(child.amount, 987654)
  assert.equal(child.breakfast_details[0].amount, 987654)
})

test('FIT/GIT changes preserve a manually agreed parent and daily amount', () => {
  const child = makeChild()

  applyParentBreakfastField(child, 'is_room', false)
  assert.equal(child.is_room, false)
  assert.equal(child.breakfast_details[0].is_room, false)
  assert.equal(child.amount, 123456)
  assert.equal(child.breakfast_details[0].amount, 123456)

  applyDetailBreakfastField(child, child.breakfast_details[0], 'is_room', true)
  assert.equal(child.is_room, true)
  assert.equal(child.amount, 123456)
  assert.equal(child.breakfast_details[0].amount, 123456)
})

test('negative breakfast amounts are rejected instead of becoming positive', () => {
  assert.equal(normalizeBreakfastAmount('-100'), 0)
  assert.equal(normalizeBreakfastAmount('−100'), 0)
  assert.equal(normalizeBreakfastAmount('100'), 100)
})

test('breakfast state changes still resolve configured defaults', () => {
  const child = makeChild()
  child.breakfast = false
  child.breakfast_details[0].breakfast = false

  applyDetailBreakfastField(child, child.breakfast_details[0], 'breakfast', true, {
    extraCharge: 90000,
    noCharge: 70000,
  })

  assert.equal(child.breakfast_details[0].amount, 90000)
  assert.equal(child.amount, 90000)
})

// Task 415: bật/tắt phụ phí không làm mất giá nhập tay khác hai giá mặc định.
const PRICES = { extraCharge: 90000, noCharge: 70000 }

function makeRow(amount, isExtra) {
  const child = {
    id: 'T2', age_group: 'child', amount, breakfast: true, is_free: false, is_extra_charge: isExtra, is_room: !isExtra,
    breakfast_details: [{ id: 1, amount, breakfast: true, is_free: false, is_extra_charge: isExtra, is_room: !isExtra }],
  }
  return child
}

test('row 415: custom price is kept when the surcharge is switched off and on', () => {
  const child = makeRow(123456, true)
  const detail = child.breakfast_details[0]

  applyDetailBreakfastField(child, detail, 'is_extra_charge', false, PRICES)
  assert.equal(detail.is_extra_charge, false)
  assert.equal(detail.amount, 123456)
  assert.equal(child.amount, 123456)

  applyDetailBreakfastField(child, detail, 'is_extra_charge', true, PRICES)
  assert.equal(detail.amount, 123456)
})

test('row 415: default prices still jump to the matching default when the surcharge is toggled', () => {
  const child = makeRow(90000, true)
  const detail = child.breakfast_details[0]

  // đang đúng giá phụ thu mặc định -> tắt phụ phí thì về giá tách từ tiền phòng (BreakfastRateChild)
  applyDetailBreakfastField(child, detail, 'is_extra_charge', false, PRICES)
  assert.equal(detail.amount, 70000)
  // đang đúng giá mặc định -> bật phụ phí thì về giá phụ thu trong Thông tin khách sạn
  applyDetailBreakfastField(child, detail, 'is_extra_charge', true, PRICES)
  assert.equal(detail.amount, 90000)
})

test('row 415: the parent row keeps a custom price too when toggling the surcharge', () => {
  const child = makeRow(123456, true)
  applyParentBreakfastField(child, 'is_extra_charge', false, PRICES)
  assert.equal(child.breakfast_details[0].amount, 123456)
  assert.equal(child.amount, 123456)

  const other = makeRow(70000, false)
  applyParentBreakfastField(other, 'is_extra_charge', true, PRICES)
  assert.equal(other.breakfast_details[0].amount, 90000)
})
