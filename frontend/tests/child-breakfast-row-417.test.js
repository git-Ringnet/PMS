import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 417: trẻ em ăn sáng không phụ phí thì không hiển thị tiền ở chi tiết ăn sáng trẻ.
const modal = readFileSync(new URL('../src/pages/reservation/components/ChildBreakfastModal.vue', import.meta.url), 'utf8')
const booking = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
const roomPlan = readFileSync(new URL('../src/utils/room-plan-amounts.js', import.meta.url), 'utf8')

test('row 417/480: amount inputs of the child rows are shown for breakfast rows, babies never show money', () => {
  const inputs = [...modal.matchAll(/:value="formatCurrency\((child|d)\.amount\)"/g)]
  assert.equal(inputs.length, 4)
  // Em bé (2 ô đầu): chỉ hiện khi có phụ phí - em bé luôn miễn phí nên không bao giờ hiện tiền.
  for (const m of inputs.slice(0, 2)) {
    const before = modal.slice(Math.max(0, m.index - 400), m.index)
    assert.match(before, new RegExp(String.raw`v-if="!${m[1]}\.is_extra_charge"[\s\S]*<div v-else`))
  }
  // Trẻ em (2 ô sau, dòng 480): hiện cả khi KHÔNG phụ phí (tiền tách từ tiền phòng) miễn là có ăn sáng và không miễn phí.
  for (const m of inputs.slice(2)) {
    const before = modal.slice(Math.max(0, m.index - 400), m.index)
    assert.match(before, new RegExp(String.raw`v-if="${m[1]}\.breakfast && !${m[1]}\.is_free"`))
    assert.doesNotMatch(before, new RegExp(String.raw`v-if="!${m[1]}\.is_extra_charge"`))
  }
})

test('row 415: modal has no close (X) button and a Quay lại button placed before Lưu', () => {
  assert.doesNotMatch(modal, /title="Đóng"/)
  const back = modal.indexOf('data-testid="child-breakfast-back"')
  const save = modal.indexOf('@click="save"')
  assert.ok(back > -1 && save > -1 && back < save, 'Quay lại phải đứng trước Lưu')
  assert.match(modal.slice(back, back + 300), /Quay lại/)
})

test('row 417: booking detail rows and totals only count surcharged child breakfast', () => {
  assert.match(booking, /if \(!d\.breakfast \|\| !d\.is_extra_charge \|\| d\.is_free \|\| amt <= 0 \|\| !dStr\) return/)
  assert.match(roomPlan, /Number\(detail\.is_extra_charge\) === 1/)
})
