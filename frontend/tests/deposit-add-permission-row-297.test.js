import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/components/DepositModal.vue', import.meta.url), 'utf8').replace(/\r\n/g, '\n')

test('TC_LT_22 dòng 297 (task 473): nút Thêm cọc chỉ hiện khi có quyền fo.deposit.create', () => {
  assert.match(source, /const canCreateDeposit = computed\(\(\) => authStore\.hasPermission\('fo\.deposit\.create'\)\)/)
  const addButton = source.match(/<button[^>]*data-testid="deposit-add-btn"[^>]*>/)
  assert.ok(addButton, 'nút Thêm phải có data-testid="deposit-add-btn"')
  assert.match(addButton[0], /v-if="!isEditing && canCreateDeposit"/)
  assert.match(addButton[0], /@click="addDeposit"/)
})
