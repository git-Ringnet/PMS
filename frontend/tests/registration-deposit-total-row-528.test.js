import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8').replace(/\r\n/g, '\n')

test('task 528: tổng cọc ở màn thông tin đăng ký chỉ lấy dòng pack2 = DPR và bỏ thanh toán miễn phí PY', () => {
  const block = source.slice(source.indexOf('const activeDepositsList = computed'), source.indexOf('const hasActiveDeposits') > 0 ? source.indexOf('const hasActiveDeposits') : undefined)
  assert.match(block, /String\(d\.pack2 \|\| ''\)\.toUpperCase\(\) === 'DPR'/)
  assert.match(block, /String\(d\.pack4 \|\| ''\)\.toUpperCase\(\) !== 'PY'/)
  assert.doesNotMatch(block, /pack2 \|\| 'DPR'/)
})
