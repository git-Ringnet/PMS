import test from 'node:test'
import assert from 'node:assert/strict'

import {
  compareRooms,
  getStatusText,
  buildWorksheetSheets,
  buildSupervisorRooms,
} from '../src/utils/hk-room-status.js'

// Cấu hình ký hiệu giống bảng hk_symbols
const SYMBOLS = {
  booking: {
    reserved: { code: 'CI' },
    checkout: { code: 'CO' },
    lco: { code: 'LCO' },
    occupied: { code: '' },
  },
  hk: {
    occupied_dirty: { code: 'OD' },
    occupied_clean: { code: 'OC' },
    vacant_clean: { code: 'VC' },
    vacant_dirty: { code: 'VD' },
  },
  extra: { eb: { code: 'EB' } },
}

// ── 1. Back-to-back ───────────────────────────────────────────
test('Dòng 357: phòng back-to-back hiển thị CI, OD, CO (không còn CO, OD)', () => {
  assert.equal(getStatusText(SYMBOLS, 'occupied_dirty', 'checkout', { isBackToBack: true, extraBedQty: 0 }), 'CI, OD, CO')
})

test('Dòng 357: phòng chỉ trả trong ngày vẫn là CO, OD', () => {
  assert.equal(getStatusText(SYMBOLS, 'occupied_dirty', 'checkout', { isBackToBack: false, extraBedQty: 0 }), 'CO, OD')
})

test('Dòng 357: phòng đang ở (không có ký hiệu booking) chỉ hiện tình trạng buồng', () => {
  assert.equal(getStatusText(SYMBOLS, 'occupied_clean', 'occupied', { isBackToBack: false }), 'OC')
})

test('Dòng 357: phòng chuẩn bị nhận trong ngày hiện CI, VC', () => {
  assert.equal(getStatusText(SYMBOLS, 'vacant_clean', 'reserved', null), 'CI, VC')
})

// ── 2. Giường phụ ─────────────────────────────────────────────
test('Dòng 357: phòng có 1 giường phụ thêm EB', () => {
  assert.equal(getStatusText(SYMBOLS, 'occupied_dirty', 'occupied', { extraBedQty: 1 }), 'OD, EB')
})

test('Dòng 357: phòng có 2 giường phụ hiện EB x2', () => {
  assert.equal(getStatusText(SYMBOLS, 'occupied_dirty', 'occupied', { extraBedQty: 2 }), 'OD, EB x2')
})

test('Dòng 357: phòng back-to-back có giường phụ hiện CI, OD, CO, EB', () => {
  assert.equal(getStatusText(SYMBOLS, 'occupied_dirty', 'checkout', { isBackToBack: true, extraBedQty: 1 }), 'CI, OD, CO, EB')
})

test('Dòng 357: không có cấu hình ký hiệu EB vẫn hiện EB', () => {
  const noEb = { ...SYMBOLS, extra: {} }
  assert.equal(getStatusText(noEb, 'vacant_clean', '', { extraBedQty: 1 }), 'VC, EB')
})

// ── 3. Sắp xếp theo tầng ──────────────────────────────────────
test('Dòng 357: sắp xếp theo tầng (2 trước 10), rồi theo số phòng', () => {
  const rooms = [
    { floor: 10, room_number: '1002' },
    { floor: 2, room_number: '205' },
    { floor: 10, room_number: '1001' },
    { floor: 1, room_number: '101' },
    { floor: 2, room_number: '201' },
  ]
  assert.deepEqual(rooms.sort(compareRooms).map(r => r.room_number), ['101', '201', '205', '1001', '1002'])
})

test('Dòng 357: thiếu tầng thì sắp theo số phòng tự nhiên', () => {
  const rooms = [{ room_number: '1001' }, { room_number: '201' }, { room_number: '101' }]
  assert.deepEqual(rooms.sort(compareRooms).map(r => r.room_number), ['101', '201', '1001'])
})

// ── Dữ liệu chung cho in lịch ─────────────────────────────────
const ROOMS = new Map([
  [1, { id: 1, room_number: '101', floor: 1 }],
  [2, { id: 2, room_number: '102', floor: 1 }],
  [3, { id: 3, room_number: '201', floor: 2 }],
  [4, { id: 4, room_number: '202', floor: 2 }],
  [5, { id: 5, room_number: '301', floor: 3 }],
])
const GROUPS = [
  { id: 'A', staff_list: [{ name: 'Ngô Thị Thanh' }, { name: 'Đinh Văn Minh' }], rooms: [{ room_id: 1, room_number: '101' }, { room_id: 2, room_number: '102' }] },
  { id: 'B', staff_list: [{ name: 'Nguyễn Thị Lan' }], rooms: [{ room_id: 3, room_number: '201' }] },
]
const ROOM_GROUP_MAP = { 1: 'A', 2: 'A', 3: 'B' }

// ── 4. In lịch nhân viên ──────────────────────────────────────
test('Dòng 357: chọn phòng chưa phân công thì in đúng các phòng đó, Housekeeping Attendant để trống', () => {
  const sheets = buildWorksheetSheets(GROUPS, new Set(), new Set([4, 5]), ROOM_GROUP_MAP, ROOMS)
  assert.equal(sheets.length, 1)
  assert.equal(sheets[0].staffNames, '')
  assert.deepEqual(sheets[0].rooms.map(r => r.room.room_number), ['202', '301'])
  assert.ok(sheets[0].rooms.every(r => r.source === 'list'))
})

test('Dòng 357: chọn lẫn phòng đã và chưa phân công thì in theo nhóm + tờ phòng chưa phân công', () => {
  const sheets = buildWorksheetSheets(GROUPS, new Set(), new Set([2, 5]), ROOM_GROUP_MAP, ROOMS)
  assert.deepEqual(sheets.map(s => s.staffNames), ['Ngô Thị Thanh / Đinh Văn Minh', ''])
  assert.deepEqual(sheets[0].rooms.map(r => r.room.room_number), ['102'])
  assert.deepEqual(sheets[1].rooms.map(r => r.room.room_number), ['301'])
})

test('Dòng 357: tích In lịch ở nhóm thì in nhóm đó, kèm phòng chưa phân công đang chọn', () => {
  const sheets = buildWorksheetSheets(GROUPS, new Set(['B']), new Set([5]), ROOM_GROUP_MAP, ROOMS)
  assert.deepEqual(sheets.map(s => s.staffNames), ['Nguyễn Thị Lan', ''])
})

test('Dòng 357: không chọn gì thì in tất cả nhóm như trước', () => {
  const sheets = buildWorksheetSheets(GROUPS, new Set(), new Set(), ROOM_GROUP_MAP, ROOMS)
  assert.equal(sheets.length, 2)
})

// ── 5. Mẫu Supervisor ─────────────────────────────────────────
test('Dòng 357: mẫu Supervisor in được từ lịch phân công (tình trạng theo lịch phân công)', () => {
  const rooms = buildSupervisorRooms(GROUPS, new Set(['A']), new Set(), ROOMS)
  assert.deepEqual(rooms.map(r => r.room.room_number), ['101', '102'])
  assert.ok(rooms.every(r => r.source === 'group'))
})

test('Dòng 357: mẫu Supervisor in từ danh sách phòng (tình trạng theo danh sách phòng)', () => {
  const rooms = buildSupervisorRooms(GROUPS, new Set(), new Set([4, 5]), ROOMS)
  assert.deepEqual(rooms.map(r => r.room.room_number), ['202', '301'])
  assert.ok(rooms.every(r => r.source === 'list'))
})

test('Dòng 357: chọn cả hai nơi thì phòng trùng lấy theo lịch phân công, không in hai lần', () => {
  const rooms = buildSupervisorRooms(GROUPS, new Set(['A']), new Set([1, 5]), ROOMS)
  assert.equal(rooms.length, 3)
  assert.equal(rooms.find(r => r.room.room_number === '101' || r.room.id === 1).source, 'group')
  assert.equal(rooms.find(r => r.room.id === 5).source, 'list')
})

test('Dòng 357: không chọn gì thì mẫu Supervisor không có phòng để in', () => {
  assert.equal(buildSupervisorRooms(GROUPS, new Set(), new Set(), ROOMS).length, 0)
})
