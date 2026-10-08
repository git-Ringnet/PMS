import test from 'node:test'
import assert from 'node:assert/strict'

function computeQuickUpdatePermissions({ currentModule, allowSaleInhouseRateDeparture, targetRooms }) {
  const hasCheckedInRoom = targetRooms.some(r => Number(r.status) === 1 || r.status === 'Checked In')

  const canEditInhouseRateDeparture = (
    currentModule === 'SALE'
      ? allowSaleInhouseRateDeparture === true
      : currentModule === 'FO'
  )

  const isArrivalDisabled = hasCheckedInRoom
  const isDepartureDisabled = hasCheckedInRoom && !canEditInhouseRateDeparture
  const isRateDisabled = hasCheckedInRoom && !canEditInhouseRateDeparture

  return {
    hasCheckedInRoom,
    canEditInhouseRateDeparture,
    isArrivalDisabled,
    isDepartureDisabled,
    isRateDisabled
  }
}

function parseStartDate(startDate) {
  if (!startDate) return null
  if (startDate instanceof Date) return startDate
  const parts = String(startDate).split('-')
  if (parts.length === 3) {
    return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10))
  }
  return null
}

test('Section 10: Inhouse room with AllowReserUpdateRate_DeptDateRoomInhouse = 0 locks departure date and rate', () => {
  const result = computeQuickUpdatePermissions({
    currentModule: 'SALE',
    allowSaleInhouseRateDeparture: false,
    targetRooms: [{ id: 105, status: 1, roomNumber: '105' }]
  })

  assert.equal(result.hasCheckedInRoom, true)
  assert.equal(result.canEditInhouseRateDeparture, false)
  assert.equal(result.isArrivalDisabled, true, 'Ngày nhận phòng phải bị khóa')
  assert.equal(result.isDepartureDisabled, true, 'Ngày trả phòng phải bị khóa khi cấu hình = 0')
  assert.equal(result.isRateDisabled, true, 'Giá phòng phải bị khóa khi cấu hình = 0')
})

test('Section 10: Inhouse room with AllowReserUpdateRate_DeptDateRoomInhouse = 1 allows departure date and rate', () => {
  const result = computeQuickUpdatePermissions({
    currentModule: 'SALE',
    allowSaleInhouseRateDeparture: true,
    targetRooms: [{ id: 105, status: 1, roomNumber: '105' }]
  })

  assert.equal(result.hasCheckedInRoom, true)
  assert.equal(result.canEditInhouseRateDeparture, true)
  assert.equal(result.isArrivalDisabled, true, 'Ngày nhận phòng vẫn luôn bị khóa với Inhouse')
  assert.equal(result.isDepartureDisabled, false, 'Ngày trả phòng được phép sửa khi cấu hình = 1')
  assert.equal(result.isRateDisabled, false, 'Giá phòng được phép sửa khi cấu hình = 1')
})

test('Section 10: SingleDatePicker startDate parses systemDate correctly to focus calendar', () => {
  const parsed = parseStartDate('2026-08-30')
  assert.notEqual(parsed, null)
  assert.equal(parsed.getFullYear(), 2026)
  assert.equal(parsed.getMonth(), 7) // 0-indexed: August
  assert.equal(parsed.getDate(), 30)

  assert.equal(parseStartDate(null), null)
  assert.equal(parseStartDate(''), null)
})
