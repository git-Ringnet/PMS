import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

test('row 350: invoice menu has working Hiện giá / Không hiện giá receipt buttons', () => {
  const src = readFileSync(new URL('../src/pages/frontdesk/CheckoutPage.vue', import.meta.url), 'utf8')
  assert.match(src, /@click="printPaymentReceipt\(true\)" data-testid="receipt-show-price-btn"/)
  assert.match(src, /@click="printPaymentReceipt\(false\)" data-testid="receipt-hide-price-btn"/)
  assert.match(src, /show_price: showPrice/)
  assert.doesNotMatch(src, /title="Chưa có chức năng">Hiện giá/)
})
