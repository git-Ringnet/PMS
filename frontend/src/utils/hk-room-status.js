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
 * Phòng "Lau dọn" khi lọc (khớp Room Map): trạng thái turndown/housekeeping (icon ngôi sao)
 * hoặc phòng khách trả trong ngày.
 */
export function isCleaningRoom(room) {
  const code = String(room?.room_status_code || '').toLowerCase()
  return code === 'turndown' || code === 'housekeeping' || code === 'cleaning'
    || room?.booking_status === 'checkout' || room?.displayBookingStatus === 'checkout'
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
