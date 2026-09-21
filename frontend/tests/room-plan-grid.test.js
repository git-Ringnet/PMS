import test from 'node:test'
import assert from 'node:assert/strict'

function filterGridBookings(allBookings, visibleStartDateStr, visibleEndDateStr) {
  return allBookings.filter(bk => {
    const checkInDate = new Date(bk.checkIn)
    const checkOutDate = new Date(bk.checkOut)
    const formatDateStr = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    const checkInDateStr = formatDateStr(checkInDate)
    const checkOutDateStr = formatDateStr(checkOutDate)
    const isDayUse = Boolean(bk.isDayUse || checkInDateStr === checkOutDateStr)
    const isLockItem = bk.code === 'LOCK'

    if (isLockItem) {
      const visibleStart = new Date(visibleStartDateStr + 'T00:00:00')
      const visibleEnd = new Date(visibleEndDateStr + 'T23:59:59')
      return !(checkOutDate < visibleStart || checkInDate > visibleEnd)
    }
    if (isDayUse) {
      return !(checkInDateStr < visibleStartDateStr || checkInDateStr > visibleEndDateStr)
    }
    // Overnight stay: if check-out date is on or before visible grid start, skip it
    return !(checkOutDateStr <= visibleStartDateStr || checkInDateStr > visibleEndDateStr)
  })
}

test('Room Plan grid: when grid starts on 2026-08-09, both Booking 1 (out 10th) and Booking 5 (in 10th) are visible', () => {
  const realBookings = [
    { code: 'GAL1', room: '105', checkIn: '2026-08-09 14:00', checkOut: '2026-08-10 12:00', isDayUse: false },
    { code: 'GAL5', room: '105', checkIn: '2026-08-10 14:00', checkOut: '2026-08-14 12:00', isDayUse: false },
  ]

  const visible = filterGridBookings(realBookings, '2026-08-09', '2026-08-15')
  assert.equal(visible.length, 2)
  assert.equal(visible[0].code, 'GAL1')
  assert.equal(visible[1].code, 'GAL5')
})

test('Room Plan grid: when grid starts on 2026-08-10, Booking 1 (checked out on 10th) is omitted and does not overlap Booking 5', () => {
  const realBookings = [
    { code: 'GAL1', room: '105', checkIn: '2026-08-09 14:00', checkOut: '2026-08-10 12:00', isDayUse: false },
    { code: 'GAL5', room: '105', checkIn: '2026-08-10 14:00', checkOut: '2026-08-14 12:00', isDayUse: false },
  ]

  const visible = filterGridBookings(realBookings, '2026-08-10', '2026-08-15')
  assert.equal(visible.length, 1)
  assert.equal(visible[0].code, 'GAL5')
})
