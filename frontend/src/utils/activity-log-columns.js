// Dòng 542: bảng Lịch sử thao tác cho sắp xếp thứ tự cột và ẩn/hiện cột, lưu theo user
// (user-settings trên server, localStorage chỉ là bản dự phòng) — dùng chung cơ chế với bảng chi tiết phòng booking.
import { applySavedRoomColumns } from './booking-room-columns.js'

export const ACTIVITY_LOG_SETTINGS_KEY = 'activity_log'

export const ACTIVITY_LOG_COLUMNS = [
  { key: 'id', label: 'ID' },
  { key: 'time', label: 'Thời Gian' },
  { key: 'userAgent', label: 'Trình Duyệt' },
  { key: 'screen', label: 'Màn Hình' },
  { key: 'user', label: 'Người Dùng' },
  { key: 'date', label: 'Ngày Thao Tác' },
  { key: 'action', label: 'Hành Động' },
  { key: 'regCode', label: 'Mã Đăng Ký' },
  { key: 'roomCode', label: 'Mã Phòng' },
  { key: 'description', label: 'Mô Tả' },
  { key: 'detail', label: 'Chi Tiết' },
].map(col => ({ ...col, visible: true }))

/** Danh sách cột theo thứ tự và ẩn/hiện đã lưu; ẩn hết cột thì dùng mặc định để bảng không trống. */
export function buildActivityLogColumns(savedOrder, savedVisible) {
  const columns = applySavedRoomColumns(ACTIVITY_LOG_COLUMNS, savedOrder, savedVisible)
  return columns.some(col => col.visible) ? columns : applySavedRoomColumns(ACTIVITY_LOG_COLUMNS, savedOrder, {})
}

export function visibleActivityLogColumns(columns) {
  return (Array.isArray(columns) ? columns : []).filter(col => col.visible)
}

/** Kéo cột `fromKey` tới vị trí của cột `toKey`; trả về mảng mới. */
export function moveActivityLogColumn(columns, fromKey, toKey) {
  const list = [...columns]
  const from = list.findIndex(col => col.key === fromKey)
  const to = list.findIndex(col => col.key === toKey)
  if (from === -1 || to === -1 || from === to) return list
  const [moved] = list.splice(from, 1)
  list.splice(to, 0, moved)
  return list
}

// Tiêu đề cột khi xuất Excel (cột Chi Tiết là nút xem nên không xuất).
const EXPORT_HEADERS = {
  id: 'ID',
  time: 'Thời gian',
  userAgent: 'Trình duyệt',
  screen: 'Màn hình',
  user: 'Người dùng',
  date: 'Ngày',
  action: 'Hành động',
  regCode: 'Mã đăng ký',
  roomCode: 'Mã phòng',
  description: 'Mô tả',
}

const quote = value => `"${String(value ?? '').replace(/"/g, '""')}"`

/** Giá trị gốc (chưa bọc dấu nháy) của một ô xuất. */
function exportCell(log, key, fmt) {
  switch (key) {
    case 'id': return log.id
    case 'time': return fmt.time(log)
    case 'userAgent': return log.user_agent || ''
    case 'screen': return log.component || 'Booking'
    case 'user': return log.employee_code || log.user_name || 'admin'
    case 'date': return fmt.date(log)
    case 'action': return fmt.action(log)
    case 'regCode': return log.booking_code || '-'
    case 'roomCode': return log.room_number || '-'
    case 'description': return log.description || ''
    default: return ''
  }
}

function exportColumns(columns) {
  const exportable = visibleActivityLogColumns(columns).filter(col => EXPORT_HEADERS[col.key])
  return exportable.length ? exportable : ACTIVITY_LOG_COLUMNS.filter(col => EXPORT_HEADERS[col.key])
}

/**
 * Checklist TC_HT_06 dòng 37: bảng xuất Excel (tiêu đề + từng dòng, mỗi giá trị 1 ô) gửi lên server dựng file .xlsx.
 * Theo đúng các cột đang hiện và thứ tự user đã chọn (dòng 542).
 */
export function buildActivityLogTable(logs, columns, fmt) {
  const exportable = exportColumns(columns)
  return {
    headers: exportable.map(col => EXPORT_HEADERS[col.key]),
    rows: (logs || []).map(log => exportable.map(col => exportCell(log, col.key, fmt))),
  }
}

/**
 * Nội dung CSV xuất theo đúng các cột đang hiện và thứ tự user đã chọn (dòng 542).
 * `fmt` cung cấp cách lấy giờ, ngày và nhãn hành động của từng log.
 */
export function buildActivityLogCsv(logs, columns, fmt) {
  const exportable = exportColumns(columns)
  const lines = [exportable.map(col => EXPORT_HEADERS[col.key]).join(',')]
  for (const log of logs) lines.push(exportable.map(col => (col.key === 'id' ? log.id : quote(exportCell(log, col.key, fmt)))).join(','))
  return lines.join(String.fromCharCode(10))
}
