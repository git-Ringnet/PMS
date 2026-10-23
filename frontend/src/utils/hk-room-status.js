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
 * Danh sách tờ in "Theo nhóm NV".
 * - Có nhóm tích "In lịch": in các nhóm đó.
 * - Không tích nhóm, có chọn phòng: phòng đã phân công in theo nhóm của phòng đó.
 * - Không chọn gì: in tất cả nhóm.
 * Phòng chưa phân công đang chọn luôn được in thêm một tờ, Housekeeping Attendant để trống.
 * @returns {{ staffNames: string, rooms: { source: 'group'|'list', room: object }[] }[]}
 */
export function buildWorksheetSheets(groups, checkedGroupIds, selectedRoomIds, roomGroupMap, roomById) {
  const staffNames = g => g.staff_list.map(s => s.name).join(' / ')
  const groupRooms = rooms => rooms.map(room => ({ source: 'group', room }))
  const selectedRooms = [...selectedRoomIds].map(id => roomById.get(id)).filter(Boolean)
  const unassigned = selectedRooms.filter(r => !roomGroupMap[r.id])

  let sheets
  const checked = groups.filter(g => checkedGroupIds.has(g.id))
  if (checked.length) {
    sheets = checked.map(g => ({ staffNames: staffNames(g), rooms: groupRooms(g.rooms) }))
  } else if (selectedRooms.length) {
    sheets = groups
      .map(g => ({ staffNames: staffNames(g), rooms: groupRooms(g.rooms.filter(r => selectedRoomIds.has(Number(r.room_id)))) }))
  } else {
    sheets = groups.map(g => ({ staffNames: staffNames(g), rooms: groupRooms(g.rooms) }))
  }
  if (unassigned.length) {
    sheets.push({ staffNames: '', rooms: unassigned.map(room => ({ source: 'list', room })) })
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
