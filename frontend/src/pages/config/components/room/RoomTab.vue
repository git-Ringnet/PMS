<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import RoomIcon from '@/components/RoomIcon.vue'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const isLoaded = ref(false)

const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null
let latestX = 0
let latestY = 0

function dragModal(moveEvent) {
  if (!isDraggingModal.value) return
  latestX = moveEvent.clientX
  latestY = moveEvent.clientY
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = latestX - dragStart.x
    modalPos.value.y = latestY - dragStart.y
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

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  e.preventDefault()
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  latestX = e.clientX
  latestY = e.clientY

  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isRoomModalOpen.value) {
    isRoomModalOpen.value = false
  }
}

const rooms = ref([])
const roomClasses = ref([])
const roomForms = ref([])

const roomPage = ref(1)
const roomPageSize = ref(25)
const roomSearchQuery = ref('')

const filteredRooms = computed(() => {
  if (!roomSearchQuery.value) return rooms.value
  const query = roomSearchQuery.value.trim().toLowerCase()
  return rooms.value.filter(r =>
    r.room_number.toLowerCase().includes(query) ||
    (r.room_class?.name || '').toLowerCase().includes(query) ||
    (r.room_form?.name || '').toLowerCase().includes(query) ||
    (r.floor && String(r.floor).toLowerCase().includes(query))
  )
})

watch(roomSearchQuery, () => {
  roomPage.value = 1
})

const paginatedRooms = computed(() => {
  const start = (roomPage.value - 1) * roomPageSize.value
  return filteredRooms.value.slice(start, start + roomPageSize.value)
})
const totalRoomPages = computed(() => Math.ceil(filteredRooms.value.length / roomPageSize.value) || 1)

// Grouped table expand states
const expandedRoomClasses = reactive({})
const toggleRoomClassExpand = (classId) => {
  expandedRoomClasses[classId] = !expandedRoomClasses[classId]
}

// Compute rooms grouped by room_class for the current page
const groupedRooms = computed(() => {
  const classMap = {}

  roomClasses.value.forEach(rc => {
    classMap[rc.id] = {
      roomClass: rc,
      rooms: [],
      count: filteredRooms.value.filter(r => r.room_class_id === rc.id).length
    }
  })

  const noClassId = 'no-class'
  classMap[noClassId] = {
    roomClass: { id: noClassId, name: 'Chưa phân loại', code: '-' },
    rooms: [],
    count: filteredRooms.value.filter(r => !r.room_class_id).length
  }

  paginatedRooms.value.forEach(r => {
    const classId = r.room_class_id || noClassId
    if (!classMap[classId]) {
      classMap[classId] = {
        roomClass: r.room_class || { id: classId, name: `Loại phòng ${classId}`, code: '' },
        rooms: [],
        count: filteredRooms.value.filter(r => r.room_class_id === classId).length
      }
    }
    classMap[classId].rooms.push(r)
  })

  return Object.values(classMap).filter(g => g.rooms.length > 0)
})

// Room Modal State
const isRoomModalOpen = ref(false)
const isEditMode = ref(false)
const currentRoomId = ref(null)

const roomFormState = reactive({
  room_number: '',
  room_form_id: '',
  room_class_id: '',
  max_guests: 2,
  floor: '',
  area: 'Khu A',
  extra_beds_limit: 0,
  grid_row: 0,
  grid_column: 0,
  owner_room: '',
  linked_room: '',
  connecting_room: '',
  is_internal: false,
  notes: ''
})

const availableConnectingRooms = computed(() => {
  const currentNum = String(roomFormState.room_number || '').trim()
  if (!currentNum) {
    return rooms.value
      .slice()
      .sort((a, b) => String(a.room_number).localeCompare(String(b.room_number), undefined, { numeric: true }))
  }

  const currentFloor = String(roomFormState.floor || '').trim()
  const sameFloorRooms = rooms.value
    .filter(rm => !currentFloor || String(rm.floor || '').trim() === currentFloor)
    .slice()
    .sort((a, b) => {
      if (a.orders != null && b.orders != null) return a.orders - b.orders
      return String(a.room_number).localeCompare(String(b.room_number), undefined, { numeric: true })
    })

  const currentIndex = sameFloorRooms.findIndex(rm => String(rm.room_number).trim() === currentNum)
  if (currentIndex === -1) {
    return sameFloorRooms.filter(rm => String(rm.room_number).trim() !== currentNum)
  }

  const adjacentRooms = []
  if (currentIndex > 0) {
    adjacentRooms.push(sameFloorRooms[currentIndex - 1])
  }
  if (currentIndex < sameFloorRooms.length - 1) {
    adjacentRooms.push(sameFloorRooms[currentIndex + 1])
  }

  if (roomFormState.connecting_room) {
    const existing = rooms.value.find(rm => String(rm.room_number).trim() === String(roomFormState.connecting_room).trim())
    if (existing && !adjacentRooms.some(rm => rm.id === existing.id)) {
      adjacentRooms.push(existing)
    }
  }

  return adjacentRooms
})

// Column selector states and helper functions
const isRoomColumnSelectorOpen = ref(false)
const roomConfigRecord = ref(null)

const roomColumns = ref([
  { id: 'room_number', label: 'Phòng', visible: true },
  { id: 'room_form', label: 'Dạng Phòng', visible: true },
  { id: 'max_guests', label: 'Khách Hàng', visible: true },
  { id: 'extra_beds_limit', label: 'Thêm Giường', visible: true },
  { id: 'area', label: 'Khu Vực', visible: true },
  { id: 'floor', label: 'Tầng', visible: true },
  { id: 'grid_row', label: 'Hàng', visible: true },
  { id: 'grid_column', label: 'Cột', visible: true },
  { id: 'is_internal', label: 'Phòng Nội Bộ', visible: true },
  { id: 'connecting_room', label: 'Phòng Thông Nhau', visible: true },
  { id: 'notes', label: 'Ghi Chú', visible: true },
  { id: 'action', label: 'Hành Động', visible: true },
])

const isRoomColumnVisible = (columnId) => {
  const col = roomColumns.value.find(c => c.id === columnId)
  return col ? col.visible : true
}

const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    isRoomColumnSelectorOpen.value = false
  }
}

let bc = null

onMounted(async () => {
  window.addEventListener('keydown', handleKeyDown)
  fetchRoomClasses()
  fetchRoomForms()
  fetchRooms()
  await fetchConfigs()
  isLoaded.value = true
  document.addEventListener('click', closeAllPopovers)
  if (typeof BroadcastChannel !== 'undefined') {
    bc = new BroadcastChannel('pms-room-updates')
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  document.removeEventListener('click', closeAllPopovers)
  stopDragModal()
  if (bc) {
    bc.close()
  }
})

const fetchRoomClasses = async () => {
  try {
    const res = await http.get('/room-classes')
    roomClasses.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải tên loại phòng:', err)
  }
}

const fetchRoomForms = async () => {
  try {
    const res = await http.get('/room-forms')
    roomForms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải dạng phòng:', err)
  }
}

const fetchRooms = async () => {
  loading.value = true
  try {
    const res = await http.get('/rooms', { params: { include_internal: 1, include_inactive: 1 } })
    rooms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải danh sách phòng:', err)
  } finally {
    loading.value = false
  }
}

const fetchConfigs = async () => {
  try {
    const res = await http.get('/hotel-configs')
    const configs = res.data.data || []

    const roomConfig = configs.find(c => c.name === 'room_columns_visibility')
    if (roomConfig) {
      roomConfigRecord.value = roomConfig
      if (roomConfig.value) {
        const visibleIds = roomConfig.value.split(',')
        roomColumns.value.forEach(c => {
          c.visible = visibleIds.includes(c.id)
        })
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình ẩn hiện cột:', err)
  }
}

const saveConfig = async () => {
  const name = 'room_columns_visibility'
  const value = roomColumns.value.filter(c => c.visible).map(c => c.id).join(',')

  try {
    if (roomConfigRecord.value) {
      const res = await http.put(`/hotel-configs/${roomConfigRecord.value.id}`, {
        name,
        value,
        description: 'Cột hiển thị của bảng phòng thực tế'
      })
      roomConfigRecord.value = res.data.data
    } else {
      const res = await http.post('/hotel-configs', {
        name,
        value,
        description: 'Cột hiển thị của bảng phòng thực tế'
      })
      roomConfigRecord.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi lưu cấu hình hiển thị cột phòng:', err)
  }
}

watch(roomColumns, () => {
  if (isLoaded.value) {
    saveConfig()
  }
}, { deep: true })

const getErrorMessage = (err, defaultMsg = 'Có lỗi xảy ra') => {
  if (err.response?.status === 422 && err.response?.data?.errors) {
    const errors = err.response.data.errors
    const messages = []

    for (const key in errors) {
      if (Array.isArray(errors[key])) {
        errors[key].forEach(msg => {
          let translated = msg
          if (msg.toLowerCase().includes('already been taken') || msg.toLowerCase().includes('đã tồn tại') || msg.toLowerCase().includes('đã được chọn')) {
            if (key === 'room_number') {
              translated = 'Số phòng đã tồn tại trong hệ thống'
            } else {
              translated = 'Dữ liệu này đã tồn tại trong hệ thống'
            }
          } else if (msg.toLowerCase().includes('field is required') || msg.toLowerCase().includes('bắt buộc')) {
            translated = 'Trường thông tin này là bắt buộc'
          }
          messages.push(translated)
        })
      }
    }
    if (messages.length > 0) {
      return messages.join(', ')
    }
  }
  return err.response?.data?.message || err.message || defaultMsg
}

const openAddRoomModal = () => {
  isEditMode.value = false
  currentRoomId.value = null
  modalPos.value = { x: 0, y: 0 }
  Object.assign(roomFormState, {
    room_number: '',
    room_form_id: roomForms.value[0]?.id || '',
    room_class_id: roomClasses.value[0]?.id || '',
    max_guests: 2,
    floor: '',
    area: 'Khu A',
    extra_beds_limit: 0,
    grid_row: 0,
    grid_column: 0,
    owner_room: '',
    linked_room: '',
    connecting_room: '',
    is_internal: false,
    notes: ''
  })
  isRoomModalOpen.value = true
}

const openEditRoomModal = (room) => {
  isEditMode.value = true
  currentRoomId.value = room.id
  modalPos.value = { x: 0, y: 0 }
  Object.assign(roomFormState, {
    room_number: room.room_number,
    room_form_id: room.room_form_id,
    room_class_id: room.room_class_id,
    max_guests: room.max_guests,
    floor: String(room.floor),
    area: room.area || 'Khu A',
    extra_beds_limit: room.extra_beds_limit,
    grid_row: room.grid_row,
    grid_column: room.grid_column,
    owner_room: room.owner_room || '',
    linked_room: room.linked_room || '',
    connecting_room: room.connecting_room || '',
    is_internal: room.is_internal,
    notes: room.notes || ''
  })
  isRoomModalOpen.value = true
}

const saveRoom = async () => {
  if (!roomFormState.room_number || !roomFormState.floor) {
    uiStore.showToast('Vui lòng điền các trường bắt buộc (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/rooms/${currentRoomId.value}`, roomFormState)
      uiStore.showToast('Cập nhật phòng thành công!', 'success')
    } else {
      await http.post('/rooms', roomFormState)
      uiStore.showToast('Thêm phòng mới thành công!', 'success')
    }
    isRoomModalOpen.value = false
    fetchRooms()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = getErrorMessage(err, 'Có lỗi xảy ra khi lưu phòng')
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoom = async (roomId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa phòng này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/rooms/${roomId}`)
    uiStore.showToast('Xóa phòng thành công!', 'success')
    fetchRooms()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Không thể xóa phòng này', 'error')
  } finally {
    loading.value = false
  }
}

const toggleRoomInternal = async (room) => {
  try {
    const updatedVal = !room.is_internal
    const payload = {
      room_number: room.room_number,
      room_form_id: room.room_form_id,
      room_class_id: room.room_class_id,
      max_guests: room.max_guests,
      floor: String(room.floor),
      area: room.area,
      extra_beds_limit: room.extra_beds_limit,
      grid_row: room.grid_row,
      grid_column: room.grid_column,
      owner_room: room.owner_room,
      linked_room: room.linked_room,
      is_internal: updatedVal,
      status: room.status,
      notes: room.notes
    }
    await http.put(`/rooms/${room.id}`, payload)
    room.is_internal = updatedVal
    uiStore.showToast('Cập nhật trạng thái phòng nội bộ thành công!', 'success')
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể cập nhật trạng thái phòng nội bộ'
    uiStore.showToast(errorMsg, 'error')
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 relative h-full">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex justify-between items-center mb-2 shrink-0">
      <div class="flex items-center gap-2 relative popover-container">
        <button @click="openAddRoomModal"
          class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Thêm Phòng
        </button>
        <button @click.stop="isRoomColumnSelectorOpen = !isRoomColumnSelectorOpen"
          class="h-8 w-8 bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-lg cursor-pointer flex items-center justify-center transition-colors shadow-xs"
          title="Ẩn/hiện cột">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
        </button>

        <!-- Column Selector Dropdown -->
        <div v-if="isRoomColumnSelectorOpen"
          class="absolute left-0 top-full mt-1.5 z-45 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px] max-h-[300px] overflow-y-auto flex flex-col gap-1">
          <label v-for="col in roomColumns" :key="col.id"
            class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
            <input type="checkbox" v-model="col.visible"
              class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
            <span class="text-xs text-slate-700 font-semibold">{{ col.label }}</span>
          </label>
        </div>
      </div>

      <!-- Search Input -->
      <div
        class="relative flex items-center border border-slate-200 rounded-lg bg-white px-3 h-8 shadow-3xs focus-within:border-sky-400 focus-within:ring-1 focus-within:ring-sky-400 transition-colors w-[260px]">
        <svg class="w-4 h-4 text-slate-400 mr-2 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input v-model="roomSearchQuery" type="text" placeholder="Tìm số phòng, loại phòng..."
          class="border-none outline-none text-xs font-normal text-slate-700 placeholder-slate-400 w-full bg-transparent p-0" />
        <button v-if="roomSearchQuery" @click="roomSearchQuery = ''"
          class="text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer font-bold text-xs shrink-0 ml-1.5"
          title="Xóa nhanh">
          ✕
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-auto border border-slate-200 rounded-xl bg-white shadow-2xs flex-1 min-h-0">
      <table class="w-full text-xs text-left border-collapse font-roboto">
        <thead class="sticky top-0 bg-slate-50 z-10 shadow-3xs">
          <tr class="bg-slate-50 text-slate-700 font-semibold text-xs">
            <th v-if="isRoomColumnVisible('room_number')" class="p-2.5 text-center align-middle border border-slate-200">Phòng</th>
            <th v-if="isRoomColumnVisible('room_form')" class="p-2.5 text-center align-middle border border-slate-200">Dạng Phòng</th>
            <th v-if="isRoomColumnVisible('max_guests')" class="p-2.5 text-center align-middle border border-slate-200">Khách Hàng</th>
            <th v-if="isRoomColumnVisible('extra_beds_limit')" class="p-2.5 text-center align-middle border border-slate-200">Thêm Giường</th>
            <th v-if="isRoomColumnVisible('area')" class="p-2.5 text-center align-middle border border-slate-200">Khu Vực</th>
            <th v-if="isRoomColumnVisible('floor')" class="p-2.5 text-center align-middle border border-slate-200">Tầng</th>
            <th v-if="isRoomColumnVisible('grid_row')" class="p-2.5 text-center align-middle border border-slate-200">Hàng</th>
            <th v-if="isRoomColumnVisible('grid_column')" class="p-2.5 text-center align-middle border border-slate-200">Cột</th>
            <th v-if="isRoomColumnVisible('is_internal')" class="p-2.5 text-center align-middle border border-slate-200">Phòng Nội Bộ</th>
            <th v-if="isRoomColumnVisible('connecting_room')" class="p-2.5 text-center align-middle border border-slate-200">Phòng Thông Nhau</th>
            <th v-if="isRoomColumnVisible('notes')" class="p-2.5 text-center align-middle border border-slate-200">Ghi Chú</th>
            <th v-if="isRoomColumnVisible('action')" class="p-2.5 text-center align-middle border border-slate-200 w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <!-- Render Grouped Rooms -->
          <template v-for="g in groupedRooms" :key="g.roomClass.id">
            <!-- Group Header Row -->
            <tr class="border-b border-slate-200 bg-slate-100/60 select-none">
              <td v-if="isRoomColumnVisible('room_number')" class="p-2.5 font-semibold text-[#000000D9]">
                <div class="flex items-center gap-2.5">
                  <button @click.stop="toggleRoomClassExpand(g.roomClass.id)"
                    class="w-5 h-5 flex items-center justify-center bg-sky-100 hover:bg-sky-200 text-sky-700 border border-sky-300 rounded cursor-pointer transition-colors shrink-0">
                    <svg v-if="!expandedRoomClasses[g.roomClass.id]" class="w-3.5 h-3.5" fill="none"
                      stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                    </svg>
                  </button>
                  <span class="font-semibold">{{ g.roomClass.name || 'Loại phòng' }}</span>
                  <span v-if="g.roomClass.is_active === false || g.roomClass.is_active === 0"
                    class="px-2 py-0.5 text-[10px] font-semibold bg-rose-100 text-rose-600 rounded-full border border-rose-200 uppercase tracking-wider ml-1">
                    Ngưng hoạt động
                  </span>
                </div>
              </td>

              <td v-if="isRoomColumnVisible('room_form')" class="p-2.5 font-semibold text-slate-800 text-center">
                {{ g.count }}
              </td>

              <td v-if="isRoomColumnVisible('max_guests')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('extra_beds_limit')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('area')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('floor')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('grid_row')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('grid_column')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('is_internal')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('connecting_room')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('notes')" class="p-2.5"></td>
              <td v-if="isRoomColumnVisible('action')" class="p-2.5"></td>
            </tr>

            <!-- Expanded Sub-rows -->
            <template v-if="expandedRoomClasses[g.roomClass.id] || roomSearchQuery">
              <tr v-for="r in g.rooms" :key="r.id" @click="openEditRoomModal(r)"
                class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors">
                <td v-if="isRoomColumnVisible('room_number')" class="p-2.5 font-semibold text-[#000000D9] pl-10">
                  {{ r.room_number }}
                </td>

                <td v-if="isRoomColumnVisible('room_form')" class="p-2.5 text-slate-700">
                  {{ r.room_form?.name }}
                </td>

                <td v-if="isRoomColumnVisible('max_guests')" class="p-2.5 text-slate-700 text-center">
                  {{ r.max_guests }}
                </td>

                <td v-if="isRoomColumnVisible('extra_beds_limit')" class="p-2.5 text-slate-700 text-center">
                  {{ r.extra_beds_limit }}
                </td>

                <td v-if="isRoomColumnVisible('area')" class="p-2.5 text-slate-700">
                  {{ r.area || 'Khu A' }}
                </td>

                <td v-if="isRoomColumnVisible('floor')" class="p-2.5 text-slate-700 text-center">
                  Tầng {{ r.floor }}
                </td>

                <td v-if="isRoomColumnVisible('grid_row')" class="p-2.5 text-slate-600 font-mono text-center">
                  {{ r.grid_row }}
                </td>

                <td v-if="isRoomColumnVisible('grid_column')" class="p-2.5 text-slate-600 font-mono text-center">
                  {{ r.grid_column }}
                </td>

                <td v-if="isRoomColumnVisible('is_internal')" class="p-2.5 text-center" @click.stop>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="r.is_internal" @change="toggleRoomInternal(r)"
                      class="sr-only peer" />
                    <div
                      class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600">
                    </div>
                  </label>
                </td>

                <td v-if="isRoomColumnVisible('connecting_room')" class="p-2.5 text-slate-700 font-semibold text-center">
                  <span v-if="r.connecting_room"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-800 text-xs font-semibold border border-slate-200">
                    <RoomIcon name="connecting-door" class="w-3.5 h-3.5 text-slate-700" />
                    {{ r.connecting_room }}
                  </span>
                  <span v-else class="text-slate-400 font-normal">-</span>
                </td>

                <td v-if="isRoomColumnVisible('notes')" class="p-2.5 text-slate-500 italic max-w-[120px] truncate"
                  :title="r.notes">
                  {{ r.notes || '-' }}
                </td>

                <td v-if="isRoomColumnVisible('action')" class="p-2.5 text-center" @click.stop>
                  <button @click="deleteRoom(r.id)"
                    class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                    title="Xóa phòng">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination controls -->
    <div class="flex items-center justify-between mt-2.5 shrink-0 select-none">
      <div class="text-xs font-semibold text-[#000000D9]">
        Tổng số phòng: <span class="ml-1 font-bold text-sky-700">{{ filteredRooms.length }}</span>
      </div>
      <div class="flex items-center gap-2">
        <button @click="roomPage = Math.max(1, roomPage - 1)" :disabled="roomPage === 1"
          class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
          &lt;
        </button>

        <button v-for="p in totalRoomPages" :key="p" @click="roomPage = p"
          class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
          :class="roomPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
          {{ p }}
        </button>

        <button @click="roomPage = Math.min(totalRoomPages, roomPage + 1)" :disabled="roomPage === totalRoomPages"
          class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
          &gt;
        </button>

        <select v-model="roomPageSize" @change="roomPage = 1"
          class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
          <option :value="10">10 / trang</option>
          <option :value="25">25 / trang</option>
          <option :value="50">50 / trang</option>
          <option :value="100">100 / trang</option>
        </select>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT ROOM -->
    <div v-if="isRoomModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-2 sm:p-4">
      <div
        class="bg-white rounded-xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 font-roboto max-h-[calc(100vh-1rem)] sm:max-h-[calc(100vh-2rem)] flex flex-col"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header -->
        <div class="px-5 py-2.5 flex items-center justify-between text-white cursor-move select-none shrink-0"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)' }"
          @mousedown="startDragModal">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Chỉnh Sửa Phòng' : 'Thêm Phòng' }}
          </h2>
          <button @mousedown.stop @click="isRoomModalOpen = false"
            class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-base font-bold p-1 leading-none"
            title="Đóng (Esc)">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-4 sm:p-4.5 flex flex-col gap-2.5 overflow-y-auto max-h-[88vh] text-xs text-slate-700 font-normal">
          <!-- Section 1: Room Info -->
          <div class="flex flex-col gap-2">
            <h3 class="text-xs font-semibold text-[#000000D9] border-b border-slate-200 pb-1 uppercase tracking-wide">
              Thông Tin Phòng
            </h3>
            <div class="grid grid-cols-2 gap-2.5">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">
                  Số Phòng <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                  <input type="text" v-model="roomFormState.room_number" placeholder="Nhập số phòng..."
                    class="rounded-lg px-2.5 py-1.5 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
                  <button v-if="roomFormState.room_number" @click="roomFormState.room_number = ''"
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                    title="Xóa nhanh">✕</button>
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Dạng Phòng</label>
                <select v-model="roomFormState.room_form_id"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white font-normal focus:outline-sky-400 text-xs">
                  <option v-for="f in roomForms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Loại Phòng</label>
                <select v-model="roomFormState.room_class_id"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white font-normal focus:outline-sky-400 text-xs">
                  <option v-for="c in roomClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Phòng Thông Nhau</label>
                <select v-model="roomFormState.connecting_room"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white font-normal focus:outline-sky-400 text-xs">
                  <option value="">-- Không có --</option>
                  <option v-for="rm in availableConnectingRooms" :key="rm.id" :value="rm.room_number">
                    Phòng {{ rm.room_number }} ({{ rm.room_class?.name || rm.room_class?.code || 'P' }})
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Section 2: Floor/Grid Info -->
          <div class="flex flex-col gap-2">
            <h3 class="text-xs font-semibold text-[#000000D9] border-b border-slate-200 pb-1 uppercase tracking-wide">
              Thông Tin Vị Trí & Tầng
            </h3>
            <div class="grid grid-cols-2 gap-2.5">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">
                  Tầng <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                  <input type="text" v-model="roomFormState.floor" placeholder="Nhập tầng..."
                    class="rounded-lg px-2.5 py-1.5 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
                  <button v-if="roomFormState.floor" @click="roomFormState.floor = ''"
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                    title="Xóa nhanh">✕</button>
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Số Khách Tối Đa</label>
                <input type="number" v-model="roomFormState.max_guests" min="1"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 font-normal focus:outline-sky-400 text-xs" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Khu Vực</label>
                <select v-model="roomFormState.area"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white font-normal focus:outline-sky-400 text-xs">
                  <option value="Khu A">Khu vực A</option>
                  <option value="Khu B">Khu vực B</option>
                  <option value="Khu C">Khu vực C</option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Giới Hạn Thêm Giường</label>
                <input type="number" v-model="roomFormState.extra_beds_limit" min="0"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 font-normal focus:outline-sky-400 text-xs" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Hàng (Grid Row)</label>
                <input type="number" v-model="roomFormState.grid_row"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 font-mono text-slate-700 focus:outline-sky-400 text-xs" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Cột (Grid Column)</label>
                <input type="number" v-model="roomFormState.grid_column"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 font-mono text-slate-700 focus:outline-sky-400 text-xs" />
              </div>
            </div>
          </div>

          <!-- Section 3: Others -->
          <div class="flex flex-col gap-2">
            <h3 class="text-xs font-semibold text-[#000000D9] border-b border-slate-200 pb-1 uppercase tracking-wide">
              Khác
            </h3>
            <div class="flex items-center justify-between select-none py-0.5">
              <span class="font-semibold text-xs text-[#000000D9]">Phòng Nội Bộ</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="roomFormState.is_internal" class="sr-only peer">
                <div
                  class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600">
                </div>
              </label>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Ghi Chú</label>
              <div class="relative flex items-center">
                <textarea v-model="roomFormState.notes" rows="2" placeholder="Ghi chú về phòng..."
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 pr-7 focus:outline-sky-400 resize-none font-normal text-xs w-full"></textarea>
                <button v-if="roomFormState.notes" @click="roomFormState.notes = ''"
                  class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-5 py-2.5 flex items-center justify-end gap-2 border-t border-slate-100 shrink-0">
          <button @click="saveRoom"
            class="btn-pms-primary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            <span>Lưu Phòng</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
