<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import CompanySettingsPage from './company/CompanySettingsPage.vue'

const router = useRouter()
const uiStore = useUiStore()

// View state: 'menu', 'hotel', 'room', 'company'
const currentView = ref('menu')
const activeHotelTab = ref('THÔNG TIN KHÁCH SẠN')
const activeRoomTab = ref('TÊN LOẠI PHÒNG')

const hotelTabs = [
  'THÔNG TIN KHÁCH SẠN',
  'DỊCH VỤ KHÁCH SẠN',
  'CA LÀM VIỆC',
  'CẤU HÌNH',
  'CHI NHÁNH',
  'MẪU',
  'BỘ PHẬN DỊCH VỤ',
  'THIẾT LẬP BÁO CÁO TỔNG HỢP'
]

const roomTabs = [
  'TÊN LOẠI PHÒNG',
  'DẠNG PHÒNG',
  'GIÁ PHÒNG CHUẨN',
  'PHÒNG'
]

// Data States
const hotelForm = reactive({
  code: '',
  name: '',
  address: '',
  tax_code: '',
  phone: '',
  fax: '',
  email: '',
  facebook: '',
  channel_manager: '',
  currency: 'VND',
  bank_name: '',
  bank_account_name: '',
  bank_account_number: '',
  adult_breakfast_price: 0,
  child_breakfast_price: 0,
  extra_bed_price: 0,
  total_rooms: 0,
  website: '',
  booking_prefix: ''
})

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
  fetchHotelSettings()
  fetchRoomClasses()
  fetchRoomForms()
  fetchStandardRates()
  fetchRooms()
})

// API Functions
const fetchHotelSettings = async () => {
  try {
    const res = await http.get('/hotel-settings')
    if (res.data && res.data.data) {
      Object.assign(hotelForm, res.data.data)
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  }
}

const saveHotelSettings = async () => {
  loading.value = true
  try {
    await http.put('/hotel-settings', hotelForm)
    uiStore.showToast('Lưu thông tin khách sạn thành công!', 'success')
  } catch (err) {
    console.error('Lỗi khi lưu cấu hình khách sạn:', err)
    uiStore.showToast('Không thể lưu cấu hình khách sạn', 'error')
  } finally {
    loading.value = false
  }
}

const fetchRoomClasses = async () => {
  try {
    const res = await http.get('/room-classes')
    roomClasses.value = res.data.data || []
  } catch (err) {
    console.error(err)
  }
}

const fetchRoomForms = async () => {
  try {
    const res = await http.get('/room-forms')
    roomForms.value = res.data.data || []
  } catch (err) {
    console.error(err)
  }
}

const fetchStandardRates = async () => {
  try {
    const res = await http.get('/standard-rates')
    standardRates.value = res.data.data || []
  } catch (err) {
    console.error(err)
  }
}

const fetchRooms = async () => {
  try {
    const res = await http.get('/rooms')
    rooms.value = res.data.data || []
  } catch (err) {
    console.error(err)
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

function handleBack() {
  if (currentView.value === 'menu') {
    router.push('/')
  } else {
    currentView.value = 'menu'
  }
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}
</script>

<template>
  <div class="flex-1 bg-slate-50 p-6 flex flex-col gap-4 overflow-y-auto h-full text-slate-800">
    
    <!-- Header: Dynamic Back Arrow & View Title -->
    <div class="flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <button 
          @click="handleBack"
          class="flex items-center gap-2 text-slate-600 hover:text-slate-900 font-black bg-transparent border-none cursor-pointer text-base"
        >
          <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          <span class="uppercase tracking-wider text-sm">
            {{ 
              currentView === 'menu' ? 'Cấu hình hệ thống' : 
              currentView === 'hotel' ? 'Định nghĩa khách sạn' : 
              currentView === 'company' ? 'Công Ty' : 'Định nghĩa phòng'
            }}
          </span>
        </button>
      </div>

      <!-- Action Save Button for Hotel Info Form -->
      <div v-if="currentView === 'hotel' && activeHotelTab === 'THÔNG TIN KHÁCH SẠN'">
        <button 
          @click="saveHotelSettings"
          class="px-5 py-2 bg-sky-500 hover:bg-sky-600 text-white font-bold rounded-lg text-sm flex items-center gap-2 border-none shadow-sm cursor-pointer transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu cấu hình
        </button>
      </div>
    </div>

    <!-- VIEW 1: LANDING CONFIGURATION MENU (5 Cards - Screenshot 1) -->
    <template v-if="currentView === 'menu'">
      <div class="flex-1 flex items-center justify-center min-h-[400px]">
        <div class="flex flex-wrap items-center justify-center gap-6 p-4">
          <!-- Card 1: Định nghĩa khách sạn -->
          <div 
            @click="currentView = 'hotel'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa khách sạn</span>
          </div>

          <!-- Card 2: Định nghĩa phòng -->
          <div 
            @click="currentView = 'room'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa phòng</span>
          </div>

          <!-- Card 3: Định nghĩa hệ thống -->
          <div 
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa hệ thống</span>
          </div>

          <!-- Card 4: Thiết lập giá -->
          <div 
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Thiết lập giá</span>
          </div>

          <!-- Card 5: Định nghĩa channel manager -->
          <div 
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a3 3 0 00-3-3.12 3 3 0 00-3 3.12m-6-6a3 3 0 00-3-3.12 3 3 0 00-3 3.12M12 6.5a3 3 0 100-6 3 3 0 000 6z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12m-9 0a9 9 0 1118 0 9 9 0 01-18 0z" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa channel manager</span>
          </div>

          <!-- Card 6: Công Ty -->
          <div 
            @click="currentView = 'company'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Công Ty</span>
          </div>
        </div>
      </div>
    </template>

    <!-- VIEW 2: DỊCH VỤ / TÁC VỤ CHI TIẾT -->
    <template v-else>
      <!-- Sub Navigation Tabs Bar -->
      <div class="border-b border-slate-200 shrink-0">
        <div class="flex flex-wrap gap-1">
          <template v-if="currentView === 'hotel'">
            <button 
              v-for="tab in hotelTabs" 
              :key="tab"
              @click="activeHotelTab = tab"
              class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
              :class="activeHotelTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'"
            >
              {{ tab }}
            </button>
          </template>
          <template v-else-if="currentView === 'room'">
            <button 
              v-for="tab in roomTabs" 
              :key="tab"
              @click="activeRoomTab = tab"
              class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
              :class="activeRoomTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'"
            >
              {{ tab }}
            </button>
          </template>
        </div>
      </div>

      <!-- Detail Card Content -->
      <div class="flex-1 bg-white rounded-xl shadow-xs border border-slate-200 p-6 overflow-y-auto min-h-[400px]">
        
        <!-- INNER VIEW: HOTEL CONFIGURATION -->
        <template v-if="currentView === 'hotel'">
          <!-- Sub Tab 1: Hotel Info Form -->
          <div v-if="activeHotelTab === 'THÔNG TIN KHÁCH SẠN'" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Col 1: Basic settings -->
            <div class="lg:col-span-5 flex flex-col gap-4">
              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Mã KS</span>
                <input type="text" v-model="hotelForm.code" class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Tên KS/KNM</span>
                <input type="text" v-model="hotelForm.name" class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Địa chỉ</span>
                <textarea v-model="hotelForm.address" rows="2" class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm resize-none"></textarea>
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Thuế</span>
                <input type="text" v-model="hotelForm.tax_code" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Số điện thoại</span>
                <input type="text" v-model="hotelForm.phone" class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Số fax</span>
                <input type="text" v-model="hotelForm.fax" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Email</span>
                <input type="email" v-model="hotelForm.email" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Facebook</span>
                <input type="text" v-model="hotelForm.facebook" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Kênh quản lý</span>
                <input type="text" v-model="hotelForm.channel_manager" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Tiền tệ</span>
                <select v-model="hotelForm.currency" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold bg-white text-sm">
                  <option value="VND">🇻🇳 VND</option>
                  <option value="USD">🇺🇸 USD</option>
                </select>
              </div>
            </div>

            <!-- Col 2: Bank details and prices -->
            <div class="lg:col-span-4 flex flex-col gap-4">
              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Tên ngân hàng</span>
                <input type="text" v-model="hotelForm.bank_name" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Tên tài khoản</span>
                <input type="text" v-model="hotelForm.bank_account_name" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Số tài khoản</span>
                <input type="text" v-model="hotelForm.bank_account_number" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Giá ăn sáng NL</span>
                <input type="number" v-model="hotelForm.adult_breakfast_price" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Giá ăn sáng trẻ em</span>
                <input type="number" v-model="hotelForm.child_breakfast_price" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Giá Thêm Giường</span>
                <input type="number" v-model="hotelForm.extra_bed_price" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Số phòng</span>
                <input type="number" v-model="hotelForm.total_rooms" class="col-span-2 border border-slate-200 rounded-lg p-2.5 bg-yellow-50 focus:outline-sky-500 font-bold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Web Hotel</span>
                <input type="text" v-model="hotelForm.website" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              </div>

              <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
                <span>Tiền tố đăng ký</span>
                <input type="text" v-model="hotelForm.booking_prefix" class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold uppercase text-sm" />
              </div>
            </div>

            <!-- Col 3: Logo and QR -->
            <div class="lg:col-span-3 flex flex-col items-center gap-6">
              <!-- Logo Box -->
              <div class="w-full max-w-[200px] aspect-square rounded-2xl border-2 border-dashed border-slate-200 flex flex-col items-center justify-center p-4 relative bg-slate-50 shadow-inner group">
                <span class="text-xs font-black text-slate-400 absolute top-2 uppercase tracking-wide">Hình ảnh / Logo</span>
                <div class="w-16 h-16 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 mb-2">
                  <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <span class="text-xs text-slate-500 font-extrabold text-center">GALLIOT LOGO</span>
                <div class="flex items-center gap-3 mt-3">
                  <button class="p-1 text-slate-400 hover:text-slate-700 bg-transparent border-none cursor-pointer"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></button>
                  <button class="p-1 text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                </div>
              </div>

              <!-- QR Box -->
              <div class="w-full max-w-[200px] flex flex-col items-center p-3 border border-slate-200 rounded-xl bg-slate-50">
                <svg class="w-28 h-28 text-slate-700" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M0 0h6v6H0V0zm1 1v4h4V1H1zm7-1h6v6H8V0zm1 1v4h4V1H9zm7-1h6v6h-6V0zm1 1v4h4V1h-4zM0 8h6v6H0V8zm1 1v4h4V9H1zm7 0h6v6H8V9zm1 1v4h4v-4H9zm7-1h1v1h-1V9zm1 1h1v1h-1v-1zm-1 1h1v1h-1v-1zm2-2h1v1h-1V9zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1zm-1 3h1v1h-1v-1zm-1-1h1v1h-1v-1zm-1 1h1v1h-1v-1zm2-1h1v1h-1v-1zm1 1h1v1h-1v-1zm-6 2h1v1H8v-1zm1 1h1v1H9v-1zm-1 1h1v1H8v-1zm2-3h1v1h-1v-1zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1zm3 0h1v1h-1v-1zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1zm-6 3h1v1h-1v-1zm1 1h1v1H9v-1zm-1 1h1v1H8v-1zm2-3h1v1h-1v-1zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1z"/>
                </svg>
                <span class="text-xs text-slate-500 font-extrabold mt-1 tracking-wider">MÃ QR THANH TOÁN / ĐẶT PHÒNG</span>
              </div>
            </div>
          </div>

          <!-- Other sub-tabs -->
          <div v-else class="text-center py-12 flex flex-col items-center justify-center gap-3">
            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">{{ activeHotelTab }}</span>
            <p class="text-sm text-slate-400 max-w-xs leading-relaxed">Chức năng cấu hình chi tiết đang được phát triển để đồng bộ hệ thống PMS.</p>
          </div>
        </template>

        <!-- INNER VIEW: ROOM CONFIGURATION -->
        <template v-else-if="currentView === 'room'">
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
        </template>

        <!-- INNER VIEW: COMPANY SETTINGS -->
        <template v-else-if="currentView === 'company'">
          <CompanySettingsPage />
        </template>
      </div>
    </template>

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
/* Scoped custom animations for modal overlay */
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
