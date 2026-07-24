import http from './http'

/**
 * Mock data cho room map khi API chưa sẵn sàng
 */
const ROOM_TYPES = {
  'DLXD': 'DLXD',
  'DLXTB': 'DLXTB',
  'DLXT': 'DLXT',
  'SUPT': 'SUPT',
  'SUPTR': 'SUPTR',
  'SUPD': 'SUPD',
  'FAM': 'FAM',
  'JST': 'JST',
  'DLXOB': 'DLXOB',
  'DLXOD': 'DLXOD',
}

// Mã tình trạng phòng (room_status_code) khớp với bảng room_statuses trong DB
const ROOM_STATUSES = {
  AVAILABLE:        'available',
  OCCUPIED:         'occupied',
  DIRTY:            'dirty',
  MAINTENANCE:      'maintenance',
  RESERVED:         'reserved',
  CHECKOUT:         'checkout',
}

// Các mã tình trạng chuẩn (dùng cho context menu và API updateStatus)
const ROOM_STATUS_CODES = {
  VACANT_READY:    'vacant_ready',
  VACANT_DIRTY:    'vacant_dirty',
  VACANT_CLEAN:    'vacant_clean',
  OOO:             'ooo',
  OOS:             'oos',
  TURNDOWN:        'turndown',
  HOUSEKEEPING:    'housekeeping',
  DND:             'dnd',
  VACANT_PRIORITY: 'vacant_priority',
  OCCUPIED_READY:  'occupied_ready',
  OCCUPIED_DIRTY:  'occupied_dirty',
  OCCUPIED_CLEAN:  'occupied_clean',
  OCCUPIED_OOO:    'occupied_ooo',
}

// Mapping room_status_code -> icon name cho frontend
const ROOM_STATUS_ICON_MAP = {
  'vacant_ready':    null,
  'vacant_dirty':    'dirty',
  'vacant_clean':    'clean',
  'ooo':             'ooo',
  'oos':             'oos',
  'turndown':        'checkout',
  'housekeeping':    'housekeeping-service',
  'dnd':             'dnd',
  'vacant_priority': 'priority',
  'occupied_ready':  null,
  'occupied_dirty':  'dirty',
  'occupied_clean':  null,
  'occupied_ooo':    'ooo',
}

// Bypasses network request timeouts if VITE_API_URL is not configured
const USE_MOCK_ONLY = false

function generateMockRooms() {
  const rooms = []
  const floors = [4, 5, 6, 7, 8, 9, 10, 11]
  const roomsPerFloor = 12
  const statuses = Object.values(ROOM_STATUSES)
  const typeKeys = Object.keys(ROOM_TYPES)

  for (const floor of floors) {
    for (let i = 1; i <= roomsPerFloor; i++) {
      const roomNumber = `${floor}${String(i).padStart(2, '0')}`
      const statusIndex = Math.floor(Math.random() * 100)
      let status
      if (statusIndex < 55) status = ROOM_STATUSES.AVAILABLE
      else if (statusIndex < 70) status = ROOM_STATUSES.OCCUPIED
      else if (statusIndex < 80) status = ROOM_STATUSES.DIRTY
      else if (statusIndex < 88) status = ROOM_STATUSES.RESERVED
      else if (statusIndex < 95) status = ROOM_STATUSES.CHECKOUT
      else status = ROOM_STATUSES.MAINTENANCE

      const type = typeKeys[Math.floor(Math.random() * typeKeys.length)]
      const maxGuests = Math.floor(Math.random() * 4) + 1

      const lockType = status === ROOM_STATUSES.MAINTENANCE ? (parseInt(roomNumber) % 2 === 0 ? 'OOO' : 'OOS') : null

      rooms.push({
        id: parseInt(roomNumber),
        room_number: roomNumber,
        floor: floor,
        room_type: type,
        room_type_name: ROOM_TYPES[type],
        status: status,
        max_guests: maxGuests,
        guest_name: status === ROOM_STATUSES.OCCUPIED ? 'Khách lưu trú' : null,
        check_in: status === ROOM_STATUSES.OCCUPIED ? '2026-06-09' : null,
        check_out: status === ROOM_STATUSES.OCCUPIED ? '2026-06-12' : null,
        notes: '',
        is_clean: status !== ROOM_STATUSES.DIRTY,
        has_issue: status === ROOM_STATUSES.MAINTENANCE,
        lock_type: lockType,
      })
    }
  }
  return rooms
}

let cachedMockRooms = null

function getMockRooms() {
  if (!cachedMockRooms) {
    cachedMockRooms = generateMockRooms()
  }
  return cachedMockRooms
}

export const roomService = {
  /**
   * Lấy danh sách tất cả phòng (room map)
   */
  async getRooms(params = {}) {
    if (USE_MOCK_ONLY) {
      const rooms = getMockRooms()
      return {
        success: true,
        data: rooms,
        meta: {
          total: rooms.length,
          floors: [...new Set(rooms.map(r => r.floor))].sort((a, b) => a - b),
        }
      }
    }

    try {
      const response = await http.get('/rooms', { params })
      return response.data
    } catch (error) {
      console.warn('Real API failed, falling back to mock data:', error.response?.data?.message || error.message)
      const rooms = getMockRooms()
      return {
        success: true,
        data: rooms,
        meta: {
          total: rooms.length,
          floors: [...new Set(rooms.map(r => r.floor))].sort((a, b) => a - b),
        }
      }
    }
  },

  /**
   * Lấy chi tiết phòng
   */
  async getRoomDetail(roomId) {
    if (USE_MOCK_ONLY) {
      const rooms = getMockRooms()
      const room = rooms.find(r => r.id === parseInt(roomId))
      return { success: true, data: room || null }
    }

    try {
      const response = await http.get(`/rooms/${roomId}`)
      return response.data
    } catch (error) {
      console.warn('Real API failed, falling back to mock data:', error.response?.data?.message || error.message)
      const rooms = getMockRooms()
      const room = rooms.find(r => r.id === parseInt(roomId))
      return { success: true, data: room || null }
    }
  },

  /**
   * Cập nhật trạng thái phòng
   */
  async updateRoomStatus(roomId, roomStatusCode) {
    if (USE_MOCK_ONLY) {
      const rooms = getMockRooms()
      const room = rooms.find(r => r.id === parseInt(roomId))
      if (room) room.room_status_code = roomStatusCode
      return { success: true, data: room }
    }

    try {
      const response = await http.put(`/rooms/${roomId}/status`, { room_status_code: roomStatusCode })
      return response.data
    } catch (error) {
      console.warn('Real API failed, falling back to mock data:', error.response?.data?.message || error.message)
      const rooms = getMockRooms()
      const room = rooms.find(r => r.id === parseInt(roomId))
      if (room) room.room_status_code = roomStatusCode
      return { success: true, data: room }
    }
  },

  /**
   * Lấy thống kê phòng
   */
  async getRoomStats() {
    if (USE_MOCK_ONLY) {
      const rooms = getMockRooms()
      const stats = {
        total: rooms.length,
        available: rooms.filter(r => r.status === ROOM_STATUSES.AVAILABLE).length,
        occupied: rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length,
        dirty: rooms.filter(r => r.status === ROOM_STATUSES.DIRTY).length,
        maintenance: rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE).length,
        reserved: rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).length,
        checkout: rooms.filter(r => r.status === ROOM_STATUSES.CHECKOUT).length,
      }
      return { success: true, data: stats }
    }

    try {
      const response = await http.get('/rooms/stats')
      return response.data
    } catch (error) {
      console.warn('Real API failed, falling back to mock data:', error.response?.data?.message || error.message)
      const rooms = getMockRooms()
      const stats = {
        total: rooms.length,
        available: rooms.filter(r => r.status === ROOM_STATUSES.AVAILABLE).length,
        occupied: rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length,
        dirty: rooms.filter(r => r.status === ROOM_STATUSES.DIRTY).length,
        maintenance: rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE).length,
        reserved: rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).length,
        checkout: rooms.filter(r => r.status === ROOM_STATUSES.CHECKOUT).length,
      }
      return { success: true, data: stats }
    }
  },

  /**
   * Tạo khóa phòng mới (OOO/OOS)
   */
  async createRoomLock(data) {
    const response = await http.post('/room-locks', data)
    return response.data
  },

  /**
   * Tạo khóa phòng hàng loạt (OOO/OOS)
   */
  async bulkLockRooms(data) {
    const response = await http.post('/room-locks/bulk-lock', data)
    return response.data
  },

  /**
   * Mở khóa phòng
   */
  async deleteRoomLock(lockId) {
    const response = await http.delete(`/room-locks/${lockId}`)
    return response.data
  }
}

export { ROOM_TYPES, ROOM_STATUSES, ROOM_STATUS_CODES, ROOM_STATUS_ICON_MAP }
