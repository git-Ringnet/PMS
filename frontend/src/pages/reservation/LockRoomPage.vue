<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import RoomIcon from '@/components/RoomIcon.vue'

const uiStore = useUiStore()

// State variables
const rooms = ref([])
const hotelConfigs = ref([])
const loading = ref(false)
const selectedRoomIds = ref([])

// Filters
const searchQuery = ref('')
const statusFilter = ref('Tất cả trạng thái')
const roomTypeFilter = ref('Tất cả loại phòng')

// Accordion collapsed state per floor
const collapsedFloors = ref({})

const isFloorCollapsed = (floor) => {
  return !!collapsedFloors.value[floor]
}

const toggleFloor = (floor) => {
  collapsedFloors.value[floor] = !collapsedFloors.value[floor]
}

// Pagination (mocked to 1 page)
const currentPage = ref(1)
const perPage = ref(15)

// Dropdown row menu tracking
const activeRowMenuId = ref(null)

// History panel state
const activeHistoryRoom = ref(null)
const historyLogs = ref([])
const loadingHistory = ref(false)

// Lock Modal State
const isBulkModalOpen = ref(false)
const bulkLockType = ref('OOO') // 'OOO' | 'OOS'
const editingLockId = ref(null) // null if creating, lock_id if editing
const bulkForm = ref({
  start_date: '',
  start_time: '00:00',
  end_date: '',
  end_time: '23:59',
  reason: '',
  maintenance_percent: 0,
})

// Modal custom room select dropdown states
const isRoomDropdownOpen = ref(false)
const modalSelectedRoomIds = ref([])
const roomSearchQuery = ref('')

const defaultLockEndTime = computed(() => {
  const cfg = hotelConfigs.value.find(c => c.name === 'FrmOOO_DefineLockByTime')
  return cfg?.value || '23:59'
})

// Broadcast channel
let bc = null

const getTodayString = () => {
  const d = new Date()
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
  const parts = formatter.formatToParts(d)
  const month = parts.find(p => p.type === 'month').value
  const day = parts.find(p => p.type === 'day').value
  const year = parts.find(p => p.type === 'year').value
  return `${year}-${month}-${day}`
}

const getCurrentTimeHourMinute = () => {
  const d = new Date()
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Ho_Chi_Minh',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
  const parts = formatter.formatToParts(d)
  let hour = parts.find(p => p.type === 'hour').value
  const minute = parts.find(p => p.type === 'minute').value
  if (hour.length === 1) hour = '0' + hour
  return `${hour}:${minute}`
}

const isEditingActiveLock = computed(() => {
  if (!editingLockId.value) return false
  const selectedRoom = rooms.value.find(r => r.lock_id === editingLockId.value)
  if (!selectedRoom || !selectedRoom.lock_start_date || !selectedRoom.lock_end_date) return false
  
  const now = new Date()
  const startDate = new Date(selectedRoom.lock_start_date.replace(/-/g, '/'))
  const endDate = new Date(selectedRoom.lock_end_date.replace(/-/g, '/'))
  return startDate <= now && endDate >= now
})

onMounted(() => {
  fetchRooms()
  fetchHotelConfigs()
  document.addEventListener('click', closeAllPopovers)
  window.addEventListener('focus', handleTabFocus)
  
  if (typeof BroadcastChannel !== 'undefined') {
    bc = new BroadcastChannel('pms-room-updates')
    bc.onmessage = (event) => {
      if (event.data === 'rooms-updated') {
        fetchRooms()
      }
    }
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllPopovers)
  window.removeEventListener('focus', handleTabFocus)
  if (bc) {
    bc.close()
  }
})

const handleTabFocus = () => {
  fetchRooms()
}

const closeAllPopovers = (e) => {
  if (!e.target.closest('.row-menu-container')) {
    activeRowMenuId.value = null
  }
  if (isRoomDropdownOpen.value && !e.target.closest('.modal-room-dropdown-container')) {
    isRoomDropdownOpen.value = false
  }
}

// Fetch all rooms from API
const fetchRooms = async () => {
  loading.value = true
  try {
    const res = await http.get('/rooms')
    if (res.data && res.data.success) {
      rooms.value = res.data.data || []
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách phòng:', err)
    uiStore.showToast('Không thể tải danh sách phòng', 'error')
  } finally {
    loading.value = false
  }
}

// Fetch hotel configs
const fetchHotelConfigs = async () => {
  try {
    const res = await http.get('/hotel-configs')
    if (res.data && res.data.success) {
      hotelConfigs.value = res.data.data || []
      // Apply default end_time to form
      bulkForm.value.end_time = defaultLockEndTime.value
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  }
}

// Format date to DD/MM/YYYY for display
const formatDateDisplay = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  return `${day}/${month}/${d.getFullYear()}`
}

// Format date-time for timeline logs
const formatDateTime = (dateStr) => {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Ho_Chi_Minh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit',
      hour12: false
    })
    const parts = formatter.formatToParts(d)
    const month = parts.find(p => p.type === 'month').value
    const day = parts.find(p => p.type === 'day').value
    const year = parts.find(p => p.type === 'year').value
    let hour = parts.find(p => p.type === 'hour').value
    const minute = parts.find(p => p.type === 'minute').value
    if (hour.length === 1) hour = '0' + hour
    return `${day}/${month}/${year} ${hour}:${minute}`
  } catch (e) {
    return dateStr
  }
}

// Room Types dropdown populating
const roomTypesList = computed(() => {
  const types = rooms.value.map(r => r.room_type_name).filter(Boolean)
  return ['Tất cả loại phòng', ...new Set(types)]
})

// Filtered rooms list
const filteredRooms = computed(() => {
  let list = rooms.value

  // Search by room number
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(r => r.room_number && r.room_number.toLowerCase().includes(q))
  }

  // Status Filter
  if (statusFilter.value === 'Sẵn sàng') {
    list = list.filter(r => !r.lock_type)
  } else if (statusFilter.value === 'Khóa OOO') {
    list = list.filter(r => r.lock_type?.toUpperCase() === 'OOO')
  } else if (statusFilter.value === 'Khóa OOS') {
    list = list.filter(r => r.lock_type?.toUpperCase() === 'OOS')
  }

  // Room Type Filter
  if (roomTypeFilter.value !== 'Tất cả loại phòng') {
    list = list.filter(r => r.room_type_name === roomTypeFilter.value)
  }

  return list
})

// Total pagination pages (mocked to 1 page)
const totalPages = computed(() => {
  return 1
})

// Paginated subset (returns all filtered rooms directly)
const paginatedRooms = computed(() => {
  return filteredRooms.value
})

// Floor grouping of paginated rooms
const roomsByFloor = computed(() => {
  const grouped = {}
  const list = paginatedRooms.value

  list.forEach(room => {
    const fl = room.floor || 'Chưa rõ'
    if (!grouped[fl]) {
      grouped[fl] = []
    }
    grouped[fl].push(room)
  })

  // Sort rooms within each floor by room number
  Object.keys(grouped).forEach(fl => {
    grouped[fl].sort((a, b) => parseInt(a.room_number) - parseInt(b.room_number))
  })

  return grouped
})

const sortedFloors = computed(() => {
  return Object.keys(roomsByFloor.value)
    .sort((a, b) => {
      const numA = parseInt(a)
      const numB = parseInt(b)
      if (isNaN(numA) || isNaN(numB)) return a.localeCompare(b)
      return numA - numB
    })
})

// Master checkbox status
const isAllSelected = computed(() => {
  return paginatedRooms.value.length > 0 && paginatedRooms.value.every(r => selectedRoomIds.value.includes(r.id))
})

const toggleSelectAll = (event) => {
  if (event.target.checked) {
    const visibleIds = paginatedRooms.value.map(r => r.id)
    visibleIds.forEach(id => {
      if (!selectedRoomIds.value.includes(id)) {
        selectedRoomIds.value.push(id)
      }
    })
  } else {
    const visibleIds = paginatedRooms.value.map(r => r.id)
    selectedRoomIds.value = selectedRoomIds.value.filter(id => !visibleIds.includes(id))
  }
}

// Maintenance status helpers
const getMaintenanceStatusLabel = (room) => {
  if (!room.lock_type) return '-'
  const pct = room.lock_maintenance_percent ?? 0
  if (pct === 100) return 'Hoàn tất'
  return 'Đang xử lý'
}

const getMaintenanceStatusClass = (room) => {
  if (!room.lock_type) return 'text-slate-400 font-normal'
  const pct = room.lock_maintenance_percent ?? 0
  if (pct === 100) return 'text-green-600 font-bold'
  return 'text-sky-600 font-bold'
}

// History loading & timeline transformation
const showHistory = async (room) => {
  activeHistoryRoom.value = room
  loadingHistory.value = true
  historyLogs.value = []
  try {
    const res = await http.get(`/room-locks/history/${room.id}`)
    if (res.data && res.data.success) {
      historyLogs.value = res.data.data || []
    }
  } catch (err) {
    console.error('Lỗi khi tải lịch sử khóa phòng:', err)
    uiStore.showToast('Không thể tải lịch sử khóa phòng', 'error')
  } finally {
    loadingHistory.value = false
  }
}

const timelineEvents = computed(() => {
  const events = []
  historyLogs.value.forEach(log => {
    // 1. Lock event
    events.push({
      id: `lock-${log.id}`,
      timestamp: log.created_at,
      type: 'lock',
      lock_type: log.lock_type,
      start_date: log.start_date,
      end_date: log.end_date,
      reason: log.reason,
      username: log.username,
      maintenance_percent: log.maintenance_percent,
      is_active: log.is_active
    })
    
    // 2. Unlock event (if closed)
    if (!log.is_active) {
      events.push({
        id: `unlock-${log.id}`,
        timestamp: log.updated_at,
        type: 'unlock',
        username: log.username
      })
    }
  })
  
  // Sort descending by timestamp
  return events.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
})

const getTimelineDotColor = (event) => {
  if (event.type === 'unlock') return '#10b981' // green
  if (event.lock_type?.toUpperCase() === 'OOO') return '#ef4444' // red
  if (event.lock_type?.toUpperCase() === 'OOS') return '#f97316' // orange
  return '#64748b' // slate
}

const getTimelineEventLabel = (event) => {
  if (event.type === 'unlock') return 'Mở khóa phòng'
  if (event.lock_type?.toUpperCase() === 'OOO') return 'Khóa phòng OOO'
  if (event.lock_type?.toUpperCase() === 'OOS') return 'Khóa phòng OOS'
  return 'Khóa phòng'
}// Bulk Unlock action
const submitBulkUnlock = async () => {
  if (selectedRoomIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một phòng cần mở khóa!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận mở khóa',
    message: `Bạn có chắc chắn muốn mở khóa cho ${selectedRoomIds.value.length} phòng đã chọn?`,
    confirmText: 'Mở khóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    const res = await http.post('/room-locks/bulk-unlock', { room_ids: selectedRoomIds.value })
    if (res.data && res.data.success) {
      uiStore.showToast(`Đã mở khóa thành công ${selectedRoomIds.value.length} phòng!`, 'success')
      selectedRoomIds.value = []
      fetchRooms()
      if (activeHistoryRoom.value) {
        showHistory(activeHistoryRoom.value)
      }
      if (bc) bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error('Lỗi mở khóa phòng:', err)
    const errMsg = err.response?.data?.message || 'Không thể mở khóa phòng'
    uiStore.showToast(errMsg, 'error')
  }
}

// Single Unlock action from ⋮ menu
const submitSingleUnlock = async (room) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận mở khóa',
    message: `Bạn có chắc chắn muốn mở khóa cho phòng ${room.room_number}?`,
    confirmText: 'Mở khóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    const res = await http.post('/room-locks/bulk-unlock', { room_ids: [room.id] })
    if (res.data && res.data.success) {
      uiStore.showToast(`Đã mở khóa thành công phòng ${room.room_number}!`, 'success')
      selectedRoomIds.value = selectedRoomIds.value.filter(id => id !== room.id)
      fetchRooms()
      if (activeHistoryRoom.value && activeHistoryRoom.value.id === room.id) {
        showHistory(room)
      }
      if (bc) bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error('Lỗi mở khóa phòng:', err)
    const errMsg = err.response?.data?.message || 'Không thể mở khóa phòng'
    uiStore.showToast(errMsg, 'error')
  }
}

// Modals Openers
const openBulkLockModal = (type) => {
  editingLockId.value = null
  bulkLockType.value = type
  bulkForm.value.start_date = getTodayString()
  bulkForm.value.start_time = getCurrentTimeHourMinute()
  bulkForm.value.end_date = getTodayString()
  bulkForm.value.end_time = defaultLockEndTime.value
  bulkForm.value.reason = ''
  bulkForm.value.maintenance_percent = 0
  modalSelectedRoomIds.value = [...selectedRoomIds.value]
  isRoomDropdownOpen.value = false
  roomSearchQuery.value = ''
  isBulkModalOpen.value = true
}

const openSingleLockModal = (room, type) => {
  editingLockId.value = null
  bulkLockType.value = type
  bulkForm.value.start_date = getTodayString()
  bulkForm.value.start_time = getCurrentTimeHourMinute()
  bulkForm.value.end_date = getTodayString()
  bulkForm.value.end_time = defaultLockEndTime.value
  bulkForm.value.reason = ''
  bulkForm.value.maintenance_percent = 0
  modalSelectedRoomIds.value = [room.id]
  isRoomDropdownOpen.value = false
  roomSearchQuery.value = ''
  isBulkModalOpen.value = true
}

const openEditLockModal = (room) => {
  editingLockId.value = room.lock_id
  bulkLockType.value = room.lock_type || 'OOS'
  
  const startParts = room.lock_start_date ? room.lock_start_date.split(' ') : []
  const endParts = room.lock_end_date ? room.lock_end_date.split(' ') : []
  
  bulkForm.value.start_date = startParts[0] || getTodayString()
  bulkForm.value.start_time = startParts[1] ? startParts[1].substring(0, 5) : '00:00'
  bulkForm.value.end_date = endParts[0] || getTodayString()
  bulkForm.value.end_time = endParts[1] ? endParts[1].substring(0, 5) : '23:59'
  
  bulkForm.value.reason = room.lock_reason || ''
  bulkForm.value.maintenance_percent = room.lock_maintenance_percent || 0
  modalSelectedRoomIds.value = [room.id]
  isRoomDropdownOpen.value = false
  roomSearchQuery.value = ''
  isBulkModalOpen.value = true
}

// Modal select room helper
const modalFilteredRooms = computed(() => {
  if (!roomSearchQuery.value) return rooms.value
  const q = roomSearchQuery.value.toLowerCase()
  return rooms.value.filter(r => 
    (r.room_number && r.room_number.toLowerCase().includes(q)) ||
    (r.room_type_name && r.room_type_name.toLowerCase().includes(q))
  )
})

const isAllModalRoomsSelected = computed(() => {
  return rooms.value.length > 0 && modalSelectedRoomIds.value.length === rooms.value.length
})

const toggleSelectAllModalRooms = (event) => {
  if (event.target.checked) {
    modalSelectedRoomIds.value = rooms.value.map(r => r.id)
  } else {
    modalSelectedRoomIds.value = []
  }
}

// Modal Submit
const submitBulkLock = async (force = false) => {
  if (modalSelectedRoomIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một phòng!', 'warning')
    return
  }
  
  if (!bulkForm.value.reason.trim()) {
    uiStore.showToast('Vui lòng nhập lý do khóa phòng ở ô Ghi chú!', 'warning')
    return
  }

  try {
    const payload = {
      start_date: `${bulkForm.value.start_date} ${bulkForm.value.start_time || '00:00'}:00`,
      end_date: `${bulkForm.value.end_date} ${bulkForm.value.end_time || '23:59'}:00`,
      reason: bulkForm.value.reason,
      maintenance_percent: parseInt(bulkForm.value.maintenance_percent) || 0,
      lock_type: bulkLockType.value,
      force: force,
    }

    if (editingLockId.value) {
      payload.is_active = true
      const res = await http.put(`/room-locks/${editingLockId.value}`, payload)
      if (res.data && res.data.success) {
        uiStore.showToast('Cập nhật thông tin khóa phòng thành công!', 'success')
        isBulkModalOpen.value = false
        selectedRoomIds.value = []
        fetchRooms()
        if (activeHistoryRoom.value && activeHistoryRoom.value.id === modalSelectedRoomIds.value[0]) {
          showHistory(activeHistoryRoom.value)
        }
        if (bc) bc.postMessage('rooms-updated')
      }
    } else {
      payload.room_ids = modalSelectedRoomIds.value
      const res = await http.post('/room-locks/bulk-lock', payload)
      if (res.data && res.data.success) {
        uiStore.showToast(`Đã khóa thành công ${modalSelectedRoomIds.value.length} phòng dạng ${bulkLockType.value}!`, 'success')
        isBulkModalOpen.value = false
        selectedRoomIds.value = []
        fetchRooms()
        if (bc) bc.postMessage('rooms-updated')
      }
    }
  } catch (err) {
    console.error('Lỗi khi lưu khóa phòng:', err)
    
    // Check if it is a confirmation request for booking overlap
    if (err.response && err.response.data && err.response.data.require_confirm) {
      const confirmed = await uiStore.confirm({
        title: 'Xác nhận đè lịch đặt phòng',
        message: err.response.data.message,
        confirmText: 'Đồng ý khóa',
        cancelText: 'Hủy'
      })
      if (confirmed) {
        await submitBulkLock(true)
      }
    } else {
      const errMsg = err.response?.data?.message || 'Không thể lưu thông tin khóa phòng'
      uiStore.showToast(errMsg, 'error')
    }
  }
}
// Toggle active row menu
const toggleRowMenu = (roomId, event) => {
  if (activeRowMenuId.value === roomId) {
    activeRowMenuId.value = null
  } else {
    activeRowMenuId.value = roomId
  }
}

</script>

<template>
  <div class="h-full flex gap-4 overflow-hidden text-xs text-slate-800 p-1">
    
    <!-- LEFT SIDE: TABLE & CONTROLS -->
    <div class="flex-1 bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col min-h-[350px]">
      
      <!-- Top Filters & Actions Bar -->
      <div class="p-3 border-b border-slate-100 flex items-center gap-3 bg-white shrink-0 flex-wrap justify-between">
        
        <!-- Left Filter Inputs -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <!-- Search input -->
          <div class="relative flex items-center border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50/50 focus-within:border-sky-400 focus-within:bg-white transition-colors w-[190px] h-[32px]">
            <svg class="w-3.5 h-3.5 text-slate-400 mr-2 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Tìm kiếm số phòng..." 
              class="border-none bg-transparent w-full focus:outline-none text-xs font-semibold text-slate-700 placeholder:text-slate-400 placeholder:font-normal"
            />
          </div>

          <!-- Status Dropdown -->
          <select 
            v-model="statusFilter"
            class="border border-slate-200 rounded-lg px-3 py-1 bg-slate-50/50 hover:bg-slate-50 text-xs font-semibold text-slate-700 focus:outline-sky-400 cursor-pointer h-[32px] min-w-[140px]"
          >
            <option value="Tất cả trạng thái">Tất cả trạng thái</option>
            <option value="Sẵn sàng">Sẵn sàng</option>
            <option value="Khóa OOO">Khóa OOO</option>
            <option value="Khóa OOS">Khóa OOS</option>
          </select>

          <!-- Room Type Dropdown -->
          <select 
            v-model="roomTypeFilter"
            class="border border-slate-200 rounded-lg px-3 py-1 bg-slate-50/50 hover:bg-slate-50 text-xs font-semibold text-slate-700 focus:outline-sky-400 cursor-pointer h-[32px] min-w-[160px]"
          >
            <option v-for="t in roomTypesList" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>

        <!-- Right Bulk Action Buttons -->
        <div class="flex items-center gap-2">
          <!-- Unlock -->
          <button 
            @click="submitBulkUnlock"
            class="px-3.5 py-1.5 border border-emerald-500 hover:bg-emerald-50 text-emerald-600 rounded-lg font-bold flex items-center gap-1.5 transition-all cursor-pointer h-[32px] text-xs shadow-3xs"
          >
            <RoomIcon name="unlock-outline" class="w-3.5 h-3.5 text-emerald-600" />
            Mở khóa
          </button>

          <!-- Lock OOS -->
          <button 
            @click="openBulkLockModal('OOS')"
            class="px-3.5 py-1.5 bg-[#f97316] hover:bg-[#ea580c] text-white border-none rounded-lg font-bold flex items-center gap-1.5 transition-all cursor-pointer h-[32px] text-xs shadow-2xs"
          >
            <RoomIcon name="oos" class="w-3.5 h-3.5 text-white" />
            Khóa phòng OOS
          </button>

          <!-- Lock OOO -->
          <button 
            @click="openBulkLockModal('OOO')"
            class="px-3.5 py-1.5 bg-[#ef4444] hover:bg-[#dc2626] text-white border-none rounded-lg font-bold flex items-center gap-1.5 transition-all cursor-pointer h-[32px] text-xs shadow-2xs"
          >
            <RoomIcon name="ooo-outline" class="w-3.5 h-3.5 text-white" />
            Khóa phòng OOO
          </button>
        </div>
      </div>

      <!-- Table Body Area -->
      <div class="flex-1 overflow-x-auto overflow-y-auto min-h-[200px]">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold h-10 whitespace-nowrap sticky top-0 z-10 uppercase text-xs">
              <th class="p-2.5 border-r border-slate-200 text-center w-[45px]">
                <input 
                  type="checkbox" 
                  @change="toggleSelectAll" 
                  :checked="isAllSelected" 
                  class="cursor-pointer w-4 h-4" 
                />
              </th>
              <th class="p-2.5 border-r border-slate-200 w-[80px] text-center">Phòng</th>
              <th class="p-2.5 border-r border-slate-200">Loại phòng</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[130px]">Trạng thái phòng</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[110px]">Ngày bắt đầu</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[110px]">Ngày kết thúc</th>
              <th class="p-2.5 border-r border-slate-200 min-w-[180px]">Lý do/Mô tả</th>
              <th class="p-2.5 border-r border-slate-200 w-[110px]">Người dùng</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[85px]">Bảo trì (%)</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[125px]">Trạng thái bảo trì</th>
              <th class="p-2.5 text-center w-[50px]"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" class="h-24">
              <td colspan="11" class="text-center text-slate-500 font-semibold text-xs">Đang tải danh sách phòng...</td>
            </tr>
            <tr v-else-if="sortedFloors.length === 0" class="h-24">
              <td colspan="11" class="text-center text-slate-400 italic text-xs">Không tìm thấy phòng nào phù hợp</td>
            </tr>
            
            <template v-else v-for="floor in sortedFloors" :key="floor">
              <!-- Floor separator -->
              <tr 
                @click="toggleFloor(floor)"
                class="bg-slate-50 border-b border-slate-200 font-extrabold h-9 cursor-pointer hover:bg-slate-100 transition-colors"
              >
                <td colspan="11" class="p-2.5 pl-4 text-slate-700 bg-slate-50/80">
                  <div class="flex items-center gap-2 text-xs uppercase tracking-wider font-extrabold">
                    <svg 
                      class="w-3.5 h-3.5 text-slate-400 transform transition-transform animate-duration-150" 
                      :class="{'rotate-[-90deg]': isFloorCollapsed(floor)}"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                    TẦNG {{ floor }}
                  </div>
                </td>
              </tr>

              <!-- Floor Room Rows -->
              <tr 
                v-show="!isFloorCollapsed(floor)"
                v-for="room in roomsByFloor[floor]" 
                :key="room.id"
                class="border-b border-slate-200 hover:bg-[#bdecfe]/20 h-11 transition-colors font-semibold text-slate-700 text-xs"
                :class="{
                  'bg-[#c9eeff]/20': selectedRoomIds.includes(room.id)
                }"
              >
                <!-- Checkbox -->
                <td class="p-2.5 border-r border-slate-200 text-center">
                  <input 
                    type="checkbox" 
                    :value="room.id" 
                    v-model="selectedRoomIds" 
                    class="cursor-pointer rounded border border-slate-300 w-4 h-4" 
                  />
                </td>

                <!-- Room Number -->
                <td class="p-2.5 border-r border-slate-200 font-extrabold text-slate-800 text-center">{{ room.room_number }}</td>

                <!-- Room Class Name -->
                <td class="p-2.5 border-r border-slate-200 text-slate-655 font-medium">{{ room.room_type_name || '-' }}</td>

                <!-- Room Status dot badge -->
                <td class="p-2.5 border-r border-slate-200 text-center">
                  <span 
                    v-if="room.lock_type?.toUpperCase() === 'OOO'"
                    class="px-2.5 py-1 rounded-full font-bold text-xs bg-red-50 text-red-600 border border-red-200 inline-flex items-center gap-1.5 shadow-3xs"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> OOO
                  </span>
                  <span 
                    v-else-if="room.lock_type?.toUpperCase() === 'OOS'"
                    class="px-2.5 py-1 rounded-full font-bold text-xs bg-orange-50 text-orange-700 border border-orange-200 inline-flex items-center gap-1.5 shadow-3xs"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span> OOS
                  </span>
                  <span 
                    v-else
                    class="px-2.5 py-1 rounded-full font-bold text-xs bg-green-50 text-green-700 border border-green-200 inline-flex items-center gap-1.5 shadow-3xs"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Sẵn sàng
                  </span>
                </td>

                <!-- Start Date -->
                <td class="p-2.5 border-r border-slate-200 text-center font-normal text-slate-500">
                  {{ formatDateDisplay(room.lock_start_date) || '-' }}
                </td>

                <!-- End Date -->
                <td class="p-2.5 border-r border-slate-200 text-center font-normal text-slate-500">
                  {{ formatDateDisplay(room.lock_end_date) || '-' }}
                </td>

                <!-- Lock Reason -->
                <td class="p-2.5 border-r border-slate-200 font-normal text-slate-600 truncate max-w-[200px]" :title="room.lock_reason">
                  {{ room.lock_reason || '-' }}
                </td>

                <!-- Username -->
                <td class="p-2.5 border-r border-slate-200 font-normal text-slate-500">
                  {{ room.lock_username || '-' }}
                </td>

                <!-- Maintenance Percent -->
                <td class="p-2.5 border-r border-slate-200 text-center font-normal text-slate-500">
                  {{ room.lock_type ? room.lock_maintenance_percent + '%' : '-' }}
                </td>

                <!-- Maintenance status -->
                <td class="p-2.5 border-r border-slate-200 text-center">
                  <span :class="getMaintenanceStatusClass(room)">
                    {{ getMaintenanceStatusLabel(room) }}
                  </span>
                </td>

                <!-- Row actions (⋮ menu) -->
                <td class="p-2.5 text-center relative row-menu-container">
                  <button 
                    @click.stop="toggleRowMenu(room.id, $event)"
                    class="w-6.5 h-6.5 rounded hover:bg-slate-100 flex items-center justify-center border-none cursor-pointer text-slate-400 hover:text-slate-600 transition-colors mx-auto"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                    </svg>
                  </button>

                  <!-- Popover Dropdown menu options -->
                  <div 
                    v-if="activeRowMenuId === room.id" 
                    class="absolute right-2 top-8 z-40 bg-white border border-slate-200 rounded-lg shadow-xl py-1 min-w-[125px] font-semibold text-slate-700 normal-case"
                  >
                    <template v-if="room.lock_type">
                      <button 
                        @click.stop="openEditLockModal(room); activeRowMenuId = null"
                        class="w-full text-left px-3.5 py-2 hover:bg-slate-50 cursor-pointer border-none bg-transparent text-xs text-slate-700 flex items-center gap-2 font-semibold"
                      >
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.83 20.013a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                        </svg>
                        Chỉnh sửa
                      </button>
                      <button 
                        @click.stop="submitSingleUnlock(room); activeRowMenuId = null"
                        class="w-full text-left px-3.5 py-2 hover:bg-slate-50 cursor-pointer border-none bg-transparent text-xs text-emerald-600 flex items-center gap-2 font-semibold"
                      >
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Mở khóa
                      </button>
                    </template>
                    <template v-else>
                      <button 
                        @click.stop="openSingleLockModal(room, 'OOO'); activeRowMenuId = null"
                        class="w-full text-left px-3.5 py-2 hover:bg-slate-50 cursor-pointer border-none bg-transparent text-xs text-rose-600 flex items-center gap-2 font-semibold"
                      >
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Khóa OOO
                      </button>
                      <button 
                        @click.stop="openSingleLockModal(room, 'OOS'); activeRowMenuId = null"
                        class="w-full text-left px-3.5 py-2 hover:bg-slate-50 cursor-pointer border-none bg-transparent text-xs text-orange-600 flex items-center gap-2 font-semibold"
                      >
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Khóa OOS
                      </button>
                    </template>
                    <button 
                      @click.stop="showHistory(room); activeRowMenuId = null"
                      class="w-full text-left px-3.5 py-2 hover:bg-slate-50 cursor-pointer border-none bg-transparent text-xs text-slate-700 flex items-center gap-2 border-t border-slate-100 font-semibold"
                    >
                      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      Xem lịch sử
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-4 py-2.5 border-t border-slate-200 bg-slate-50/50 flex items-center justify-between shrink-0">
        <span class="text-slate-500 font-bold text-xs">
          Hiển thị tất cả phòng theo số tầng (Tổng số: {{ filteredRooms.length }} phòng)
        </span>
        <div class="flex items-center gap-1.5">
          <button 
            class="px-2.5 py-1 border border-slate-200 rounded text-xs font-bold text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
            disabled
          >
            Trước
          </button>
          <button 
            class="px-2.5 py-1 border rounded text-xs font-black border-sky-400 text-sky-600 bg-sky-50"
          >
            1
          </button>
          <button 
            class="px-2.5 py-1 border border-slate-200 rounded text-xs font-bold text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
            disabled
          >
            Sau
          </button>
        </div>
      </div>

    </div>

    <!-- RIGHT SIDEBAR: LOCK HISTORY TIMELINE -->
    <div 
      v-if="activeHistoryRoom" 
      class="w-[340px] border border-slate-200 bg-white rounded-xl flex flex-col overflow-hidden shrink-0 animate-in shadow-sm relative"
    >
      <!-- Sidebar Header -->
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-sky-600 fill-none stroke-current" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-xs font-black uppercase tracking-wider">Lịch sử khóa</span>
        </div>
        <button 
          @click="activeHistoryRoom = null" 
          class="text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-sm font-semibold transition-colors"
        >
          ✕
        </button>
      </div>

      <!-- Active Room Indicator Bar -->
      <div class="px-4 py-2.5 bg-sky-50/40 border-b border-slate-100 flex items-center gap-2 text-sky-700 font-extrabold text-xs">
        <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-10.5h16.5m-16.5 3h16.5m-16.5 3h16.5M6.75 21v-3.75m.75-3h-1.5m3.75 6.75V15m.75-3h-1.5m3.75 9.75v-3.75m.75-3h-1.5m3.75 6.75V15m.75-3h-1.5m3.75 9.75V3.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-12 4.5h12m-12 4.5h12" />
        </svg>
        Phòng {{ activeHistoryRoom.room_number }}
      </div>

      <!-- Timeline Logs Scroll Area -->
      <div class="flex-1 overflow-y-auto scrollbar-none flex flex-col p-4 gap-4">
        <div v-if="loadingHistory" class="text-center py-10 text-slate-400 italic">Đang tải lịch sử khóa...</div>
        <div v-else-if="timelineEvents.length === 0" class="text-center py-10 text-slate-400 italic">Chưa có lịch sử khóa phòng.</div>
        
        <template v-else>
          <!-- Title Section -->
          <div class="text-[9px] text-slate-400 font-black tracking-wider uppercase mb-1">Dữ liệu gần đây</div>
          
          <!-- Event Timeline list loop -->
          <div v-for="event in timelineEvents" :key="event.id" class="flex gap-3 relative">
            
            <!-- Point and vertical line indicator -->
            <div class="w-3 flex flex-col items-center shrink-0">
              <div 
                class="w-2.5 h-2.5 rounded-full border border-white mt-1" 
                :style="{ backgroundColor: getTimelineDotColor(event) }"
              ></div>
              <div class="w-[1.5px] bg-slate-200 flex-1 my-1"></div>
            </div>

            <!-- Card item content block -->
            <div class="flex-1 pb-4">
              <!-- Log formatted time -->
              <div class="text-[9px] text-slate-400 font-bold mb-1">{{ formatDateTime(event.timestamp) }}</div>
              
              <!-- Card details body -->
              <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 relative hover:shadow-xs transition-shadow">
                <h5 class="text-xs font-black text-slate-800 mb-1">{{ getTimelineEventLabel(event) }}</h5>
                
                <!-- Details specific for lock -->
                <div v-if="event.type === 'lock'" class="text-[10px] text-slate-500 font-semibold flex flex-col gap-0.5">
                  <div><span class="text-slate-400 font-normal">Ngày bắt đầu:</span> {{ formatDateTime(event.start_date) }}</div>
                  <div><span class="text-slate-400 font-normal">Ngày kết thúc:</span> {{ formatDateTime(event.end_date) }}</div>
                  <div class="mt-1 font-normal italic text-slate-600"><span class="text-slate-400 not-italic font-bold">Lý do:</span> {{ event.reason || '-' }}</div>
                </div>

                <!-- Footer meta: user and badge status -->
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-150/80">
                  <div class="flex items-center gap-1 text-[9px] text-slate-400">
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span>{{ event.username || 'Admin' }}</span>
                  </div>
                  <span 
                    v-if="event.type === 'lock'"
                    class="px-1.5 py-0.5 rounded-sm text-[8px] font-black uppercase"
                    :class="event.maintenance_percent === 100 ? 'bg-green-50 text-green-600 border border-green-200' : 'bg-sky-50 text-sky-600 border border-sky-200'"
                  >
                    {{ event.maintenance_percent === 100 ? 'Hoàn tất' : 'Đang xử lý' }}
                  </span>
                  <span 
                    v-else
                    class="px-1.5 py-0.5 rounded-sm text-[8px] font-black uppercase bg-emerald-50 text-emerald-600 border border-emerald-200"
                  >
                    Hoàn tất
                  </span>
                </div>
              </div>
            </div>
            
          </div>
        </template>
      </div>

    </div>

    <!-- BULK / SINGLE LOCK MODAL -->
    <div 
      v-if="isBulkModalOpen" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs font-bold"
      @click.self="isBulkModalOpen = false"
    >
      <div 
        class="bg-white shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-200 rounded-2xl w-[620px] max-w-[95vw]"
      >
        <!-- Modal Header -->
        <div 
          class="px-5 py-3.5 flex items-center justify-between text-white border-b border-slate-100 rounded-t-2xl bg-[#8dcbf4]"
        >
          <h2 class="text-xs font-black uppercase tracking-wider m-0">
            {{ editingLockId ? 'Chỉnh sửa thông tin khóa' : 'Thêm khóa' }}
          </h2>
          <button 
            @click="isBulkModalOpen = false" 
            class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-sm font-black transition-colors"
          >
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div 
          class="p-5 text-slate-700 font-bold grid grid-cols-1 md:grid-cols-[7fr_5fr] gap-5"
        >
          <!-- Left fields col -->
          <div class="flex flex-col gap-4">
            <!-- Start Date & Time -->
            <div class="flex flex-col gap-1">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[9px]">Bắt đầu</span>
              <div class="flex items-center gap-2 border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50/50 focus-within:border-sky-400 focus-within:bg-white transition-colors h-[32px]">
                <input 
                  type="date" 
                  v-model="bulkForm.start_date" 
                  :disabled="isEditingActiveLock"
                  class="border-none outline-none font-bold text-slate-700 text-xs bg-transparent w-[130px] disabled:opacity-60 disabled:cursor-not-allowed" 
                />
                <input 
                  type="time" 
                  v-model="bulkForm.start_time" 
                  :disabled="isEditingActiveLock"
                  class="border-none outline-none font-bold text-slate-700 text-xs bg-transparent w-[90px] disabled:opacity-60 disabled:cursor-not-allowed" 
                />
              </div>
            </div>

            <!-- End Date & Time -->
            <div class="flex flex-col gap-1">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[9px]">Kết thúc</span>
              <div class="flex items-center gap-2 border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50/50 focus-within:border-sky-400 focus-within:bg-white transition-colors h-[32px]">
                <input 
                  type="date" 
                  v-model="bulkForm.end_date" 
                  class="border-none outline-none font-bold text-slate-700 text-xs bg-transparent w-[130px]" 
                />
                <input 
                  type="time" 
                  v-model="bulkForm.end_time" 
                  class="border-none outline-none font-bold text-slate-700 text-xs bg-transparent w-[90px]" 
                />
              </div>
            </div>

            <!-- Room selection selector dropdown (Only visible when not editing single lock) -->
            <div class="flex flex-col gap-1 modal-room-dropdown-container">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[9px]">Phòng</span>
              <div class="relative w-full">
                <button 
                  @click.stop="editingLockId ? null : (isRoomDropdownOpen = !isRoomDropdownOpen)" 
                  class="w-full flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50 text-xs font-black text-slate-700 transition-all"
                  :class="editingLockId ? 'opacity-65 cursor-not-allowed' : 'hover:border-slate-300 cursor-pointer'"
                >
                  <span v-if="editingLockId">
                    Phòng: {{ rooms.find(r => r.id === modalSelectedRoomIds[0])?.room_number }}
                  </span>
                  <span v-else>Chọn: {{ modalSelectedRoomIds.length }} phòng</span>
                  <svg 
                    v-if="!editingLockId"
                    class="w-3.5 h-3.5 text-slate-400 transform transition-transform" 
                    :class="{'rotate-180': isRoomDropdownOpen}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <!-- Dropdown items checklist -->
                <div 
                  v-if="isRoomDropdownOpen && !editingLockId" 
                  class="absolute left-0 right-0 z-45 bg-white border border-slate-200 rounded-lg shadow-xl p-2.5 flex flex-col gap-2 min-w-[200px] normal-case"
                  style="top: 100%; margin-top: 4px;"
                  @click.stop
                >
                  <!-- Search input -->
                  <div class="relative flex items-center border border-slate-200 rounded px-2.5 py-1 bg-slate-50/80">
                    <input 
                      type="text" 
                      v-model="roomSearchQuery" 
                      placeholder="Tìm số phòng..." 
                      class="border-none bg-transparent w-full focus:outline-none text-[11px] font-semibold text-slate-700"
                    />
                    <svg class="w-3 h-3 text-slate-400 ml-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </div>

                  <!-- Scroll list checkboxes -->
                  <div class="overflow-y-auto flex flex-col gap-1 py-1 text-slate-700" style="max-height: 140px;">
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1.5 rounded text-xs">
                      <input 
                        type="checkbox" 
                        :checked="isAllModalRoomsSelected" 
                        @change="toggleSelectAllModalRooms" 
                        class="cursor-pointer"
                      />
                      <span class="font-extrabold">Tất cả</span>
                    </label>
                    <label 
                      v-for="r in modalFilteredRooms" 
                      :key="r.id" 
                      class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1.5 rounded text-xs"
                    >
                      <input 
                        type="checkbox" 
                        :value="r.id" 
                        v-model="modalSelectedRoomIds" 
                        class="cursor-pointer"
                      />
                      <span class="font-bold">{{ r.room_number }} - {{ r.room_type_name || '-' }}</span>
                    </label>
                  </div>

                  <!-- Close button inside dropdown -->
                  <button 
                    @click="isRoomDropdownOpen = false"
                    class="w-full py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded font-extrabold cursor-pointer border-none text-[11px] shadow-3xs transition-colors"
                  >
                    Xác nhận
                  </button>
                </div>
              </div>
            </div>

            <!-- Maintenance Progress percent -->
            <div class="flex flex-col gap-1">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[9px]">Tiến độ bảo trì</span>
              <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-slate-50/50 focus-within:border-sky-400 focus-within:bg-white transition-colors h-[32px]">
                <input 
                  type="number" 
                  min="0" 
                  max="100" 
                  v-model="bulkForm.maintenance_percent" 
                  class="border-none outline-none px-3 py-1.5 w-full text-xs font-bold text-slate-700 bg-transparent" 
                />
                <span class="bg-slate-100 text-slate-500 font-black px-3.5 py-1.5 border-l border-slate-200 text-xs select-none">%</span>
              </div>
            </div>
          </div>

          <!-- Right Reason note block -->
          <div class="flex flex-col gap-1">
            <span class="text-slate-500 font-bold uppercase tracking-wider text-[9px]">Ghi chú *</span>
            <textarea 
              v-model="bulkForm.reason" 
              placeholder="Nhập ghi chú hoặc lý do bảo trì..."
              class="w-full border border-slate-200 rounded-lg p-2.5 bg-slate-50/50 focus:bg-white focus:outline-sky-400 resize-none font-semibold text-xs leading-relaxed h-[138px]"
            ></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-5 py-3.5 flex items-center justify-end gap-2 border-t border-slate-100 rounded-b-2xl">
          <button 
            @click="isBulkModalOpen = false" 
            class="px-4 py-1.5 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-xs cursor-pointer transition-colors"
          >
            Đóng
          </button>
          <button 
            @click="submitBulkLock"
            class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-xs border-none cursor-pointer shadow-xs transition-colors flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            {{ editingLockId ? 'Cập nhật' : 'Khóa phòng' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Animations and scrollbar styling */
.animate-in {
  animation: fadeIn 0.18s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
