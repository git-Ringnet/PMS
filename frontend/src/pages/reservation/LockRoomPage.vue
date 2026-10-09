<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import { fetchSystemDate } from '@/services/booking-service'
import RoomIcon from '@/components/RoomIcon.vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { getLockRoomState, setLockRoomState } from './lock-room-view-state.js'

const uiStore = useUiStore()
const authStore = useAuthStore()

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

// Khôi phục in-memory state từ module riêng biệt ngoài vòng đời component (Dòng 50)
const initialLockState = getLockRoomState()

// State variables
const rooms = ref(initialLockState.rooms ? [...initialLockState.rooms] : [])
const hotelConfigs = ref(initialLockState.hotelConfigs ? [...initialLockState.hotelConfigs] : [])
const systemDate = ref(authStore.systemDate || localStorage.getItem('pms_system_date') || '')
const loading = ref(false)
const selectedRowKeys = ref(initialLockState.selectedRowKeys ? [...initialLockState.selectedRowKeys] : []) // Binds to lock_id (if locked) or room_number (if available)

// Filters
const searchQuery = ref(initialLockState.searchQuery || '')
const statusFilter = ref(initialLockState.statusFilter || 'Tất cả trạng thái')
const roomTypeFilter = ref(initialLockState.roomTypeFilter || 'Tất cả loại phòng')

// Accordion collapsed state per floor
const collapsedFloors = ref({ ...initialLockState.collapsedFloors })

const isFloorCollapsed = (floor) => {
  return !!collapsedFloors.value[floor]
}

const toggleFloor = (floor) => {
  collapsedFloors.value[floor] = !collapsedFloors.value[floor]
}

// Pagination (mocked to 1 page)
const currentPage = ref(initialLockState.currentPage || 1)
const perPage = ref(15)

// Dropdown row menu tracking
const activeRowMenuId = ref(null)

// History panel state
const activeHistoryRoom = ref(null)
const historyLogs = ref([])
const loadingHistory = ref(false)

// Lock Modal State
const isBulkModalOpen = ref(false)
watch(isBulkModalOpen, (newVal) => {
  if (newVal) {
    modalPos.value = { x: 0, y: 0 }
  }
})
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
const modalSelectedRoomNumbers = ref([])
const roomSearchQuery = ref('')

const defaultLockEndTime = computed(() => {
  const cfg = hotelConfigs.value.find(c => c.name === 'FrmOOO_DefineLockByTime')
  return cfg?.value || '23:59'
})

// Broadcast channel
let bc = null

const getTodayString = () => {
  if (systemDate.value) return systemDate.value
  const cached = authStore.systemDate || localStorage.getItem('pms_system_date')
  if (cached) return cached
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

const isEditingActiveLock = computed(() => {
  if (!editingLockId.value) return false
  const startDateStr = bulkForm.value.start_date ? bulkForm.value.start_date.split(' ')[0] : ''
  if (!startDateStr) return false
  const sysDate = systemDate.value || getTodayString()
  return startDateStr <= sysDate
})



function stepMaintenancePercent(delta) {
  const current = parseInt(bulkForm.value.maintenance_percent, 10) || 0
  const next = Math.max(0, Math.min(100, current + delta))
  bulkForm.value.maintenance_percent = next
}

function stepBatchMaintenancePercent(lockId, delta) {
  if (!editedLocks.value[lockId]) return
  const current = parseInt(editedLocks.value[lockId].maintenance_percent, 10) || 0
  const next = Math.max(0, Math.min(100, current + delta))
  editedLocks.value[lockId].maintenance_percent = next
}

watch(() => bulkForm.value.start_date, (newVal) => {
  if (newVal) {
    bulkForm.value.start_time = '00:00'
  }
})

const handleGlobalKeydown = (e) => {
  if (e.key === 'Escape' || e.key === 'Esc') {
    if (isBulkModalOpen.value) {
      isBulkModalOpen.value = false
    } else if (activeHistoryRoom.value) {
      activeHistoryRoom.value = null
    }
  }
}

onMounted(async () => {
  window.addEventListener('keydown', handleGlobalKeydown)
  
  // Chỉ tải từ server khi chưa có cache trong RAM (lần đầu vào hoặc F5); nếu đã có thì giữ nguyên dữ liệu, không load lại (Dòng 50)
  const currentState = getLockRoomState()
  if (!currentState.rooms || currentState.rooms.length === 0) {
    fetchRooms()
  }
  if (!currentState.hotelConfigs || currentState.hotelConfigs.length === 0) {
    fetchHotelConfigs()
  }

  try {
    const sysRes = await fetchSystemDate()
    if (sysRes.data && sysRes.data.success && sysRes.data.data?.system_date) {
      systemDate.value = sysRes.data.data.system_date
      authStore.setSystemDate(sysRes.data.data.system_date)
    }
  } catch (e) {
    console.error('Lỗi khi tải ngày hệ thống:', e)
  }
  document.addEventListener('click', closeAllPopovers)
  
  if (typeof BroadcastChannel !== 'undefined') {
    bc = new BroadcastChannel('pms-room-updates')
    bc.onmessage = (event) => {
      if (event.data === 'rooms-updated') {
        fetchRooms(true)
      }
    }
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  document.removeEventListener('click', closeAllPopovers)
  if (bc) {
    bc.close()
  }
  // Ghi nhớ trạng thái bộ lọc và lựa chọn vào module cache ngoài component trước khi unmount
  setLockRoomState({
    rooms: rooms.value,
    hotelConfigs: hotelConfigs.value,
    searchQuery: searchQuery.value,
    statusFilter: statusFilter.value,
    roomTypeFilter: roomTypeFilter.value,
    collapsedFloors: { ...collapsedFloors.value },
    selectedRowKeys: [...selectedRowKeys.value],
    currentPage: currentPage.value
  })
})

const closeAllPopovers = (e) => {
  if (!e.target.closest('.row-menu-container')) {
    activeRowMenuId.value = null
  }
  if (isRoomDropdownOpen.value && !e.target.closest('.modal-room-dropdown-container')) {
    isRoomDropdownOpen.value = false
  }
}

// Fetch all rooms from API
const fetchRooms = async (silent = false) => {
  if (!silent && (!rooms.value || rooms.value.length === 0)) {
    loading.value = true
  }
  try {
    const res = await http.get('/rooms')
    if (res.data && res.data.success) {
      const data = (res.data.data || []).filter(r => !r.is_internal)
      rooms.value = data
      setLockRoomState({ rooms: data })
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách phòng:', err)
    if (!silent) uiStore.showToast('Không thể tải danh sách phòng', 'error')
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
      setLockRoomState({ hotelConfigs: hotelConfigs.value })
      // Apply default end_time to form
      bulkForm.value.end_time = defaultLockEndTime.value
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  }
}

const parseDateInLocalTimezone = (dateStr) => {
  if (!dateStr) return new Date()
  if (dateStr.includes('T') || dateStr.includes('+') || dateStr.endsWith('Z')) {
    return new Date(dateStr)
  }
  // Convert "YYYY-MM-DD HH:mm:ss" to "YYYY-MM-DDTHH:mm:ss+07:00"
  const formatted = dateStr.replace(' ', 'T') + '+07:00'
  return new Date(formatted)
}

// Format date to DD/MM/YYYY for display
const formatDateDisplay = (dateStr) => {
  if (!dateStr) return ''
  const clean = String(dateStr).split(' ')[0].split('T')[0]
  const parts = clean.split('-')
  if (parts.length === 3 && parts[0].length === 4) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  const d = parseDateInLocalTimezone(dateStr)
  if (isNaN(d.getTime())) return dateStr
  try {
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
    return `${day}/${month}/${year}`
  } catch (e) {
    return dateStr
  }
}

// Format date-time for timeline logs
const formatDateTime = (dateStr) => {
  if (!dateStr) return ''
  try {
    const d = parseDateInLocalTimezone(dateStr)
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

// Flatten rooms: each row is an active lock period, or room if unlocked
const flatRows = computed(() => {
  const list = []
  filteredRooms.value.forEach(room => {
    if (room.active_locks && room.active_locks.length > 0) {
      room.active_locks.forEach(lock => {
        list.push({
          ...room,
          currentLock: lock,
          lock_id: lock.lock_id,
          lock_type: lock.lock_type,
          lock_start_date: lock.lock_start_date,
          lock_end_date: lock.lock_end_date,
          lock_reason: lock.lock_reason,
          lock_maintenance_percent: lock.lock_maintenance_percent,
          lock_status: lock.lock_status,
          lock_username: lock.lock_username,
        })
      })
    } else {
      list.push({
        ...room,
        currentLock: null,
        lock_id: null,
        lock_type: null,
        lock_start_date: null,
        lock_end_date: null,
        lock_reason: null,
        lock_maintenance_percent: 0,
        lock_status: '',
        lock_username: '',
      })
    }
  })
  return list
})

// Total pagination pages (mocked to 1 page)
const totalPages = computed(() => {
  return 1
})

// Paginated subset
const paginatedRooms = computed(() => {
  return flatRows.value
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

  // Sort rooms: chronologically for same room
  Object.keys(grouped).forEach(fl => {
    grouped[fl].sort((a, b) => {
      const roomDiff = parseInt(a.room_number) - parseInt(b.room_number)
      if (roomDiff !== 0) return roomDiff
      if (a.lock_start_date && b.lock_start_date) {
        return new Date(a.lock_start_date) - new Date(b.lock_start_date)
      }
      return 0
    })
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
  const visibleKeys = paginatedRooms.value.map(r => r.currentLock ? r.currentLock.lock_id : r.room_number)
  return visibleKeys.length > 0 && visibleKeys.every(k => selectedRowKeys.value.includes(k))
})

const toggleSelectAll = (event) => {
  const visibleKeys = paginatedRooms.value.map(r => r.currentLock ? r.currentLock.lock_id : r.room_number)
  if (event.target.checked) {
    visibleKeys.forEach(k => {
      if (!selectedRowKeys.value.includes(k)) {
        selectedRowKeys.value.push(k)
      }
    })
  } else {
    selectedRowKeys.value = selectedRowKeys.value.filter(k => !visibleKeys.includes(k))
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
    const res = await http.get(`/room-locks/history/${room.room_number}`)
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
      is_active: log.is_active == 1
    })
    
    // 2. Unlock event (if closed)
    if (log.is_active == 2 || log.is_active == 0) {
      events.push({
        id: `unlock-${log.id}`,
        timestamp: log.unlocked_at || log.updated_at,
        type: 'unlock',
        start_date: log.start_date,
        unlock_date: log.unlocked_at || log.updated_at,
        username: log.username,
        unlock_username: log.unlock_username || 'Admin'
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
}

// Bulk Unlock action
const submitBulkUnlock = async () => {
  const lockIdsToUnlock = selectedRowKeys.value.filter(val => typeof val === 'number')
  if (lockIdsToUnlock.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một phòng đang khóa cần mở khóa!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận mở khóa',
    message: `Bạn có chắc chắn muốn mở khóa cho ${lockIdsToUnlock.length} lịch khóa đã chọn?`,
    confirmText: 'Mở khóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    const res = await http.post('/room-locks/bulk-unlock', { lock_ids: lockIdsToUnlock })
    if (res.data && res.data.success) {
      uiStore.showToast(`Đã mở khóa thành công ${lockIdsToUnlock.length} lịch khóa phòng!`, 'success')
      selectedRowKeys.value = selectedRowKeys.value.filter(k => !lockIdsToUnlock.includes(k))
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
const submitSingleUnlock = async (row) => {
  if (!row.currentLock) return
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận mở khóa',
    message: `Bạn có chắc chắn muốn mở khóa cho phòng ${row.room_number}?`,
    confirmText: 'Mở khóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    const res = await http.post('/room-locks/bulk-unlock', { lock_ids: [row.currentLock.lock_id] })
    if (res.data && res.data.success) {
      uiStore.showToast(`Đã mở khóa thành công phòng ${row.room_number}!`, 'success')
      selectedRowKeys.value = selectedRowKeys.value.filter(k => k !== row.currentLock.lock_id)
      fetchRooms()
      if (activeHistoryRoom.value && activeHistoryRoom.value.id === row.id) {
        showHistory(row)
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
  bulkForm.value.start_time = '00:00'
  bulkForm.value.end_date = getTodayString()
  bulkForm.value.end_time = defaultLockEndTime.value
  bulkForm.value.reason = ''
  bulkForm.value.maintenance_percent = 0

  // Resolve room numbers from selection
  const roomNumbers = selectedRowKeys.value.map(val => {
    if (typeof val === 'number') {
      const foundRow = flatRows.value.find(r => r.currentLock?.lock_id === val)
      return foundRow?.room_number
    }
    return val // room_number string
  }).filter(Boolean)

  modalSelectedRoomNumbers.value = [...new Set(roomNumbers)]
  isRoomDropdownOpen.value = false
  roomSearchQuery.value = ''
  isBulkModalOpen.value = true
}

const openSingleLockModal = (room, type) => {
  editingLockId.value = null
  bulkLockType.value = type
  bulkForm.value.start_date = getTodayString()
  bulkForm.value.start_time = '00:00'
  bulkForm.value.end_date = getTodayString()
  bulkForm.value.end_time = defaultLockEndTime.value
  bulkForm.value.reason = ''
  bulkForm.value.maintenance_percent = 0
  modalSelectedRoomNumbers.value = [room.room_number]
  isRoomDropdownOpen.value = false
  roomSearchQuery.value = ''
  isBulkModalOpen.value = true
}

const openEditLockModal = (row) => {
  const lock = row.currentLock
  if (!lock) return
  editingLockId.value = lock.lock_id
  bulkLockType.value = lock.lock_type || 'OOS'
  
  const startParts = lock.lock_start_date ? lock.lock_start_date.split(' ') : []
  const endParts = lock.lock_end_date ? lock.lock_end_date.split(' ') : []
  
  bulkForm.value.start_date = startParts[0] || getTodayString()
  bulkForm.value.start_time = startParts[1] ? startParts[1].substring(0, 5) : '00:00'
  bulkForm.value.end_date = endParts[0] || getTodayString()
  bulkForm.value.end_time = endParts[1] ? endParts[1].substring(0, 5) : '23:59'
  
  bulkForm.value.reason = lock.lock_reason || ''
  bulkForm.value.maintenance_percent = lock.lock_maintenance_percent || 0
  modalSelectedRoomNumbers.value = [row.room_number]
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
  return rooms.value.length > 0 && modalSelectedRoomNumbers.value.length === rooms.value.length
})

const toggleSelectAllModalRooms = (event) => {
  if (event.target.checked) {
    modalSelectedRoomNumbers.value = rooms.value.map(r => r.room_number)
  } else {
    modalSelectedRoomNumbers.value = []
  }
}

// Modal Submit
const submitBulkLock = async (force = false) => {
  if (modalSelectedRoomNumbers.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một phòng!', 'warning')
    return
  }
  
  if (!bulkForm.value.reason.trim()) {
    uiStore.showToast('Vui lòng nhập lý do khóa phòng ở ô Ghi chú!', 'warning')
    return
  }

  const minAllowedDate = systemDate.value || getTodayString()
  if (!editingLockId.value || !isEditingActiveLock.value) {
    if (bulkForm.value.start_date < minAllowedDate) {
      uiStore.showToast(`Ngày bắt đầu khóa không được nhỏ hơn Ngày hệ thống (${minAllowedDate})!`, 'warning')
      return
    }
  }
  if (bulkForm.value.end_date < bulkForm.value.start_date) {
    uiStore.showToast('Ngày kết thúc khóa không được nhỏ hơn Ngày bắt đầu khóa!', 'warning')
    return
  }
  if (isEditingActiveLock.value && bulkForm.value.end_date < minAllowedDate) {
    uiStore.showToast(`Ngày kết thúc khóa không được nhỏ hơn Ngày hệ thống (${minAllowedDate})!`, 'warning')
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
      payload.is_active = 1
      const res = await http.put(`/room-locks/${editingLockId.value}`, payload)
      if (res.data && res.data.success) {
        uiStore.showToast('Cập nhật thông tin khóa phòng thành công!', 'success')
        isBulkModalOpen.value = false
        selectedRowKeys.value = []
        fetchRooms()
        if (activeHistoryRoom.value && activeHistoryRoom.value.room_number === modalSelectedRoomNumbers.value[0]) {
          showHistory(activeHistoryRoom.value)
        }
        if (bc) bc.postMessage('rooms-updated')
      }
    } else {
      payload.room_numbers = modalSelectedRoomNumbers.value
      const res = await http.post('/room-locks/bulk-lock', payload)
      if (res.data && res.data.success) {
        uiStore.showToast(`Đã khóa thành công ${modalSelectedRoomNumbers.value.length} phòng dạng ${bulkLockType.value}!`, 'success')
        isBulkModalOpen.value = false
        selectedRowKeys.value = []
        fetchRooms()
        if (bc) bc.postMessage('rooms-updated')
      }
    }
  } catch (err) {
    console.error('Lỗi khi lưu khóa phòng:', err)
    
    // Check if it is a confirmation request for booking overlap
    if (err.response && err.response.data && err.response.data.require_confirm) {
      const confirmed = await uiStore.confirm({
        title: 'Cảnh báo',
        message: err.response.data.message || 'Phòng âm. Bạn có muốn tiếp tục thao tác?',
        confirmText: 'Tiếp tục',
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

// ==================== BATCH / INLINE EDITING ====================
const isBatchEditing = ref(false)
const savingBatch = ref(false)
const editedLocks = ref({})

const isLockStartDateDisabled = (lock) => {
  if (!lock || !lock.lock_start_date) return true
  const startDateStr = lock.lock_start_date.split(' ')[0]
  const sysDate = systemDate.value || getTodayString()
  return startDateStr <= sysDate
}

const startBatchEdit = () => {
  const newEdited = {}
  flatRows.value.forEach(r => {
    if (r.currentLock) {
      const lock = r.currentLock
      const startParts = lock.lock_start_date ? lock.lock_start_date.split(' ') : []
      const endParts = lock.lock_end_date ? lock.lock_end_date.split(' ') : []
      newEdited[lock.lock_id] = {
        lock_id: lock.lock_id,
        room_number: r.room_number,
        start_date: startParts[0] || '',
        start_time: startParts[1] ? startParts[1].substring(0, 5) : '00:00',
        end_date: endParts[0] || '',
        end_time: endParts[1] ? endParts[1].substring(0, 5) : defaultLockEndTime.value,
        reason: lock.lock_reason || '',
        maintenance_percent: lock.lock_maintenance_percent ?? 0,
        original_start_date: startParts[0] || '',
      }
    }
  })

  if (Object.keys(newEdited).length === 0) {
    uiStore.showToast('Không có phòng nào đang khóa để chỉnh sửa!', 'warning')
    return
  }

  editedLocks.value = newEdited
  isBatchEditing.value = true
}

const cancelBatchEdit = () => {
  isBatchEditing.value = false
  editedLocks.value = {}
}

const submitBatchSave = async (force = false) => {
  const locksArray = Object.values(editedLocks.value)
  if (locksArray.length === 0) {
    isBatchEditing.value = false
    return
  }

  const sysDate = systemDate.value || getTodayString()

  // Validate all records before sending
  for (const item of locksArray) {
    if (!item.start_date) {
      uiStore.showToast(`Phòng ${item.room_number}: Vui lòng chọn Ngày bắt đầu!`, 'warning')
      return
    }
    // Nếu start_date ban đầu > sysDate thì không được đổi thành < sysDate
    if (item.original_start_date > sysDate && item.start_date < sysDate) {
      uiStore.showToast(`Phòng ${item.room_number}: Ngày bắt đầu không được nhỏ hơn ngày hệ thống (${sysDate})!`, 'warning')
      return
    }
    if (!item.end_date) {
      uiStore.showToast(`Phòng ${item.room_number}: Vui lòng chọn Ngày kết thúc!`, 'warning')
      return
    }
    if (item.end_date < item.start_date) {
      uiStore.showToast(`Phòng ${item.room_number}: Ngày kết thúc không được nhỏ hơn Ngày bắt đầu!`, 'warning')
      return
    }
    // Nếu phòng active (start_date <= sysDate), end_date không được < sysDate
    if (item.original_start_date <= sysDate && item.end_date < sysDate) {
      uiStore.showToast(`Phòng ${item.room_number}: Ngày kết thúc không được nhỏ hơn Ngày hệ thống (${sysDate})!`, 'warning')
      return
    }
    if (!item.reason || !item.reason.trim()) {
      uiStore.showToast(`Phòng ${item.room_number}: Vui lòng nhập lý do/mô tả!`, 'warning')
      return
    }
  }

  savingBatch.value = true
  try {
    const payload = {
      locks: locksArray.map(item => ({
        lock_id: item.lock_id,
        start_date: `${item.start_date} ${item.start_time || '00:00'}:00`,
        end_date: `${item.end_date} ${item.end_time || '23:59'}:00`,
        reason: item.reason,
        maintenance_percent: parseInt(item.maintenance_percent) || 0,
      })),
      force: force
    }

    const res = await http.post('/room-locks/bulk-update', payload)
    if (res.data && res.data.success) {
      uiStore.showToast(res.data.message || 'Cập nhật danh sách phòng khóa thành công!', 'success')
      isBatchEditing.value = false
      editedLocks.value = {}
      fetchRooms()
      if (bc) bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error('Lỗi khi lưu hàng loạt phòng khóa:', err)
    if (err.response?.data?.require_confirm) {
      const confirmed = await uiStore.confirm({
        title: 'Cảnh báo',
        message: err.response.data.message || 'Phòng âm. Bạn có muốn tiếp tục thao tác?',
        confirmText: 'Tiếp tục',
        cancelText: 'Hủy'
      })
      if (confirmed) {
        await submitBatchSave(true)
      }
    } else {
      const errMsg = err.response?.data?.message || 'Không thể cập nhật danh sách phòng khóa'
      uiStore.showToast(errMsg, 'error')
    }
  } finally {
    savingBatch.value = false
  }
}
// Toggle active row menu
const toggleRowMenu = (rowKey, event) => {
  if (activeRowMenuId.value === rowKey) {
    activeRowMenuId.value = null
  } else {
    activeRowMenuId.value = rowKey
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
            <button
              v-if="searchQuery"
              type="button"
              @click="searchQuery = ''"
              class="text-slate-400 hover:text-slate-600 ml-1 shrink-0 bg-transparent border-none p-0 cursor-pointer flex items-center"
              title="Xóa tìm kiếm"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
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
          <!-- Sửa hàng loạt (Inline Edit) -->
          <button 
            v-if="!isBatchEditing"
            @click="startBatchEdit"
            class="btn-pms-secondary h-8 px-3"
            title="Bật chế độ sửa trực tiếp danh sách phòng khóa"
          >
            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.83 20.013a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
            </svg>
            <span>Sửa</span>
          </button>

          <template v-else>
            <button 
              @click="cancelBatchEdit"
              :disabled="savingBatch"
              class="btn-pms-secondary h-8 px-3"
              :class="{ 'opacity-50 cursor-not-allowed': savingBatch }"
            >
              <span>Hủy</span>
            </button>
            <button 
              @click="() => submitBatchSave(false)"
              :disabled="savingBatch"
              class="btn-pms-primary h-8 px-3"
              :class="{ 'opacity-50 cursor-not-allowed': savingBatch }"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
              </svg>
              <span>{{ savingBatch ? 'Đang lưu...' : 'Lưu' }}</span>
            </button>
          </template>

          <!-- Unlock -->
          <button 
            :disabled="isBatchEditing"
            @click="submitBulkUnlock"
            class="px-3 h-8 border border-emerald-500 hover:bg-emerald-50 text-emerald-600 rounded-lg font-semibold flex items-center gap-1.5 transition-all cursor-pointer text-xs shadow-3xs disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <RoomIcon name="unlock-outline" class="w-3.5 h-3.5 text-emerald-600" />
            <span>Mở khóa</span>
          </button>

          <!-- Lock OOS -->
          <button 
            :disabled="isBatchEditing"
            @click="openBulkLockModal('OOS')"
            class="px-3 h-8 bg-[#f97316] hover:bg-[#ea580c] text-white border-none rounded-lg font-semibold flex items-center gap-1.5 transition-all cursor-pointer text-xs shadow-2xs disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <RoomIcon name="oos" class="w-3.5 h-3.5 text-white" />
            <span>Khóa phòng OOS</span>
          </button>

          <!-- Lock OOO -->
          <button 
            :disabled="isBatchEditing"
            @click="openBulkLockModal('OOO')"
            class="px-3 h-8 bg-[#ef4444] hover:bg-[#dc2626] text-white border-none rounded-lg font-semibold flex items-center gap-1.5 transition-all cursor-pointer text-xs shadow-2xs disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <RoomIcon name="ooo-outline" class="w-3.5 h-3.5 text-white" />
            <span>Khóa phòng OOO</span>
          </button>
        </div>
      </div>

      <!-- Table Body Area -->
      <div class="flex-1 overflow-x-auto overflow-y-auto min-h-[200px]">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-[#000000D9] font-semibold h-9 whitespace-nowrap sticky top-0 z-10 text-xs">
              <th class="p-2.5 border-r border-slate-200 text-center w-[45px] align-middle border">
                <input 
                  type="checkbox" 
                  @change="toggleSelectAll" 
                  :checked="isAllSelected" 
                  class="cursor-pointer w-4 h-4" 
                />
              </th>
              <th class="p-2.5 border-r border-slate-200 w-[80px] text-center align-middle border">Phòng</th>
              <th class="p-2.5 border-r border-slate-200 text-center align-middle border">Loại Phòng</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[130px] align-middle border">Trạng Thái Phòng</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[125px] align-middle border">Ngày Bắt Đầu</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[125px] align-middle border">Ngày Mở Khóa</th>
              <th class="p-2.5 border-r border-slate-200 min-w-[180px] text-center align-middle border">Lý Do/Mô Tả</th>
              <th class="p-2.5 border-r border-slate-200 w-[110px] text-center align-middle border">Người Dùng</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[85px] align-middle border">Bảo Trì (%)</th>
              <th class="p-2.5 border-r border-slate-200 text-center w-[125px] align-middle border">Trạng Thái Bảo Trì</th>
              <th class="p-2.5 text-center w-[50px] align-middle border-r border-slate-200 border"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" class="h-24">
              <td colspan="11" class="text-center text-slate-500 font-semibold text-xs border-slate-200">Đang tải danh sách phòng...</td>
            </tr>
            <tr v-else-if="sortedFloors.length === 0" class="h-24">
              <td colspan="11" class="text-center text-slate-400 italic text-xs border-slate-200">Không tìm thấy phòng nào phù hợp</td>
            </tr>
            
            <template v-else v-for="floor in sortedFloors" :key="floor">
              <!-- Floor separator -->
              <tr 
                @click="toggleFloor(floor)"
                class="bg-slate-50 border-b border-slate-200 font-extrabold h-9 cursor-pointer hover:bg-slate-100 transition-colors"
              >
                <td colspan="11" class="p-2.5 pl-4 text-slate-700 bg-slate-50/80 border-slate-200">
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
                :key="room.currentLock ? 'lock-' + room.currentLock.lock_id : 'room-' + room.id"
                class="border-b border-slate-200 hover:bg-[#bdecfe]/20 h-11 transition-colors font-semibold text-slate-700 text-xs"
                :class="{
                  'bg-[#c9eeff]/20': selectedRowKeys.includes(room.currentLock ? room.currentLock.lock_id : room.room_number)
                }"
              >
                <!-- Checkbox -->
                <td class="p-2.5 border-slate-200 text-center">
                  <input 
                    type="checkbox" 
                    :value="room.currentLock ? room.currentLock.lock_id : room.room_number" 
                    v-model="selectedRowKeys" 
                    class="cursor-pointer rounded border border-slate-300 w-4 h-4" 
                  />
                </td>

                <!-- Room Number -->
                <td class="p-2.5 border-slate-200 font-extrabold text-slate-800 text-center">{{ room.room_number }}</td>

                <!-- Room Class Name -->
                <td class="p-2.5 border-slate-200 text-slate-655 font-medium">{{ room.room_type_name || '-' }}</td>

                <!-- Room Status dot badge -->
                <td class="p-2.5 border-slate-200 text-center">
                  <span 
                    v-if="room.lock_type?.toUpperCase() === 'OOO'"
                    class="px-2.5 py-1 rounded-full font-bold text-xs inline-flex items-center gap-1.5 shadow-3xs"
                    :class="room.currentLock?.is_future ? 'bg-red-50/50 text-red-400 border border-red-100' : 'bg-red-50 text-red-600 border border-red-200'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="room.currentLock?.is_future ? 'bg-red-300' : 'bg-red-500'"></span>
                    OOO
                  </span>
                  <span 
                    v-else-if="room.lock_type?.toUpperCase() === 'OOS'"
                    class="px-2.5 py-1 rounded-full font-bold text-xs inline-flex items-center gap-1.5 shadow-3xs"
                    :class="room.currentLock?.is_future ? 'bg-orange-50/50 text-orange-400 border border-orange-100' : 'bg-orange-50 text-orange-700 border border-orange-200'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="room.currentLock?.is_future ? 'bg-orange-400' : 'bg-orange-500'"></span>
                    OOS
                  </span>
                </td>

                <!-- Start Date -->
                <td class="p-2 border-slate-200 text-center font-normal text-slate-500">
                  <template v-if="isBatchEditing && room.currentLock && editedLocks[room.currentLock.lock_id]">
                    <div class="max-w-[130px] mx-auto">
                      <SingleDatePicker 
                        v-model="editedLocks[room.currentLock.lock_id].start_date" 
                        :disabled="isLockStartDateDisabled(room.currentLock)"
                        :min-date="isLockStartDateDisabled(room.currentLock) ? undefined : (systemDate || getTodayString())"
                        :title="isLockStartDateDisabled(room.currentLock) ? 'Ngày bắt đầu <= ngày hệ thống nên không được sửa' : ''"
                        placeholder="dd/mm/yyyy"
                        four-digit-year
                        input-class="!h-7 !px-1.5"
                        text-input-class="!font-normal text-center"
                      />
                    </div>
                  </template>
                  <template v-else>
                    {{ formatDateDisplay(room.lock_start_date) || '-' }}
                  </template>
                </td>

                <!-- End Date -->
                <td class="p-2 border-slate-200 text-center font-normal text-slate-500">
                  <template v-if="isBatchEditing && room.currentLock && editedLocks[room.currentLock.lock_id]">
                    <div class="max-w-[130px] mx-auto">
                      <SingleDatePicker 
                        v-model="editedLocks[room.currentLock.lock_id].end_date" 
                        :min-date="isLockStartDateDisabled(room.currentLock) ? (systemDate || getTodayString()) : (editedLocks[room.currentLock.lock_id].start_date || systemDate || getTodayString())"
                        placeholder="dd/mm/yyyy"
                        four-digit-year
                        input-class="!h-7 !px-1.5"
                        text-input-class="!font-normal text-center"
                      />
                    </div>
                  </template>
                  <template v-else>
                    {{ formatDateDisplay(room.lock_end_date) || '-' }}
                  </template>
                </td>

                <!-- Lock Reason -->
                <td class="p-2 border-slate-200 font-normal text-slate-600 truncate max-w-[200px]" :title="room.lock_reason">
                  <template v-if="isBatchEditing && room.currentLock && editedLocks[room.currentLock.lock_id]">
                    <div class="relative flex items-center w-full">
                      <input 
                        type="text" 
                        v-model="editedLocks[room.currentLock.lock_id].reason" 
                        placeholder="Lý do/mô tả..."
                        class="border border-slate-300 rounded pl-2 pr-6 py-0.5 text-xs font-normal text-slate-700 bg-white w-full focus:outline-sky-400"
                      />
                      <button
                        v-if="editedLocks[room.currentLock.lock_id].reason"
                        type="button"
                        @click="editedLocks[room.currentLock.lock_id].reason = ''"
                        class="absolute right-1 text-slate-400 hover:text-rose-500 p-0.5 rounded cursor-pointer border-none bg-transparent flex items-center justify-center transition-colors"
                        title="Xóa lý do"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </template>
                  <template v-else>
                    {{ room.lock_reason || '-' }}
                  </template>
                </td>

                <!-- Username -->
                <td class="p-2.5 border-slate-200 font-normal text-slate-500">
                  {{ room.lock_username || '-' }}
                </td>

                <!-- Maintenance Percent -->
                <td class="p-2 border-slate-200 text-center font-normal text-slate-500">
                  <template v-if="isBatchEditing && room.currentLock && editedLocks[room.currentLock.lock_id]">
                    <div class="flex items-center justify-center gap-0.5 max-w-[95px] mx-auto">
                      <button 
                        type="button" 
                        @click="stepBatchMaintenancePercent(room.currentLock.lock_id, -5)"
                        class="w-5 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center border border-slate-200 cursor-pointer font-bold text-xs shrink-0 select-none"
                        title="Giảm 5%"
                      >-</button>
                      <input 
                        type="number" 
                        min="0" 
                        max="100" 
                        v-model.number="editedLocks[room.currentLock.lock_id].maintenance_percent" 
                        class="border border-slate-300 rounded px-1 py-0.5 text-xs font-semibold text-slate-700 bg-white w-[38px] text-center focus:outline-sky-400"
                      />
                      <button 
                        type="button" 
                        @click="stepBatchMaintenancePercent(room.currentLock.lock_id, 5)"
                        class="w-5 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center border border-slate-200 cursor-pointer font-bold text-xs shrink-0 select-none"
                        title="Tăng 5%"
                      >+</button>
                      <span class="text-xs text-slate-400 font-semibold ml-0.5">%</span>
                    </div>
                  </template>
                  <template v-else>
                    {{ room.lock_type ? room.lock_maintenance_percent + '%' : '-' }}
                  </template>
                </td>

                <!-- Maintenance status -->
                <td class="p-2.5 border-slate-200 text-center">
                  <span :class="getMaintenanceStatusClass(room)">
                    {{ getMaintenanceStatusLabel(room) }}
                  </span>
                </td>

                <!-- Row actions (⋮ menu) -->
                <td class="p-2.5 text-center relative row-menu-container border-slate-200">
                  <button 
                    @click.stop="toggleRowMenu(room.currentLock ? 'lock-' + room.currentLock.lock_id : 'room-' + room.id, $event)"
                    class="w-6.5 h-6.5 rounded hover:bg-slate-100 flex items-center justify-center border-none cursor-pointer text-slate-400 hover:text-slate-600 transition-colors mx-auto"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                    </svg>
                  </button>

                  <!-- Popover Dropdown menu options -->
                  <div 
                    v-if="activeRowMenuId === (room.currentLock ? 'lock-' + room.currentLock.lock_id : 'room-' + room.id)" 
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
          <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Dữ liệu gần đây</div>
          
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
              <div class="text-xs text-slate-400 font-medium mb-1">{{ formatDateTime(event.timestamp) }}</div>
              
              <!-- Card details body -->
              <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 relative hover:shadow-xs transition-shadow">
                <h5 class="text-xs font-semibold text-[#000000D9] mb-1">{{ getTimelineEventLabel(event) }}</h5>
                
                <!-- Details specific for lock -->
                <div v-if="event.type === 'lock'" class="text-xs text-slate-600 font-normal flex flex-col gap-0.5">
                  <div><span class="text-slate-400 font-normal">Ngày bắt đầu:</span> {{ formatDateTime(event.start_date) }}</div>
                  <div><span class="text-slate-400 font-normal">Ngày mở khóa:</span> {{ formatDateTime(event.end_date) }}</div>
                  <div class="mt-1 font-normal italic text-slate-600"><span class="text-slate-400 not-italic font-semibold">Lý do:</span> {{ event.reason || '-' }}</div>
                </div>

                <!-- Details specific for unlock -->
                <div v-else-if="event.type === 'unlock'" class="text-xs text-slate-600 font-normal flex flex-col gap-0.5">
                  <div><span class="text-slate-400 font-normal">Ngày bắt đầu:</span> {{ formatDateTime(event.start_date) }}</div>
                  <div><span class="text-slate-400 font-normal">Ngày mở khóa:</span> {{ formatDateTime(event.unlock_date) }}</div>
                  <div v-if="event.unlock_username" class="mt-0.5"><span class="text-slate-400 font-normal">Người mở khóa:</span> {{ event.unlock_username }}</div>
                </div>

                <!-- Footer meta: user and badge status -->
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-150/80">
                  <div class="flex items-center gap-1 text-xs text-slate-400">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span>{{ event.type === 'unlock' ? (event.unlock_username || 'Admin') : (event.username || 'Admin') }}</span>
                  </div>
                  <span 
                    v-if="event.type === 'lock'"
                    class="px-2 py-0.5 rounded text-xs font-semibold uppercase"
                    :class="event.maintenance_percent === 100 ? 'bg-green-50 text-green-600 border border-green-200' : 'bg-sky-50 text-sky-600 border border-sky-200'"
                  >
                    {{ event.maintenance_percent === 100 ? 'Hoàn tất' : 'Đang xử lý' }}
                  </span>
                  <span 
                    v-else
                    class="px-2 py-0.5 rounded text-xs font-semibold uppercase bg-emerald-50 text-emerald-600 border border-emerald-200"
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
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/20 font-bold animate-in fade-in duration-200"
      @click.self="isBulkModalOpen = false"
    >
      <div 
        class="bg-white shadow-2xl border border-slate-200 rounded-xl w-[620px] max-w-[95vw] overflow-hidden"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
      >
        <!-- Modal Header -->
        <div 
          class="px-5 py-3 flex items-center justify-between text-white rounded-t-xl cursor-move select-none"
          :style="{ background: 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }"
          @mousedown="startDragModal"
        >
          <h2 class="text-xs font-semibold uppercase tracking-wider m-0 text-white">
            {{ editingLockId ? 'Chỉnh sửa thông tin khóa' : 'Thêm khóa' }}
          </h2>
          <button 
            @click="isBulkModalOpen = false" 
            class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-sm font-semibold transition-colors"
            title="Đóng (Esc)"
          >
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div 
          class="p-5 text-[#000000D9] grid grid-cols-1 md:grid-cols-[7fr_5fr] gap-5"
        >
          <!-- Left fields col -->
          <div class="flex flex-col gap-4">
            <!-- Start Date -->
            <div class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-[#000000D9]">Bắt đầu</span>
              <SingleDatePicker 
                v-model="bulkForm.start_date" 
                :disabled="isEditingActiveLock"
                :min-date="isEditingActiveLock ? undefined : (systemDate || getTodayString())"
                :title="isEditingActiveLock ? 'Ngày bắt đầu <= ngày hệ thống nên không được sửa' : ''"
                placeholder="dd/mm/yyyy"
                four-digit-year
                input-class="!h-8"
                text-input-class="!font-normal"
              />
            </div>

            <!-- End Date -->
            <div class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-[#000000D9]">Kết thúc</span>
              <SingleDatePicker 
                v-model="bulkForm.end_date" 
                :min-date="isEditingActiveLock ? (systemDate || getTodayString()) : (bulkForm.start_date || systemDate || getTodayString())"
                placeholder="dd/mm/yyyy"
                four-digit-year
                input-class="!h-8"
                text-input-class="!font-normal"
              />
            </div>

            <!-- Room selection selector dropdown (Only visible when not editing single lock) -->
            <div class="flex flex-col gap-1 modal-room-dropdown-container">
              <span class="text-xs font-semibold text-[#000000D9]">Phòng</span>
              <div class="relative w-full">
                <button 
                  @click.stop="editingLockId ? null : (isRoomDropdownOpen = !isRoomDropdownOpen)" 
                  class="w-full flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold text-[#000000D9] transition-all"
                  :class="editingLockId ? 'bg-slate-100/70 opacity-65 cursor-not-allowed' : 'bg-white hover:border-slate-300 cursor-pointer'"
                >
                  <span v-if="editingLockId">
                    Phòng: {{ modalSelectedRoomNumbers[0] }}
                  </span>
                  <span v-else-if="modalSelectedRoomNumbers.length === 0" class="text-[#A8B0BF] font-normal">
                    Chọn phòng...
                  </span>
                  <span v-else-if="modalSelectedRoomNumbers.length === 1">
                    Phòng: {{ modalSelectedRoomNumbers[0] }}
                  </span>
                  <span v-else class="truncate block max-w-[280px]" :title="modalSelectedRoomNumbers.join(', ')">
                    Phòng: {{ modalSelectedRoomNumbers.join(', ') }}
                  </span>
                  <svg 
                    v-if="!editingLockId"
                    class="w-3.5 h-3.5 text-slate-400 transform transition-transform shrink-0 ml-1" 
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
                      class="border-none bg-transparent w-full focus:outline-none text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF]"
                    />
                    <button
                      v-if="roomSearchQuery"
                      type="button"
                      @click="roomSearchQuery = ''"
                      class="text-slate-400 hover:text-slate-600 p-0.5 rounded cursor-pointer border-none bg-transparent flex items-center justify-center transition-colors mr-1"
                      title="Xóa tìm kiếm"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                    <svg class="w-3 h-3 text-slate-400 ml-1 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
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
                      <span class="font-semibold text-[#000000D9]">Tất cả</span>
                    </label>
                    <label 
                      v-for="r in modalFilteredRooms" 
                      :key="r.id" 
                      class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1.5 rounded text-xs"
                    >
                      <input 
                        type="checkbox" 
                        :value="r.room_number" 
                        v-model="modalSelectedRoomNumbers" 
                        class="cursor-pointer"
                      />
                      <span class="font-normal text-[#000000D9]">{{ r.room_number }} - {{ r.room_type_name || '-' }}</span>
                    </label>
                  </div>

                  <!-- Close button inside dropdown -->
                  <button 
                    @click="isRoomDropdownOpen = false"
                    class="w-full h-8 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-semibold cursor-pointer border-none text-xs shadow-3xs transition-colors flex items-center justify-center"
                  >
                    Xác nhận
                  </button>
                </div>
              </div>
            </div>

            <!-- Maintenance Progress percent -->
            <div class="flex flex-col gap-1">
              <span class="text-xs font-semibold text-[#000000D9]">Tiến độ bảo trì</span>
              <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white focus-within:border-sky-400 transition-colors h-[32px]">
                <button 
                  type="button" 
                  @click="stepMaintenancePercent(-5)"
                  class="px-2.5 h-full bg-slate-50 hover:bg-slate-100 text-slate-600 border-r border-slate-200 cursor-pointer font-bold text-xs select-none"
                  title="Giảm 5%"
                >-</button>
                <input 
                  type="number" 
                  min="0" 
                  max="100" 
                  v-model.number="bulkForm.maintenance_percent" 
                  class="border-none outline-none px-3 py-1.5 w-full text-xs font-normal text-[#000000D9] bg-transparent text-center" 
                />
                <button 
                  type="button" 
                  @click="stepMaintenancePercent(5)"
                  class="px-2.5 h-full bg-slate-50 hover:bg-slate-100 text-slate-600 border-l border-slate-200 cursor-pointer font-bold text-xs select-none"
                  title="Tăng 5%"
                >+</button>
                <span class="bg-slate-100 text-slate-500 font-semibold px-3 py-1.5 border-l border-slate-200 text-xs select-none">%</span>
              </div>
            </div>
          </div>

          <!-- Right Reason note block -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-[#000000D9] block">Ghi chú <span class="text-red-500">*</span></label>
            <div class="relative w-full">
              <textarea 
                v-model="bulkForm.reason" 
                placeholder="Nhập ghi chú hoặc lý do bảo trì..."
                class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2.5 pr-8 focus:outline-none focus:ring-1 focus:ring-amber-400 resize-none font-normal text-xs text-[#000000D9] leading-relaxed h-[138px]"
              ></textarea>
              <button
                v-if="bulkForm.reason"
                type="button"
                @click="bulkForm.reason = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-rose-500 p-0.5 rounded cursor-pointer border-none bg-transparent flex items-center justify-center transition-colors"
                title="Xóa ghi chú"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100 rounded-b-xl">
          <button 
            @click="submitBulkLock"
            class="btn-pms-primary"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            <span>{{ editingLockId ? 'Cập nhật' : 'Khóa phòng' }}</span>
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
