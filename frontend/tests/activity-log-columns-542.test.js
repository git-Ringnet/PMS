import test from 'node:test'
import assert from 'node:assert/strict'
import {
  ACTIVITY_LOG_COLUMNS,
  ACTIVITY_LOG_SETTINGS_KEY,
  buildActivityLogColumns,
  moveActivityLogColumn,
  visibleActivityLogColumns,
} from '../src/utils/activity-log-columns.js'

const keys = columns => columns.map(col => col.key)

test('row 542: mặc định đủ 11 cột theo thứ tự hiện tại, đều hiện', () => {
  const columns = buildActivityLogColumns(null, null)
  assert.deepEqual(keys(columns), ['id', 'time', 'userAgent', 'screen', 'user', 'date', 'action', 'regCode', 'roomCode', 'description', 'detail'])
  assert.ok(columns.every(col => col.visible))
  assert.equal(ACTIVITY_LOG_SETTINGS_KEY, 'activity_log')
})

test('row 542: áp thứ tự và ẩn/hiện đã lưu của user', () => {
  const columns = buildActivityLogColumns(['description', 'id', 'roomCode'], { userAgent: false, id: true })
  assert.deepEqual(keys(columns).slice(0, 3), ['description', 'id', 'roomCode'])
  assert.equal(columns.find(col => col.key === 'userAgent').visible, false)
  assert.equal(columns.length, ACTIVITY_LOG_COLUMNS.length)
})

test('row 542: key lạ bị bỏ, cột mới nối vào cuối', () => {
  const columns = buildActivityLogColumns(['bogus', 'roomCode'], {})
  assert.equal(columns[0].key, 'roomCode')
  assert.ok(!keys(columns).includes('bogus'))
})

test('row 542: ẩn hết cột thì trả về mặc định để bảng không trống', () => {
  const hideAll = Object.fromEntries(ACTIVITY_LOG_COLUMNS.map(col => [col.key, false]))
  const columns = buildActivityLogColumns(null, hideAll)
  assert.equal(visibleActivityLogColumns(columns).length, ACTIVITY_LOG_COLUMNS.length)
})

test('row 542: kéo thả đổi chỗ cột, không đổi mảng gốc', () => {
  const columns = buildActivityLogColumns(null, null)
  const moved = moveActivityLogColumn(columns, 'roomCode', 'id')
  assert.deepEqual(keys(moved).slice(0, 2), ['roomCode', 'id'])
  assert.deepEqual(keys(columns).slice(0, 2), ['id', 'time'])
  assert.deepEqual(keys(moveActivityLogColumn(columns, 'id', 'id')), keys(columns))
  assert.deepEqual(keys(moveActivityLogColumn(columns, 'nope', 'id')), keys(columns))
})

test('row 542: chỉ lấy các cột đang hiện, giữ thứ tự', () => {
  const columns = buildActivityLogColumns(['roomCode', 'id'], { id: false })
  assert.equal(keys(visibleActivityLogColumns(columns))[0], 'roomCode')
  assert.ok(!keys(visibleActivityLogColumns(columns)).includes('id'))
})

import { buildActivityLogCsv } from '../src/utils/activity-log-columns.js'

const fmt = {
  time: log => log.created_time || '10:00:00',
  date: log => log.created_date || '01/09/2026',
  action: log => ({ Add: 'Thêm' }[log.action] || log.action),
}
const sampleLog = { id: 7, user_agent: 'Chrome "x"', component: 'Booking', user_name: 'thao', action: 'Add', booking_code: 'GAL1', room_number: 'G1, G2', description: 'Nội dung "A"' }

test('row 542: xuất Excel mặc định giữ đúng 10 cột và thứ tự như trước', () => {
  const csv = buildActivityLogCsv([sampleLog], buildActivityLogColumns(null, null), fmt).split('\n')
  assert.equal(csv[0], 'ID,Thời gian,Trình duyệt,Màn hình,Người dùng,Ngày,Hành động,Mã đăng ký,Mã phòng,Mô tả')
  assert.equal(csv[1], '7,"10:00:00","Chrome ""x""","Booking","thao","01/09/2026","Thêm","GAL1","G1, G2","Nội dung ""A"""')
})

test('row 542: xuất Excel theo cột đang hiện và thứ tự user đã chọn', () => {
  const columns = buildActivityLogColumns(['roomCode', 'id', 'description'], { userAgent: false, time: false, screen: false })
  const csv = buildActivityLogCsv([sampleLog], columns, fmt).split('\n')
  assert.equal(csv[0], 'Mã phòng,ID,Mô tả,Người dùng,Ngày,Hành động,Mã đăng ký')
  assert.equal(csv[1], '"G1, G2",7,"Nội dung ""A""","thao","01/09/2026","Thêm","GAL1"')
})

test('row 542: chỉ còn cột Chi Tiết (không xuất được) thì xuất đủ cột mặc định', () => {
  const only = Object.fromEntries(ACTIVITY_LOG_COLUMNS.map(col => [col.key, col.key === 'detail']))
  const csv = buildActivityLogCsv([sampleLog], buildActivityLogColumns(null, only), fmt).split('\n')
  assert.equal(csv[0].split(',').length, 10)
})
