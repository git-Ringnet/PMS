/**
 * Tình trạng phòng cho In phân công phòng (dòng 357).
 */

/** Sắp xếp theo tầng, rồi theo số phòng (số tự nhiên: 101 < 102 < 1001). */
export function compareRooms(a, b) {
  const floorA = Number(a.floor)
  const floorB = Number(b.floor)
  if (Number.isFinite(floorA) && Number.isFinite(floorB) && floorA !== floorB) return floorA - floorB
  return String(a.room_number).localeCompare(String(b.room_number), undefined, { numeric: true })
}

/** Phòng thuộc một tầng. Số tầng có thể là chuỗi hoặc số nên so sánh sau khi chuẩn hóa. */
export function roomsOnFloor(rooms, floor) {
  return rooms.filter(r => String(r.floor) === String(floor))
}

const IN_HOUSE_BOOKING = new Set(['occupied', 'checkout', 'lco'])

/**
 * Dòng 357 (note 4/11): phòng đang có khách ở (chiếm dụng) phải hiện OC/OD, không hiện mã phòng trống (VR/VC)
 * hay OR. Chiếm dụng sạch / sẵn sàng / lau dọn (icon ngôi sao) đều là OC; chiếm dụng bẩn là OD.
 */
export function normalizeHkCode(roomStatusCode, bookingStatus, isBackToBack = false) {
  const inHouse = IN_HOUSE_BOOKING.has(bookingStatus) || isBackToBack
  if (!inHouse) return roomStatusCode
  if (['vacant_ready', 'vacant_clean', 'occupied_ready', 'occupied_clean', 'turndown', 'housekeeping'].includes(roomStatusCode)) {
    return 'occupied_clean'
  }
  if (roomStatusCode === 'vacant_dirty') return 'occupied_dirty'
  return roomStatusCode
}

/**
 * Phòng "Lau dọn" khi lọc (khớp Room Map, dòng 357 note 23/11): phòng sạch/lau dọn (cùng icon ngôi sao),
 * trạng thái turndown/housekeeping.
 * Note 26/11: chỉ xét tình trạng buồng; phòng khách trả trong ngày (CO) đang bẩn (OD) không phải phòng lau dọn.
 */
export function isCleaningRoom(room) {
  const code = String(room?.room_status_code || '').toLowerCase()
  return ['clean', 'vacant_clean', 'occupied_clean', 'turndown', 'housekeeping', 'cleaning'].includes(code)
}

/**
 * Trạng thái đặt phòng hiển thị theo ngày làm việc.
 * Phòng chưa check in có ngày đến = ngày làm việc luôn là CI (kể cả day use đến = đi cùng ngày),
 * không được rơi vào nhánh trả phòng.
 */
export function deriveDisplayBookingStatus(room, workDate) {
  const status = room?.booking_status || ''
  if (!status) return ''
  const arr = room.arrival_date ? String(room.arrival_date).substring(0, 10) : ''
  const dep = room.departure_date ? String(room.departure_date).substring(0, 10) : ''
  if (status === 'occupied') return 'occupied'
  if (status === 'reserved' && arr === workDate) return 'reserved'
  if (dep === workDate) return 'checkout'
  if (arr === workDate) return 'reserved'
  return 'occupied'
}

/** Bộ lọc Vệ sinh: Phòng sạch và Phòng lau dọn cùng icon ngôi sao nên chỉ còn 1 mục Lau dọn. */
export const hkFilterOptions = [
  { key: 'all', label: 'Tất cả', icon: null },
  { key: 'cleaning', label: 'Phòng lau dọn', icon: 'checkout', iconClass: 'text-cyan-600' },
  { key: 'dirty', label: 'Phòng bẩn', icon: 'dirty', iconClass: 'text-amber-600' },
  { key: 'ready', label: 'Phòng sẵn sàng', icon: 'available', iconClass: 'text-emerald-600' },
  { key: 'ooo', label: 'Phòng OOO', icon: 'maintenance', iconClass: 'text-rose-500' },
  { key: 'oos', label: 'Phòng OOS', icon: 'maintenance', iconClass: 'text-amber-500' },
]

export function matchesHkFilter(room, filterKey) {
  if (!filterKey || filterKey === 'all') return true
  const code = String(room?.room_status_code || '').toLowerCase()
  switch (filterKey) {
    case 'cleaning': return isCleaningRoom(room)
    case 'dirty': return ['vacant_dirty', 'occupied_dirty', 'dirty'].includes(code)
    case 'ready': return ['vacant_ready', 'occupied_ready', 'ready'].includes(code)
    case 'ooo': return !!room?.is_ooo
    case 'oos': return !!room?.is_oos
    default: return true
  }
}

/**
 * Ký hiệu tình trạng phòng.
 * - Phòng back-to-back (khách trả và khách đến cùng ngày): CI, <tình trạng buồng>, CO.
 * - Phòng có kê giường phụ: thêm EB (kèm số lượng nếu > 1).
 * @param {object} symbols cấu hình ký hiệu { hk: {}, booking: {}, extra: {} }
 * @param {object|null} liveRoom { isBackToBack, extraBedQty }
 */
export function getStatusSymbols(symbols, roomStatusCode, bookingStatus, liveRoom = null) {
  const list = []
  const push = (sym, fallbackColor) => {
    if (sym?.code && !list.some(x => x.code === sym.code)) list.push({ code: sym.code, color: sym.color || fallbackColor })
  }

  roomStatusCode = normalizeHkCode(roomStatusCode, bookingStatus, !!liveRoom?.isBackToBack)

  if (liveRoom?.isBackToBack) {
    push(symbols?.booking?.reserved, '#64748b')
    push(symbols?.hk?.[roomStatusCode], '#94a3b8')
    push(symbols?.booking?.checkout, '#64748b')
  } else {
    push(symbols?.booking?.[bookingStatus], '#64748b')
    push(symbols?.hk?.[roomStatusCode], '#94a3b8')
  }

  if (liveRoom?.extraBedQty > 0) {
    const eb = symbols?.extra?.eb
    list.push({ code: (eb?.code || 'EB') + (liveRoom.extraBedQty > 1 ? ` x${liveRoom.extraBedQty}` : ''), color: eb?.color || '#7c3aed' })
  }
  return list
}

export function getStatusText(symbols, roomStatusCode, bookingStatus, liveRoom = null) {
  return getStatusSymbols(symbols, roomStatusCode, bookingStatus, liveRoom).map(s => s.code).join(', ')
}

/**
 * Danh sách tờ in "Theo nhóm NV" (dòng 357, note 4/11).
 * - Có nhóm tích "In lịch": in các nhóm đó (tình trạng + nhân viên theo lịch phân công).
 * - Phòng chọn ở danh sách phòng bên trái (chưa nằm trong nhóm đã tích): gom một tờ, tình trạng lấy theo
 *   danh sách phòng, Housekeeping Attendant để trống, kể cả phòng đã phân công.
 * - Không chọn gì: in tất cả nhóm.
 * @returns {{ staffNames: string, rooms: { source: 'group'|'list', room: object }[] }[]}
 */
export function buildWorksheetSheets(groups, checkedGroupIds, selectedRoomIds, roomGroupMap, roomById) {
  const staffNames = g => g.staff_list.map(s => s.name).join(' / ')
  const groupRooms = rooms => rooms.map(room => ({ source: 'group', room }))
  const selectedRooms = [...selectedRoomIds].map(id => roomById.get(id)).filter(Boolean)

  const checked = groups.filter(g => checkedGroupIds.has(g.id))
  if (!checked.length && !selectedRooms.length) {
    return groups.map(g => ({ staffNames: staffNames(g), rooms: groupRooms(g.rooms) })).filter(s => s.rooms.length)
  }

  const sheets = checked.map(g => ({ staffNames: staffNames(g), rooms: groupRooms(g.rooms) }))
  const inCheckedGroups = new Set(checked.flatMap(g => g.rooms.map(r => Number(r.room_id))))
  const fromList = selectedRooms.filter(r => !inCheckedGroups.has(Number(r.id)))
  if (fromList.length) {
    sheets.push({ staffNames: '', rooms: fromList.map(room => ({ source: 'list', room })) })
  }
  return sheets.filter(s => s.rooms.length)
}

/**
 * Danh sách phòng cho mẫu "Theo phòng (Supervisor)": phòng của nhóm tích "In lịch"
 * (tình trạng theo lịch phân công) và phòng chọn ở danh sách bên trái (tình trạng hiện tại).
 * @returns {{ source: 'group'|'list', room: object }[]}
 */
export function buildSupervisorRooms(groups, checkedGroupIds, selectedRoomIds, roomById) {
  const byId = new Map()
  groups.filter(g => checkedGroupIds.has(g.id))
    .forEach(g => g.rooms.forEach(r => byId.set(Number(r.room_id), { source: 'group', room: r })))
  ;[...selectedRoomIds].forEach(id => {
    const room = roomById.get(id)
    if (room && !byId.has(id)) byId.set(id, { source: 'list', room })
  })
  return [...byId.values()]
}
