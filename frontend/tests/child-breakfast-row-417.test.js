import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 417: trẻ em ăn sáng không phụ phí thì không hiển thị tiền ở chi tiết ăn sáng trẻ.
const modal = readFileSync(new URL('../src/pages/reservation/components/ChildBreakfastModal.vue', import.meta.url), 'utf8')
const booking = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
const roomPlan = readFileSync(new URL('../src/utils/room-plan-amounts.js', import.meta.url), 'utf8')

test('row 417: every amount input in the modal is shown only when the row has a surcharge', () => {
  const inputs = [...modal.matchAll(/:value="formatCurrency\((child|d)\.amount\)"/g)]
  assert.equal(inputs.length, 4)
  for (const m of inputs) {
    const before = modal.slice(Math.max(0, m.index - 400), m.index)
    assert.match(before, new RegExp(String.raw`v-if="!${m[1]}\.is_extra_charge"[\s\S]*<div v-else`))
  }
})

test('row 417: booking detail rows and totals only count surcharged child breakfast', () => {
  assert.match(booking, /if \(!d\.breakfast \|\| !d\.is_extra_charge \|\| d\.is_free \|\| amt <= 0 \|\| !dStr\) return/)
  assert.match(roomPlan, /Number\(detail\.is_extra_charge\) === 1/)
})
