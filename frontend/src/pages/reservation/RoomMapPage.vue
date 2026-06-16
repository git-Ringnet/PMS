<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useRoomStore } from '@/stores/room-store'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'
import RoomDetailModal from '@/components/RoomDetailModal.vue'
import AvailableRoomsPage from './AvailableRoomsPage.vue'
import RoomPlanPage from './RoomPlanPage.vue'
import ShiftWorkPage from './ShiftWorkPage.vue'
import LockRoomPage from './LockRoomPage.vue'
import CompanySettingsPage from '@/pages/config/company/CompanySettingsPage.vue'

const roomStore = useRoomStore()
const uiStore = useUiStore()
const route = useRoute()

const currentTab = computed(() => route.query.tab || 'room-map')

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
  const type = room.room_type || room.room_class?.code || '';
  if (type.includes('D') || type.includes('Double')) return 'Double'
  if (type.includes('TB') || type.includes('Twin')) return 'Twin'
  if (type.includes('TR') || type.includes('Triple')) return 'Triple'
  return 'Family'
}

// Date formatter for columns
function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`
}

// Context Menu State
const contextMenu = ref({
  show: false,
  x: 0,
  y: 0,
  room: null,
  isLeft: false,
})

const statusItems = [
  { key: ROOM_STATUSES.AVAILABLE, label: 'Sẵn sàng' },
  { key: ROOM_STATUSES.DIRTY, label: 'Phòng bẩn' },
  { key: ROOM_STATUSES.CHECKOUT, label: 'Lau dọn' },
  { key: ROOM_STATUSES.MAINTENANCE, label: 'Dịch vụ dọn phòng' },
  { key: ROOM_STATUSES.RESERVED, label: 'Phòng ưu tiên' },
  { key: ROOM_STATUSES.OCCUPIED, label: 'Phòng không làm phiền' },
]

function handleContextMenu(event, room) {
  event.preventDefault()
  const isLeft = event.clientX > window.innerWidth - 460
  contextMenu.value = {
    show: true,
    x: event.clientX,
    y: event.clientY,
    room: room,
    isLeft: isLeft,
  }
}

function closeContextMenu() {
  contextMenu.value.show = false
}

// Show room info modal
function showRoomInfo(room) {
  roomStore.selectRoom(room)
  showDetailModal.value = true
  closeContextMenu()
}

// Trigger warning toast for other options
function triggerMenuItem(actionName) {
  uiStore.showToast(`Tính năng "${actionName}" đang được phát triển!`, 'warning')
  closeContextMenu()
}

// Change room status directly from context menu
async function changeRoomStatus(room, newStatus) {
  if (!room || newStatus === room.status) return
  
  const statusLabel = statusItems.find(s => s.key === newStatus)?.label || newStatus
  
  // Close context menu BEFORE showing confirm dialog
  closeContextMenu()
  
  const confirmed = await uiStore.confirm({
    title: 'Đổi trạng thái phòng',
    message: `Bạn có chắc chắn muốn chuyển phòng ${room.room_number} sang trạng thái "${statusLabel}" không?`,
    confirmText: 'Đồng ý',
    cancelText: 'Hủy bỏ'
  })

  if (confirmed) {
    try {
      await roomStore.updateRoomStatus(room.id, newStatus)
      uiStore.showToast(`Đã đổi trạng thái phòng ${room.room_number} sang "${statusLabel}" thành công!`, 'success')
    } catch (err) {
      uiStore.showToast('Không thể cập nhật trạng thái phòng. Vui lòng thử lại!', 'error')
    }
  }
}

onMounted(async () => {
  // Run data fetches in parallel to minimize load latency
  await Promise.all([
    roomStore.fetchRooms(),
    roomStore.fetchStats()
  ])
  isLoaded.value = true
  
  window.addEventListener('click', closeContextMenu)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeContextMenu)
})

watch(() => contextMenu.value.show, (newVal) => {
  if (newVal) {
    window.addEventListener('scroll', closeContextMenu, { passive: true })
  } else {
    window.removeEventListener('scroll', closeContextMenu)
  }
})
</script>

<template>
  <div class="flex h-full overflow-hidden">
    <!-- Left Slim Sidebar (Visual Match with circular badges) -->
    <aside v-if="currentTab !== 'available' && currentTab !== 'room-plan' && currentTab !== 'shift-work' && currentTab !== 'company' && currentTab !== 'lock-room'" class="w-[118px] shrink-0 border-r border-slate-200 bg-white flex flex-col items-center py-3 overflow-y-auto z-20">
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

    <!-- Main Content Area (Phòng Trống Tab AvailableRoomsPage) -->
    <div v-if="currentTab === 'available'" class="flex-1 p-4 bg-slate-100 overflow-hidden">
      <AvailableRoomsPage />
    </div>

    <!-- Main Content Area (Kế hoạch phòng Tab RoomPlanPage) -->
    <div v-else-if="currentTab === 'room-plan'" class="flex-1 p-4 bg-slate-100 overflow-hidden">
      <RoomPlanPage />
    </div>

    <!-- Main Content Area (D.S Công Việc Tab ShiftWorkPage) -->
    <div v-else-if="currentTab === 'shift-work'" class="flex-1 p-4 bg-slate-100 overflow-hidden">
      <ShiftWorkPage />
    </div>

    <!-- Main Content Area (Công Ty Tab CompanySettingsPage) -->
    <div v-else-if="currentTab === 'company'" class="flex-1 p-4 bg-slate-100 overflow-hidden">
      <CompanySettingsPage />
    </div>

    <!-- Main Content Area (Khóa Phòng Tab LockRoomPage) -->
    <div v-else-if="currentTab === 'lock-room'" class="flex-1 p-4 bg-slate-100 overflow-hidden">
      <LockRoomPage />
    </div>

    <!-- Main Content Area (ALLOTMENT Tab) -->
    <div v-else-if="currentTab === 'allotment'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100">
          <div>
            <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Cấu hình phân bổ phòng (Allotment)</h2>
            <p class="text-xs text-slate-400 font-bold mt-1">Quản lý định mức bán phòng qua các đối tác OTA & lữ hành</p>
          </div>
          <button @click="showDevelopmentToast('Thêm Allotment')" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-black shadow-sm transition-all border-none cursor-pointer">
            + Tạo Allotment Mới
          </button>
        </div>
        
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Đối tác OTA / Đại lý</th>
              <th class="p-2.5">Loại phòng phân bổ</th>
              <th class="p-2.5 text-center">Số lượng phân bổ</th>
              <th class="p-2.5 text-center">Đã bán</th>
              <th class="p-2.5 text-center">Còn trống</th>
              <th class="p-2.5 text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { partner: 'Agoda.com', type: 'Deluxe Double', allocated: 10, sold: 6, status: 'Đang mở' },
              { partner: 'Booking.com', type: 'Executive Suite', allocated: 5, sold: 3, status: 'Đang mở' },
              { partner: 'Travel Concierge', type: 'Standard Twin', allocated: 8, sold: 8, status: 'Đã hết' },
              { partner: 'Trip.com', type: 'Standard Double', allocated: 6, sold: 1, status: 'Đang mở' }
            ]" :key="item.partner" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-slate-900 font-black">{{ item.partner }}</td>
              <td class="p-2.5">{{ item.type }}</td>
              <td class="p-2.5 text-center text-slate-900">{{ item.allocated }} phòng</td>
              <td class="p-2.5 text-center text-sky-600">{{ item.sold }}</td>
              <td class="p-2.5 text-center text-slate-500">{{ item.allocated - item.sold }}</td>
              <td class="p-2.5 text-center">
                <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'Đang mở' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-500 border-red-100'">
                  {{ item.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (CHI TIẾT ALLOTMENT Tab) -->
    <div v-else-if="currentTab === 'allotment-detail'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="pb-4 border-b border-slate-100">
          <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Chi tiết phân bổ phòng (Allotment Details)</h2>
          <p class="text-xs text-slate-400 font-bold mt-1">Danh sách phòng phân bổ cụ thể theo ngày</p>
        </div>

        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Mã Allotment</th>
              <th class="p-2.5">Đối tác</th>
              <th class="p-2.5 text-center">Số phòng</th>
              <th class="p-2.5 text-center">Ngày bắt đầu</th>
              <th class="p-2.5 text-center">Ngày kết thúc</th>
              <th class="p-2.5 text-center">Mở khoá</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { id: 'AL-1009', partner: 'Agoda.com', room: '202', start: '10-06-2026', end: '20-06-2026', active: true },
              { id: 'AL-1012', partner: 'Booking.com', room: '304', start: '12-06-2026', end: '22-06-2026', active: true },
              { id: 'AL-1015', partner: 'Travel Concierge', room: '104', start: '15-06-2026', end: '25-06-2026', active: false }
            ]" :key="item.id" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-slate-900 font-black">{{ item.id }}</td>
              <td class="p-2.5 text-slate-900">{{ item.partner }}</td>
              <td class="p-2.5 text-center font-black">{{ item.room }}</td>
              <td class="p-2.5 text-center text-slate-500">{{ item.start }}</td>
              <td class="p-2.5 text-center text-slate-500">{{ item.end }}</td>
              <td class="p-2.5 text-center">
                <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.active ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-slate-100 text-slate-500 border-slate-200'">
                  {{ item.active ? 'Đang mở' : 'Đã khóa' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (BÁO CÁO PHÂN BỔ PHÒNG ALLOTMENT Tab) -->
    <div v-else-if="currentTab === 'allotment-report'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-6 text-slate-800">
        <div class="pb-4 border-b border-slate-100">
          <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Báo cáo phân bổ phòng Allotment</h2>
          <p class="text-xs text-slate-400 font-bold mt-1">Báo cáo hiệu suất bán hàng qua các kênh đại lý</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="item in [
            { title: 'Tổng số phòng Allotment', value: '45 phòng', color: 'text-blue-600', desc: 'Đã phân bổ trong tháng' },
            { title: 'Phòng đã lấp đầy', value: '29 phòng', color: 'text-emerald-600', desc: 'Chiếm tỷ lệ lấp đầy 64.4%' },
            { title: 'Doanh thu Allotment', value: '38.250.000 đ', color: 'text-indigo-600', desc: 'Thanh toán từ đại lý' }
          ]" :key="item.title" class="bg-slate-50 rounded-xl p-4 border border-slate-100">
            <span class="text-[11px] font-black uppercase text-slate-400 tracking-wide block mb-1">{{ item.title }}</span>
            <span class="text-xl font-black block" :class="item.color">{{ item.value }}</span>
            <span class="text-[10px] text-slate-500 block mt-1.5 font-bold">{{ item.desc }}</span>
          </div>
        </div>

        <div class="flex flex-col gap-3.5 mt-2">
          <span class="text-xs font-black uppercase text-slate-600 tracking-wider">Hiệu suất bán theo kênh đại lý</span>
          <div v-for="k in [
            { name: 'Agoda.com', percent: '80%', sold: '16/20 phòng' },
            { name: 'Booking.com', percent: '60%', sold: '9/15 phòng' },
            { name: 'Travel Concierge', percent: '40%', sold: '4/10 phòng' }
          ]" :key="k.name" class="flex flex-col gap-1.5">
            <div class="flex justify-between text-xs font-bold text-slate-700">
              <span>{{ k.name }}</span>
              <span>{{ k.sold }} ({{ k.percent }})</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-blue-500 rounded-full" :style="{ width: k.percent }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Area (BÁO CÁO ĐĂNG KÝ Tab) -->
    <div v-else-if="currentTab === 'report-reg'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="pb-4 border-b border-slate-100">
          <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Báo cáo đăng ký nhận phòng</h2>
          <p class="text-xs text-slate-400 font-bold mt-1">Thông số thống kê các lượt đăng ký phòng mới</p>
        </div>

        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Mã đăng ký</th>
              <th class="p-2.5">Khách hàng</th>
              <th class="p-2.5 text-center">Số phòng</th>
              <th class="p-2.5 text-center">Nhận phòng</th>
              <th class="p-2.5 text-center">Trả phòng</th>
              <th class="p-2.5 text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { id: 'REG-552', guest: 'Ms. Ivanova Daria', room: '102', in: '12-06-2026', out: '16-06-2026', status: 'Hoạt động' },
              { id: 'REG-553', guest: 'Mr. Rachid Boufarki', room: '204', in: '14-06-2026', out: '18-06-2026', status: 'Hoạt động' },
              { id: 'REG-554', guest: 'Walkin Guest', room: '302', in: '15-06-2026', out: '16-06-2026', status: 'Hoàn thành' }
            ]" :key="item.id" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-slate-900 font-black text-sm">{{ item.id }}</td>
              <td class="p-2.5 text-slate-900">{{ item.guest }}</td>
              <td class="p-2.5 text-center font-black">{{ item.room }}</td>
              <td class="p-2.5 text-center text-slate-500">{{ item.in }}</td>
              <td class="p-2.5 text-center text-slate-500">{{ item.out }}</td>
              <td class="p-2.5 text-center">
                <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'Hoạt động' ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-slate-100 text-slate-500 border-slate-200'">
                  {{ item.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (BÁO CÁO THỐNG KÊ Tab) -->
    <div v-else-if="currentTab === 'report-stats'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="pb-4 border-b border-slate-100">
          <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Báo cáo thống kê hoạt động phòng</h2>
          <p class="text-xs text-slate-400 font-bold mt-1">Thống kê công suất phòng và các chỉ số vận hành</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 flex flex-col justify-center">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Công suất sử dụng trung bình</span>
            <span class="text-3xl font-black text-blue-600 tracking-tight mt-1">72.8%</span>
          </div>
          <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 flex flex-col justify-center">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Thời gian lưu trú trung bình</span>
            <span class="text-3xl font-black text-emerald-600 tracking-tight mt-1">2.4 ngày</span>
          </div>
        </div>

        <table class="w-full text-left border-collapse text-xs mt-2">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Phân loại phòng</th>
              <th class="p-2.5 text-center">Tổng số phòng</th>
              <th class="p-2.5 text-center">Phòng đang ở</th>
              <th class="p-2.5 text-center">Phòng sửa chữa</th>
              <th class="p-2.5 text-center">Hiệu suất</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { name: 'Standard Double', total: 30, occ: 22, repair: 1, rate: '73.3%' },
              { name: 'Deluxe Twin', total: 25, occ: 20, repair: 0, rate: '80.0%' },
              { name: 'Executive Suite', total: 12, occ: 7, repair: 2, rate: '58.3%' }
            ]" :key="item.name" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-slate-900 font-black">{{ item.name }}</td>
              <td class="p-2.5 text-center text-slate-800">{{ item.total }}</td>
              <td class="p-2.5 text-center text-sky-600">{{ item.occ }}</td>
              <td class="p-2.5 text-center text-rose-500">{{ item.repair }}</td>
              <td class="p-2.5 text-center text-slate-900">{{ item.rate }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (BÁO CÁO PHÒNG Tab) -->
    <div v-else-if="currentTab === 'report-rooms'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="pb-4 border-b border-slate-100">
          <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Báo cáo hiện trạng phòng</h2>
          <p class="text-xs text-slate-400 font-bold mt-1">Thông tin chi tiết về số lượng phòng sạch/dơ, sửa chữa</p>
        </div>

        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Trạng thái phòng</th>
              <th class="p-2.5 text-center">Số lượng</th>
              <th class="p-2.5 text-center">Tỷ lệ %</th>
              <th class="p-2.5">Ghi chú vận hành</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { status: 'Phòng Sạch Sẵn Sàng (Clean Vacant)', count: 42, pct: '39.3%', desc: 'Sẵn sàng tiếp đón khách mới' },
              { status: 'Phòng Có Khách Đang Ở (Occupied)', count: 45, pct: '42.1%', desc: 'Khách lưu trú bình thường' },
              { status: 'Phòng Dơ Chưa Dọn (Dirty Vacant)', count: 15, pct: '14.0%', desc: 'Cần dọn dẹp khẩn cấp' },
              { status: 'Phòng Đang Bảo Trì (Maintenance)', count: 5, pct: '4.6%', desc: 'Khóa sửa chữa thiết bị kỹ thuật' }
            ]" :key="item.status" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-slate-900 font-black">{{ item.status }}</td>
              <td class="p-2.5 text-center font-black text-slate-800">{{ item.count }} phòng</td>
              <td class="p-2.5 text-center text-sky-600">{{ item.pct }}</td>
              <td class="p-2.5 text-slate-500 font-medium">{{ item.desc }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (BÁO CÁO HỦY PHÒNG Tab) -->
    <div v-else-if="currentTab === 'report-cancel'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="pb-4 border-b border-slate-100">
          <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Báo cáo hủy phòng đặt</h2>
          <p class="text-xs text-slate-400 font-bold mt-1">Danh sách các booking bị hủy hoặc xóa giao dịch</p>
        </div>

        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Mã hủy</th>
              <th class="p-2.5">Tên khách hàng</th>
              <th class="p-2.5 text-center">Ngày hủy</th>
              <th class="p-2.5 text-right">Giá trị booking</th>
              <th class="p-2.5">Lý do hủy phòng</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { id: 'CN-201', guest: 'Mr. David Lee', date: '08-06-2026', val: '2.500.000', reason: 'Thay đổi kế hoạch du lịch cá nhân' },
              { id: 'CN-202', guest: 'Ms. Nguyen Kim Chi', date: '10-06-2026', val: '3.600.000', reason: 'Đặt nhầm ngày, đặt lại booking khác' },
              { id: 'CN-203', guest: 'Mr. Park Jung Woo', date: '12-06-2026', val: '1.200.000', reason: 'Lý do gia đình đột xuất' }
            ]" :key="item.id" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-rose-600 font-black text-sm">{{ item.id }}</td>
              <td class="p-2.5 text-slate-900">{{ item.guest }}</td>
              <td class="p-2.5 text-center text-slate-500">{{ item.date }}</td>
              <td class="p-2.5 text-right text-slate-800 font-black">{{ item.val }} đ</td>
              <td class="p-2.5 text-slate-500 font-medium truncate max-w-[300px]">{{ item.reason }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (CHANNEL MANAGER Tab) -->
    <div v-else-if="currentTab === 'channel-manager'" class="flex-1 p-6 bg-slate-100 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100">
          <div>
            <h2 class="text-base font-black tracking-wide uppercase text-slate-800">Báo cáo đăng ký Channel Manager</h2>
            <p class="text-xs text-slate-400 font-bold mt-1">Đồng bộ giá phòng và số lượng phòng trống lên các OTA</p>
          </div>
          <button @click="showDevelopmentToast('Đồng bộ OTA')" class="px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-black shadow-sm transition-all border-none cursor-pointer">
            Đồng bộ ngay
          </button>
        </div>

        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
              <th class="p-2.5">Kênh OTA liên kết</th>
              <th class="p-2.5 text-center">Số lượng phòng khả dụng</th>
              <th class="p-2.5 text-right">Giá phòng đang đồng bộ</th>
              <th class="p-2.5 text-center">Đồng bộ cuối</th>
              <th class="p-2.5 text-center">Trạng thái kết nối</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
            <tr v-for="item in [
              { channel: 'Agoda API Connection', rooms: 15, rate: '650.000 đ', time: '10 phút trước', status: 'Hoạt động' },
              { channel: 'Booking.com XML Sync', rooms: 12, rate: '680.000 đ', time: '5 phút trước', status: 'Hoạt động' },
              { channel: 'Expedia.com OTA link', rooms: 8, rate: '720.000 đ', time: '1 giờ trước', status: 'Đang kết nối lại' }
            ]" :key="item.channel" class="hover:bg-slate-50 h-10">
              <td class="p-2.5 text-slate-900 font-black">{{ item.channel }}</td>
              <td class="p-2.5 text-center font-black text-slate-800">{{ item.rooms }} phòng</td>
              <td class="p-2.5 text-right text-emerald-600 font-black">{{ item.rate }}</td>
              <td class="p-2.5 text-center text-slate-400">{{ item.time }}</td>
              <td class="p-2.5 text-center">
                <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'Hoạt động' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'">
                  {{ item.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Main Content Area (Room Map Sơ đồ / Lưới danh sách) -->
    <div v-else class="flex-1 overflow-x-auto overflow-y-auto bg-slate-100 py-4 pr-4 pl-0 flex flex-col gap-2.5">
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
            <div class="w-9 h-9 rounded-md bg-[#c9eeff] text-[#1d4ed8] font-black text-sm flex items-center justify-center shadow-sm">
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
                  ? 'bg-[#c9eeff] hover:bg-[#8ecefa] border-[#7ec0f3] text-slate-800' 
                  : 'bg-white hover:bg-slate-50 border-slate-200/80 text-slate-700 shadow-sm'
              ]"
              @click="handleRoomClick(room)"
              @contextmenu.prevent="handleContextMenu($event, room)"
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
                    {{ room.room_type || room.room_class?.code }} - SL khách: {{ room.max_guests }}
                  </span>
                  <span v-else>
                    {{ room.room_type || room.room_class?.code }}
                  </span>
                </div>

                <!-- Room Status Icon Badge (Bottom Right) -->
                <div class="shrink-0 ml-1 flex items-center justify-center">
                  <!-- Sẵn sàng (available) -->
                  <svg v-if="room.status === ROOM_STATUSES.AVAILABLE" class="w-5.5 h-5.5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12l5.25 5 2.625-3M8 12l5.25 5L22 7" />
                  </svg>
                  
                  <!-- Phòng bẩn (dirty) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.DIRTY" class="w-5.5 h-5.5 text-[#0369a1] fill-current" viewBox="0 0 24 24">
                    <path d="M19 19L5 5M12 12l2.5-2.5m1.5-1.5l1.5-1.5M7.5 7.5L5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5.5 19.5c.6.6 1.4 1 2.3 1H10l9-9c1-1 1-2.6 0-3.5l-1.5-1.5c-1-1-2.6-1-3.5 0l-9 9v2.2c0 .9.4 1.7 1 2.3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  
                  <!-- Lau dọn (checkout) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.CHECKOUT" class="w-5 h-5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
                  </svg>
                  
                  <!-- Dịch vụ dọn phòng (maintenance) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.MAINTENANCE" class="w-5.5 h-5.5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="5" r="2" />
                    <path d="M7 21v-7a3 3 0 0 1 6 0v7" />
                    <path d="M5 11h10" />
                    <path d="M17 6v15M15 21h4" />
                    <rect x="3" y="16" width="3" height="4" rx="0.5" />
                  </svg>
                  
                  <!-- Phòng ưu tiên (reserved) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.RESERVED" class="w-5 h-5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="17" x2="12" y2="22" />
                    <path d="M5 17h14v-1.76a2 2 0 0 0-.44-1.24l-2.78-3.56A2 2 0 0 1 15 9.2V5a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4.2a2 2 0 0 1-.78 1.24l-2.78 3.56a2 2 0 0 0-.44 1.24V17z" />
                  </svg>
                  
                  <!-- Phòng không làm phiền (occupied) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.OCCUPIED" class="w-5.5 h-5.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="8" y1="12" x2="16" y2="12" />
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
              :class="room.status === ROOM_STATUSES.OCCUPIED ? 'bg-[#c9eeff]/80 hover:bg-[#8ecefa]/80' : 'bg-white'"
              @click="handleRoomClick(room)"
              @contextmenu.prevent="handleContextMenu($event, room)"
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
              <!-- TT Phòng (Status Icon) -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                <div class="flex items-center justify-center">
                  <!-- Sẵn sàng (available) -->
                  <svg v-if="room.status === ROOM_STATUSES.AVAILABLE" class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12l5.25 5 2.625-3M8 12l5.25 5L22 7" />
                  </svg>
                  
                  <!-- Phòng bẩn (dirty) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.DIRTY" class="w-4.5 h-4.5 text-[#0369a1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 19L5 5M12 12l2.5-2.5m1.5-1.5l1.5-1.5M7.5 7.5L5 5" />
                    <path d="M5.5 19.5c.6.6 1.4 1 2.3 1H10l9-9c1-1 1-2.6 0-3.5l-1.5-1.5c-1-1-2.6-1-3.5 0l-9 9v2.2c0 .9.4 1.7 1 2.3Z" />
                  </svg>
                  
                  <!-- Lau dọn (checkout) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.CHECKOUT" class="w-4.5 h-4.5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
                  </svg>
                  
                  <!-- Dịch vụ dọn phòng (maintenance) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.MAINTENANCE" class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="5" r="2" />
                    <path d="M7 21v-7a3 3 0 0 1 6 0v7" />
                    <path d="M5 11h10" />
                    <path d="M17 6v15M15 21h4" />
                    <rect x="3" y="16" width="3" height="4" rx="0.5" />
                  </svg>
                  
                  <!-- Phòng ưu tiên (reserved) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.RESERVED" class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="17" x2="12" y2="22" />
                    <path d="M5 17h14v-1.76a2 2 0 0 0-.44-1.24l-2.78-3.56A2 2 0 0 1 15 9.2V5a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4.2a2 2 0 0 1-.78 1.24l-2.78 3.56a2 2 0 0 0-.44 1.24V17z" />
                  </svg>
                  
                  <!-- Phòng không làm phiền (occupied) -->
                  <svg v-else-if="room.status === ROOM_STATUSES.OCCUPIED" class="w-4.5 h-4.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="8" y1="12" x2="16" y2="12" />
                  </svg>
                </div>
              </td>
              <!-- Thêm giường -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold">-</td>
              <!-- Yêu cầu DB -->
              <td class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold">-</td>
              <!-- Loại phòng -->
              <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                {{ room.room_type || room.room_class?.code }}
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

    <!-- Teleported Context Menu -->
    <Teleport to="body">
      <div
        v-if="contextMenu.show && contextMenu.room"
        class="fixed z-[9999] bg-[#eaeaea] border border-slate-300 rounded-xl shadow-2xl py-1.5 w-[220px] select-none text-slate-800 font-medium animate-[fadeIn_0.15s_ease-out]"
        :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
        @click.stop
      >
        <!-- Thông tin -->
        <button
          @click="showRoomInfo(contextMenu.room)"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Info icon -->
          <svg class="w-4.5 h-4.5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          <span>Thông tin</span>
        </button>

        <!-- Đăng ký -->
        <button
          @click="triggerMenuItem('Đăng ký')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Register icon -->
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <line x1="19" y1="8" x2="19" y2="14" />
            <line x1="16" y1="11" x2="22" y2="11" />
          </svg>
          <span>Đăng ký</span>
        </button>

        <!-- Hóa đơn -->
        <button
          @click="triggerMenuItem('Hóa đơn')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Invoice icon -->
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          <span>Hóa đơn</span>
        </button>

        <!-- Nhóm hóa đơn -->
        <button
          @click="triggerMenuItem('Nhóm hóa đơn')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Coins/group icon -->
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5" />
            <path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6" />
          </svg>
          <span>Nhóm hóa đơn</span>
        </button>

        <div class="h-px bg-slate-300 my-1"></div>

        <!-- Chuyển Phòng -->
        <button
          @click="triggerMenuItem('Chuyển Phòng')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Circular arrows transfer icon -->
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 2.1a9 9 0 0 0-9 0L5 4M3 9V4h5M7 21.9a9 9 0 0 0 9 0l3-2.1M21 15v5h-5" />
          </svg>
          <span>Chuyển Phòng</span>
        </button>

        <!-- Thông báo -->
        <button
          @click="triggerMenuItem('Thông báo')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Bell icon -->
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <span>Thông báo</span>
        </button>

        <!-- In phiếu ăn sáng -->
        <button
          @click="triggerMenuItem('In phiếu ăn sáng')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Breakfast coffee icon -->
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 8h1a4 4 0 1 1 0 8h-1" />
            <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" />
            <line x1="6" y1="2" x2="6" y2="4" />
            <line x1="10" y1="2" x2="10" y2="4" />
            <line x1="14" y1="2" x2="14" y2="4" />
          </svg>
          <span>In phiếu ăn sáng</span>
        </button>

        <!-- In mẫu đăng ký -->
        <button
          @click="triggerMenuItem('In mẫu đăng ký')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <!-- Printer icon -->
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect x="6" y="14" width="12" height="8" />
          </svg>
          <span>In mẫu đăng ký</span>
        </button>

        <!-- Chuyển tình trạng phòng (Submenu Trigger) -->
        <div class="relative group mt-0.5">
          <div
            class="flex items-center justify-between px-3 py-2 bg-[#8ecefa] text-white text-xs font-bold hover:bg-[#6ab3e7] transition-colors cursor-pointer select-none"
          >
            <div class="flex items-center gap-2.5">
              <!-- Sync / state icon -->
              <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
              </svg>
              <span>Chuyển tình trạng phòng</span>
            </div>
            <!-- Chevron Right -->
            <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </div>

          <!-- Submenu Panel -->
          <div
            class="absolute top-0 hidden group-hover:block bg-[#eaeaea] border border-slate-300 rounded-xl shadow-2xl py-1.5 w-60 z-[99999]"
            :class="contextMenu.isLeft ? 'right-full mr-1' : 'left-full ml-1'"
          >
            <!-- Sẵn sàng -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.AVAILABLE)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12l5.25 5 2.625-3M8 12l5.25 5L22 7" />
              </svg>
              <span>Sẵn sàng</span>
            </button>

            <!-- Phòng bẩn -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.DIRTY)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-[#0369a1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 19L5 5M12 12l2.5-2.5m1.5-1.5l1.5-1.5M7.5 7.5L5 5" />
                <path d="M5.5 19.5c.6.6 1.4 1 2.3 1H10l9-9c1-1 1-2.6 0-3.5l-1.5-1.5c-1-1-2.6-1-3.5 0l-9 9v2.2c0 .9.4 1.7 1 2.3Z" />
              </svg>
              <span>Phòng bẩn</span>
            </button>

            <!-- Lau dọn -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.CHECKOUT)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
              </svg>
              <span>Lau dọn</span>
            </button>

            <!-- Dịch vụ dọn phòng -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.MAINTENANCE)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="10" cy="5" r="2" />
                <path d="M7 21v-7a3 3 0 0 1 6 0v7" />
                <path d="M5 11h10" />
                <path d="M17 6v15M15 21h4" />
                <rect x="3" y="16" width="3" height="4" rx="0.5" />
              </svg>
              <span>Dịch vụ dọn phòng</span>
            </button>

            <!-- Phòng ưu tiên -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.RESERVED)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="17" x2="12" y2="22" />
                <path d="M5 17h14v-1.76a2 2 0 0 0-.44-1.24l-2.78-3.56A2 2 0 0 1 15 9.2V5a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4.2a2 2 0 0 1-.78 1.24l-2.78 3.56a2 2 0 0 0-.44 1.24V17z" />
              </svg>
              <span>Phòng ưu tiên</span>
            </button>

            <!-- Phòng không làm phiền -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.OCCUPIED)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="8" y1="12" x2="16" y2="12" />
              </svg>
              <span>Phòng không làm phiền</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.97);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
