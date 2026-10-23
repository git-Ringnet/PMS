import test from 'node:test'
import assert from 'node:assert/strict'

import { storageImageUrl } from '../src/utils/storage-url.js'

test('Dòng 354: ảnh sản phẩm lưu trong storage hiển thị bằng đường dẫn /storage/... (không gắn localhost)', () => {
  assert.equal(storageImageUrl('products/7up.jpg'), '/storage/products/7up.jpg')
  assert.equal(storageImageUrl('storage/products/7up.jpg'), '/storage/products/7up.jpg')
  assert.equal(storageImageUrl('/storage/products/7up.jpg'), '/storage/products/7up.jpg')
  assert.equal(storageImageUrl('http://localhost:8000/storage/products/7up.jpg'), '/storage/products/7up.jpg')
  assert.equal(storageImageUrl('https://pms.ringnet.vn/storage/products/7up.jpg'), '/storage/products/7up.jpg')
})

test('Dòng 354: không có ảnh thì trả về null để hiện biểu tượng thay thế', () => {
  assert.equal(storageImageUrl(null), null)
  assert.equal(storageImageUrl(''), null)
  assert.equal(storageImageUrl('   '), null)
})

test('Dòng 354: ảnh ngoài (không phải storage) giữ nguyên', () => {
  assert.equal(storageImageUrl('https://cdn.example.com/a.png'), 'https://cdn.example.com/a.png')
})
