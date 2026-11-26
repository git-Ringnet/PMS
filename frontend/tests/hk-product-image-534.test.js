import test from 'node:test'
import assert from 'node:assert/strict'

import { productDisplayImage, outletImage } from '../src/utils/hk-product-image.js'

test('Dòng 534: sản phẩm có ảnh riêng dùng ảnh của sản phẩm', () => {
  assert.equal(productDisplayImage({ image: 'products/coca.png' }, { image: 'outlets/mb.png' }), '/storage/products/coca.png')
})

test('Dòng 534: sản phẩm chưa có ảnh dùng ảnh outlet (giống màn Tạo menu)', () => {
  assert.equal(productDisplayImage({ image: null }, { image: 'outlets/mb.png' }), '/storage/outlets/mb.png')
  assert.equal(productDisplayImage({ image: '' }, { image_url: 'http://x/storage/outlets/mb.png' }), '/storage/outlets/mb.png')
})

test('Dòng 534: không có ảnh nào thì trả null để hiện icon mặc định', () => {
  assert.equal(productDisplayImage({ image: null }, { image: null }), null)
  assert.equal(productDisplayImage({}, null), null)
})

test('Dòng 534: ảnh outlet cho thanh outlet', () => {
  assert.equal(outletImage({ image: 'outlets/mb.png' }), '/storage/outlets/mb.png')
  assert.equal(outletImage({}), null)
})
