import { isCheckedOutPerson } from './guest-info.js'

const IMPORT_FIELDS = [
  'title', 'full_name', 'dob', 'nationality_code', 'id_type', 'id_number', 'passport_expiry', 'province', 'district', 'ward',
  'phone', 'email', 'address', 'guest_type', 'visa_no', 'residence_type', 'temp_residence_to', 'entry_date',
  'visa_expiry_date', 'entry_purpose', 'border_gate', 'note',
]

/**
 * Điền các dòng khách đọc từ Excel vào bảng khách đang sửa (chưa lưu).
 * - Dòng có "Số Phòng": điền vào khách kế tiếp còn trống của đúng phòng đó.
 * - Dòng không có số phòng: điền vào khách trống kế tiếp theo thứ tự trên bảng.
 * - Chỉ điền vào khách còn trống (tên rỗng hoặc dạng "Guest N"); khách đã có tên hoặc đã trả phòng không bị ghi đè. Ô Excel trống giữ nguyên dữ liệu hiện có.
 * @returns {{ filled: object[], unmatched: object[] }} filled: [{ guest, room }]; unmatched: các dòng không còn chỗ.
 */
export function applyImportedGuests(groups, rows) {
  const filled = []
  const unmatched = []
  const isBlank = (g) => !g.full_name || /^guest\s*\d*$/i.test(String(g.full_name).trim())
  const used = new Set()
  const slotsOf = (group) => (group.guests || []).filter((g) => !isCheckedOutPerson(g) && !used.has(g))

  for (const row of rows) {
    const room = row.room_number ? String(row.room_number).trim() : ''
    const candidates = room
      ? groups.filter((g) => String(g.room_number || '').trim() === room)
      : groups
    let target = null
    let targetGroup = null
    for (const group of candidates) {
      const slots = slotsOf(group)
      target = slots.find(isBlank) || null
      if (target) { targetGroup = group; break }
    }
    if (!target) { unmatched.push(room && !candidates.length ? { ...row, reason: 'room_not_in_booking' } : { ...row, reason: 'no_free_slot' }); continue }
    used.add(target)
    for (const field of IMPORT_FIELDS) {
      if (row[field] !== undefined && row[field] !== '') target[field] = row[field]
    }
    filled.push({ guest: target, room: targetGroup.room_number })
  }
  return { filled, unmatched }
}
