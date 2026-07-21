<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const isLoaded = ref(false)

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
  is_internal: false,
  notes: ''
})

// Column selector states and helper functions
const isRoomColumnSelectorOpen = ref(false)
const roomConfigRecord = ref(null)

const roomColumns = ref([
  { id: 'room_number', label: 'PHÒNG', visible: true },
  { id: 'room_form', label: 'DẠNG PHÒNG', visible: true },
  { id: 'max_guests', label: 'Khách hàng', visible: true },
  { id: 'extra_beds_limit', label: 'Thêm giường', visible: true },
  { id: 'area', label: 'Khu vực', visible: true },
  { id: 'floor', label: 'Tầng', visible: true },
  { id: 'grid_row', label: 'Hàng', visible: true },
  { id: 'grid_column', label: 'Cột', visible: true },
  { id: 'is_internal', label: 'Phòng nội bộ', visible: true },
  { id: 'notes', label: 'Ghi chú', visible: true },
  { id: 'action', label: 'Hành động', visible: true },
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
  document.removeEventListener('click', closeAllPopovers)
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
    const res = await http.get('/rooms', { params: { include_internal: 1 } })
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

watch(roomColumns, (newCols) => {
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
    is_internal: false,
    notes: ''
  })
  isRoomModalOpen.value = true
}

const openEditRoomModal = (room) => {
  isEditMode.value = true
  currentRoomId.value = room.id
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
    uiStore.showToast('Không thể xóa phòng này', 'error')
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
  <div class="relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-4">
      <div class="flex items-center gap-2 relative popover-container">
        <button @click="openAddRoomModal"
          class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1 border-none cursor-pointer shadow-xs transition-colors">
          + Thêm
        </button>
        <button @click.stop="isRoomColumnSelectorOpen = !isRoomColumnSelectorOpen"
          class="p-2 bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-lg cursor-pointer flex items-center justify-center transition-colors shadow-xs"
          title="Ẩn/hiện cột">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
        </button>

        <!-- Column Selector Dropdown -->
        <div v-if="isRoomColumnSelectorOpen"
          class="absolute right-0 top-full mt-1.5 z-45 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px] max-h-[300px] overflow-y-auto flex flex-col gap-1">
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
        class="relative flex items-center border border-slate-200 rounded-lg bg-white px-3 py-1.5 shadow-3xs focus-within:border-sky-400 focus-within:ring-1 focus-within:ring-sky-400 transition-colors w-[260px]">
        <svg class="w-4 h-4 text-slate-400 mr-2 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input v-model="roomSearchQuery" type="text" placeholder="Tìm số phòng, loại phòng..."
          class="border-none outline-none text-xs font-semibold text-slate-700 placeholder-slate-400 w-full bg-transparent p-0" />
        <button v-if="roomSearchQuery" @click="roomSearchQuery = ''"
          class="text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer font-black text-xs shrink-0 ml-1.5">
          ✕
        </button>
      </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th v-if="isRoomColumnVisible('room_number')" class="p-3">
              <div class="flex items-center gap-1.5">
                <span>PHÒNG</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
            </th>
            <th v-if="isRoomColumnVisible('room_form')" class="p-3">DẠNG PHÒNG</th>
            <th v-if="isRoomColumnVisible('max_guests')" class="p-3 text-center">Khách hàng</th>
            <th v-if="isRoomColumnVisible('extra_beds_limit')" class="p-3 text-center">Thêm giường</th>
            <th v-if="isRoomColumnVisible('area')" class="p-3">Khu vực</th>
            <th v-if="isRoomColumnVisible('floor')" class="p-3">Tầng</th>
            <th v-if="isRoomColumnVisible('grid_row')" class="p-3 text-center">Hàng</th>
            <th v-if="isRoomColumnVisible('grid_column')" class="p-3 text-center">Cột</th>
            <th v-if="isRoomColumnVisible('is_internal')" class="p-3">Phòng nội bộ</th>
            <th v-if="isRoomColumnVisible('notes')" class="p-3">Ghi chú</th>
            <th v-if="isRoomColumnVisible('action')" class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <!-- Render Grouped Rooms -->
          <template v-for="g in groupedRooms" :key="g.roomClass.id">
            <!-- Group Header Row -->
            <tr class="border-b border-slate-200 bg-slate-50/40 select-none">
              <td v-if="isRoomColumnVisible('room_number')" class="p-3 font-bold text-slate-700">
                <div class="flex items-center gap-3">
                  <button @click.stop="toggleRoomClassExpand(g.roomClass.id)"
                    class="w-5 h-5 flex items-center justify-center bg-sky-100 hover:bg-sky-200 text-sky-600 border border-sky-300 rounded cursor-pointer transition-colors shrink-0">
                    <svg v-if="!expandedRoomClasses[g.roomClass.id]" class="w-3.5 h-3.5" fill="none"
                      stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                    </svg>
                  </button>
                  <span>{{ g.roomClass.name || 'Loại phòng' }}</span>
                </div>
              </td>

              <td v-if="isRoomColumnVisible('room_form')" class="p-3 font-bold text-slate-800">
                {{ g.count }}
              </td>

              <td v-if="isRoomColumnVisible('max_guests')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('extra_beds_limit')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('area')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('floor')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('grid_row')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('grid_column')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('is_internal')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('notes')" class="p-3"></td>
              <td v-if="isRoomColumnVisible('action')" class="p-3"></td>
            </tr>

            <!-- Expanded Sub-rows -->
            <template v-if="expandedRoomClasses[g.roomClass.id] || roomSearchQuery">
              <tr v-for="r in g.rooms" :key="r.id" @click="openEditRoomModal(r)"
                class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td v-if="isRoomColumnVisible('room_number')" class="p-3 font-black text-slate-700 text-sm pl-11">
                  {{ r.room_number }}
                </td>

                <td v-if="isRoomColumnVisible('room_form')" class="p-3 font-semibold text-slate-600">
                  {{ r.room_form?.name }}
                </td>

                <td v-if="isRoomColumnVisible('max_guests')" class="p-3 text-slate-600 font-medium text-center">
                  {{ r.max_guests }}
                </td>

                <td v-if="isRoomColumnVisible('extra_beds_limit')" class="p-3 text-slate-500 font-medium text-center">
                  {{ r.extra_beds_limit }}
                </td>

                <td v-if="isRoomColumnVisible('area')" class="p-3 text-slate-500 font-medium">
                  {{ r.area || 'Khu A' }}
                </td>

                <td v-if="isRoomColumnVisible('floor')" class="p-3 text-slate-500 font-medium">
                  Tầng {{ r.floor }}
                </td>

                <td v-if="isRoomColumnVisible('grid_row')" class="p-3 text-slate-500 font-mono text-center">
                  {{ r.grid_row }}
                </td>

                <td v-if="isRoomColumnVisible('grid_column')" class="p-3 text-slate-500 font-mono text-center">
                  {{ r.grid_column }}
                </td>

                <td v-if="isRoomColumnVisible('is_internal')" class="p-3" @click.stop>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="r.is_internal" @change="toggleRoomInternal(r)"
                      class="sr-only peer" />
                    <div
                      class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                    </div>
                  </label>
                </td>

                <td v-if="isRoomColumnVisible('notes')" class="p-3 text-slate-400 italic max-w-[120px] truncate"
                  :title="r.notes">
                  {{ r.notes || '-' }}
                </td>

                <td v-if="isRoomColumnVisible('action')" class="p-3 text-right" @click.stop>
                  <div class="flex justify-end">
                    <button @click="deleteRoom(r.id)"
                      class="w-7 h-7 flex items-center justify-center bg-sky-100 hover:bg-sky-200 border border-sky-300 rounded-lg text-sky-600 hover:text-sky-700 cursor-pointer transition-colors shadow-xs"
                      title="Xóa phòng">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination controls -->
    <div class="flex items-center justify-between mt-4 select-none">
      <div class="text-sm font-bold text-slate-700">
        Tổng số phòng <span class="ml-2 font-black text-base">{{ filteredRooms.length }}</span>
      </div>
      <div class="flex items-center gap-2">
        <button @click="roomPage = Math.max(1, roomPage - 1)" :disabled="roomPage === 1"
          class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
          &lt;
        </button>

        <button v-for="p in totalRoomPages" :key="p" @click="roomPage = p"
          class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
          :class="roomPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
          {{ p }}
        </button>

        <button @click="roomPage = Math.min(totalRoomPages, roomPage + 1)" :disabled="roomPage === totalRoomPages"
          class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
          &gt;
        </button>

        <select v-model="roomPageSize" @change="roomPage = 1"
          class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
          <option :value="10">10 / page</option>
          <option :value="25">25 / page</option>
          <option :value="50">50 / page</option>
          <option :value="100">100 / page</option>
        </select>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT ROOM -->
    <div v-if="isRoomModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider text-white">{{ isEditMode ? 'Chỉnh sửa phòng' : 'Thêm phòng' }}</h2>
          <button @click="isRoomModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-6 overflow-y-auto max-h-[80vh] text-sm font-bold text-slate-600">
          <!-- Section 1: Room Info -->
          <div class="flex flex-col gap-3">
            <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Thông tin phòng*</h3>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <span>PHÒNG</span>
                <input type="text" v-model="roomFormState.room_number" placeholder="Nhập số phòng..."
                  class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 focus:bg-white text-sm" />
              </div>
              <div class="flex flex-col gap-1.5">
                <span>DẠNG PHÒNG</span>
                <select v-model="roomFormState.room_form_id"
                  class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                  <option v-for="f in roomForms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
              </div>
            </div>
            <div class="flex flex-col gap-1.5">
              <span>TÊN LOẠI PHÒNG</span>
              <select v-model="roomFormState.room_class_id"
                class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                <option v-for="c in roomClasses.filter(item => item.is_active)" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <!-- Section 2: Floor/Grid Info -->
          <div class="flex flex-col gap-3">
            <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Thông tin tầng*</h3>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <span>Khách hàng</span>
                <input type="number" v-model="roomFormState.max_guests"
                  class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
              </div>
              <div class="flex flex-col gap-1.5">
                <span>Tầng</span>
                <input type="text" v-model="roomFormState.floor" placeholder="Nhập tầng..."
                  class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <span>Khu vực</span>
                <select v-model="roomFormState.area"
                  class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                  <option value="Khu A">Khu vực A</option>
                  <option value="Khu B">Khu vực B</option>
                  <option value="Khu C">Khu vực C</option>
                </select>
              </div>
              <div class="flex flex-col gap-1.5">
                <span>Thêm giường</span>
                <input type="number" v-model="roomFormState.extra_beds_limit"
                  class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <span>Hàng</span>
                <input type="number" v-model="roomFormState.grid_row"
                  class="border border-slate-200 rounded-lg p-2.5 font-mono text-red-500 focus:outline-sky-500 text-sm" />
              </div>
              <div class="flex flex-col gap-1.5">
                <span>Cột</span>
                <input type="number" v-model="roomFormState.grid_column"
                  class="border border-slate-200 rounded-lg p-2.5 font-mono text-red-500 focus:outline-sky-500 text-sm" />
              </div>
            </div>
          </div>

          <!-- Section 3: Others -->
          <div class="flex flex-col gap-3">
            <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Khác*</h3>
            <div class="flex items-center justify-between select-none py-1.5">
              <span>Phòng nội bộ</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="roomFormState.is_internal" class="sr-only peer">
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Ghi chú</span>
              <textarea v-model="roomFormState.notes" rows="2"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 resize-none font-semibold text-sm"></textarea>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isRoomModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveRoom"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu phòng
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
