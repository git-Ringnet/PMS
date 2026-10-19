import test from 'node:test'
import assert from 'node:assert/strict'

// Test logic for Dòng 342: Extra Bed display logic when last night has quantity 0
function getRoomDisplayServices(room, sysDate = '2026-10-09') {
  const list = []
  const cleanDateStr = (d) => String(d || '').trim().substring(0, 10)

  // Giả lập lặp qua các đêm lưu trú của phòng
  const ci = new Date(room.arrivalDate || room.arrival_date)
  const co = new Date(room.departureDate || room.departure_date)
  const curr = new Date(ci)

  while (curr < co) {
    const yyyy = curr.getFullYear()
    const mm = String(curr.getMonth() + 1).padStart(2, '0')
    const dd = String(curr.getDate()).padStart(2, '0')
    const dStr = `${yyyy}-${mm}-${dd}`
    const isPastNight = sysDate ? (dStr < sysDate) : false

    // Kiểm tra xem phòng có cấu hình EB theo từng ngày (dailyExtraBeds hoặc services có EB) hay không
    const hasDailyEbConfig = (room.dailyExtraBeds && room.dailyExtraBeds.length > 0) ||
      (room.services && room.services.some(s => s.service_code === 'EB'))

    let hasEbConfig = false
    if (hasDailyEbConfig) {
      // Đã cấu hình theo từng ngày: Hiển thị dòng Giường phụ cho tất cả các đêm của chu kỳ lưu trú
      hasEbConfig = true
    } else {
      // Chưa cấu hình theo từng ngày: Dùng cấu hình chung của phòng cho tất cả các đêm
      hasEbConfig = Number(room.extraBedQty) > 0 && Number(room.extraBedPrice) > 0
    }

    if (!hasEbConfig) {
      curr.setDate(curr.getDate() + 1)
      continue
    }

    if (!isPastNight) {
      const daily = (room.dailyExtraBeds || []).find(d => cleanDateStr(d.dateStr || d.date) === dStr)
      const ebSvc = (room.services || []).find(s => s.service_code === 'EB' && cleanDateStr(s.service_date) === dStr)
      const defaultEbPrice = (room.extraBedPrice !== undefined && room.extraBedPrice !== null && room.extraBedPrice !== '') ? Number(room.extraBedPrice) : 300000
      if (daily) {
        const rate = (daily.rate !== undefined && daily.rate !== null && daily.rate !== '') ? Number(daily.rate) : defaultEbPrice
        list.push({
          id: `daily-eb-${room.id}-${dStr}`,
          service_date: dStr,
          service_name: 'Giường phụ (Extra Bed)',
          service_code: 'EB',
          quantity: Number(daily.quantity) || 0,
          rate: rate,
          total: (Number(daily.quantity) || 0) * rate,
        })
      } else if (ebSvc) {
        const rate = (ebSvc.rate !== undefined && ebSvc.rate !== null && ebSvc.rate !== '') ? Number(ebSvc.rate) : defaultEbPrice
        list.push({
          id: ebSvc.id,
          service_date: dStr,
          service_name: ebSvc.service_name || 'Giường phụ (Extra Bed)',
          service_code: 'EB',
          quantity: Number(ebSvc.quantity) || 0,
          rate: rate,
          total: (Number(ebSvc.quantity) || 0) * rate,
        })
      } else if (hasDailyEbConfig) {
        list.push({
          id: `daily-eb-${room.id}-${dStr}`,
          service_date: dStr,
          service_name: 'Giường phụ (Extra Bed)',
          service_code: 'EB',
          quantity: 0,
          rate: defaultEbPrice,
          total: 0,
        })
      } else if (Number(room.extraBedQty) > 0) {
        list.push({
          id: `room-eb-${room.id}-${dStr}`,
          service_date: dStr,
          service_name: 'Giường phụ (Extra Bed)',
          service_code: 'EB',
          quantity: Number(room.extraBedQty),
          rate: defaultEbPrice,
          total: Number(room.extraBedQty) * defaultEbPrice,
        })
      }
    }

    curr.setDate(curr.getDate() + 1)
  }

  return list
}

test('Dòng 342: Ngày cuối setup 0 kê giường -> tạo dòng Extra Bed với SL 0 và thành tiền 0', () => {
  const room = {
    id: 101,
    arrivalDate: '2026-10-09',
    departureDate: '2026-10-12', // 3 đêm: 09, 10, 11
    extraBedQty: 1, // max qty của phòng
    extraBedPrice: 300000,
    dailyExtraBeds: [
      { dateStr: '2026-10-09', quantity: 1, rate: 300000 },
      { dateStr: '2026-10-10', quantity: 1, rate: 300000 },
      { dateStr: '2026-10-11', quantity: 0, rate: 300000 } // Ngày cuối kê 0 giường
    ]
  }

  const services = getRoomDisplayServices(room, '2026-10-09')

  assert.equal(services.length, 3, 'Cả 3 đêm đều hiển thị dòng Giường phụ')
  assert.equal(services[0].service_date, '2026-10-09')
  assert.equal(services[0].quantity, 1)
  assert.equal(services[0].total, 300000)
  assert.equal(services[1].service_date, '2026-10-10')
  assert.equal(services[1].quantity, 1)
  assert.equal(services[1].total, 300000)

  const night3Eb = services.find(s => s.service_date === '2026-10-11')
  assert.notEqual(night3Eb, undefined, 'Đêm cuối 2026-10-11 có dòng Giường phụ')
  assert.equal(night3Eb.quantity, 0, 'Đêm cuối 2026-10-11 hiển thị đúng SL 0')
  assert.equal(night3Eb.total, 0, 'Thành tiền của đêm cuối bằng 0')
})

test('Dòng 343: Đồng bộ bookingCode vào URL và dọn sạch query param cũ (edit_id, booking_id, ...)', () => {
  let currentQuery = { tab: 'create-res', edit_id: 'BK00005', booking_code: 'BK00005' }

  function syncUrlBookingCode(bookingCode) {
    const nextQuery = { ...currentQuery }
    delete nextQuery.edit_id
    delete nextQuery.booking_code
    delete nextQuery.booking_id
    delete nextQuery.edit_code
    if (bookingCode) {
      nextQuery.tab = nextQuery.tab || 'create-res'
      nextQuery.bookingCode = String(bookingCode)
    } else {
      delete nextQuery.bookingCode
    }
    currentQuery = nextQuery
  }

  // Ban đầu URL có edit_id: BK00005
  assert.equal(currentQuery.edit_id, 'BK00005')

  // Chọn BK00003 từ kết quả tìm kiếm -> URL chỉ còn bookingCode: BK00003, không còn edit_id
  syncUrlBookingCode('BK00003')
  assert.deepEqual(currentQuery, { tab: 'create-res', bookingCode: 'BK00003' }, 'URL phải cập nhật thành bookingCode BK00003 và xóa sạch edit_id')

  // Khi tải lại trang F5, hệ thống sẽ đọc đúng BK00003
  assert.equal(currentQuery.bookingCode, 'BK00003')

  // Chuyển sang tạo mới booking (NEW_BOOKING)
  syncUrlBookingCode(null)
  assert.deepEqual(currentQuery, { tab: 'create-res' }, 'Tạo mới booking thì bookingCode bị xóa khỏi URL')
})

test('Modal Extra Bed: ngày 30/08 và 31/08 không kê giường (SL 0), ngày 01/09 và 02/09 kê 1 giường -> hiển thị đúng SL 0 và thành tiền 0 cho 30 và 31, tổng tiền 600,000 VND', () => {
  function cleanDate(d) {
    if (!d) return ''
    const s = String(d).trim()
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s
    if (s.includes('T') || s.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(s)) {
      const dt = new Date(s)
      if (!isNaN(dt.getTime())) {
        const year = dt.getFullYear()
        const month = String(dt.getMonth() + 1).padStart(2, '0')
        const day = String(dt.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
      }
    }
    return s.substring(0, 10)
  }

  function buildStayDates(checkInStr, checkOutStr) {
    const dates = []
    const cIn = cleanDate(checkInStr)
    const cOut = cleanDate(checkOutStr)
    if (!cIn) return [new Date().toISOString().split('T')[0]]

    const partsIn = cIn.split('-')
    let curr = partsIn.length === 3 
      ? new Date(Number(partsIn[0]), Number(partsIn[1]) - 1, Number(partsIn[2]))
      : new Date(cIn)

    const partsOut = cOut ? cOut.split('-') : []
    let end = partsOut.length === 3
      ? new Date(Number(partsOut[0]), Number(partsOut[1]) - 1, Number(partsOut[2]))
      : (cOut ? new Date(cOut) : new Date(curr.getTime() + 86400000))

    if (isNaN(curr.getTime())) curr = new Date()
    if (isNaN(end.getTime()) || curr >= end) {
      return [cIn || new Date().toISOString().split('T')[0]]
    }

    while (curr < end) {
      const yyyy = curr.getFullYear()
      const mm = String(curr.getMonth() + 1).padStart(2, '0')
      const dd = String(curr.getDate()).padStart(2, '0')
      dates.push(`${yyyy}-${mm}-${dd}`)
      curr.setDate(curr.getDate() + 1)
    }
    return dates.length ? dates : [cIn || new Date().toISOString().split('T')[0]]
  }

  // Dữ liệu từ backend: các record 0 giường bị backend lược bỏ, chỉ còn các đêm 01/09 và 02/09 có service ISO UTC
  const mockRoom = {
    roomNumber: '106',
    checkIn: '2026-08-30',
    checkOut: '2026-09-03', // 4 đêm: 30/08, 31/08, 01/09, 02/09
    extraBedQty: 1,
    extraBedPrice: 300000,
    dailyExtraBeds: [
      { dateStr: '2026-08-30', quantity: 0, rate: 300000 },
      { dateStr: '2026-08-31', quantity: 0, rate: 300000 },
      { dateStr: '2026-09-01', quantity: 1, rate: 300000 },
      { dateStr: '2026-09-02', quantity: 1, rate: 300000 }
    ],
    services: [
      // Giả lập service trả về từ backend với UTC timestamp: 17:00 ngày trước = 00:00 ngày sau ở VN (UTC+7)
      { service_code: 'EB', service_date: '2026-08-31T17:00:00.000000Z', quantity: 1, rate: 300000 },
      { service_code: 'EB', service_date: '2026-09-01T17:00:00.000000Z', quantity: 1, rate: 300000 }
    ]
  }

  const stayDates = buildStayDates(mockRoom.checkIn, mockRoom.checkOut)
  assert.deepEqual(stayDates, ['2026-08-30', '2026-08-31', '2026-09-01', '2026-09-02'])

  const existingDaily = mockRoom.dailyExtraBeds || []
  const ebServices = mockRoom.services ? mockRoom.services.filter(s => s.service_code === 'EB') : []
  const hasExplicitDaily = existingDaily.length > 0 || ebServices.length > 0

  const dailyRates = stayDates.map(dStr => {
    const found = existingDaily.find(ed => cleanDate(ed.dateStr || ed.date) === dStr)
    const foundSvc = ebServices.find(s => cleanDate(s.service_date || s.dateStr || s.date) === dStr)

    let q = 0
    let r = mockRoom.extraBedPrice

    if (found) {
      q = Number(found.quantity) || 0
      r = Number(found.rate) || 0
    } else if (foundSvc) {
      q = Number(foundSvc.quantity) || 0
      r = Number(foundSvc.rate) || 0
    } else if (!hasExplicitDaily) {
      q = mockRoom.extraBedQty
      r = mockRoom.extraBedPrice
    }

    return {
      dateStr: dStr,
      quantity: q,
      rate: r,
      total: q * r
    }
  })

  // Kiểm tra từng ngày:
  // Ngày 30/08: SL 0, Total 0
  assert.equal(dailyRates[0].dateStr, '2026-08-30')
  assert.equal(dailyRates[0].quantity, 0)
  assert.equal(dailyRates[0].total, 0)

  // Ngày 31/08: BẮT BUỘC SL 0, Total 0 (không bị gán nhầm thành 1 do ISO UTC)
  assert.equal(dailyRates[1].dateStr, '2026-08-31')
  assert.equal(dailyRates[1].quantity, 0)
  assert.equal(dailyRates[1].total, 0)

  // Ngày 01/09: SL 1, Total 300,000
  assert.equal(dailyRates[2].dateStr, '2026-09-01')
  assert.equal(dailyRates[2].quantity, 1)
  assert.equal(dailyRates[2].total, 300000)

  // Ngày 02/09: SL 1, Total 300,000
  assert.equal(dailyRates[3].dateStr, '2026-09-02')
  assert.equal(dailyRates[3].quantity, 1)
  assert.equal(dailyRates[3].total, 300000)

  // Tổng tiền trong modal
  const computedTotalSum = dailyRates.reduce((sum, d) => sum + d.total, 0)
  assert.equal(computedTotalSum, 600000, 'Tổng tiền trong modal phải là 600,000 VND (chỉ tính 2 đêm 01/09 và 02/09)')
})

test('Dòng 367: Xóa Extra Bed 1 ngày trong subtable -> đặt số lượng ngày đó về 0 và tính lại tổng tiền phòng', () => {
  const room = {
    id: 1,
    extraBedQty: 1,
    extraBedPrice: 300000,
    dailyExtraBeds: [
      { dateStr: '2026-08-31', quantity: 1, rate: 300000, total: 300000 },
      { dateStr: '2026-09-01', quantity: 1, rate: 300000, total: 300000 }
    ],
    services: [
      { id: 101, service_code: 'EB', service_name: 'Extra Bed', service_date: '2026-08-31', quantity: 1, rate: 300000 },
      { id: 102, service_code: 'EB', service_name: 'Extra Bed', service_date: '2026-09-01', quantity: 1, rate: 300000 }
    ]
  }

  // Giả lập logic inline delete EB cho ngày 2026-08-31
  const svcToDelete = room.services[0]
  const cleanDate = '2026-08-31'

  // 1. Cập nhật dailyExtraBeds
  const existingDeb = room.dailyExtraBeds.find(d => d.dateStr === cleanDate)
  if (existingDeb) {
    existingDeb.quantity = 0
    existingDeb.total = 0
  }

  // 2. Lọc bỏ khỏi services
  room.services = room.services.filter(s => !(s.service_code === 'EB' && s.service_date === cleanDate))

  // 3. Tính lại extraBedQty
  const activeDeb = room.dailyExtraBeds.filter(d => Number(d.quantity) > 0)
  room.extraBedQty = activeDeb.length > 0 ? Math.max(...activeDeb.map(d => d.quantity)) : 0

  assert.equal(existingDeb.quantity, 0, 'SL EB ngày 31/08 phải về 0')
  assert.equal(room.services.length, 1, 'Chỉ còn 1 dòng dịch vụ EB ngày 01/09')
  assert.equal(room.services[0].service_date, '2026-09-01')
  assert.equal(room.extraBedQty, 1, 'extraBedQty phòng vẫn là 1 do ngày 01/09 còn 1 EB')
})

test('Bảo toàn đơn giá 0 khi người dùng nhập 0đ cho Extra Bed hoặc Tiền phòng', () => {
  const room = {
    id: 102,
    arrivalDate: '2026-10-09',
    departureDate: '2026-10-11',
    extraBedQty: 1,
    extraBedPrice: 0, // Đơn giá cấu hình phòng là 0
    dailyExtraBeds: [
      { dateStr: '2026-10-09', quantity: 1, rate: 0 },
      { dateStr: '2026-10-10', quantity: 1, rate: 0 }
    ],
    services: [
      { id: 901, service_code: 'EB', service_date: '2026-10-09', quantity: 1, rate: 0, total_amount: 0 },
      { id: 902, service_code: 'EB', service_date: '2026-10-10', quantity: 1, rate: 0, total_amount: 0 }
    ]
  }

  const services = getRoomDisplayServices(room, '2026-10-09')
  assert.equal(services.length, 2)
  assert.equal(services[0].rate, 0, 'Đơn giá ngày 09 phải giữ nguyên 0đ, không bị gán fallback 300,000đ')
  assert.equal(services[0].total, 0, 'Thành tiền ngày 09 phải là 0đ')
  assert.equal(services[1].rate, 0, 'Đơn giá ngày 10 phải giữ nguyên 0đ')
  assert.equal(services[1].total, 0, 'Thành tiền ngày 10 phải là 0đ')
})

