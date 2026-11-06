import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Checklist dòng 102, 103 (Lễ tân): In xác nhận / phiếu đăng ký / hóa đơn tạm.
// Modal xem trước là lớp fixed có overflow nên trình duyệt chỉ in trang đầu, nội dung dài hơn một trang bị cắt mất.
// Khi in phải: ẩn phần giao diện phía sau, đưa tờ in về luồng bình thường (không fixed/overflow) để tự ngắt trang.
const source = readFileSync(new URL('../src/pages/reservation/components/BookingPrintPreviewModal.vue', import.meta.url), 'utf8')

function printBlocks() {
  // Lấy mọi khối @media print trong style của file.
  return [...source.matchAll(/@media print\s*\{([\s\S]*?)\n\}/g)].map(m => m[1]).join('\n')
}

test('rows 102-103: the print modal root is marked so print CSS can release it from fixed/overflow', () => {
  assert.match(source, /class="[^"]*\bprint-root\b[^"]*"/)
})

test('rows 102-103: while printing the app behind the modal is hidden', () => {
  assert.match(printBlocks(), /body\.print-modal-open\s*>\s*#app\s*\{[^}]*display:\s*none\s*!important/)
})

test('rows 102-103: while printing the modal root flows normally so long content paginates', () => {
  const css = printBlocks()
  const root = css.match(/body\.print-modal-open\s+\.print-root\s*\{([^}]*)\}/)
  assert.ok(root, 'thiếu luật in cho .print-root')
  assert.match(root[1], /position:\s*static\s*!important/)
  assert.match(root[1], /overflow:\s*visible\s*!important/)
})

test('rows 102-103: html/body can grow beyond one screen while printing', () => {
  assert.match(printBlocks(), /body\.print-modal-open[^{]*\{[^}]*height:\s*auto\s*!important/)
})

test('rows 102-103: the body marker class follows the modal open state and is removed on unmount', () => {
  assert.match(source, /classList\.add\('print-modal-open'\)/)
  assert.match(source, /classList\.remove\('print-modal-open'\)/)
})

test('rows 102-103: the hotel logo uses a root-relative URL so it loads on every page', () => {
  assert.doesNotMatch(source, /:src="hotel\.logo_url"/)
  assert.match(source, /:src="hotelLogoSrc"/)
  assert.match(source, /path\.startsWith\('\/'\) \? path : `\/\$\{path\}`/)
})
