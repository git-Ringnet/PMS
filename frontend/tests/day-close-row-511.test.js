import test from 'node:test'
import assert from 'node:assert/strict'
import { loadStatusOptions, saveStatusOptions, isInHouseForDayClose, alreadyRolledMessage } from '../src/utils/day-close-options.js'

function memoryStorage() {
  const data = new Map()
  return { getItem: k => (data.has(k) ? data.get(k) : null), setItem: (k, v) => data.set(k, String(v)) }
}

test('row 511: 2 ô chuyển tình trạng phòng giữ cố định theo từng user', () => {
  const storage = memoryStorage()
  assert.deepEqual(loadStatusOptions(storage, 'fom'), { occupiedToDirty: true, emptyToInspect: true }, 'mặc định bật cả hai')
  saveStatusOptions(storage, 'fom', { occupiedToDirty: false, emptyToInspect: true })
  assert.deepEqual(loadStatusOptions(storage, 'FOM'), { occupiedToDirty: false, emptyToInspect: true }, 'vào lại vẫn giữ lựa chọn bỏ check')
  assert.deepEqual(loadStatusOptions(storage, 'other'), { occupiedToDirty: true, emptyToInspect: true }, 'user khác không bị ảnh hưởng')
})

test('row 511: tab Phòng đang ở không lấy phòng có ngày đi = ngày hệ thống', () => {
  assert.equal(isInHouseForDayClose({ isCheckedIn: true, departureDate: '2026-09-10', systemDate: '2026-09-10' }), false)
  assert.equal(isInHouseForDayClose({ isCheckedIn: true, departureDate: '2026-09-11', systemDate: '2026-09-10' }), true)
  assert.equal(isInHouseForDayClose({ isCheckedIn: false, departureDate: '2026-09-11', systemDate: '2026-09-10' }), false)
})

test('row 511: thông báo nêu thời điểm sang ngày gần nhất', () => {
  assert.equal(
    alreadyRolledMessage('2026-11-10 09:05:33'),
    'Hệ thống đã phát hiện lần gần nhất được sang ngày vào lúc 09:05 ngày 10/11/2026. Bạn có muốn tiếp tục sang ngày không?'
  )
})

test('row 516: báo cáo dự kiến doanh thu mở trong module Lễ tân đang xem', async () => {
  const { revenueReportRoute } = await import('../src/utils/day-close-options.js')
  assert.deepEqual(revenueReportRoute('/frontdesk'), { path: '/frontdesk', query: { tab: 'reports', report: 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT' } })
  assert.equal(revenueReportRoute('/reports').path, '/frontdesk')
})
