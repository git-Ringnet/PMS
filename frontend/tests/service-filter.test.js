import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

import { deletedServiceBills, filterServiceItems } from '../src/utils/service-filter.js'

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8')

const services = [
  { id: 1, serviceCode: 'MB', department: 'FO', folio: 3, serviceDate: '2026-08-30 07:52:00', isPaid: false },
  { id: 2, serviceCode: 'DO', department: 'FO', folio: 3, serviceDate: '2026-08-31 07:42:00', isPaid: false },
  { id: 3, serviceCode: 'DN', department: 'FO', folio: 2, serviceDate: '2026-09-04', isPaid: true, paymentCode: '34' },
  { id: 4, serviceCode: 'MB', department: 'HK', folio: 1, serviceDate: '2026-09-01', isPaid: false },
]
const ids = list => list.map(service => service.id)

test('lọc dịch vụ: tick nhiều dịch vụ, mỗi dịch vụ có folio riêng', () => {
  assert.deepEqual(ids(filterServiceItems(services, { displayFilter: 'all', services: [{ code: 'MB', folio: '' }] })), [1, 4])
  assert.deepEqual(ids(filterServiceItems(services, { displayFilter: 'all', services: [{ code: 'mb', folio: '3' }, { code: 'DO', folio: '' }] })), [1, 2])
})

test('lọc dịch vụ: theo bộ phận, ngày và trạng thái thanh toán', () => {
  assert.deepEqual(ids(filterServiceItems(services, { displayFilter: 'all', departments: ['HK'] })), [4])
  assert.deepEqual(ids(filterServiceItems(services, { displayFilter: 'paid' })), [3])
  assert.deepEqual(ids(filterServiceItems(services, { displayFilter: 'unpaid', startDate: '2026-08-31', endDate: '2026-09-01' })), [2, 4])
  // Không tick dịch vụ/bộ phận nào thì không lọc theo mục đó.
  assert.deepEqual(ids(filterServiceItems(services, { displayFilter: 'unpaid', services: [], departments: [] })), [1, 2, 4])
  assert.equal(filterServiceItems(services, null), services)
})

test('lọc theo ngày: ngày dạng UTC của Laravel tính theo giờ VN', () => {
  process.env.TZ = 'Asia/Ho_Chi_Minh'
  const rows = [{ id: 9, serviceCode: 'EB', serviceDate: '2026-09-01T17:00:00.000000Z' }]
  assert.deepEqual(ids(filterServiceItems(rows, { displayFilter: 'all', startDate: '2026-09-02', endDate: '2026-09-02' })), [9])
  assert.deepEqual(ids(filterServiceItems(rows, { displayFilter: 'all', startDate: '2026-09-01', endDate: '2026-09-01' })), [])
})

test('lọc "Đã xoá": lấy bill Edit=1 Status=3, bỏ dòng âm đối trừ và bill đã chuyển', () => {
  const bills = [
    { Ma: 10, Edit: 1, Status: 3, Amount: 200000 },
    { Ma: 11, Edit: 1, Status: 3, Amount: -200000, Pack1: '10' },
    { Ma: 12, Edit: 1, Status: 4, Amount: 50000 },
    { Ma: 13, Edit: 0, Status: 1, Amount: 50000 },
  ]
  assert.deepEqual(deletedServiceBills(bills).map(bill => bill.Ma), [10])
})

test('modal Lọc dịch vụ không còn dòng dịch vụ/bộ phận cố định', () => {
  const modal = read('../src/pages/frontdesk/components/FilterServiceModal.vue')
  assert.doesNotMatch(modal, />Minibar\/Phí Minibar</)
  assert.doesNotMatch(modal, />Reception\/ Lễ Tân</)
  assert.match(modal, /v-for="row in serviceRows"/)
  assert.match(modal, /v-for="row in departmentRows"/)
})
