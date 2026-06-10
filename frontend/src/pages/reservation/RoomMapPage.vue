<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoomStore } from '@/stores/room-store'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'
import RoomDetailModal from '@/components/RoomDetailModal.vue'

const roomStore = useRoomStore()
const uiStore = useUiStore()

const showDetailModal = ref(false)
const isLoaded = ref(false)
const showSearch = ref(false)

// Top toggle state: isFuture (false = Hiện tại, true = Tương Lai)
const isFuture = ref(false)
const rawDate = ref(new Date().toISOString().split('T')[0])

// Bottom toggle state: isGridMode (true = Bảng, false = Lưới)
const isGridMode = ref(true)

function formatDate(date) {
  const d = new Date(date)
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

const selectedDate = computed(() => {
  return formatDate(new Date(rawDate.value))
})

watch(isFuture, (newVal) => {
  if (!newVal) {
    rawDate.value = new Date().toISOString().split('T')[0]
  }
})

watch(rawDate, async () => {
  isLoaded.value = false
  await roomStore.fetchRooms()
  isLoaded.value = true
})

// Circular widgets stats computed dynamically
const checkinStats = computed(() => {
  const count = roomStore.rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).length
  return `${count}/23`
})

const checkoutStats = computed(() => {
  const count = roomStore.rooms.filter(r => r.status === ROOM_STATUSES.CHECKOUT).length
  return `${count}/31`
})

const occupiedStats = computed(() => {
  const count = roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length
  return `${count}/${roomStore.rooms.length || 107}`
})

const occupancyRateStats = computed(() => {
  return `${roomStore.occupancyRate}%`
})

// Sorted Floors list
const sortedFloors = computed(() => {
  const floors = Object.keys(roomStore.roomsByFloor)
    .map(Number)
    .sort((a, b) => a - b)
  return floors
})

const activeFilter = computed(() => roomStore.filters.status)

// Methods
function handleRoomClick(room) {
  roomStore.selectRoom(room)
  showDetailModal.value = true
}

function closeModal() {
  showDetailModal.value = false
  roomStore.clearSelectedRoom()
}

function filterByStatus(status) {
  if (roomStore.filters.status === status) {
    roomStore.setFilter('status', null)
  } else {
    roomStore.setFilter('status', status)
  }
}

function resetAllFilters() {
  roomStore.resetFilters()
}

// Show Warning toast for features under development
function showDevelopmentToast(featureName) {
  uiStore.showToast(`Tính năng "${featureName}" đang được phát triển!`, 'warning')
}

// Logic for status dot on the top right
function getRoomDotClass(room) {
  if (room.status === ROOM_STATUSES.MAINTENANCE || room.status === ROOM_STATUSES.CHECKOUT) {
    return 'bg-red-500'
  }
  if (room.status === ROOM_STATUSES.RESERVED) {
    return 'bg-green-500'
  }
  if (room.status === ROOM_STATUSES.OCCUPIED) {
    if (room.id % 3 === 0) return 'bg-red-500'
    if (room.id % 3 === 1) return 'bg-green-500'
  }
  return null
}

// Room number red color warning rule
function isRoomNumberRed(room) {
  return room.status === ROOM_STATUSES.MAINTENANCE || room.status === ROOM_STATUSES.CHECKOUT || room.has_issue || (room.id % 7 === 0)
}

// Show Broom icon for dirty rooms
function shouldShowBroom(room) {
  return room.status === ROOM_STATUSES.DIRTY || !room.is_clean
}

// Show Sparkles icon for clean vacant rooms
function shouldShowSparkles(room) {
  return room.is_clean && room.status !== ROOM_STATUSES.OCCUPIED && room.status !== ROOM_STATUSES.DIRTY && (room.id % 2 === 0)
}// Guest names list matching image 2
function getMockGuestName(room) {
  if (room.status === ROOM_STATUSES.OCCUPIED) {
    const names = [
      'Ms.MARDANOVA DURDONA',
      'Ms.IVANOVA DARIA',
      'Ms.KOVALEVA NATALIA',
      'Mr.Guest 1',
      'Walkin Guest',
      'Mr.Rachid Boufarki',
      'Mr.MARDANOV FAYOZJON',
      'Mr.RYBCHUK ALEXANDR',
      'Ms.MUNAITPASHOVA LYAZZAT',
      'NGUYỄN THỊ HỒNG PHƯƠNG',
    ]
    return names[room.id % names.length]
  }
  if (room.status === ROOM_STATUSES.RESERVED) {
    return 'Walkin Guest'
  }
  return ''
}

// Booking ID generator
function getMockRegId(room) {
  if (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED || room.status === ROOM_STATUSES.CHECKOUT) {
    return room.id % 2000 + 4000
  }
  return ''
}

// Booking details/registration name matching image 2
function getMockRegName(room) {
  if (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED || room.status === ROOM_STATUSES.CHECKOUT) {
    const agencies = [
      'TRAVEL CONCIERGE - 2637449 (COMMITMENT)',
      'PEGAS - 10660748',
      'FUN & SUN TRAVEL - 11723091',
      'Walkin Guest',
      'Agoda - 173481589 - Rachid Boufarki',
      'Trip.Com - 1658111816718262 - KATHY DIỆU TRINH',
      'NGUYỄN THỊ HỒNG PHƯƠNG'
    ]
    return agencies[room.id % agencies.length]
  }
  return ''
}

// Company list matching image 2
function getMockCompany(room) {
  if (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED || room.status === ROOM_STATUSES.CHECKOUT) {
    const companies = [
      'TRAVEL CONCIERGE (COMMITMENT)',
      'PEGAS',
      'FUN & SUN TRAVEL',
      'KHÁCH LẺ',
      'AGODA',
      'TRIP.COM',
      'MANGO TRIP'
    ]
    return companies[room.id % companies.length]
  }
  return ''
}

// Room shape description
function getRoomTypeShape(room) {
  if (room.room_type.includes('D') || room.room_type.includes('Double')) return 'Double'
  if (room.room_type.includes('TB') || room.room_type.includes('Twin')) return 'Twin'
  if (room.room_type.includes('TR') || room.room_type.includes('Triple')) return 'Triple'
  return 'Family'
}

// Date formatter for columns
function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`
}

onMounted(async () => {
  // Run data fetches in parallel to minimize load latency
  await Promise.all([
    roomStore.fetchRooms(),
    roomStore.fetchStats()
  ])
  isLoaded.value = true
})
</script>

<template>
  <div class="flex h-full overflow-hidden">
    <!-- Left Slim Sidebar (Visual Match with circular badges) -->
    <aside class="w-[118px] shrink-0 border-r border-slate-200 bg-white flex flex-col items-center py-3 overflow-y-auto z-20">
      <!-- Date Display (Editable input when isFuture is true, else static today's date text) -->
      <div class="mb-3 w-full px-2 flex flex-col items-center gap-1 shrink-0">
        <input 
          v-if="isFuture"
          type="date" 
          v-model="rawDate" 
          class="text-xs font-black text-slate-700 bg-slate-50 border border-slate-200 rounded px-1.5 py-1 text-center w-full focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
        />
        <div v-else class="text-[12px] font-black text-slate-400 select-none text-center leading-tight tracking-wide py-1">
          {{ selectedDate }}
        </div>
      </div>

      <!-- "Hiện tại" / "Tương Lai" Toggle Switch -->
      <div class="flex flex-col items-center gap-1.5 mb-4 select-none">
        <span class="text-[11px] text-slate-500 font-extrabold uppercase tracking-wider transition-colors duration-200" :class="isFuture ? 'text-blue-600' : 'text-slate-500'">
          {{ isFuture ? 'Tương Lai' : 'Hiện tại' }}
        </span>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="isFuture" class="sr-only peer">
          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
        </label>
      </div>

      <!-- Circular Badges -->
      <div class="flex flex-col gap-3.5 items-center w-full border-b border-slate-100 pb-4">
        <!-- Đã đến -->
        <button
          @click="filterByStatus(ROOM_STATUSES.RESERVED)"
          class="w-[76px] h-[76px] rounded-full border flex flex-col justify-center items-center text-center cursor-pointer transition-all duration-200 select-none shadow-sm"
          :class="activeFilter === ROOM_STATUSES.RESERVED 
            ? 'bg-sky-200 border-sky-400 text-sky-700 font-bold scale-105 ring-1 ring-sky-300' 
            : 'bg-[#e0f2fe]/60 border-sky-200/70 hover:bg-[#e0f2fe] text-[#0369a1]'"
        >
          <span class="text-[11px] font-extrabold text-sky-700 uppercase tracking-tight leading-none mb-1">Đã đến</span>
          <span class="text-[14px] font-black text-slate-800 leading-none">{{ checkinStats }}</span>
        </button>

        <!-- Đã đi -->
        <button
          @click="filterByStatus(ROOM_STATUSES.CHECKOUT)"
          class="w-[76px] h-[76px] rounded-full border flex flex-col justify-center items-center text-center cursor-pointer transition-all duration-200 select-none shadow-sm"
          :class="activeFilter === ROOM_STATUSES.CHECKOUT 
            ? 'bg-sky-200 border-sky-400 text-sky-700 font-bold scale-105 ring-1 ring-sky-300' 
            : 'bg-[#e0f2fe]/60 border-sky-200/70 hover:bg-[#e0f2fe] text-[#0369a1]'"
        >
          <span class="text-[11px] font-extrabold text-sky-700 uppercase tracking-tight leading-none mb-1">Đã đi</span>
          <span class="text-[14px] font-black text-slate-800 leading-none">{{ checkoutStats }}</span>
        </button>

        <!-- Đang ở -->
        <button
          @click="filterByStatus(ROOM_STATUSES.OCCUPIED)"
          class="w-[76px] h-[76px] rounded-full border flex flex-col justify-center items-center text-center cursor-pointer transition-all duration-200 select-none shadow-sm"
          :class="activeFilter === ROOM_STATUSES.OCCUPIED 
            ? 'bg-sky-200 border-sky-400 text-sky-700 font-bold scale-105 ring-1 ring-sky-300' 
            : 'bg-[#e0f2fe]/60 border-sky-200/70 hover:bg-[#e0f2fe] text-[#0369a1]'"
        >
          <span class="text-[11px] font-extrabold text-sky-700 uppercase tracking-tight leading-none mb-1">Đang ở</span>
          <span class="text-[14px] font-black text-slate-800 leading-none">{{ occupiedStats }}</span>
        </button>

        <!-- Thống kê -->
        <button
          @click="resetAllFilters"
          class="w-[76px] h-[76px] rounded-full border flex flex-col justify-center items-center text-center cursor-pointer transition-all duration-200 select-none shadow-sm"
          :class="!activeFilter 
            ? 'bg-sky-200 border-sky-400 text-sky-700 font-bold scale-105 ring-1 ring-sky-300' 
            : 'bg-[#e0f2fe]/60 border-sky-200/70 hover:bg-[#e0f2fe] text-[#0369a1]'"
        >
          <span class="text-[11px] font-extrabold text-sky-700 uppercase tracking-tight leading-none mb-1">Thống kê</span>
          <span class="text-[14px] font-black text-slate-800 leading-none">{{ occupancyRateStats }}</span>
        </button>
      </div>

      <!-- Action Buttons Vertical Stack -->
      <div class="flex flex-col gap-3 w-full px-2 py-3 items-center flex-1">
        <!-- Dashboard / Stats -->
        <button
          @click="showDevelopmentToast('Thống kê chi tiết')"
          class="w-11 h-11 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer border-none transition-colors"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
          </svg>
        </button>

        <!-- Guest Directory -->
        <button
          @click="showDevelopmentToast('Danh sách khách hàng')"
          class="w-11 h-11 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer border-none transition-colors"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
        </button>

        <!-- Room Key Status -->
        <button
          @click="showDevelopmentToast('Quản lý khóa thẻ từ')"
          class="w-11 h-11 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer border-none transition-colors"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4" />
          </svg>
        </button>

        <!-- Reports -->
        <button
          @click="showDevelopmentToast('Báo cáo doanh thu')"
          class="w-11 h-11 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer border-none transition-colors"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" />
          </svg>
        </button>

        <!-- Search Toggle -->
        <button
          @click="showSearch = !showSearch"
          class="w-11 h-11 flex items-center justify-center rounded-lg cursor-pointer border-none transition-colors"
          :class="showSearch ? 'bg-blue-500 text-white hover:bg-blue-600' : 'bg-slate-100 hover:bg-slate-200 text-slate-500'"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
        </button>

        <!-- Help Info -->
        <button
          @click="showDevelopmentToast('Trung tâm trợ giúp')"
          class="w-11 h-11 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer border-none transition-colors"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
        </button>

        <!-- "Bảng" / "Lưới" Toggle -->
        <div class="flex flex-col items-center gap-1.5 my-1 select-none">
          <span class="text-[11px] text-slate-500 font-extrabold uppercase tracking-wider transition-colors duration-200" :class="isGridMode ? 'text-blue-600' : 'text-slate-500'">
            {{ isGridMode ? 'Bảng' : 'Lưới' }}
          </span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="isGridMode" class="sr-only peer">
            <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-500"></div>
          </label>
        </div>

        <!-- Settings (Gear) at bottom -->
        <button
          @click="showDevelopmentToast('Cấu hình nâng cao')"
          class="w-11 h-11 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer border-none transition-colors mt-auto"
        >
          <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
          </svg>
        </button>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-x-auto overflow-y-auto bg-slate-100 py-4 pr-4 pl-0 flex flex-col gap-2.5">
      <!-- Toggled Search Input Header -->
      <div v-if="showSearch" class="bg-white rounded-lg p-2.5 border border-slate-200 shadow-sm flex items-center gap-3 w-full shrink-0 ml-14">
        <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input
          v-model="roomStore.filters.search"
          type="text"
          placeholder="Tìm theo số phòng, loại phòng..."
          class="border-none bg-transparent text-sm focus:outline-none flex-1 text-slate-700"
        />
        <button
          v-if="roomStore.filters.search"
          @click="roomStore.filters.search = ''"
          class="text-xs text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer"
        >
          Xóa
        </button>
      </div>

      <!-- Loading State (Premium 3D Rotating Rings Loader) -->
      <div v-if="roomStore.loading" class="flex items-center justify-center flex-1 relative min-h-[250px]">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="roomStore.error" class="flex items-center justify-center flex-1">
        <div class="text-center p-8 bg-white rounded-xl shadow-sm max-w-md border border-slate-200">
          <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <p class="text-slate-700 font-medium mb-2">{{ roomStore.error }}</p>
          <button
            @click="roomStore.fetchRooms()"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors cursor-pointer border-none shadow-sm"
          >
            Thử lại
          </button>
        </div>
      </div>

      <!-- Room Grid System (isGridMode is true) -->
      <div v-else-if="isGridMode" class="flex-1 flex flex-col gap-2.5 w-[133.33%] pr-4">
        <div
          v-for="floor in sortedFloors"
          :key="floor"
          class="flex items-stretch gap-3 transition-opacity duration-150"
          :class="isLoaded ? 'opacity-100' : 'opacity-0'"
        >
          <!-- Floor Rounded Label Badge on the left (Sticky bg-slate-100 acts as opaque cover when scrolling) -->
          <div class="w-14 shrink-0 flex items-center justify-center select-none sticky left-0 z-10 bg-slate-100 pr-1.5">
            <div class="w-9 h-9 rounded-md bg-[#a6dcfc] text-[#1d4ed8] font-black text-sm flex items-center justify-center shadow-sm">
              {{ floor }}
            </div>
          </div>

          <!-- Rooms Grid row (12 columns) -->
          <div class="flex-1 grid grid-cols-12 gap-3">
            <div
              v-for="room in roomStore.roomsByFloor[floor]"
              :key="room.id"
              class="room-card relative cursor-pointer border rounded-xl p-3.5 min-h-[110px] flex flex-col justify-between select-none transition-all duration-200"
              :class="[
                room.status === ROOM_STATUSES.OCCUPIED 
                  ? 'bg-[#a6dcfc] hover:bg-[#8ecefa] border-[#7ec0f3] text-slate-800' 
                  : 'bg-white hover:bg-slate-50 border-slate-200/80 text-slate-700 shadow-sm'
              ]"
              @click="handleRoomClick(room)"
            >
              <!-- Status Dot (Top Right) -->
              <div class="absolute top-2.5 right-2.5">
                <span
                  v-if="getRoomDotClass(room)"
                  class="w-3 h-3 rounded-full block shadow-sm border border-white/20"
                  :class="getRoomDotClass(room)"
                ></span>
              </div>

              <!-- Room Number (Top Left) -->
              <div class="font-black text-[20px] leading-tight">
                <span :class="isRoomNumberRed(room) ? 'text-red-500' : 'text-slate-800'">
                  {{ room.room_number }}
                </span>
              </div>

              <!-- Room Type & Guests (Bottom Left) -->
              <div class="flex items-end justify-between mt-auto">
                <div class="text-[12px] font-bold text-slate-600 leading-tight">
                  <span v-if="room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED">
                    {{ room.room_type }} - SL khách: {{ room.max_guests }}
                  </span>
                  <span v-else>
                    {{ room.room_type }}
                  </span>
                </div>

                <!-- Clean/Dirty Icon Badge (Bottom Right) -->
                <div class="shrink-0 ml-1">
                  <!-- Broom SVG for dirty rooms -->
                  <svg
                    v-if="shouldShowBroom(room)"
                    class="w-5.5 h-5.5 text-slate-500/80 fill-current"
                    viewBox="0 0 24 24"
                  >
                    <path d="M19 19L5 5M12 12l2.5-2.5m1.5-1.5l1.5-1.5M7.5 7.5L5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5.5 19.5c.6.6 1.4 1 2.3 1H10l9-9c1-1 1-2.6 0-3.5l-1.5-1.5c-1-1-2.6-1-3.5 0l-9 9v2.2c0 .9.4 1.7 1 2.3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  
                  <!-- Sparkles SVG for clean vacant rooms -->
                  <svg
                    v-else-if="shouldShowSparkles(room)"
                    class="w-5 h-5 text-slate-700/80"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Room Table System (isGridMode is false, UI like image 2) -->
      <div v-else class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto overflow-y-auto min-h-[300px] ml-1">
        <table class="w-full text-left border-collapse text-xs table-fixed min-w-[1400px]">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none whitespace-nowrap">
              <th class="p-2 border-r border-slate-200 text-center w-[60px]">TTDK</th>
              <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">Nhận phòng trễ</th>
              <th class="p-2 border-r border-slate-200 text-center w-[100px] leading-tight text-[10px]">Chuyển phòng kế hoạch</th>
              <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">TT Phòng</th>
              <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">Thêm giường</th>
              <th class="p-2 border-r border-slate-200 text-center w-[80px] leading-tight text-[10px]">Yêu cầu DB</th>
              <th class="p-2 border-r border-slate-200 text-center w-[85px]">Loại phòng</th>
              <th class="p-2 border-r border-slate-200 text-center w-[95px]">Dạng phòng</th>
              <th class="p-2 border-r border-slate-200 text-center w-[75px]">Phòng</th>
              <th class="p-2 border-r border-slate-200 w-[180px]">Tên khách</th>
              <th class="p-2 border-r border-slate-200 text-center w-[75px]">Mã DK</th>
              <th class="p-2 border-r border-slate-200 w-[240px]">Tên đăng ký</th>
              <th class="p-2 border-r border-slate-200 text-center w-[95px]">Ngày đến</th>
              <th class="p-2 border-r border-slate-200 text-center w-[95px]">Ngày đi</th>
              <th class="p-2 border-r border-slate-200 w-[200px]">Công ty</th>
              <th class="p-2 text-center w-[60px]">Tầng</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="room in roomStore.filteredRooms"
              :key="room.id"
              class="border-b border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer select-none h-9"
              :class="room.status === ROOM_STATUSES.OCCUPIED ? 'bg-[#a6dcfc]/80 hover:bg-[#8ecefa]/80' : 'bg-white'"
              @click="handleRoomClick(room)"
            >
              <!-- TTDK (Status Dot) -->
              <td class="p-2 border-r border-slate-200 text-center">
                <div class="flex items-center justify-center">
                  <span
                    v-if="getRoomDotClass(room)"
                    class="w-2.5 h-2.5 rounded-full block border border-white/20 shadow-sm"
                    :class="getRoomDotClass(room)"
                  ></span>
                </div>
              </td>
              <!-- Nhận phòng trễ -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold">-</td>
              <!-- Chuyển phòng kế hoạch -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold">-</td>
              <!-- TT Phòng (Broom/Sparkle icon) -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                <div class="flex items-center justify-center">
                  <!-- Broom SVG for dirty rooms -->
                  <svg
                    v-if="shouldShowBroom(room)"
                    class="w-4.5 h-4.5 text-slate-600/95"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M19 19L5 5M12 12l2.5-2.5m1.5-1.5l1.5-1.5M7.5 7.5L5 5" />
                    <path d="M5.5 19.5c.6.6 1.4 1 2.3 1H10l9-9c1-1 1-2.6 0-3.5l-1.5-1.5c-1-1-2.6-1-3.5 0l-9 9v2.2c0 .9.4 1.7 1 2.3Z" />
                  </svg>
                  
                  <!-- Sparkles SVG for clean vacant rooms -->
                  <svg
                    v-else-if="shouldShowSparkles(room)"
                    class="w-4 h-4 text-slate-700/80"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
                  </svg>
                  <span v-else class="text-slate-400 font-bold">-</span>
                </div>
              </td>
              <!-- Thêm giường -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold">-</td>
              <!-- Yêu cầu DB -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold">-</td>
              <!-- Loại phòng -->
              <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                {{ room.room_type }}
              </td>
              <!-- Dạng phòng -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold text-[11px]">
                {{ getRoomTypeShape(room) }}
              </td>
              <!-- Phòng (Room number) -->
              <td class="p-2 border-r border-slate-200 text-center font-black text-[13px]">
                <span :class="isRoomNumberRed(room) ? 'text-red-500' : 'text-slate-800'">
                  {{ room.room_number }}
                </span>
              </td>
              <!-- Tên khách -->
              <td class="p-2 border-r border-slate-200 font-bold text-slate-700 truncate">
                {{ getMockGuestName(room) }}
              </td>
              <!-- Mã DK -->
              <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-800 text-[11px]">
                {{ getMockRegId(room) }}
              </td>
              <!-- Tên đăng ký -->
              <td class="p-2 border-r border-slate-200 text-slate-700 font-bold text-[11px] truncate">
                {{ getMockRegName(room) }}
              </td>
              <!-- Ngày đến -->
              <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 text-[11px]">
                {{ formatDateShort(room.check_in) || (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED ? '09-06-2026' : '') }}
              </td>
              <!-- Ngày đi -->
              <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 text-[11px]">
                {{ formatDateShort(room.check_out) || (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED ? '14-06-2026' : '') }}
              </td>
              <!-- Công ty -->
              <td class="p-2 border-r border-slate-200 text-slate-800 font-extrabold text-[11px] truncate">
                {{ getMockCompany(room) }}
              </td>
              <!-- Tầng -->
              <td class="p-2 text-center font-black text-slate-800">
                {{ room.floor }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Room Detail Modal -->
    <RoomDetailModal
      v-if="showDetailModal && roomStore.selectedRoom"
      :room="roomStore.selectedRoom"
      @close="closeModal"
    />
  </div>
</template>
