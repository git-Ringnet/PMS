import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/components/ExtraBedModal.vue', import.meta.url), 'utf8')

// Dòng 522: khách không hiểu vì sao đêm 10/09, 11/09 hiện "Đã chốt". Đêm bị khóa khi dòng EB đã post vào
// hóa đơn (is_posted = 1), không phụ thuộc ngày hệ thống -> nhãn và tooltip phải nói rõ lý do.
test('row 522: a locked night explains it was already posted to the invoice', () => {
  const badge = source.match(/<span\s+v-if="night\.isLocked && !night\.isPast"([\s\S]*?)<\/span>/)[0]
  assert.match(badge, /title="[^"]*đã post[^"]*hóa đơn[^"]*"/i)
  assert.match(badge, /Đã chốt \(đã post\)/)
})

test('row 522: posted EB rows lock the night even when it is not in the past', () => {
  assert.match(source, /const isPosted = Boolean\(foundSvc && Number\(foundSvc\.is_posted\) === 1\)/)
  assert.match(source, /const isLocked = isPastDate \|\| isPosted/)
})
