import test from 'node:test'
import assert from 'node:assert/strict'

import { zeroNightArrivalItems, zeroNightArrivalMessage } from '../src/utils/noshow-one-day.js'

test('Dòng 529: phòng đến có 0 đêm (đến = đi = ngày hệ thống) bị chặn noshow one day', () => {
  const items = [
    { roomNumber: '101', nights: 0 },
    { roomNumber: '102', nights: 2 },
    { roomNumber: '103', nights: 1 },
    { roomNumber: '104', nights: 0 },
  ]
  assert.deepEqual(zeroNightArrivalItems(items).map(i => i.roomNumber), ['101', '104'])
})

test('Dòng 529: toàn phòng từ 1 đêm trở lên thì không bị chặn', () => {
  assert.deepEqual(zeroNightArrivalItems([{ roomNumber: '102', nights: 3 }, { roomNumber: '103', nights: 1 }]), [])
})

test('Dòng 529: thông báo nêu số phòng và hướng dẫn dùng Noshow (by stage)', () => {
  const message = zeroNightArrivalMessage([{ roomNumber: '101', nights: 0 }, { roomNumber: 'Chưa gán', nights: 0 }])
  assert.match(message, /101/)
  assert.match(message, /Chưa gán/)
  assert.match(message, /Noshow/)
  assert.match(message, /by stage/)
})
