import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

test('row 350: In Bill hiện giá và In Bill Không Hiện Giá đều gọi in biên nhận kèm show_price', () => {
  const src = readFileSync(new URL('../src/pages/frontdesk/CheckoutPage.vue', import.meta.url), 'utf8')
  assert.match(src, /@click="printPaymentReceipt"\s+data-testid="receipt-show-price-btn"/)
  assert.match(src, /@click="printPaymentReceipt\(false\)"\s+data-testid="receipt-hide-price-btn"/)
  assert.match(src, /show_price: showPrice/)
  assert.doesNotMatch(src, /title="Chưa có chức năng">Hiện giá/)
})
