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
