import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/components/ChildBreakfastModal.vue', import.meta.url), 'utf8')
const header = source.slice(source.indexOf('<!-- HEADER -->'), source.indexOf('<!-- BODY -->'))

test('row 541: header modal Chi tiết ăn sáng trẻ có nút X đóng', () => {
  assert.match(header, /fa-xmark/)
  assert.match(header, /@click="close"/)
  assert.match(header, /title="Đóng"/)
})

test('row 541: nút X nằm trong header, không nằm ở phần thân', () => {
  const body = source.slice(source.indexOf('<!-- BODY -->'))
  assert.equal((header.match(/fa-xmark/g) || []).length, 1)
  assert.ok(body.includes('data-testid="child-breakfast-back"'))
})
