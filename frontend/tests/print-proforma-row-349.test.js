import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

test('row 349: proforma invoice always shows prices, independent of the registration card Hiện/Không hiện giá choice', () => {
  const src = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
  const block = src.slice(src.indexOf('async function openProformaInvoicePrint'), src.indexOf('function handleConfirmSelectedPrintServices'))
  assert.match(block, /printPreviewModalShowPrice\.value = true/)
  assert.doesNotMatch(block, /isPrintPrice/)
})
