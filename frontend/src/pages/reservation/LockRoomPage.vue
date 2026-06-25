<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import RoomIcon from '@/components/RoomIcon.vue'

const uiStore = useUiStore()

// State variables
const rooms = ref([])
const loading = ref(false)
const selectedRoomIds = ref([])
const filterType = ref('all') // 'all' | 'unlocked' | 'locked'
const editMode = ref(false)
const editedLocks = ref({}) // map room_id -> lock details for inline editing

// Floor expansion state
const expandedFloors = ref([])

// History Log panel state
const activeHistoryRoom = ref(null)
const historyLogs = ref([])
const loadingHistory = ref(false)

// Bulk Lock Modal State
const isBulkModalOpen = ref(false)
const bulkLockType = ref('OOO') // 'OOO' | 'OOS'
const bulkForm = ref({
  start_date: '2026-06-09', // Default start date to match screenshot date
  end_date: '2026-06-09',   // Default end date
  reason: '',
  maintenance_percent: 0,
})

// Modal custom room select dropdown states
const isRoomDropdownOpen = ref(false)
const modalSelectedRoomIds = ref([])
const roomSearchQuery = ref('')

// Search states inside table headers
const searchQueries = ref({
  room_number: '',
  room_form: '',
  room_class: '',
  start_date: '',
  end_date: '',
})

const isSearchRoomOpen = ref(false)
const isSearchRoomFormOpen = ref(false)
const isSearchRoomClassOpen = ref(false)
const isSearchStartDateOpen = ref(false)
const isSearchEndDateOpen = ref(false)

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

// Format date to DD/MM/YYYY for display
const formatDateDisplay = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  return `${day}/${month}/${d.getFullYear()}`
}

// Floor Grouping logic
const roomsByFloor = computed(() => {
  const grouped = {}
  
  // First apply status filters & header search filters to the raw rooms list
  let list = rooms.value

  if (filterType.value === 'locked') {
    list = list.filter(r => r.lock_type === 'OOO' || r.lock_type === 'OOS')
  } else if (filterType.value === 'unlocked') {
    list = list.filter(r => !r.lock_type)
  }

  // Apply column-level header searches
  if (searchQueries.value.room_number) {
    const q = searchQueries.value.room_number.toLowerCase()
    list = list.filter(r => r.room_number && r.room_number.toLowerCase().includes(q))
  }
  if (searchQueries.value.room_form) {
    const q = searchQueries.value.room_form.toLowerCase()
    list = list.filter(r => r.room_form?.name && r.room_form.name.toLowerCase().includes(q))
  }
  if (searchQueries.value.room_class) {
    const q = searchQueries.value.room_class.toLowerCase()
    list = list.filter(r => r.room_type_name && r.room_type_name.toLowerCase().includes(q))
  }
  if (searchQueries.value.start_date) {
    const q = searchQueries.value.start_date
    list = list.filter(r => r.lock_start_date && r.lock_start_date.includes(q))
  }
  if (searchQueries.value.end_date) {
    const q = searchQueries.value.end_date
    list = list.filter(r => r.lock_end_date && r.lock_end_date.includes(q))
  }

  // Group rooms by floor
  list.forEach(room => {
    if (!grouped[room.floor]) {
      grouped[room.floor] = []
    }
    grouped[room.floor].push(room)
  })

  // Sort rooms within each floor by room number
  Object.keys(grouped).forEach(fl => {
    grouped[fl].sort((a, b) => parseInt(a.room_number) - parseInt(b.room_number))
  })

  return grouped
})

const sortedFloors = computed(() => {
  return Object.keys(roomsByFloor.value)
    .map(Number)
    .sort((a, b) => a - b)
})

// Floor checkboxes select/deselect logic
const isFloorAllSelected = (floor) => {
  const floorRooms = roomsByFloor.value[floor] || []
  if (floorRooms.length === 0) return false
  return floorRooms.every(r => selectedRoomIds.value.includes(r.id))
}

const toggleSelectFloor = (floor, event) => {
  const floorRooms = roomsByFloor.value[floor] || []
  const floorRoomIds = floorRooms.map(r => r.id)
  
  if (event.target.checked) {
    floorRoomIds.forEach(id => {
      if (!selectedRoomIds.value.includes(id)) {
        selectedRoomIds.value.push(id)
      }
    })
  } else {
    selectedRoomIds.value = selectedRoomIds.value.filter(id => !floorRoomIds.includes(id))
  }
}

// Toggle floor expanded list
const toggleFloorExpanded = (floor) => {
  const idx = expandedFloors.value.indexOf(floor)
  if (idx > -1) {
    expandedFloors.value.splice(idx, 1)
  } else {
    expandedFloors.value.push(floor)
  }
}

const isFloorExpanded = (floor) => {
  return expandedFloors.value.includes(floor)
}

// Master toggle check all visible floors and rooms
const isAllSelected = computed(() => {
  const allVisibleRoomIds = []
  Object.values(roomsByFloor.value).forEach(floorRooms => {
    floorRooms.forEach(r => allVisibleRoomIds.push(r.id))
  })
  return allVisibleRoomIds.length > 0 && allVisibleRoomIds.every(id => selectedRoomIds.value.includes(id))
})

const toggleSelectAll = (event) => {
  if (event.target.checked) {
    const allVisibleRoomIds = []
    Object.values(roomsByFloor.value).forEach(floorRooms => {
      floorRooms.forEach(r => allVisibleRoomIds.push(r.id))
    })
    selectedRoomIds.value = allVisibleRoomIds
  } else {
    selectedRoomIds.value = []
  }
}

// History Log panel loader
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

// Bulk action triggers
const openBulkLockModal = (type) => {
  bulkLockType.value = type
  bulkForm.value.reason = ''
  bulkForm.value.maintenance_percent = 0
  
  // Initialize modalSelectedRoomIds with selected room IDs
  modalSelectedRoomIds.value = [...selectedRoomIds.value]
  isRoomDropdownOpen.value = false
  roomSearchQuery.value = ''
  isBulkModalOpen.value = true
}

const submitBulkLock = async () => {
  if (modalSelectedRoomIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một phòng cần khóa!', 'warning')
    return
  }
  
  if (!bulkForm.value.reason.trim()) {
    uiStore.showToast('Vui lòng nhập lý do khóa phòng ở ô Ghi chú!', 'warning')
    return
  }

  try {
    const payload = {
      room_ids: modalSelectedRoomIds.value,
      start_date: bulkForm.value.start_date,
      end_date: bulkForm.value.end_date,
      reason: bulkForm.value.reason,
      maintenance_percent: bulkForm.value.maintenance_percent,
      lock_type: bulkLockType.value,
    }

    const res = await http.post('/room-locks/bulk-lock', payload)
    if (res.data && res.data.success) {
      uiStore.showToast(`Đã khóa thành công ${modalSelectedRoomIds.value.length} phòng dạng ${bulkLockType.value}!`, 'success')
      isBulkModalOpen.value = false
      selectedRoomIds.value = []
      fetchRooms()
      if (bc) {
        bc.postMessage('rooms-updated')
      }
    }
  } catch (err) {
    console.error('Lỗi khóa phòng hàng loạt:', err)
    uiStore.showToast('Không thể khóa phòng. Vui lòng kiểm tra lại.', 'error')
  }
}

const submitBulkUnlock = async () => {
  if (selectedRoomIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một phòng cần mở khóa!', 'warning')
    return
  }

  if (!confirm(`Bạn có chắc chắn muốn mở khóa cho ${selectedRoomIds.value.length} phòng đã chọn?`)) {
    return
  }

  try {
    const res = await http.post('/room-locks/bulk-unlock', { room_ids: selectedRoomIds.value })
    if (res.data && res.data.success) {
      uiStore.showToast(`Đã mở khóa thành công ${selectedRoomIds.value.length} phòng!`, 'success')
      selectedRoomIds.value = []
      fetchRooms()
      if (activeHistoryRoom.value) {
        showHistory(activeHistoryRoom.value)
      }
      if (bc) {
        bc.postMessage('rooms-updated')
      }
    }
  } catch (err) {
    console.error('Lỗi mở khóa phòng:', err)
    uiStore.showToast('Không thể mở khóa phòng', 'error')
  }
}

// Inline edit mode triggers
const enableEditMode = () => {
  if (selectedRoomIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn các phòng bạn muốn sửa trực tiếp trên bảng trước!', 'warning')
    return
  }

  editMode.value = true
  selectedRoomIds.value.forEach(id => {
    const room = rooms.value.find(r => r.id === id)
    if (room) {
      editedLocks.value[id] = {
        start_date: room.lock_start_date || '2026-06-09',
        end_date: room.lock_end_date || '2026-06-09',
        reason: room.lock_reason || '',
        maintenance_percent: room.lock_maintenance_percent || 0,
        lock_type: room.lock_type || 'OOS',
      }
    }
  })
}

const saveInlineEdits = async () => {
  loading.value = true
  try {
    const promises = selectedRoomIds.value.map(id => {
      const data = editedLocks.value[id]
      return http.post('/room-locks/bulk-lock', {
        room_ids: [id],
        start_date: data.start_date,
        end_date: data.end_date,
        reason: data.reason,
        maintenance_percent: data.maintenance_percent,
        lock_type: data.lock_type,
      })
    })

    await Promise.all(promises)
    uiStore.showToast('Lưu thông tin khóa phòng thành công!', 'success')
    editMode.value = false
    editedLocks.value = {}
    selectedRoomIds.value = []
    fetchRooms()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error('Lỗi khi lưu thông tin chỉnh sửa:', err)
    uiStore.showToast('Có lỗi xảy ra khi lưu thông tin chỉnh sửa', 'error')
  } finally {
    loading.value = false
  }
}

const cancelInlineEdits = () => {
  editMode.value = false
  editedLocks.value = {}
}

// Modal room dropdown checklist logic
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

const applyModalRoomSelection = () => {
  isRoomDropdownOpen.value = false
}

const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    isSearchRoomOpen.value = false
    isSearchRoomFormOpen.value = false
    isSearchRoomClassOpen.value = false
    isSearchStartDateOpen.value = false
    isSearchEndDateOpen.value = false
  }
  if (isRoomDropdownOpen.value && !e.target.closest('.modal-room-dropdown-container')) {
    isRoomDropdownOpen.value = false
  }
}

let bc = null

const handleTabFocus = () => {
  fetchRooms()
}

onMounted(() => {
  fetchRooms()
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
</script>

<template>
  <div class="h-full flex flex-col gap-4 overflow-hidden text-xs text-slate-800">
    
    <!-- MAIN WORK AREA CARD -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col min-h-[350px] flex-1">
      
      <!-- Top Title Bar -->
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between shrink-0">
        <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Phòng</h3>
      </div>

      <!-- Action Toolbar (Matches screenshot) -->
      <div class="p-3 border-b border-slate-100 flex items-center gap-2 bg-white shrink-0 flex-wrap select-none justify-between">
        
        <!-- Action Badges (Left) -->
        <div class="flex items-center gap-2">
          <!-- Button OOO (Padlock Blue Style) -->
          <button 
            @click="openBulkLockModal('OOO')"
            :disabled="editMode"
            class="px-3 py-1.5 bg-[#8dcbf4]/20 hover:bg-[#8dcbf4]/35 text-[#0369a1] border border-[#8dcbf4]/40 rounded-lg font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed text-xs"
          >
            <RoomIcon name="ooo-outline" class="w-3.5 h-3.5" />
            Phòng OOO
          </button>

          <!-- Button OOS (Padlock Blue Style) -->
          <button 
            @click="openBulkLockModal('OOS')"
            :disabled="editMode"
            class="px-3 py-1.5 bg-[#8dcbf4]/20 hover:bg-[#8dcbf4]/35 text-[#0369a1] border border-[#8dcbf4]/40 rounded-lg font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed text-xs"
          >
            <RoomIcon name="oos" class="w-3.5 h-3.5" />
            Phòng OOS
          </button>

          <!-- Button Mở khóa (Gray Style) -->
          <button 
            @click="submitBulkUnlock"
            :disabled="editMode"
            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 rounded-lg font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed text-xs"
          >
            <RoomIcon name="unlock-outline" class="w-3.5 h-3.5" />
            Mở khóa
          </button>

          <!-- Filter segment toggle tab -->
          <div class="flex border border-slate-200 rounded-lg overflow-hidden ml-2 shadow-xs bg-slate-50">
            <button 
              @click="filterType = 'all'"
              class="px-3.5 py-1.5 font-bold transition-all border-none cursor-pointer text-xs"
              :class="filterType === 'all' ? 'bg-[#bdecfe] text-[#0369a1]' : 'bg-white text-slate-600 hover:bg-slate-100'"
            >
              Tất cả
            </button>
            <button 
              @click="filterType = 'unlocked'"
              class="px-3.5 py-1.5 font-bold transition-all border-none border-l border-slate-200 cursor-pointer text-xs"
              :class="filterType === 'unlocked' ? 'bg-[#bdecfe] text-[#0369a1]' : 'bg-white text-slate-600 hover:bg-slate-100'"
            >
              Chưa khóa
            </button>
            <button 
              @click="filterType = 'locked'"
              class="px-3.5 py-1.5 font-bold transition-all border-none border-l border-slate-200 cursor-pointer text-xs"
              :class="filterType === 'locked' ? 'bg-[#bdecfe] text-[#0369a1]' : 'bg-white text-slate-600 hover:bg-slate-100'"
            >
              Khóa
            </button>
          </div>
        </div>

        <!-- Inline Edit Action Buttons (Right) -->
        <div class="flex items-center gap-2">
          <button 
            v-if="!editMode"
            @click="enableEditMode"
            class="px-3.5 py-1.5 bg-sky-100 hover:bg-sky-200 text-sky-700 border border-sky-200 rounded-lg font-bold cursor-pointer transition-all shadow-xs flex items-center gap-1.5 text-xs"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.83 20.013a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
            </svg>
            Sửa
          </button>
          <template v-else>
            <button 
              @click="saveInlineEdits"
              class="px-3.5 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-bold transition-colors shadow-sm cursor-pointer border-none flex items-center gap-1.5 text-xs"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              Lưu
            </button>
            <button 
              @click="cancelInlineEdits"
              class="px-3.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-bold transition-colors cursor-pointer border-none text-xs"
            >
              Hủy
            </button>
          </template>
        </div>

      </div>

      <!-- Main Room Lock List Table with Floor Grouping -->
      <div class="flex-1 overflow-x-auto overflow-y-auto min-h-[200px]">
        <table class="w-full text-left border-collapse text-xs select-none">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-200 text-slate-650 font-bold select-none h-9 whitespace-nowrap sticky top-0 z-10">
              <th class="p-2 border-r border-slate-200 text-center select-none" style="width: 45px; min-width: 45px; max-width: 45px;">
                <input 
                  type="checkbox" 
                  @change="toggleSelectAll" 
                  :checked="isAllSelected" 
                  class="cursor-pointer w-4 h-4" 
                />
              </th>
              <th class="p-2 border-r border-slate-200 text-center w-[75px]">STT</th>
              <th class="p-2 border-r border-slate-200 w-[100px] text-center relative popover-container select-none">
                <div class="flex items-center justify-between gap-1.5">
                  <span>Phòng</span>
                  <button 
                    @click.stop="isSearchRoomOpen = !isSearchRoomOpen; isSearchStartDateOpen = false; isSearchEndDateOpen = false" 
                    class="p-0.5 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-655 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                    :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueries.room_number}"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </button>
                </div>
                <!-- Search Popover -->
                <div v-if="isSearchRoomOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                  <div class="relative flex items-center">
                    <input 
                      v-model="searchQueries.room_number" 
                      type="text" 
                      placeholder="Tìm số phòng..." 
                      class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                      @click.stop
                    />
                    <button 
                      v-if="searchQueries.room_number" 
                      @click.stop="searchQueries.room_number = ''" 
                      class="absolute right-2 text-slate-400 hover:text-slate-655 bg-transparent border-none cursor-pointer text-xs"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </th>
              
              <!-- Dạng phòng -->
              <th class="p-2 border-r border-slate-200 w-[115px] text-center relative popover-container select-none">
                <div class="flex items-center justify-between gap-1.5">
                  <span>Dạng phòng</span>
                  <button 
                    @click.stop="isSearchRoomFormOpen = !isSearchRoomFormOpen; isSearchRoomOpen = false; isSearchRoomClassOpen = false; isSearchStartDateOpen = false; isSearchEndDateOpen = false" 
                    class="p-0.5 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-655 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                    :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueries.room_form}"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </button>
                </div>
                <!-- Search Popover -->
                <div v-if="isSearchRoomFormOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                  <div class="relative flex items-center">
                    <input 
                      v-model="searchQueries.room_form" 
                      type="text" 
                      placeholder="Tìm dạng phòng..." 
                      class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                      @click.stop
                    />
                    <button 
                      v-if="searchQueries.room_form" 
                      @click.stop="searchQueries.room_form = ''" 
                      class="absolute right-2 text-slate-400 hover:text-slate-655 bg-transparent border-none cursor-pointer text-xs"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </th>

              <!-- Loại phòng -->
              <th class="p-2 border-r border-slate-200 w-[170px] text-center relative popover-container select-none">
                <div class="flex items-center justify-between gap-1.5">
                  <span>Loại phòng</span>
                  <button 
                    @click.stop="isSearchRoomClassOpen = !isSearchRoomClassOpen; isSearchRoomOpen = false; isSearchRoomFormOpen = false; isSearchStartDateOpen = false; isSearchEndDateOpen = false" 
                    class="p-0.5 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-655 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                    :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueries.room_class}"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </button>
                </div>
                <!-- Search Popover -->
                <div v-if="isSearchRoomClassOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                  <div class="relative flex items-center">
                    <input 
                      v-model="searchQueries.room_class" 
                      type="text" 
                      placeholder="Tìm loại phòng..." 
                      class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                      @click.stop
                    />
                    <button 
                      v-if="searchQueries.room_class" 
                      @click.stop="searchQueries.room_class = ''" 
                      class="absolute right-2 text-slate-400 hover:text-slate-655 bg-transparent border-none cursor-pointer text-xs"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </th>

              <!-- Ngày bắt đầu with search icon -->
              <th class="p-2 border-r border-slate-200 w-[125px] text-center relative popover-container select-none">
                <div class="flex items-center justify-between gap-1.5">
                  <span>Ngày bắt đầu</span>
                  <button 
                    @click.stop="isSearchStartDateOpen = !isSearchStartDateOpen; isSearchRoomOpen = false; isSearchEndDateOpen = false" 
                    class="p-0.5 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-655 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                    :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueries.start_date}"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </button>
                </div>
                <!-- Search Popover -->
                <div v-if="isSearchStartDateOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                  <div class="relative flex items-center">
                    <input 
                      v-model="searchQueries.start_date" 
                      type="date" 
                      class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                      @click.stop
                    />
                    <button 
                      v-if="searchQueries.start_date" 
                      @click.stop="searchQueries.start_date = ''" 
                      class="absolute right-2 text-slate-400 hover:text-slate-655 bg-transparent border-none cursor-pointer text-xs"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </th>

              <!-- Ngày kết thúc with search icon -->
              <th class="p-2 border-r border-slate-200 w-[125px] text-center relative popover-container select-none">
                <div class="flex items-center justify-between gap-1.5">
                  <span>Ngày kết thúc</span>
                  <button 
                    @click.stop="isSearchEndDateOpen = !isSearchEndDateOpen; isSearchRoomOpen = false; isSearchStartDateOpen = false" 
                    class="p-0.5 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-655 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                    :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueries.end_date}"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </button>
                </div>
                <!-- Search Popover -->
                <div v-if="isSearchEndDateOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                  <div class="relative flex items-center">
                    <input 
                      v-model="searchQueries.end_date" 
                      type="date" 
                      class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                      @click.stop
                    />
                    <button 
                      v-if="searchQueries.end_date" 
                      @click.stop="searchQueries.end_date = ''" 
                      class="absolute right-2 text-slate-400 hover:text-slate-655 bg-transparent border-none cursor-pointer text-xs"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </th>

              <th class="p-2 border-r border-slate-200 min-w-[200px]">Mô tả khóa</th>
              <th class="p-2 border-r border-slate-200 text-center w-[85px]">Bảo trì (%)</th>
              <th class="p-2 border-r border-slate-200 text-center w-[90px]">Trạng thái</th>
              <th class="p-2 border-r border-slate-200 w-[95px]">Người dùng</th>
              <th class="p-2 border-r border-slate-200 text-center w-[90px]">Loại khóa</th>
              <th class="p-2 text-center w-[75px]">Lịch sử</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" class="h-24">
              <td colspan="13" class="text-center text-slate-500 font-semibold">Đang tải danh sách phòng...</td>
            </tr>
            <tr v-else-if="sortedFloors.length === 0" class="h-24">
              <td colspan="13" class="text-center text-slate-400 italic">Không tìm thấy phòng nào phù hợp</td>
            </tr>
            
            <!-- Floor loop (Render floors & expanded rooms) -->
            <template v-else v-for="floor in sortedFloors" :key="floor">
              
              <!-- Floor Row (Header Row of each floor) -->
              <tr 
                class="bg-slate-50/80 hover:bg-[#bdecfe]/35 border-b border-slate-200 font-bold h-9 transition-colors cursor-pointer"
                :class="{'bg-[#c9eeff]': isFloorAllSelected(floor)}"
              >
                <!-- Floor select checkbox -->
                <td class="p-2 border-r border-slate-200 text-center" style="width: 45px; min-width: 45px; max-width: 45px;">
                  <input 
                    type="checkbox" 
                    :checked="isFloorAllSelected(floor)"
                    @change="toggleSelectFloor(floor, $event)"
                    class="cursor-pointer w-4 h-4" 
                  />
                </td>
                
                <!-- STT column contains expand/collapse button & floor number -->
                <td class="p-2 border-r border-slate-200 w-[75px]">
                  <div class="flex items-center pl-2 gap-2">
                    <button 
                      @click.stop="toggleFloorExpanded(floor)"
                      class="w-4 h-4 rounded flex items-center justify-center text-[11px] font-bold cursor-pointer border border-sky-300 bg-sky-100 text-sky-700 hover:bg-[#bae6fd] hover:text-[#0369a1] transition-colors shadow-3xs"
                    >
                      {{ isFloorExpanded(floor) ? '-' : '+' }}
                    </button>
                    <span class="text-slate-700 ml-1 font-bold">{{ floor }}</span>
                  </div>
                </td>
                
                <!-- Rest of columns empty for floor row -->
                <td colspan="11" class="p-2 border-r border-slate-200"></td>
              </tr>

              <!-- Expanded Room Rows of this floor -->
              <template v-if="isFloorExpanded(floor)">
                <tr 
                  v-for="(room, idx) in roomsByFloor[floor]" 
                  :key="room.id"
                  class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-10 transition-colors"
                  :class="{
                    'bg-[#c9eeff]': selectedRoomIds.includes(room.id) || room.lock_type
                  }"
                >
                  <!-- Room select checkbox with indent alignment and fixed cell width -->
                  <td class="p-2 border-r border-slate-200 text-center" style="width: 45px; min-width: 45px; max-width: 45px;">
                    <input 
                      type="checkbox" 
                      :value="room.id" 
                      v-model="selectedRoomIds" 
                      class="cursor-pointer rounded border border-sky-400 accent-sky-600 transition-all" 
                      style="width: 12px; height: 12px; min-width: 12px; min-height: 12px; margin-left: 10px; box-shadow: 0 0 0 1.5px rgba(14, 165, 233, 0.4);"
                    />
                  </td>

                  <!-- STT inside expanded floor: show index starting from 1 with tree hierarchy icon -->
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-500 w-[75px]">
                    <div class="pl-3 flex items-center justify-center gap-1.5 text-slate-400">
                      <span class="font-normal select-none">└─</span>
                      <span class="text-slate-500">{{ idx + 1 }}</span>
                    </div>
                  </td>

                  <!-- Room Number & descriptions -->
                  <td class="p-2 border-r border-slate-200 text-center font-black text-slate-800 text-sm">{{ room.room_number }}</td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-500">{{ room.room_form?.name || '-' }}</td>
                  <td class="p-2 border-r border-slate-200 font-bold text-slate-700 truncate" :title="room.room_type_name">{{ room.room_type_name || '-' }}</td>

                  <!-- LOCK COLUMNS (INLINE EDITABLE) -->
                  
                  <!-- Start Date -->
                  <td class="p-2 border-r border-slate-200 text-center font-semibold">
                    <input 
                      v-if="editMode && selectedRoomIds.includes(room.id)" 
                      type="date" 
                      v-model="editedLocks[room.id].start_date" 
                      class="border border-slate-300 rounded px-1 py-0.5 text-center w-full focus:outline-sky-500 font-bold text-xs" 
                    />
                    <span v-else>{{ formatDateDisplay(room.lock_start_date) || '-' }}</span>
                  </td>

                  <!-- End Date -->
                  <td class="p-2 border-r border-slate-200 text-center font-semibold">
                    <input 
                      v-if="editMode && selectedRoomIds.includes(room.id)" 
                      type="date" 
                      v-model="editedLocks[room.id].end_date" 
                      class="border border-slate-300 rounded px-1 py-0.5 text-center w-full focus:outline-sky-500 font-bold text-xs" 
                    />
                    <span v-else>{{ formatDateDisplay(room.lock_end_date) || '-' }}</span>
                  </td>

                  <!-- Reason -->
                  <td class="p-2 border-r border-slate-200 font-semibold">
                    <input 
                      v-if="editMode && selectedRoomIds.includes(room.id)" 
                      type="text" 
                      v-model="editedLocks[room.id].reason" 
                      placeholder="Mô tả..."
                      class="border border-slate-300 rounded px-2 py-0.5 w-full focus:outline-sky-500 font-bold text-xs" 
                    />
                    <span v-else class="truncate block max-w-[200px]" :title="room.lock_reason">{{ room.lock_reason || '-' }}</span>
                  </td>

                  <!-- Maintenance Percent -->
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600">
                    <input 
                      v-if="editMode && selectedRoomIds.includes(room.id)" 
                      type="number" 
                      min="0" 
                      max="100"
                      v-model="editedLocks[room.id].maintenance_percent" 
                      class="border border-slate-300 rounded px-1 py-0.5 text-center w-[55px] focus:outline-sky-500 text-xs" 
                    />
                    <span v-else>{{ room.lock_type ? room.lock_maintenance_percent + '%' : '-' }}</span>
                  </td>

                  <!-- Status -->
                  <td class="p-2 border-r border-slate-200 text-center">
                    <span 
                      v-if="room.lock_status"
                      class="px-2 py-0.5 rounded-sm font-extrabold text-[10px] uppercase border"
                      :class="room.lock_status === 'Active' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                    >
                      {{ room.lock_status }}
                    </span>
                    <span v-else class="text-slate-400 font-semibold">-</span>
                  </td>

                  <!-- Username -->
                  <td class="p-2 border-r border-slate-200 font-bold text-slate-600">{{ room.lock_username || '-' }}</td>

                  <!-- Lock Type -->
                  <td class="p-2 border-r border-slate-200 text-center font-black">
                    <select 
                      v-if="editMode && selectedRoomIds.includes(room.id)" 
                      v-model="editedLocks[room.id].lock_type" 
                      class="border border-slate-300 rounded p-0.5 w-full bg-white focus:outline-sky-500 text-xs font-bold"
                    >
                      <option value="OOO">OOO</option>
                      <option value="OOS">OOS</option>
                    </select>
                    <span 
                      v-else-if="room.lock_type"
                      class="px-2.5 py-0.5 rounded font-black text-[10px] text-white shadow-3xs"
                      :style="{ backgroundColor: room.lock_type?.trim().toUpperCase() === 'OOO' ? '#ef4444' : '#f59e0b' }"
                    >
                      {{ room.lock_type?.toUpperCase() }}
                    </span>
                    <span v-else class="text-slate-400 font-semibold">-</span>
                  </td>

                  <!-- History log loader -->
                  <td class="p-2 text-center">
                    <button 
                      @click="showHistory(room)"
                      class="w-6 h-6 rounded-full bg-slate-100 hover:bg-slate-200 text-blue-500 flex items-center justify-center border-none cursor-pointer shadow-3xs transition-colors mx-auto"
                      title="Xem lịch sử khóa"
                    >
                      <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </template>

            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-4 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-between shrink-0 select-none">
        <span class="text-slate-500 font-semibold">Hiển thị tất cả phòng theo số tầng</span>
        <div class="flex items-center gap-1.5">
          <button class="w-7 h-7 rounded border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 font-bold flex items-center justify-center cursor-pointer shadow-3xs disabled:opacity-50" disabled>&lt;</button>
          <button class="w-7 h-7 rounded bg-[#bdecfe] text-[#0369a1] border border-[#7dd3fc] font-bold flex items-center justify-center shadow-3xs">1</button>
          <button class="w-7 h-7 rounded border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 font-bold flex items-center justify-center cursor-pointer shadow-3xs disabled:opacity-50" disabled>&gt;</button>
        </div>
      </div>

    </div>

    <!-- BOTTOM PANEL: LOCK HISTORY -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-[230px] shrink-0">
      
      <!-- Panel Header -->
      <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50/50 flex items-center gap-2 shrink-0">
        <svg class="w-4 h-4 text-sky-600 fill-none stroke-current" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">
          Lịch sử khóa {{ activeHistoryRoom ? `phòng ${activeHistoryRoom.room_number}` : '' }}
        </h4>
      </div>

      <!-- History Table Body -->
      <div class="flex-1 overflow-x-auto overflow-y-auto">
        <table class="w-full text-left border-collapse text-xs select-none">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold select-none h-8 sticky top-0 z-10">
              <th class="p-2 border-r border-slate-200 text-center w-[60px]">STT</th>
              <th class="p-2 border-r border-slate-200 text-center w-[120px]">Dạng phòng</th>
              <th class="p-2 border-r border-slate-200 w-[180px]">Loại phòng</th>
              <th class="p-2 border-r border-slate-200 text-center w-[120px]">Ngày bắt đầu</th>
              <th class="p-2 border-r border-slate-200 text-center w-[120px]">Ngày kết thúc</th>
              <th class="p-2 border-r border-slate-200 min-w-[200px]">Mô tả khóa</th>
              <th class="p-2 border-r border-slate-200 text-center w-[90px]">Bảo trì (%)</th>
              <th class="p-2 border-r border-slate-200 text-center w-[100px]">Trạng thái</th>
              <th class="p-2 border-r border-slate-200 w-[100px]">Người dùng</th>
              <th class="p-2 text-center w-[95px]">Loại khóa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!activeHistoryRoom">
              <td colspan="10" class="text-center p-6 text-slate-400 italic">Vui lòng click vào biểu tượng Lịch sử (clock) ở cột cuối của phòng bất kỳ để xem lịch sử khóa của phòng đó.</td>
            </tr>
            <tr v-else-if="loadingHistory">
              <td colspan="10" class="text-center p-6 text-slate-500 font-medium">Đang tải lịch sử khóa...</td>
            </tr>
            <tr v-else-if="historyLogs.length === 0">
              <td colspan="10" class="text-center p-6 text-slate-400 italic">Phòng {{ activeHistoryRoom.room_number }} chưa từng có bản ghi khóa nào trong lịch sử.</td>
            </tr>
            <tr 
              v-else 
              v-for="(log, idx) in historyLogs" 
              :key="log.id"
              class="border-b border-slate-100 hover:bg-[#bdecfe]/35 cursor-pointer h-8 font-semibold text-slate-700 transition-colors"
            >
              <td class="p-2 border-r border-slate-200 text-center">{{ idx + 1 }}</td>
              <td class="p-2 border-r border-slate-200 text-center">{{ activeHistoryRoom.room_form?.name || '-' }}</td>
              <td class="p-2 border-r border-slate-200">{{ activeHistoryRoom.room_type_name || '-' }}</td>
              <td class="p-2 border-r border-slate-200 text-center">{{ formatDateDisplay(log.start_date) }}</td>
              <td class="p-2 border-r border-slate-200 text-center">{{ formatDateDisplay(log.end_date) }}</td>
              <td class="p-2 border-r border-slate-200">{{ log.reason || '-' }}</td>
              <td class="p-2 border-r border-slate-200 text-center">{{ log.maintenance_percent }}%</td>
              <td class="p-2 border-r border-slate-200 text-center">
                <span 
                  class="px-2 py-0.5 rounded-sm font-extrabold text-[9px] uppercase border"
                  :class="log.is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
                >
                  {{ log.is_active ? 'Active' : 'Closed' }}
                </span>
              </td>
              <td class="p-2 border-r border-slate-200">{{ log.username }}</td>
              <td class="p-2 text-center font-black">
                <span 
                  class="px-2 py-0.5 rounded font-black text-[10px] text-white"
                  :style="{ backgroundColor: log.lock_type?.trim().toUpperCase() === 'OOO' ? '#ef4444' : '#f59e0b' }"
                >
                  {{ log.lock_type?.toUpperCase() }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- BULK LOCK MODAL (TÔNG MÀU NHẠT #8dcbf4 CHUẨN CÔNG TY/DS CÔNG VIỆC) -->
    <div 
      v-if="isBulkModalOpen" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 backdrop-blur-xs select-none font-bold"
      @click.self="isBulkModalOpen = false"
    >
      <div 
        class="bg-white shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-200"
        style="width: 620px; max-width: 95vw; min-height: 420px; border-radius: 1rem;"
      >
        
        <!-- Modal Header (Tông màu xanh nhạt nhẹ nhàng #8dcbf4) -->
        <div 
          class="px-5 py-3.5 flex items-center justify-between text-white border-b border-slate-100 rounded-t-2xl"
          style="background-color: #8dcbf4;"
        >
          <h2 class="text-xs font-black uppercase tracking-wider m-0">Thêm khóa</h2>
          <button 
            @click="isBulkModalOpen = false" 
            class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-sm font-black transition-colors"
          >
            ✕
          </button>
        </div>

        <!-- Modal Body Form (Layout 2 cột chuẩn thiết kế dùng inline grid cực kỳ chính xác) -->
        <div 
          class="p-5 text-slate-700 font-bold"
          style="display: grid; grid-template-columns: 7fr 5fr; gap: 20px;"
        >
          
          <!-- Column 1 (Left): 7/12 width -->
          <div class="flex flex-col gap-4">
            
            <!-- Start & End Date Selector horizontally aligned -->
            <div class="flex flex-col gap-1">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Ngày bắt đầu - Ngày kết thúc</span>
              <div class="flex items-center gap-1 border border-slate-200 rounded-lg px-2 py-1.5 bg-slate-50/50 focus-within:border-sky-400 focus-within:bg-white transition-colors">
                <input 
                  type="date" 
                  v-model="bulkForm.start_date" 
                  class="border-none outline-none font-bold text-slate-700 text-xs bg-transparent" 
                  style="width: 110px; padding: 2px 0;"
                />
                <span class="text-slate-400 font-extrabold px-0.5">~</span>
                <input 
                  type="date" 
                  v-model="bulkForm.end_date" 
                  class="border-none outline-none font-bold text-slate-700 text-xs bg-transparent" 
                  style="width: 110px; padding: 2px 0;"
                />
                <svg class="w-3.5 h-3.5 text-slate-400 ml-auto mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
            </div>

            <!-- Custom Select Dropdown checkbox list for Rooms (Đã được chuyển container relative bọc quanh button để hiển thị dropdown đúng vị trí ngay bên dưới) -->
            <div class="flex flex-col gap-1 modal-room-dropdown-container">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Phòng</span>
              <div class="relative w-full">
                <button 
                  @click.stop="isRoomDropdownOpen = !isRoomDropdownOpen" 
                  class="w-full flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50 text-xs font-black text-slate-700 hover:border-slate-300 cursor-pointer text-left transition-all"
                >
                  <span>Chọn: {{ modalSelectedRoomIds.length }}</span>
                  <svg 
                    class="w-3.5 h-3.5 text-slate-400 transform transition-transform" 
                    :class="{'rotate-180': isRoomDropdownOpen}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <!-- Dropdown popover list checklist (Hiển thị ngay dưới button, có z-index cao và không bị modal overflow-hidden đè) -->
                <div 
                  v-if="isRoomDropdownOpen" 
                  class="absolute left-0 right-0 z-45 bg-white border border-slate-200 rounded-lg shadow-xl p-2.5 flex flex-col gap-2 min-w-[200px]"
                  style="top: 100%; margin-top: 4px;"
                  @click.stop
                >
                  <!-- Search Box -->
                  <div class="relative flex items-center border border-slate-200 rounded px-2.5 py-1 bg-slate-50/80">
                    <input 
                      type="text" 
                      v-model="roomSearchQuery" 
                      placeholder="Tìm số phòng..." 
                      class="border-none bg-transparent w-full focus:outline-none text-xs font-semibold text-slate-650"
                    />
                    <svg class="w-3 h-3 text-slate-400 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </div>

                  <!-- Checkbox Checklist Scroll Area -->
                  <div class="overflow-y-auto flex flex-col gap-1 py-1 text-slate-700" style="max-height: 150px;">
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-1.5 rounded text-xs select-none">
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
                      class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-1.5 rounded text-xs select-none"
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

                  <!-- Dropdown Action button -->
                  <button 
                    @click="applyModalRoomSelection"
                    class="w-full py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded font-extrabold cursor-pointer border-none text-[11px] shadow-3xs transition-colors"
                  >
                    Lưu
                  </button>
                </div>
              </div>
            </div>

            <!-- Maintenance Percent Input (styled with % suffix block) -->
            <div class="flex flex-col gap-1">
              <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Tiến độ bảo trì</span>
              <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-slate-50/50 focus-within:border-sky-400 focus-within:bg-white transition-colors">
                <input 
                  type="number" 
                  min="0" 
                  max="100" 
                  v-model="bulkForm.maintenance_percent" 
                  class="border-none outline-none px-3 py-2 w-full text-xs font-bold text-slate-700 bg-transparent" 
                />
                <span class="bg-slate-100 text-slate-500 font-black px-3.5 py-2 border-l border-slate-200 text-xs select-none">%</span>
              </div>
            </div>

          </div>

          <!-- Column 2 (Right): 5/12 width -->
          <div class="flex flex-col gap-1 h-full">
            <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Ghi chú *</span>
            <textarea 
              v-model="bulkForm.reason" 
              placeholder="Nhập ghi chú..."
              class="w-full border border-slate-200 rounded-lg p-3 bg-slate-50/50 focus:bg-white focus:outline-sky-400 resize-none font-semibold text-xs leading-relaxed flex-1"
              style="min-height: 180px;"
            ></textarea>
          </div>

        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100 rounded-b-2xl">
          <button 
            @click="isBulkModalOpen = false" 
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-xs cursor-pointer transition-colors"
          >
            Đóng
          </button>
          <button 
            @click="submitBulkLock"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-xs border-none cursor-pointer shadow-xs transition-colors flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            Khóa phòng
          </button>
        </div>

      </div>
    </div>

  </div>
</template>

<style scoped>
/* Custom animations for modal */
.animate-in {
  animation: fadeIn 0.18s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}
</style>
