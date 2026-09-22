import assert from 'node:assert/strict'
import test from 'node:test'
import { filterResidenceDeclarationRows } from '../src/utils/residence-declaration-filter.js'

// Dữ liệu mô phỏng đúng ví dụ nghiệp vụ của người dùng:
// - Phòng 405 ở từ 1/9 đến 5/9, ngày 3/9 chuyển đến phòng 301
// - Phòng 105 nhận ngày 9/8, cùng ngày 9/8 chuyển sang phòng 102
const mockGuests = [
  // 1. Phòng 405 (phòng gốc đã ở 2 đêm rồi chuyển ngày 3/9)
  {
    id: 1,
    ma: 'BK001',
    phong: '405',
    ten: 'Nguyễn Văn A',
    ngayDen: '01/09/2026',
    ngayDi: '03/09/2026',
    arrivalDate: '2026-09-01',
    departureDate: '2026-09-03',
    roomStatus: 100, // đã chuyển phòng
    isStayedThenMoved: true,
    isSameDayMoved: false,
    transferNote: ''
  },
  // 2. Phòng 301 (phòng mới nhận chuyển tới vào ngày 3/9)
  {
    id: 2,
    ma: 'BK001',
    phong: '301',
    ten: 'Nguyễn Văn A',
    ngayDen: '03/09/2026',
    ngayDi: '05/09/2026',
    arrivalDate: '2026-09-03',
    departureDate: '2026-09-05',
    roomStatus: 1, // in-house
    isStayedThenMoved: true,
    isSameDayMoved: false,
    transferNote: 'From Room 405, old arrival date: 01/09/2026'
  },
  // 3. Phòng 105 (nhận ngày 9/8, chuyển cùng ngày 9/8 sang phòng 102)
  {
    id: 3,
    ma: 'BK002',
    phong: '105',
    ten: 'Trần Thị B',
    ngayDen: '09/08/2026',
    ngayDi: '09/08/2026',
    arrivalDate: '2026-08-09',
    departureDate: '2026-08-09',
    roomStatus: 100, // đã chuyển phòng
    isStayedThenMoved: false,
    isSameDayMoved: true,
    transferNote: ''
  },
  // 4. Phòng 102 (phòng mới chuyển tới trong ngày 9/8)
  {
    id: 4,
    ma: 'BK002',
    phong: '102',
    ten: 'Trần Thị B',
    ngayDen: '09/08/2026',
    ngayDi: '10/08/2026',
    arrivalDate: '2026-08-09',
    departureDate: '2026-08-10',
    roomStatus: 1, // in-house
    isStayedThenMoved: false,
    isSameDayMoved: true,
    transferNote: 'From Room 105, old arrival date: 09/08/2026'
  }
]

test('Xem ngày 1/9: hiển thị khách của phòng 405, không hiển thị phòng 301', () => {
  const result = filterResidenceDeclarationRows(mockGuests, '01/09/2026', { roomMove: false, inHouse: false })
  
  assert.equal(result.length, 1)
  assert.equal(result[0].phong, '405')
  assert.equal(result[0].transferNote, '')
})

test('Xem ngày 3/9 (chưa chọn Phòng Chuyển): không hiển thị phòng 301, không hiển thị phòng 405', () => {
  const result = filterResidenceDeclarationRows(mockGuests, '03/09/2026', { roomMove: false, inHouse: false })
  
  assert.equal(result.length, 0)
})

test('Xem ngày 3/9 (CÓ chọn Phòng Chuyển): hiển thị phòng 301 kèm ghi chú chuyển phòng từ 405', () => {
  const result = filterResidenceDeclarationRows(mockGuests, '03/09/2026', { roomMove: true, inHouse: false })
  
  assert.equal(result.length, 1)
  assert.equal(result[0].phong, '301')
  assert.equal(result[0].transferNote, 'From Room 405, old arrival date: 01/09/2026')
})

test('Ngày check in = ngày chuyển phòng (9/8 chuyển 105 sang 102): chỉ hiển thị phòng vừa chuyển tới (102), ẩn phòng cũ (105 tình trạng 100)', () => {
  const result = filterResidenceDeclarationRows(mockGuests, '09/08/2026', { roomMove: false, inHouse: false })
  
  const roomNumbers = result.map(r => r.phong)
  assert.ok(roomNumbers.includes('102'), 'Phòng 102 phải hiển thị')
  assert.ok(!roomNumbers.includes('105'), 'Phòng cũ 105 (status 100) tuyệt đối không được hiển thị')
  
  const room102 = result.find(r => r.phong === '102')
  assert.equal(room102.transferNote, 'From Room 105, old arrival date: 09/08/2026')
})
