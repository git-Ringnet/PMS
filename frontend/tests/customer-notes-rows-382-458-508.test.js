import test from 'node:test'
import assert from 'node:assert/strict'
import { extraBedBillRow } from '../src/utils/booking-room-extra-bed-bills.js'
import { cancelMirrorIds } from '../src/utils/service-cancel-selection.js'
import { noshowChargeNights } from '../src/utils/noshow-charge.js'

test('row 382: dòng EB đã post lấy tên danh mục, không lấy mô tả hóa đơn', () => {
  const bill = { Ma: 1, ServiceId: 'EB', Quantity: 1, Amount: 300000, RentalRoomId2: 'G1', DescriptionServive: 'Phụ thu thêm giường - Phòng 707 (BK_24=>R_708)' }
  assert.equal(extraBedBillRow(bill, '2026-08-31', 'Extrabed/Thêm Giường').service_name, 'Extrabed/Thêm Giường')
})

test('row 382: không có tên danh mục thì vẫn còn mô tả bill', () => {
  const bill = { Ma: 1, ServiceId: 'EB', Quantity: 1, Amount: 300000, DescriptionServive: 'Phụ thu thêm giường' }
  assert.equal(extraBedBillRow(bill, '2026-08-31').service_name, 'Phụ thu thêm giường')
})

test('row 458: chọn hết dòng đang hiện của bill thì xóa theo bill, không gửi mirror', () => {
  const rows = [{ serviceBillId: 1, bookingRoomServiceId: 11 }, { serviceBillId: 2, bookingRoomServiceId: 22 }]
  assert.deepEqual(cancelMirrorIds(rows, rows), [])
})

test('row 458: chỉ chọn một phần bill thì vẫn gửi mirror để server chặn', () => {
  const first = { serviceBillId: 1, bookingRoomServiceId: 11 }
  const second = { serviceBillId: 1, bookingRoomServiceId: 12 }
  assert.deepEqual(cancelMirrorIds([first], [first, second]), [11])
})

test('row 508: chọn từ ngày đến ngày tính cả hai đầu', () => {
  assert.equal(noshowChargeNights('2026-09-03', '2026-09-04'), 2)
  assert.equal(noshowChargeNights('2026-09-03', '2026-09-03'), 1)
  assert.equal(noshowChargeNights('2026-08-31', '2026-09-02'), 3)
})

test('row 508: khoảng ngày sai hoặc thiếu không tính đêm nào', () => {
  assert.equal(noshowChargeNights('2026-09-04', '2026-09-03'), 0)
  assert.equal(noshowChargeNights('', '2026-09-03'), 0)
})
