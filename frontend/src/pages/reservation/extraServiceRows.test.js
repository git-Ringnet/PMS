import assert from 'node:assert/strict'
import { test } from 'node:test'
import { buildAddServicePayloads, resolvePostedServicePlan } from './extraServiceRows.js'

const tour = { service_code: 'TOUR', service_name: 'Tour', quantity: 1, rate: 200000, is_room: true }
const dinner = { service_code: 'DINNER', service_name: 'Ăn tối', quantity: 1, rate: 500000, is_room: true }
const roomDates = ['2026-10-30', '2026-10-31', '2026-11-01']

test('row 351: lưu modal chỉ thêm dịch vụ vừa chọn, không gửi lại dịch vụ cũ', () => {
  // Phòng đã có Tour; lần 2 chỉ chọn Ăn tối cho đêm cuối.
  const payloads = buildAddServicePayloads({
    items: [dinner], checkedDates: ['2026-11-01'], roomDates, today: '2026-10-30',
  })
  assert.deepEqual(payloads, [{
    service_code: 'DINNER', service_name: 'Ăn tối', service_date: '2026-11-01', quantity: 1, rate: 500000, is_room: 1,
  }])
  assert.equal(payloads.some(p => p.service_code === 'TOUR'), false)
})

test('row 351: thêm cho tất cả các đêm từ ngày hệ thống, bỏ ngày quá khứ và ngày ngoài giai đoạn ở', () => {
  const payloads = buildAddServicePayloads({
    items: [tour], checkedDates: ['2026-10-29', ...roomDates, '2026-11-02'], roomDates, today: '2026-10-31',
  })
  assert.deepEqual(payloads.map(p => p.service_date), ['2026-10-31', '2026-11-01'])
})

test('row 351: bỏ qua dịch vụ bị skip (phụ thu ăn sáng trẻ khi phòng không có trẻ)', () => {
  const payloads = buildAddServicePayloads({
    items: [tour, { ...dinner, service_code: 'BFC' }], checkedDates: ['2026-10-30'], roomDates, today: '2026-10-30',
    skip: item => item.service_code === 'BFC',
  })
  assert.deepEqual(payloads.map(p => p.service_code), ['TOUR'])
})

test('row 351: không chọn dịch vụ nào thì không gửi gì (không xóa dịch vụ cũ)', () => {
  assert.deepEqual(buildAddServicePayloads({ items: [], checkedDates: roomDates, roomDates, today: '2026-10-30' }), [])
})

const dateOf = s => s.service_date

test('row 351: dịch vụ đã post + dòng giá mới cùng ngày gộp thành một dòng', () => {
  const posted = { id: 1, service_code: 'TOUR', service_date: '2026-10-30', rate: 200000, is_posted: 1, service_bill_id: 99 }
  const plan = { id: 2, service_code: 'TOUR', service_date: '2026-10-30', rate: 300000, is_posted: 0, service_bill_id: null }
  const services = [posted, plan]

  assert.deepEqual(resolvePostedServicePlan(posted, services, dateOf), { hidden: false, pendingPlan: plan })
  assert.deepEqual(resolvePostedServicePlan(plan, services, dateOf), { hidden: true, pendingPlan: null })
})

test('row 351: dịch vụ chưa post bình thường vẫn hiển thị, không có giá chờ', () => {
  const tourDay1 = { id: 3, service_code: 'TOUR', service_date: '2026-10-30', rate: 200000, is_posted: 0 }
  const tourDay2 = { id: 4, service_code: 'TOUR', service_date: '2026-10-31', rate: 200000, is_posted: 0 }
  const dinnerDay1 = { id: 5, service_code: 'DINNER', service_date: '2026-10-30', rate: 500000, is_posted: 1, service_bill_id: 7 }
  const services = [tourDay1, tourDay2, dinnerDay1]

  assert.deepEqual(resolvePostedServicePlan(tourDay1, services, dateOf), { hidden: false, pendingPlan: null })
  assert.deepEqual(resolvePostedServicePlan(dinnerDay1, services, dateOf), { hidden: false, pendingPlan: null })
})
