import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { roomService, ROOM_STATUSES } from '@/services/room-service'

export const useRoomStore = defineStore('room', () => {
  // State
  const rooms = ref([])
  const selectedRoom = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const stats = ref(null)
  const filters = ref({
    floor: null,
    status: null,
    roomType: null,
    search: '',
  })

  // Getters
  const floors = computed(() => {
    const floorSet = new Set(rooms.value.map(r => r.floor))
    return [...floorSet].sort((a, b) => a - b)
  })

  const filteredRooms = computed(() => {
    let result = rooms.value

    if (filters.value.floor) {
      result = result.filter(r => r.floor === filters.value.floor)
    }
    if (filters.value.status) {
      if (filters.value.status === 'OOO') {
        result = result.filter(r => r.status === ROOM_STATUSES.MAINTENANCE && r.lock_type !== 'OOS')
      } else if (filters.value.status === 'OOS') {
        result = result.filter(r => r.status === ROOM_STATUSES.MAINTENANCE && r.lock_type === 'OOS')
      } else if (filters.value.status === 'occupied') {
        result = result.filter(r => r.booking_status === 'occupied' || r.booking_status === 'checkout')
      } else if (filters.value.status === 'reserved') {
        result = result.filter(r => r.booking_status === 'reserved')
      } else if (filters.value.status === 'checkout') {
        result = result.filter(r => r.booking_status === 'checkout')
      } else if (filters.value.status === 'available') {
        result = result.filter(r => r.status === ROOM_STATUSES.AVAILABLE && !r.booking_status)
      } else {
        result = result.filter(r => r.status === filters.value.status)
      }
    }
    if (filters.value.roomType) {
      result = result.filter(r => r.room_type === filters.value.roomType)
    }
    if (filters.value.search) {
      const search = filters.value.search.toLowerCase()
      result = result.filter(r =>
        r.room_number.includes(search) ||
        (r.room_type || r.room_class?.code || '').toLowerCase().includes(search)
      )
    }

    return result
  })

  const roomsByFloor = computed(() => {
    const grouped = {}
    for (const room of filteredRooms.value) {
      if (!grouped[room.floor]) {
        grouped[room.floor] = []
      }
      grouped[room.floor].push(room)
    }
    // Sort rooms within each floor by room number
    for (const floor in grouped) {
      grouped[floor].sort((a, b) => parseInt(a.room_number) - parseInt(b.room_number))
    }
    return grouped
  })

  const roomStats = computed(() => {
    if (stats.value) return stats.value
    // Calculate from local data
    const total = rooms.value.length
    const occupied = rooms.value.filter(r => r.booking_status === 'occupied' || r.booking_status === 'checkout').length
    const reserved = rooms.value.filter(r => r.booking_status === 'reserved').length
    const checkout = rooms.value.filter(r => r.booking_status === 'checkout').length
    const maintenance = rooms.value.filter(r => r.status === ROOM_STATUSES.MAINTENANCE).length
    const dirty = rooms.value.filter(r => r.status === ROOM_STATUSES.DIRTY).length
    const available = rooms.value.filter(r => r.status === ROOM_STATUSES.AVAILABLE && !r.booking_status).length

    return {
      total,
      available,
      occupied,
      dirty,
      maintenance,
      reserved,
      checkout
    }
  })

  const occupancyRate = computed(() => {
    if (rooms.value.length === 0) return 0
    return Math.round((roomStats.value.occupied / rooms.value.length) * 100)
  })

  // Actions
  async function fetchRooms(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await roomService.getRooms(params)
      rooms.value = response.data
    } catch (err) {
      error.value = 'Không thể tải danh sách phòng. Vui lòng thử lại.'
      console.error('fetchRooms error:', err)
    } finally {
      loading.value = false
    }
  }

  async function fetchRoomDetail(roomId) {
    loading.value = true
    error.value = null
    try {
      const response = await roomService.getRoomDetail(roomId)
      selectedRoom.value = response.data
    } catch (err) {
      error.value = 'Không thể tải thông tin phòng.'
      console.error('fetchRoomDetail error:', err)
    } finally {
      loading.value = false
    }
  }

  async function updateRoomStatus(roomId, status, lockType = null) {
    error.value = null
    try {
      await roomService.updateRoomStatus(roomId, status)
      // Update local state
      const room = rooms.value.find(r => r.id === roomId)
      if (room) {
        room.status = status
        room.lock_type = lockType
      }
    } catch (err) {
      error.value = 'Không thể cập nhật trạng thái phòng.'
      console.error('updateRoomStatus error:', err)
    }
  }

  async function fetchStats() {
    try {
      const response = await roomService.getRoomStats()
      stats.value = response.data
    } catch (err) {
      console.error('fetchStats error:', err)
    }
  }

  function setFilter(key, value) {
    filters.value[key] = value
  }

  function resetFilters() {
    filters.value = {
      floor: null,
      status: null,
      roomType: null,
      search: '',
    }
  }

  function selectRoom(room) {
    selectedRoom.value = room
  }

  function clearSelectedRoom() {
    selectedRoom.value = null
  }

  return {
    // State
    rooms,
    selectedRoom,
    loading,
    error,
    stats,
    filters,
    // Getters
    floors,
    filteredRooms,
    roomsByFloor,
    roomStats,
    occupancyRate,
    // Actions
    fetchRooms,
    fetchRoomDetail,
    updateRoomStatus,
    fetchStats,
    setFilter,
    resetFilters,
    selectRoom,
    clearSelectedRoom,
  }
})
