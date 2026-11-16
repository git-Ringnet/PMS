import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

import { wrapPrintDocument } from '../src/utils/print-html.js'

const checkout = readFileSync(new URL('../src/pages/frontdesk/CheckoutPage.vue', import.meta.url), 'utf8')
const menuStart = checkout.indexOf('<!-- In hóa đơn: các nút hiện sẵn')
const menu = checkout.slice(menuStart, checkout.indexOf('<!-- In VAT -->', menuStart))

test('In Hóa Đơn Folio: bấm nút in mẫu có hiện giá', () => {
  assert.match(menu, /@click="openCheckoutInvoice\(\{ showPrice: true \}\)"[\s\S]*?In Hóa Đơn Folio/)
})

test('Các nút in hiện sẵn, không giấu trong menu con', () => {
  assert.match(menu, /@click="openCheckoutInvoice\(\{ showPrice: false \}\)"[\s\S]*?In Không Hiện Giá/)
  assert.match(menu, /@click="printPaymentReceipt"[\s\S]*?In Bill/)
  assert.doesNotMatch(checkout, /showInvoiceMenu/)
})

test('In Tổng chỉ hiện khi đứng ở dòng Master', () => {
  assert.match(menu, /v-if="isMasterRowSelected"\s+@click="openCheckoutInvoice\(\{ showPrice: true, total: true \}\)"[\s\S]*?In Tổng/)
  assert.match(checkout, /const isMasterRowSelected = computed\(\(\) => Number\(selectedBooking\.value\?\.bookingId\) > 0 && !selectedRoomItem\.value\)/)
})

test('In phiếu thu mở màn xem trước, không gọi hộp thoại in ngay', () => {
  const start = checkout.indexOf('const printReceiptForPayments = async')
  const body = checkout.slice(start, checkout.indexOf('\n}\n', start))
  assert.match(body, /printPreview\.value = \{ show: true, kind: 'receipt'/)
  assert.doesNotMatch(body, /print\(\)|printHtmlDocument/)
})

test('Tờ in sao chép sang tài liệu in độc lập', () => {
  const html = wrapPrintDocument('<div class="ckinv">X</div>', 'Hóa đơn')
  assert.match(html, /^<!doctype html>/)
  assert.match(html, /<meta charset="utf-8">/)
  assert.match(html, /<body style="margin:0"><div class="ckinv">X<\/div><\/body>/)
})
