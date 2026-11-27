import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/housekeeping/components/InventoryTab.vue', import.meta.url), 'utf8')

test('row 372 (note 26/11): kho chưa chọn điểm bán hàng thì báo rõ thay vì danh sách trống', () => {
  assert.match(source, /v-if="!\(activeWarehouse\?\.outlet_ids \|\| \[\]\)\.length"[^>]*data-testid="inventory-no-outlet-msg"/)
  assert.match(source, /Kho chưa chọn điểm bán hàng\. Vui lòng cập nhật thông tin kho/)
})

test('row 372 (note 26/11): bấm ra ngoài danh sách Kho đã ẩn thì tự đóng', () => {
  assert.match(source, /closest\('\[data-testid="hidden-warehouses-btn"\], \[data-testid="hidden-warehouses-panel"\]'\)[\s\S]*?showHiddenWarehouses\.value = false/)
})
