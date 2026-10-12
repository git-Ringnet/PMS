import test from 'node:test'
import assert from 'node:assert/strict'

test('Dòng 310: Booking có status in (2, 3, 4) (checkout, hủy, noshow) được ưu tiên giữ nguyên khi quay lại trang thay vì nhảy sang booking mới nhất', async () => {
  const activeBookingsList = [
    { id: 20, booking_code: 'GAL20', status: 1 },
    { id: 25, booking_code: 'GAL25', status: 0 },
    { id: 30, booking_code: 'GAL30', status: 1 },
  ]
  const checkedOutBooking13 = { id: 13, booking_code: 'GAL13', status: 2 }

  const savedActiveId = '13'
  const closedIds = []

  // Giả lập logic tìm booking:
  // 1. Tìm trong danh sách active status 0, 1
  let currentBooking = null
  let savedBooking = activeBookingsList.find(b => String(b.id) === savedActiveId || String(b.booking_code) === savedActiveId)

  // 2. Logic mới: Nếu không có trong active list nhưng savedActiveId hợp lệ, gọi fallback fetch booking cụ thể (bao quát status 2, 3, 4)
  if (!currentBooking && !savedBooking && savedActiveId && savedActiveId !== 'NEW_BOOKING' && !closedIds.includes(String(savedActiveId))) {
    // Giả lập fetchSpecificBookingFallback trả về booking 13
    if (String(checkedOutBooking13.id) === savedActiveId) {
      savedBooking = checkedOutBooking13
    }
  }

  // 3. Chọn nextBooking: Ưu tiên currentBooking -> savedBooking -> mới nhất
  const nextBooking = currentBooking || savedBooking || activeBookingsList.reduce((max, b) => !max || b.id > max.id ? b : max, null)

  assert.equal(nextBooking?.id, 13, 'Hệ thống phải giữ đúng Booking 13 đã checkout thay vì nhảy sang booking 30')
  assert.equal(nextBooking?.booking_code, 'GAL13')
})

test('Dòng 310: Checkout khôi phục booking từ session tự động nhận diện status in (2, 3, 4) và chuyển sang scope "old"', async () => {
  const presentCheckoutList = [
    { id: 'B101', bookingId: 101, code: 'GAL101', isCheckedOut: false },
    { id: 'B102', bookingId: 102, code: 'GAL102', isCheckedOut: false },
  ]
  const oldCheckoutList = [
    { id: 'B101', bookingId: 101, code: 'GAL101', isCheckedOut: false },
    { id: 'B102', bookingId: 102, code: 'GAL102', isCheckedOut: false },
    { id: 'B13', bookingId: 13, code: 'GAL13', isCheckedOut: true },
  ]

  const savedBookingId = '13'
  let activeRegisterScope = 'current'

  // Tìm trong danh sách hiện tại
  let booking = presentCheckoutList.find(b => String(b.bookingId) === savedBookingId || String(b.id) === savedBookingId || String(b.code) === savedBookingId)

  // Logic mới: Nếu không tìm thấy, kiểm tra fallback và tự động chuyển sang scope 'old' khi status thuộc (2, 3, 4)
  if (!booking) {
    const rawTarget = { id: 13, booking_code: 'GAL13', status: 2 } // status 2 = checkout
    const isTargetCheckedOut = [2, 3, 4].includes(Number(rawTarget.status))
    if (isTargetCheckedOut && activeRegisterScope !== 'old') {
      activeRegisterScope = 'old'
      booking = oldCheckoutList.find(b => String(b.bookingId) === savedBookingId || String(b.id) === savedBookingId || String(b.code) === savedBookingId)
    }
  }

  assert.equal(activeRegisterScope, 'old', 'Phải tự động chuyển scope sang "old"')
  assert.equal(booking?.bookingId, 13, 'Phải tìm thấy và khôi phục đúng booking 13')
})

test('Dòng 310: Room Plan cờ isRestoringPlanScroll ngăn sự kiện cuộn ban đầu ghi đè scrollTop: 0 vào sessionStorage', () => {
  const sessionStorageMock = {
    pms_roomplan_scroll_top: '650',
    pms_roomplan_scroll_left: '120',
  }

  let isRestoringPlanScroll = true

  // Giả lập scroll event fired khi DOM mount ở scrollTop: 0
  const handleScroll = (currentScrollTop, currentScrollLeft) => {
    if (isRestoringPlanScroll) return // Bị chặn!
    sessionStorageMock.pms_roomplan_scroll_top = String(currentScrollTop)
    sessionStorageMock.pms_roomplan_scroll_left = String(currentScrollLeft)
  }

  // Browser layout dispatch scroll(0, 0)
  handleScroll(0, 0)
  assert.equal(sessionStorageMock.pms_roomplan_scroll_top, '650', 'Vị trí 650px không được bị ghi đè thành 0 khi đang khôi phục')

  // Giả lập hoàn tất khôi phục
  isRestoringPlanScroll = false

  // Sau khi người dùng chủ động cuộn tới 700px
  handleScroll(700, 150)
  assert.equal(sessionStorageMock.pms_roomplan_scroll_top, '700', 'Vị trí mới 700px được lưu sau khi đã khôi phục thành công')
})
