import apiClient from './api'

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

const ROOM_STATUSES = {
  AVAILABLE: 'available',
  OCCUPIED: 'occupied',
  DIRTY: 'dirty',
  MAINTENANCE: 'maintenance',
  RESERVED: 'reserved',
  CHECKOUT: 'checkout',
}

// Bypasses network request timeouts if VITE_API_URL is not configured
const USE_MOCK_ONLY = !import.meta.env.VITE_API_URL

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
      const response = await apiClient.get('/rooms', { params })
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
      const response = await apiClient.get(`/rooms/${roomId}`)
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
  async updateRoomStatus(roomId, status) {
    if (USE_MOCK_ONLY) {
      const rooms = getMockRooms()
      const room = rooms.find(r => r.id === parseInt(roomId))
      if (room) room.status = status
      return { success: true, data: room }
    }

    try {
      const response = await apiClient.put(`/rooms/${roomId}/status`, { status })
      return response.data
    } catch (error) {
      console.warn('Real API failed, falling back to mock data:', error.response?.data?.message || error.message)
      const rooms = getMockRooms()
      const room = rooms.find(r => r.id === parseInt(roomId))
      if (room) room.status = status
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
      const response = await apiClient.get('/rooms/stats')
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
}

export { ROOM_TYPES, ROOM_STATUSES }
