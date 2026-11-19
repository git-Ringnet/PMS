import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/frontdesk/CheckoutPage.vue', import.meta.url), 'utf8').replace(/\r\n/g, '\n')
const buttonOf = testId => source.match(new RegExp(`<button[^>]*data-testid="${testId}"[^>]*>`))

test('TC_LT_22 dòng 297 (task 473): nút Thêm Dịch Vụ cần quyền dịch vụ và fo.bill.create (khớp API post-fo-service-bill)', () => {
  const button = buttonOf('checkout-add-service-btn')
  assert.ok(button, 'nút Thêm Dịch Vụ phải có data-testid="checkout-add-service-btn"')
  assert.match(button[0], /v-if="\(can\('fo\.service\.add'\) \|\| can\('fo\.service\.edit'\)\) && can\('fo\.bill\.create'\)"/)
  assert.match(button[0], /@click="openAddServiceModal"/)
})

test('TC_LT_22 dòng 297 (task 473): nút Thanh Toán cần fo.payment.create và fo.bill.create (khớp API settle-payment)', () => {
  const button = buttonOf('checkout-pay-btn')
  assert.ok(button, 'nút Thanh Toán phải có data-testid="checkout-pay-btn"')
  assert.match(button[0], /v-if="can\('fo\.payment\.create'\) && can\('fo\.bill\.create'\)"/)
  assert.match(button[0], /@click="openPaymentModal"/)
})
