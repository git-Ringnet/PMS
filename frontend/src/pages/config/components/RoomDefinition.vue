<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'TÊN LOẠI PHÒNG'
  }
})

const emit = defineEmits(['update:activeTab'])

const uiStore = useUiStore()

const activeRoomTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  activeRoomTab.value = newVal
})

watch(activeRoomTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const roomTabs = [
  'TÊN LOẠI PHÒNG',
  'DẠNG PHÒNG',
  'GIÁ PHÒNG CHUẨN',
  'PHÒNG'
]

// Data States
const roomClasses = ref([])
const roomForms = ref([])
const standardRates = ref([])
const rooms = ref([])
const loading = ref(false)

// Modal State
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

// Load initial data
onMounted(() => {
  fetchRoomClasses()
  fetchRoomForms()
  fetchStandardRates()
  fetchRooms()
})

// API Functions
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

const fetchStandardRates = async () => {
  try {
    const res = await http.get('/standard-rates')
    standardRates.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải giá phòng chuẩn:', err)
  }
}

const fetchRooms = async () => {
  try {
    const res = await http.get('/rooms')
    rooms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải danh sách phòng:', err)
  }
}

// Room CRUD functions
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
  } catch (err) {
    console.error(err)
    uiStore.showToast('Có lỗi xảy ra khi lưu phòng', 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoom = async (roomId) => {
  if (!confirm('Bạn có chắc chắn muốn xóa phòng này?')) return
  try {
    await http.delete(`/rooms/${roomId}`)
    uiStore.showToast('Xóa phòng thành công!', 'success')
    fetchRooms()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa phòng này', 'error')
  }
}

const formatCurrency = (val) => {
  if (val === null || val === undefined || isNaN(Number(val))) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val))
}
</script>

<template>
  <div class="flex-1 flex flex-col gap-4">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button 
          v-for="tab in roomTabs" 
          :key="tab"
          @click="activeRoomTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeRoomTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 bg-white rounded-xl shadow-xs border border-slate-200 p-6 overflow-y-auto min-h-[400px]">
      <!-- Sub Tab 1: Room Classes -->
      <div v-if="activeRoomTab === 'TÊN LOẠI PHÒNG'" class="overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Tên loại phòng</h3>
          <button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded text-sm font-bold border-none cursor-pointer">+ Thêm loại</button>
        </div>
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">Tên loại phòng</th>
              <th class="p-3">Tên viết tắt</th>
              <th class="p-3">Màu sắc</th>
              <th class="p-3">Có sử dụng</th>
              <th class="p-3">Nhóm loại phòng</th>
              <th class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rc in roomClasses" :key="rc.id" class="border-b border-slate-100 hover:bg-slate-50/55">
              <td class="p-3 font-bold text-slate-800">{{ rc.name }}</td>
              <td class="p-3 font-semibold text-slate-500">{{ rc.code }}</td>
              <td class="p-3">
                <div class="flex items-center gap-2">
                  <span class="w-4 h-4 rounded-full border border-slate-200 block shadow-xs" :style="{ backgroundColor: rc.color }"></span>
                  <span class="font-mono text-slate-400">{{ rc.color }}</span>
                </div>
              </td>
              <td class="p-3">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" :checked="rc.is_active" class="sr-only peer" disabled />
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </td>
              <td class="p-3 text-slate-500 font-semibold uppercase text-xs">{{ rc.group }}</td>
              <td class="p-3 text-right">
                <button class="p-1 hover:bg-slate-100 rounded text-slate-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Sub Tab 2: Room Forms -->
      <div v-if="activeRoomTab === 'DẠNG PHÒNG'" class="overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Dạng phòng (Giường)</h3>
          <button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded text-sm font-bold border-none cursor-pointer">+ Thêm dạng</button>
        </div>
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">Dạng phòng</th>
              <th class="p-3">Người lớn</th>
              <th class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rf in roomForms" :key="rf.id" class="border-b border-slate-100 hover:bg-slate-50/55">
              <td class="p-3 font-bold text-slate-800">{{ rf.name }}</td>
              <td class="p-3 font-bold text-slate-600">{{ rf.max_adults }}</td>
              <td class="p-3 text-right">
                <button class="p-1 hover:bg-slate-100 rounded text-slate-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Sub Tab 3: Standard Rates -->
      <div v-if="activeRoomTab === 'GIÁ PHÒNG CHUẨN'" class="overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Giá phòng chuẩn</h3>
          <button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded text-sm font-bold border-none cursor-pointer">+ Cập nhật bảng giá</button>
        </div>
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">Loại phòng</th>
              <th class="p-3">Dạng phòng</th>
              <th class="p-3">Giá phòng</th>
              <th class="p-3">Giá thêm giường</th>
              <th class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rate in standardRates" :key="rate.id" class="border-b border-slate-100 hover:bg-slate-50/55">
              <td class="p-3 font-bold text-slate-800">{{ rate.room_class?.name }}</td>
              <td class="p-3 font-bold text-slate-600">{{ rate.room_form?.name }}</td>
              <td class="p-3 font-extrabold text-sky-700">{{ formatCurrency(rate.room_price) }}</td>
              <td class="p-3 font-bold text-amber-600">{{ formatCurrency(rate.extra_bed_price) }}</td>
              <td class="p-3 text-right">
                <button class="p-1 hover:bg-slate-100 rounded text-slate-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Sub Tab 4: Rooms -->
      <div v-if="activeRoomTab === 'PHÒNG'" class="overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Phòng thực tế</h3>
          <div class="flex gap-2">
            <button 
              @click="openAddRoomModal"
              class="px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-bold flex items-center gap-1 border-none cursor-pointer shadow-xs transition-colors"
            >
              + Thêm phòng
            </button>
            <button class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-sm font-bold flex items-center gap-1 border-none cursor-pointer transition-colors">
              Nhập Excel
            </button>
          </div>
        </div>
        
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">PHÒNG</th>
              <th class="p-3">DẠNG PHÒNG</th>
              <th class="p-3">TÊN LOẠI PHÒNG</th>
              <th class="p-3">TẦNG</th>
              <th class="p-3 text-center">Khách tối đa</th>
              <th class="p-3 text-center">Thêm giường</th>
              <th class="p-3 text-center">Vị trí (Hàng, Cột)</th>
              <th class="p-3">Phòng nội bộ</th>
              <th class="p-3">Ghi chú</th>
              <th class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rooms" :key="r.id" class="border-b border-slate-100 hover:bg-slate-50/55">
              <td class="p-3 font-black text-slate-800 text-base">{{ r.room_number }}</td>
              <td class="p-3 font-semibold text-slate-600">{{ r.room_form?.name }}</td>
              <td class="p-3 font-bold text-sky-700">{{ r.room_class?.name }}</td>
              <td class="p-3 text-slate-500 font-bold">Tầng {{ r.floor }}</td>
              <td class="p-3 text-center font-bold text-slate-600">{{ r.max_guests }}</td>
              <td class="p-3 text-center font-bold text-slate-500">{{ r.extra_beds_limit }}</td>
              <td class="p-3 text-center text-slate-500 font-mono">({{ r.grid_row }}, {{ r.grid_column }})</td>
              <td class="p-3">
                <span 
                  class="px-2 py-0.5 rounded-sm font-extrabold text-xs uppercase shadow-2xs"
                  :class="r.is_internal ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-green-100 text-green-700 border border-green-200'"
                >
                  {{ r.is_internal ? 'Nội bộ' : 'Thương mại' }}
                </span>
              </td>
              <td class="p-3 text-slate-400 italic max-w-[120px] truncate" :title="r.notes">{{ r.notes || '-' }}</td>
              <td class="p-3 text-right">
                <div class="flex justify-end gap-1.5">
                  <button 
                    @click="openEditRoomModal(r)"
                    class="p-1 hover:bg-slate-100 rounded text-sky-600 bg-transparent border-none cursor-pointer"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button 
                    @click="deleteRoom(r.id)"
                    class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- OVERLAY MODAL: ADD / EDIT ROOM -->
  <div 
    v-if="isRoomModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none"
  >
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="bg-sky-500 px-6 py-4 flex items-center justify-between text-white">
        <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa phòng' : 'Thêm phòng' }}</h2>
        <button 
          @click="isRoomModalOpen = false" 
          class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black"
        >
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
              <input 
                type="text" 
                v-model="roomFormState.room_number" 
                placeholder="Nhập số phòng..." 
                class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 focus:bg-white text-sm" 
              />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>DẠNG PHÒNG</span>
              <select v-model="roomFormState.room_form_id" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                <option v-for="f in roomForms" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>TÊN LOẠI PHÒNG</span>
            <select v-model="roomFormState.room_class_id" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
              <option v-for="c in roomClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <!-- Section 2: Floor/Grid Info -->
        <div class="flex flex-col gap-3">
          <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Thông tin tầng*</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Khách hàng</span>
              <input type="number" v-model="roomFormState.max_guests" class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Tầng</span>
              <input type="text" v-model="roomFormState.floor" placeholder="Nhập tầng..." class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Khu vực</span>
              <select v-model="roomFormState.area" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                <option value="Khu A">Khu vực A</option>
                <option value="Khu B">Khu vực B</option>
                <option value="Khu C">Khu vực C</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Thêm giường</span>
              <input type="number" v-model="roomFormState.extra_beds_limit" class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Hàng</span>
              <input type="number" v-model="roomFormState.grid_row" class="border border-slate-200 rounded-lg p-2.5 font-mono text-red-500 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Cột</span>
              <input type="number" v-model="roomFormState.grid_column" class="border border-slate-200 rounded-lg p-2.5 font-mono text-red-500 focus:outline-sky-500 text-sm" />
            </div>
          </div>
        </div>

        <!-- Section 3: Others -->
        <div class="flex flex-col gap-3">
          <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Khác*</h3>
          <div class="flex flex-col gap-1.5">
            <span>Phòng chủ sở hữu</span>
            <select v-model="roomFormState.owner_room" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
              <option value="">Không có</option>
              <option value="Chủ sở hữu A">Chủ sở hữu A</option>
              <option value="Chủ sở hữu B">Chủ sở hữu B</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4 items-center">
            <div class="flex flex-col gap-1.5">
              <span>Liên kết</span>
              <input type="text" v-model="roomFormState.linked_room" class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-2 pt-5 select-none">
              <div class="flex items-center justify-between">
                <span>Phòng nội bộ</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="roomFormState.is_internal" class="sr-only peer">
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Ghi chú</span>
            <textarea v-model="roomFormState.notes" rows="2" class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 resize-none font-semibold text-sm"></textarea>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
        <button 
          @click="isRoomModalOpen = false" 
          class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors"
        >
          Đóng
        </button>
        <button 
          @click="saveRoom"
          class="px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors"
        >
          Lưu phòng
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
