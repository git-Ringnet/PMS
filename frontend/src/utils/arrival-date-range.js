// Khoảng ngày đến của ô "Tìm kiếm toàn hệ thống" (checklist dòng 95).
// Khi ngày vừa chọn làm khoảng ngày bị ngược (từ > đến) thì kéo ô còn lại theo NGAY LÚC chọn,
// để lần tìm kiếm đầu tiên đã dùng đúng khoảng ngày (trước đây phải chọn thêm ngày kia mới ra dữ liệu).
export function normalizeArrivalRange(from, to, changed) {
  const range = { from: from || '', to: to || '' }
  if (!range.from || !range.to || range.from <= range.to) return range
  if (changed === 'to') range.from = range.to
  else range.to = range.from
  return range
}
