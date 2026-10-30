// Dòng 282: gợi ý khách đã lưu trú khi nhập Tên khách / Số giấy tờ (dùng chung các màn hình thông tin khách).

const escapeHtml = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;')

function highlight(text, query) {
  const safe = escapeHtml(text)
  const q = String(query ?? '').trim()
  if (!q) return safe
  const pattern = escapeHtml(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return safe.replace(new RegExp(`(${pattern})`, 'gi'), '<span class="text-orange-600 font-extrabold">$1</span>')
}

const formatMoney = (value) => Number(value || 0).toLocaleString('vi-VN')

/** Tên - ngày sinh - số giấy tờ - số BK - giá phòng lần lưu trú gần nhất. */
export function formatGuestSuggestion(guest, query) {
  const parts = [highlight(guest.full_name || 'Khách', query)]
  if (guest.dob) {
    const [y, m, d] = String(guest.dob).slice(0, 10).split('-')
    parts.push(d ? `${d}/${m}/${y}` : escapeHtml(guest.dob))
  }
  if (guest.id_number) parts.push(highlight(guest.id_number, query))
  parts.push(`${guest.stay_count || 1} BK`)
  if (Number(guest.last_room_rate) > 0) parts.push(`${formatMoney(guest.last_room_rate)} VND`)
  return parts.join(' - ')
}

const INHERITED_FIELDS = [
  'full_name', 'title', 'dob', 'nationality_code', 'phone', 'email', 'id_type', 'id_number',
  'passport_number', 'id_issue_date', 'residence_type', 'address', 'avatar',
]

/**
 * Kế thừa thông tin khách cũ vào dòng/form đang nhập: chỉ ghi các trường khách cũ có dữ liệu,
 * không đổi id / phòng của khách đang nhập.
 */
export function inheritGuestInfo(target, guest, fields = INHERITED_FIELDS) {
  if (!target || !guest) return target
  for (const field of fields) {
    const value = guest[field]
    if (value === null || value === undefined || value === '') continue
    target[field] = ['dob', 'id_issue_date'].includes(field) ? String(value).slice(0, 10) : value
  }
  return target
}
