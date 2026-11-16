import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 94/100/107/118 (task 372): ô "Chọn Kho" của sản phẩm phải lấy từ danh sách KHO tồn kho (bảng warehouses),
// vì Kiểm kê định kỳ lọc sản phẩm theo inventory_id = id kho. Lấy từ bảng inventories (id khác) làm sản phẩm
// "có theo dõi" không hiện trong kiểm kê của kho đã chọn.
const src = readFileSync(new URL('../src/pages/housekeeping/components/CreateMenuTab.vue', import.meta.url), 'utf8')

test('Chọn Kho của sản phẩm lấy từ /warehouses, không lấy từ /inventories', () => {
  assert.match(src, /http\.get\('\/warehouses'\)/)
  assert.doesNotMatch(src, /http\.get\('\/inventories'\)/)
})
