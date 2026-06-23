<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useRoomStore } from '@/stores/room-store'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'
import { t } from '@/utils/i18n'
import RoomDetailModal from '@/components/RoomDetailModal.vue'
import RoomIcon from '@/components/RoomIcon.vue'
import AvailableRoomsPage from './AvailableRoomsPage.vue'
import RoomPlanPage from './RoomPlanPage.vue'
import ShiftWorkPage from './ShiftWorkPage.vue'
import LockRoomPage from './LockRoomPage.vue'
import CompanySettingsPage from '@/pages/config/company/CompanySettingsPage.vue'
import LostAndFound from '@/pages/housekeeping/components/LostAndFound.vue'
import CreateRegistrationPage from './CreateRegistrationPage.vue'
import HelpGuidePopover from '@/components/HelpGuidePopover.vue'

const roomStore = useRoomStore()
const uiStore = useUiStore()
const route = useRoute()

const currentTab = computed(() => route.query.tab || 'room-map')

const showDetailModal = ref(false)
const isLoaded = ref(false)
const showSearch = ref(false)
const showFilters = ref(true)

// Top toggle state: isFuture (false = Hiện tại, true = Tương Lai)
const isFuture = ref(false)
const rawDate = ref(new Date().toISOString().split('T')[0])

// Bottom toggle state: isGridMode (true = Bảng, false = Lưới)
const isGridMode = ref(true)

// Auto scale / zoom layout state
const autoScale = ref(localStorage.getItem('pms_room_map_auto_scale') !== 'false')
const manualScale = ref(parseFloat(localStorage.getItem('pms_room_map_scale') || '1.0'))
const scaleFactor = ref(1.0)

function calculateScale() {
  if (autoScale.value) {
    const width = window.innerWidth
    if (width < 1440) {
      scaleFactor.value = Math.max(0.78, width / 1440)
    } else {
      scaleFactor.value = 1.0
    }
  } else {
    scaleFactor.value = manualScale.value
  }
}

function toggleAutoScale() {
  autoScale.value = !autoScale.value
  localStorage.setItem('pms_room_map_auto_scale', String(autoScale.value))
  calculateScale()
}

function adjustManualScale(direction) {
  if (direction === 'in') {
    manualScale.value = Math.min(1.2, manualScale.value + 0.05)
  } else if (direction === 'out') {
    manualScale.value = Math.max(0.7, manualScale.value - 0.05)
  } else {
    manualScale.value = 1.0
  }
  localStorage.setItem('pms_room_map_scale', String(manualScale.value))
  calculateScale()
}

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
  return `${count}/${roomStore.rooms.length || 181}`
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

// Checkbox helper for filters
function toggleStatusFilter(status) {
  if (roomStore.filters.status === status) {
    roomStore.setFilter('status', null)
  } else {
    roomStore.setFilter('status', status)
  }
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
  const isEn = t('roomMap.filterTitle') === 'Filters'
  const msg = isEn ? `Feature "${featureName}" is under development!` : `Tính năng "${featureName}" đang được phát triển!`
  uiStore.showToast(msg, 'warning')
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
}

// Guest names list matching image 2
function getMockGuestName(room) {
  if (room.status === ROOM_STATUSES.OCCUPIED) {
    const names = [
      'Nguyễn Văn A',
      'Trần Thị B',
      'Lê Hoàng Nam',
      'Phạm Minh Tuấn',
      'Walkin Guest',
      'Đặng Hồng Nhung',
      'Nguyễn Thị Kim Chi',
      'Mr. Rydchuk Alexandr',
      'Ms. Kathy Diệu Trinh',
      'Nguyễn Thị Hồng Phương',
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
  const isEn = t('roomMap.filterTitle') === 'Filters'
  const msg = isEn ? `Feature "${actionName}" is under development!` : `Tính năng "${actionName}" đang được phát triển!`
  uiStore.showToast(msg, 'warning')
  closeContextMenu()
}

// Change room status directly from context menu
async function changeRoomStatus(room, newStatus) {
  if (!room || newStatus === room.status) return
  
  let statusKey = 'available'
  if (newStatus === ROOM_STATUSES.DIRTY) statusKey = 'dirty'
  else if (newStatus === ROOM_STATUSES.CHECKOUT) statusKey = 'cleaning'
  else if (newStatus === ROOM_STATUSES.MAINTENANCE) statusKey = 'maintenance'
  else if (newStatus === ROOM_STATUSES.RESERVED) statusKey = 'priorityRoom'
  else if (newStatus === ROOM_STATUSES.OCCUPIED) statusKey = 'dnd'
  
  const statusLabel = t(`roomMap.${statusKey}`)
  
  // Close context menu BEFORE showing confirm dialog
  closeContextMenu()
  
  const confirmed = await uiStore.confirm({
    title: t('roomMap.changeStatusTitle'),
    message: t('roomMap.changeStatusConfirm', { room: room.room_number, status: statusLabel }),
    confirmText: t('roomMap.filterTitle') === 'Filters' ? 'Agree' : 'Đồng ý',
    cancelText: t('roomMap.filterTitle') === 'Filters' ? 'Cancel' : 'Hủy bỏ'
  })

  if (confirmed) {
    try {
      await roomStore.updateRoomStatus(room.id, newStatus)
      uiStore.showToast(t('roomMap.changeStatusSuccess', { room: room.room_number, status: statusLabel }), 'success')
    } catch (err) {
      uiStore.showToast(t('roomMap.changeStatusError'), 'error')
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
  
  calculateScale()
  window.addEventListener('resize', calculateScale)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeContextMenu)
  window.removeEventListener('resize', calculateScale)
})

watch(() => contextMenu.value.show, (newVal) => {
  if (newVal) {
    window.addEventListener('scroll', closeContextMenu, { passive: true })
  } else {
    window.removeEventListener('scroll', closeContextMenu)
  }
})

const uniqueRoomTypes = computed(() => {
  const types = new Set(roomStore.rooms.map(r => r.room_type || r.room_class?.code).filter(Boolean))
  return [...types].sort()
})

const uniqueFloors = computed(() => {
  const floors = new Set(roomStore.rooms.map(r => r.floor).filter(Boolean))
  return [...floors].sort((a, b) => a - b)
})
</script>

<template>
  <div class="flex h-full w-full overflow-hidden bg-white">
    <!-- Main Content Area Wrapper -->
    <div class="flex-1 flex flex-col min-h-0 min-w-0 bg-white" :style="{ zoom: scaleFactor }">
      
      <!-- TOP HORIZONTAL METRICS BAR (Only displayed for Room Map tab) -->
      <div v-if="currentTab === 'room-map'" class="bg-white border-b border-slate-200 px-6 py-3 shrink-0 flex items-center justify-between gap-4 select-none">
        <div class="flex items-center gap-3 overflow-x-auto px-1.5 py-1 scrollbar-thin">
          <!-- Date card -->
          <div class="bg-white border border-slate-200/80 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 transform-gpu">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-500 shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="6" width="18" height="15" rx="2" fill="#F8FAFC" stroke="#6366F1" stroke-width="2"/>
                <path d="M3 6C3 4.89543 3.89543 4 5 4H19C20.1046 4 21 4.89543 21 6V9H3V6Z" fill="#EE4444"/>
                <path d="M8 2V5" stroke="#475569" stroke-width="2" stroke-linecap="round"/>
                <path d="M16 2V5" stroke="#475569" stroke-width="2" stroke-linecap="round"/>
                <circle cx="7" cy="13" r="1" fill="#6366F1"/>
                <circle cx="12" cy="13" r="1" fill="#6366F1"/>
                <circle cx="17" cy="13" r="1" fill="#6366F1"/>
                <circle cx="7" cy="17" r="1" fill="#6366F1"/>
                <circle cx="12" cy="17" r="1" fill="#6366F1"/>
                <circle cx="17" cy="17" r="1" fill="#6366F1"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[12px] font-semibold text-gray-900 leading-tight">{{ selectedDate }}</span>
              <span class="text-[10px] text-gray-900 font-semibold uppercase mt-0.5">{{ t('roomMap.current') }}</span>
            </div>
          </div>

          <!-- Đã đến card -->
          <button @click="filterByStatus(ROOM_STATUSES.RESERVED)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.RESERVED ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-50 to-emerald-100/60 border border-emerald-200/50 flex items-center justify-center text-emerald-600 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H19C20.1046 3 21 3.89543 21 5V19C21 20.1046 20.1046 21 19 21Z" fill="#F0FDF4" stroke="#22C55E" stroke-width="2"/>
                <path d="M10 17H6V7H10" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 12H9" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 9L9 12L12 15" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] text-gray-900 font-semibold uppercase leading-tight">{{ t('roomMap.arrivals') }}</span>
              <span class="text-[13px] font-semibold text-gray-900 mt-0.5">{{ checkinStats }}</span>
            </div>
          </button>

          <!-- Đã đi card -->
          <button @click="filterByStatus(ROOM_STATUSES.CHECKOUT)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.CHECKOUT ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-rose-50 to-rose-100/60 border border-rose-200/50 flex items-center justify-center text-rose-500 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H19C20.1046 3 21 3.89543 21 5V19C21 20.1046 20.1046 21 19 21Z" fill="#FEF2F2" stroke="#EF4444" stroke-width="2"/>
                <path d="M6 17H10V7H6" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M11 12H20" stroke="#DC2626" stroke-width="2" stroke-linecap="round"/>
                <path d="M17 9L20 12L17 15" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] text-gray-900 font-semibold uppercase leading-tight">{{ t('roomMap.departures') }}</span>
              <span class="text-[13px] font-semibold text-gray-900 mt-0.5">{{ checkoutStats }}</span>
            </div>
          </button>

          <!-- Đang ở card -->
          <button @click="filterByStatus(ROOM_STATUSES.OCCUPIED)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.OCCUPIED ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-50 to-sky-100/80 border border-sky-200/50 flex items-center justify-center text-sky-600 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 20V5H4V14H20V5H22V20H20V17H4V20H2C2 20 2 20 2 20Z" fill="#3B82F6"/>
                <rect x="5" y="10" width="14" height="4" rx="1" fill="#93C5FD"/>
                <rect x="6" y="7" width="4" height="2.5" rx="0.5" fill="#1D4ED8"/>
                <rect x="14" y="7" width="4" height="2.5" rx="0.5" fill="#1D4ED8"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] text-gray-900 font-semibold uppercase leading-tight">{{ t('roomMap.occupied') }}</span>
              <span class="text-[13px] font-semibold text-gray-900 mt-0.5">{{ occupiedStats }}</span>
            </div>
          </button>

          <!-- Khóa OOO card -->
          <button @click="filterByStatus(ROOM_STATUSES.MAINTENANCE)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.MAINTENANCE ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200/60 border border-slate-200/60 flex items-center justify-center text-slate-600 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="10" width="16" height="11" rx="2" fill="#FEF3C7" stroke="#D97706" stroke-width="2"/>
                <path d="M8 10V6C8 3.79086 9.79086 2 12 2C14.2091 2 16 3.79086 16 6V10" stroke="#D97706" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="15" r="1.5" fill="#D97706"/>
                <path d="M12 16.5V18.5" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] text-gray-900 font-semibold uppercase leading-tight">{{ t('roomMap.lockOoo') }}</span>
              <span class="text-[13px] font-semibold text-gray-900 mt-0.5">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE).length }}</span>
            </div>
          </button>

          <!-- Khóa OOS card -->
          <div class="bg-white border border-slate-200/80 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 transform-gpu">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-slate-50 to-slate-100/80 border border-slate-200/40 flex items-center justify-center text-slate-400 shadow-xs shrink-0">
              <RoomIcon name="oos" class="w-5 h-5 text-[#4B5563]" />
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] text-gray-900 font-semibold uppercase leading-tight">{{ t('roomMap.lockOos') }}</span>
              <span class="text-[13px] font-semibold text-gray-900 mt-0.5">0</span>
            </div>
          </div>

          <!-- Công suất card -->
          <button @click="resetAllFilters" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="!activeFilter ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-slate-800 font-extrabold text-[10px] shrink-0 relative">
              <svg class="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                <path
                  class="text-slate-100"
                  stroke="currentColor"
                  stroke-width="4.5"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <path
                  class="text-amber-500 transition-all duration-1000 ease-out progress-ring-path"
                  :stroke-dasharray="`${roomStore.occupancyRate || 0}, 100`"
                  stroke-width="4.5"
                  stroke-linecap="round"
                  fill="none"
                  stroke="currentColor"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
              </svg>
              <span class="relative z-10 text-[9px] font-semibold text-gray-900 leading-none">
                {{ occupancyRateStats }}
              </span>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] text-gray-900 font-semibold uppercase leading-tight">{{ t('roomMap.occupancy') }}</span>
              <span class="text-[13px] font-semibold text-gray-900 mt-0.5">{{ occupancyRateStats }}</span>
            </div>
          </button>
        </div>

        <!-- View Mode & Zoom switchers -->
        <div class="flex items-center gap-2.5 shrink-0">
          <!-- Help / Guide Trigger -->
          <HelpGuidePopover />

          <!-- Zoom Layout Controls -->
          <div class="flex items-center gap-1 bg-slate-50 border border-slate-200/80 rounded-lg p-0.5 select-none shrink-0 font-semibold text-[11.5px] text-slate-700">
            <!-- Auto Scale Toggle Button -->
            <button 
              @click="toggleAutoScale" 
              class="px-2.5 py-1 rounded-md cursor-pointer transition-all duration-200 text-[11px] border-none"
              :class="autoScale ? 'bg-sky-500 text-white font-bold shadow-xs' : 'bg-transparent text-slate-500 hover:bg-slate-100'"
              title="Tự động thu phóng để vừa khít màn hình"
            >
              Auto Fit
            </button>
            
            <!-- Manual Zoom Controls (Disabled if Auto Scale is enabled) -->
            <div class="flex items-center gap-0.5 pl-1 pr-0.5" :class="autoScale ? 'opacity-40 pointer-events-none' : ''">
              <button 
                @click="adjustManualScale('out')" 
                class="w-5.5 h-5.5 rounded-md hover:bg-slate-200 flex items-center justify-center cursor-pointer font-extrabold border-none text-slate-600 active:scale-90 transition-transform"
                title="Thu nhỏ"
              >
                -
              </button>
              <span class="w-9 text-center font-bold text-slate-800 text-[10.5px] tabular-nums">
                {{ Math.round(scaleFactor * 100) }}%
              </span>
              <button 
                @click="adjustManualScale('in')" 
                class="w-5.5 h-5.5 rounded-md hover:bg-slate-200 flex items-center justify-center cursor-pointer font-extrabold border-none text-slate-600 active:scale-90 transition-transform"
                title="Phóng to"
              >
                +
              </button>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <button @click="isGridMode = false" 
              class="p-2 border rounded-lg cursor-pointer transition-colors"
              :class="!isGridMode ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              :title="t('roomMap.listView')">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <button @click="isGridMode = true" 
              class="p-2 border rounded-lg cursor-pointer transition-colors"
              :class="isGridMode ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              :title="t('roomMap.gridView')">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
              </svg>
            </button>
            <button @click="showFilters = !showFilters" 
              class="p-2 border rounded-lg cursor-pointer transition-colors"
              :class="showFilters ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              :title="t('roomMap.toggleFilter')">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.586a1 1 0 0 1-.293.707l-6.414 6.414a1 1 0 0 0-.293.707V17l-4 4v-6.586a1 1 0 0 0-.293-.707L3.293 7.293A1 1 0 0 1 3 6.586V4z" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Main Layout Body (Grid or list or subpages) -->
      <div class="flex-1 flex min-h-0 min-w-0 overflow-hidden">
        
        <!-- Left/Center content container -->
        <div class="flex-1 min-w-0 overflow-auto p-5 bg-white flex flex-col gap-4">
          
          <!-- Tab 1: Phòng Trống AvailableRoomsPage -->
          <div v-if="currentTab === 'available'" class="h-full overflow-hidden">
            <AvailableRoomsPage />
          </div>

          <!-- Tab 2: Kế hoạch phòng RoomPlanPage -->
          <div v-else-if="currentTab === 'room-plan'" class="h-full overflow-hidden">
            <RoomPlanPage />
          </div>

          <!-- Tab 3: D.S Công Việc ShiftWorkPage -->
          <div v-else-if="currentTab === 'shift-work'" class="h-full overflow-hidden">
            <ShiftWorkPage />
          </div>

          <!-- Tab 4: Công Ty CompanySettingsPage -->
          <div v-else-if="currentTab === 'company'" class="h-full overflow-hidden">
            <CompanySettingsPage />
          </div>

          <!-- Tab 5: Khóa Phòng LockRoomPage -->
          <div v-else-if="currentTab === 'lock-room'" class="h-full overflow-hidden">
            <LockRoomPage />
          </div>

          <!-- Tab 6: Quản Lý Đồ Thất Lạc LostAndFound -->
          <div v-else-if="currentTab === 'lost-found'" class="h-full overflow-hidden">
            <LostAndFound />
          </div>

          <!-- Tab 7: Tạo Đăng Ký CreateRegistrationPage -->
          <div v-else-if="currentTab === 'create-res'" class="h-full overflow-hidden">
            <CreateRegistrationPage />
          </div>

          <!-- Tab 8: ALLOTMENT Tab -->
          <div v-else-if="currentTab === 'allotment'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
              <div>
                <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.allotmentConfigTitle') }}</h2>
                <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.allotmentConfigDesc') }}</p>
              </div>
              <button @click="showDevelopmentToast(t('roomMap.createNewAllotment'))" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-black shadow-sm transition-all border-none cursor-pointer">
                {{ t('roomMap.createNewAllotment') }}
              </button>
            </div>
            
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.partnerOta') }}</th>
                  <th class="p-2.5">{{ t('roomMap.allotmentRoomType') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentQty') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentSold') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentRemaining') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentStatus') }}</th>
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
                  <td class="p-2.5 text-center text-slate-900">{{ item.allocated }} {{ t('roomMap.allotmentRoomsUnit') }}</td>
                  <td class="p-2.5 text-center text-sky-600">{{ item.sold }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.allocated - item.sold }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'Đang mở' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-500 border-red-100'">
                      {{ item.status === 'Đang mở' ? t('roomMap.allotmentOpen') : t('roomMap.allotmentSoldOut') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 9: CHI TIẾT ALLOTMENT Tab -->
          <div v-else-if="currentTab === 'allotment-detail'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.allotmentDetailTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.allotmentDetailDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.allotmentIdCode') }}</th>
                  <th class="p-2.5">{{ t('roomMap.allotmentPartner') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentRoomNo') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentStartDate') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentEndDate') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentUnlock') }}</th>
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
                      {{ item.active ? t('roomMap.allotmentOpen') : t('roomMap.allotmentLocked') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 10: BÁO CÁO PHÂN BỔ PHÒNG ALLOTMENT -->
          <div v-else-if="currentTab === 'allotment-report'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-6 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.allotmentReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.allotmentReportDesc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div v-for="item in [
                { title: t('roomMap.allotmentTotalRooms'), value: '45 ' + t('roomMap.allotmentRoomsUnit'), color: 'text-blue-600', desc: t('roomMap.allotmentAllocatedMonth') },
                { title: t('roomMap.allotmentOccupiedRooms'), value: '29 ' + t('roomMap.allotmentRoomsUnit'), color: 'text-emerald-600', desc: t('roomMap.allotmentOccupiedDesc') },
                { title: t('roomMap.allotmentRevenueVal'), value: '38.250.000 đ', color: 'text-indigo-600', desc: t('roomMap.allotmentRevenueDesc') }
              ]" :key="item.title" class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                <span class="text-[11px] font-black uppercase text-slate-400 tracking-wide block mb-1">{{ item.title }}</span>
                <span class="text-xl font-black block" :class="item.color">{{ item.value }}</span>
                <span class="text-[10px] text-slate-500 block mt-1.5 font-bold">{{ item.desc }}</span>
              </div>
            </div>

            <div class="flex flex-col gap-3.5 mt-2">
              <span class="text-xs font-black uppercase text-slate-600 tracking-wider">{{ t('roomMap.allotmentChannelPerf') }}</span>
              <div v-for="k in [
                { name: 'Agoda.com', percent: '80%', sold: `16/20 ${t('roomMap.allotmentRoomsUnit')}` },
                { name: 'Booking.com', percent: '60%', sold: `9/15 ${t('roomMap.allotmentRoomsUnit')}` },
                { name: 'Travel Concierge', percent: '40%', sold: `4/10 ${t('roomMap.allotmentRoomsUnit')}` }
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

          <!-- Tab 11: BÁO CÁO ĐĂNG KÝ Tab -->
          <div v-else-if="currentTab === 'report-reg'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.regReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.regReportDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.regIdCode') }}</th>
                  <th class="p-2.5">{{ t('roomMap.regCustomer') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regRoomNo') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regCheckIn') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regCheckOut') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regStatus') }}</th>
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
                      {{ item.status === 'Hoạt động' ? t('roomMap.regActive') : t('roomMap.regCompleted') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 12: BÁO CÁO THỐNG KÊ Tab -->
          <div v-else-if="currentTab === 'report-stats'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.statReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.statReportDesc') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 flex flex-col justify-center">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wide">{{ t('roomMap.statAverageOccupancy') }}</span>
                <span class="text-3xl font-black text-blue-600 tracking-tight mt-1">72.8%</span>
              </div>
              <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 flex flex-col justify-center">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wide">{{ t('roomMap.statAverageStay') }}</span>
                <span class="text-3xl font-black text-emerald-600 tracking-tight mt-1">2.4 {{ t('roomMap.statDaysUnit') }}</span>
              </div>
            </div>

            <table class="w-full text-left border-collapse text-xs mt-2">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.statRoomCategory') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statTotalRooms') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statOccupiedRooms') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statRepairRooms') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statEfficiency') }}</th>
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

          <!-- Tab 13: BÁO CÁO PHÒNG Tab -->
          <div v-else-if="currentTab === 'report-rooms'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.roomsReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.roomsReportDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.roomsReportStatus') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.roomsReportQty') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.roomsReportPct') }}</th>
                  <th class="p-2.5">{{ t('roomMap.roomsReportNotes') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { key: 'roomsCleanVacant', count: 42, pct: '39.3%' },
                  { key: 'roomsOccupied', count: 45, pct: '42.1%' },
                  { key: 'roomsDirtyVacant', count: 15, pct: '14.0%' },
                  { key: 'roomsMaintenance', count: 5, pct: '4.6%' }
                ]" :key="item.key" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ t('roomMap.' + item.key) }}</td>
                  <td class="p-2.5 text-center font-black text-slate-800">{{ item.count }} {{ t('roomMap.allotmentRoomsUnit') }}</td>
                  <td class="p-2.5 text-center text-sky-600">{{ item.pct }}</td>
                  <td class="p-2.5 text-slate-500 font-medium">{{ t('roomMap.' + item.key + 'Desc') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 14: BÁO CÁO HỦY PHÒNG Tab -->
          <div v-else-if="currentTab === 'report-cancel'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.cancelReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.cancelReportDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.cancelIdCode') }}</th>
                  <th class="p-2.5">{{ t('roomMap.cancelGuestName') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.cancelDate') }}</th>
                  <th class="p-2.5 text-right">{{ t('roomMap.cancelValue') }}</th>
                  <th class="p-2.5">{{ t('roomMap.cancelReason') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { id: 'CN-201', guest: 'Mr. David Lee', date: '08-06-2026', val: '2.500.000', reasonKey: 'cancelReason1' },
                  { id: 'CN-202', guest: 'Ms. Nguyen Kim Chi', date: '10-06-2026', val: '3.600.000', reasonKey: 'cancelReason2' },
                  { id: 'CN-203', guest: 'Mr. Park Jung Woo', date: '12-06-2026', val: '1.200.000', reasonKey: 'cancelReason3' }
                ]" :key="item.id" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-rose-600 font-black text-sm">{{ item.id }}</td>
                  <td class="p-2.5 text-slate-900">{{ item.guest }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.date }}</td>
                  <td class="p-2.5 text-right text-slate-800 font-black">{{ item.val }} đ</td>
                  <td class="p-2.5 text-slate-500 font-medium truncate max-w-[300px]">{{ t('roomMap.' + item.reasonKey) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 15: CHANNEL MANAGER Tab -->
          <div v-else-if="currentTab === 'channel-manager'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
              <div>
                <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.channelManagerTitle') }}</h2>
                <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.channelManagerDesc') }}</p>
              </div>
              <button @click="showDevelopmentToast(t('roomMap.channelManagerSyncNow'))" class="px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-black shadow-sm transition-all border-none cursor-pointer">
                {{ t('roomMap.channelManagerSyncNow') }}
              </button>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.channelManagerOtaLink') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.channelManagerAvailRooms') }}</th>
                  <th class="p-2.5 text-right">{{ t('roomMap.channelManagerSyncRates') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.channelManagerLastSync') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.channelManagerConnStatus') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { channel: 'Agoda API Connection', rooms: 15, rate: '650.000 đ', timeKey: 'channelManagerMinsAgo', timeVal: 10, status: 'active' },
                  { channel: 'Booking.com XML Sync', rooms: 12, rate: '680.000 đ', timeKey: 'channelManagerMinsAgo', timeVal: 5, status: 'active' },
                  { channel: 'Expedia.com OTA link', rooms: 8, rate: '720.000 đ', timeKey: 'channelManagerHourAgo', timeVal: 1, status: 'reconnecting' }
                ]" :key="item.channel" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ item.channel }}</td>
                  <td class="p-2.5 text-center font-black text-slate-800">{{ item.rooms }} {{ t('roomMap.allotmentRoomsUnit') }}</td>
                  <td class="p-2.5 text-right text-emerald-600 font-black">{{ item.rate }}</td>
                  <td class="p-2.5 text-center text-slate-400">{{ t('roomMap.' + item.timeKey, { n: item.timeVal }) }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'">
                      {{ item.status === 'active' ? t('roomMap.regActive') : t('roomMap.channelManagerReconnecting') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- THE DEFAULT ROOM MAP VIEW -->
          <div v-else class="flex-1 flex flex-col gap-4">
            
            <!-- Loading State -->
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
                <button @click="roomStore.fetchRooms()" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors cursor-pointer border-none shadow-sm">
                  Thử lại
                </button>
              </div>
            </div>

            <!-- Room Grid view (Lưới sơ đồ) -->
            <div v-else-if="isGridMode" class="flex-1 overflow-x-auto pt-2.5 pl-2.5 pr-2.5 pb-4 scrollbar-thin flex flex-col gap-3.5 animate-room-grid">
              <div
                v-for="(floor, floorIdx) in sortedFloors"
                :key="floor"
                class="flex gap-4 floor-row-animate"
                :style="{ animationDelay: `${floorIdx * 65}ms` }"
              >
                <!-- Vertical Floor Pill shape on the left - Sticky to keep floor numbers visible -->
                <div class="floor-pill cursor-pointer">
                  <span>{{ t('roomMap.floor', { floor }) }}</span>
                  <span class="text-[10px] opacity-80 font-bold mt-1">{{ t('roomMap.roomsCount', { count: roomStore.roomsByFloor[floor]?.length || 0 }) }}</span>
                </div>

                <!-- Rooms horizontal flex container inside this floor -->
                <div class="flex gap-3">
                  <div
                    v-for="(room, roomIdx) in roomStore.roomsByFloor[floor]"
                    :key="room.id"
                    class="room-card room-card-animate"
                    :style="{ animationDelay: `${(floorIdx * 80) + (roomIdx * 20)}ms` }"
                    :class="{ 'occupied-room': room.status === ROOM_STATUSES.OCCUPIED }"
                    @click="handleRoomClick(room)"
                    @contextmenu.prevent="handleContextMenu($event, room)"
                  >
                    <!-- Status Indicator Dot (Top Right) -->
                    <div class="absolute top-2.5 right-2.5 flex items-center gap-1">
                      <span
                        v-if="getRoomDotClass(room)"
                        class="w-2.5 h-2.5 rounded-full block border border-white/20 shadow-sm relative pulse-dot-ring"
                        :class="getRoomDotClass(room)"
                      ></span>
                    </div>

                    <!-- Room Number (Centered) -->
                    <div class="font-bold text-[18px] leading-tight text-center w-full">
                      <span :class="isRoomNumberRed(room) ? 'text-red-600' : 'text-gray-900'">
                        {{ room.room_number }}
                      </span>
                    </div>

                    <!-- Room details/guest name (Centered) -->
                    <div class="mt-2 text-[11px] font-bold text-gray-900 leading-tight text-center w-full">
                      <div v-if="room.status === ROOM_STATUSES.OCCUPIED" class="flex flex-col items-center gap-0.5 w-full">
                        <span class="truncate text-gray-900 font-bold max-w-full">
                          {{ getMockGuestName(room) }}
                        </span>
                        <span class="text-[10px] text-gray-900 font-bold flex items-center justify-center gap-0.5">
                          👤 2
                        </span>
                      </div>
                      <div v-else-if="room.status === ROOM_STATUSES.RESERVED" class="flex flex-col items-center gap-0.5 w-full">
                        <span class="truncate text-gray-900 font-bold max-w-full">
                          {{ getMockGuestName(room) }}
                        </span>
                        <span class="text-[10px] text-gray-900 font-bold flex items-center justify-center gap-0.5">
                          👤 2
                        </span>
                      </div>
                      <div v-else class="text-[10px] text-gray-900 font-bold uppercase text-center w-full">
                        {{ room.room_type || room.room_class?.code }}
                      </div>
                    </div>

                    <!-- Status Icon (Bottom Right) -->
                    <div class="flex items-center justify-end text-slate-400 mt-auto">
                      <!-- Sẵn sàng (available) checkmark -->
                      <RoomIcon v-if="room.status === ROOM_STATUSES.AVAILABLE" name="available" class="w-5 h-5" />
                      
                      <!-- Phòng bẩn (dirty) -->
                      <RoomIcon v-else-if="room.status === ROOM_STATUSES.DIRTY" name="dirty" class="w-5 h-5 text-amber-600" />
                      
                      <!-- Lau dọn (checkout) -->
                      <RoomIcon v-else-if="room.status === ROOM_STATUSES.CHECKOUT" name="checkout" class="w-5 h-5" />
                      
                      <!-- Dịch vụ dọn phòng (maintenance) -->
                      <RoomIcon v-else-if="room.status === ROOM_STATUSES.MAINTENANCE" name="maintenance" class="w-5 h-5" />
                      
                      <!-- Phòng ưu tiên (reserved) checkmark -->
                      <RoomIcon v-else-if="room.status === ROOM_STATUSES.RESERVED" name="reserved" class="w-5 h-5" />
                      
                      <!-- Phòng không làm phiền (occupied) checkmark -->
                      <RoomIcon v-else-if="room.status === ROOM_STATUSES.OCCUPIED" name="occupied" class="w-4.5 h-4.5 text-sky-700" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Room List Table view (Bảng danh sách) -->
            <div v-else class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-x-auto overflow-y-auto min-h-[300px] ml-1">
              <table class="w-full text-left border-collapse text-xs table-fixed min-w-[1400px]">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none whitespace-nowrap">
                    <th class="p-2 border-r border-slate-200 text-center w-[60px]">{{ t('roomMap.status') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">{{ t('roomMap.lateIn') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[100px] leading-tight text-[10px]">{{ t('roomMap.planMove') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">{{ t('roomMap.roomStatus') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">{{ t('roomMap.extraBed') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[80px] leading-tight text-[10px]">{{ t('roomMap.splReq') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px]">{{ t('roomMap.roomType') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[95px]">{{ t('roomMap.roomShape') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[75px]">{{ t('roomMap.roomNum') }}</th>
                    <th class="p-2 border-r border-slate-200 w-[180px]">{{ t('roomMap.guestName') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[75px]">{{ t('roomMap.regId') }}</th>
                    <th class="p-2 border-r border-slate-200 w-[240px]">{{ t('roomMap.regName') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[95px]">{{ t('roomMap.arrivalDate') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[95px]">{{ t('roomMap.departureDate') }}</th>
                    <th class="p-2 border-r border-slate-200 w-[200px]">{{ t('roomMap.company') }}</th>
                    <th class="p-2 text-center w-[60px]">{{ t('roomMap.floorHeader') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="room in roomStore.filteredRooms"
                    :key="room.id"
                    class="border-b border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer select-none h-9"
                    :class="room.status === ROOM_STATUSES.OCCUPIED ? 'bg-[#97d5ff]/40 hover:bg-[#97d5ff]/60' : 'bg-white'"
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
                        <!-- Sẵn sàng -->
                        <RoomIcon v-if="room.status === ROOM_STATUSES.AVAILABLE" name="double-check" class="w-5 h-5 text-blue-500" />
                        
                        <!-- Phòng bẩn -->
                        <RoomIcon v-else-if="room.status === ROOM_STATUSES.DIRTY" name="dirty" class="w-4.5 h-4.5 text-amber-600" />
                        
                        <!-- Lau dọn -->
                        <RoomIcon v-else-if="room.status === ROOM_STATUSES.CHECKOUT" name="star-outline" class="w-4.5 h-4.5 text-cyan-500" />
                        
                        <!-- Dịch vụ dọn phòng -->
                        <RoomIcon v-else-if="room.status === ROOM_STATUSES.MAINTENANCE" name="maintenance-list" class="w-5 h-5 text-blue-500" />
                        
                        <!-- Phòng ưu tiên -->
                        <RoomIcon v-else-if="room.status === ROOM_STATUSES.RESERVED" name="bell-outline" class="w-4.5 h-4.5 text-sky-500" />
                        
                        <!-- Phòng không làm phiền -->
                        <RoomIcon v-else-if="room.status === ROOM_STATUSES.OCCUPIED" name="minus-circle" class="w-4.5 h-4.5 text-slate-500" />
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
                    <!-- Phòng -->
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
        </div>

        <!-- RIGHT FILTER PANEL (BỘ LỌC) (Only displayed for Room Map tab) -->
        <div 
          v-if="currentTab === 'room-map'"
          class="filter-panel-wrapper overflow-hidden flex shrink-0 bg-white h-full"
          :class="showFilters ? 'border-l border-slate-200' : 'border-l-0'"
          :style="{ width: showFilters ? '320px' : '0px' }"
        >
          <aside class="w-80 bg-white p-5 flex flex-col gap-4 overflow-y-auto h-full select-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">{{ t('roomMap.filterTitle') }}</h3>
              <div class="flex items-center gap-2.5">
                <button @click="resetAllFilters" class="text-xs text-blue-500 font-bold hover:underline bg-transparent border-none cursor-pointer">
                  {{ t('roomMap.clearFilter') }}
                </button>
                <button @click="showFilters = false" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors" :title="t('roomMap.all') === 'All' ? 'Close' : 'Đóng'">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
            
            <div class="flex flex-col gap-3.5 text-xs font-bold text-slate-600">
              <!-- Ngày filter -->
              <div class="flex flex-col gap-1.5">
                <span>{{ t('roomMap.date') }}</span>
                <input type="date" v-model="rawDate" class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] font-bold text-slate-700 bg-white" />
              </div>

              <!-- Tầng filter -->
              <div class="flex flex-col gap-1.5">
                <span>{{ t('roomMap.floorLabel') }}</span>
                <select v-model="roomStore.filters.floor" class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] font-bold text-slate-700 bg-white">
                  <option :value="null">{{ t('roomMap.all') }}</option>
                  <option v-for="floor in uniqueFloors" :key="floor" :value="floor">{{ t('roomMap.floor', { floor }) }}</option>
                </select>
              </div>

              <!-- Loại phòng filter -->
              <div class="flex flex-col gap-1.5">
                <span>{{ t('roomMap.roomTypeLabel') }}</span>
                <select v-model="roomStore.filters.roomType" class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] font-bold text-slate-700 bg-white">
                  <option :value="null">{{ t('roomMap.all') }}</option>
                  <option v-for="type in uniqueRoomTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>

              <!-- Trạng thái lưu trú checkboxes -->
              <div class="flex flex-col gap-2 pt-2">
                <span>{{ t('roomMap.statusLabel') }}</span>
                <div class="flex flex-col gap-2">
                  <label v-for="st in [
                    { key: ROOM_STATUSES.AVAILABLE, labelKey: 'filterVacant' },
                    { key: ROOM_STATUSES.OCCUPIED, labelKey: 'filterOccupied' },
                    { key: ROOM_STATUSES.RESERVED, labelKey: 'filterArrival' },
                    { key: ROOM_STATUSES.CHECKOUT, labelKey: 'filterDeparture' },
                    { key: ROOM_STATUSES.MAINTENANCE, labelKey: 'filterLocked' }
                  ]" :key="st.key" class="flex items-center gap-2 cursor-pointer font-bold text-slate-700">
                    <input type="checkbox" :checked="roomStore.filters.status === st.key" 
                      @change="toggleStatusFilter(st.key)"
                      class="rounded border-slate-300 text-sky-600 focus:ring-[#97d5ff] h-4.5 w-4.5" />
                    <span>{{ t('roomMap.' + st.labelKey) }}</span>
                  </label>
                </div>
              </div>

              <!-- Trạng thái buồng phòng -->
              <div class="flex flex-col gap-1.5 pt-2">
                <span>{{ t('roomMap.housekeepingStatus') }}</span>
                <select class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] bg-white cursor-not-allowed font-bold text-slate-400" disabled>
                  <option value="all">{{ t('roomMap.all') }}</option>
                </select>
              </div>
            </div>

            <button @click="resetAllFilters" class="mt-auto w-full py-3 bg-[#97d5ff] hover:bg-[#7bc4ff] text-slate-900 font-black rounded-xl text-xs tracking-wider uppercase transition-colors shadow-xs border-none cursor-pointer">
              {{ t('roomMap.applyFilters') }}
            </button>
          </aside>
        </div>

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
        class="fixed z-[9999] bg-[#eaeaea] border border-slate-300 rounded-xl shadow-2xl py-1.5 w-[220px] select-none text-slate-800 font-bold animate-[fadeIn_0.15s_ease-out]"
        :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
        @click.stop
      >
        <!-- Thông tin -->
        <button
          @click="showRoomInfo(contextMenu.room)"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          <span>{{ t('roomMap.info') }}</span>
        </button>

        <!-- Đăng ký -->
        <button
          @click="triggerMenuItem('Đăng ký')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <line x1="19" y1="8" x2="19" y2="14" />
            <line x1="16" y1="11" x2="22" y2="11" />
          </svg>
          <span>{{ t('roomMap.registration') }}</span>
        </button>

        <!-- Hóa đơn -->
        <button
          @click="triggerMenuItem('Hóa đơn')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          <span>{{ t('roomMap.invoice') }}</span>
        </button>

        <!-- Nhóm hóa đơn -->
        <button
          @click="triggerMenuItem('Nhóm hóa đơn')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5" />
            <path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6" />
          </svg>
          <span>{{ t('roomMap.groupInvoice') }}</span>
        </button>

        <div class="h-px bg-slate-300 my-1"></div>

        <!-- Chuyển Phòng -->
        <button
          @click="triggerMenuItem('Chuyển Phòng')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 2.1a9 9 0 0 0-9 0L5 4M3 9V4h5M7 21.9a9 9 0 0 0 9 0l3-2.1M21 15v5h-5" />
          </svg>
          <span>{{ t('roomMap.roomMove') }}</span>
        </button>

        <!-- Thông báo -->
        <button
          @click="triggerMenuItem('Thông báo')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <span>{{ t('roomMap.notifications') }}</span>
        </button>

        <!-- In phiếu ăn sáng -->
        <button
          @click="triggerMenuItem('In phiếu ăn sáng')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 8h1a4 4 0 1 1 0 8h-1" />
            <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" />
            <line x1="6" y1="2" x2="6" y2="4" />
            <line x1="10" y1="2" x2="10" y2="4" />
            <line x1="14" y1="2" x2="14" y2="4" />
          </svg>
          <span>{{ t('roomMap.printBreakfast') }}</span>
        </button>

        <!-- In mẫu đăng ký -->
        <button
          @click="triggerMenuItem('In mẫu đăng ký')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
        >
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect x="6" y="14" width="12" height="8" />
          </svg>
          <span>{{ t('roomMap.printRegForm') }}</span>
        </button>

        <!-- Chuyển tình trạng phòng (Submenu Trigger) -->
        <div class="relative group mt-0.5">
          <div
            class="flex items-center justify-between px-3 py-2 bg-[#8ecefa] text-white text-xs font-bold hover:bg-[#6ab3e7] transition-colors cursor-pointer select-none"
          >
            <div class="flex items-center gap-2.5">
              <svg class="w-4.5 h-4.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
              </svg>
              <span>{{ t('roomMap.changeRoomStatus') }}</span>
            </div>
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
              <span>{{ t('roomMap.available') }}</span>
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
              <span>{{ t('roomMap.dirty') }}</span>
            </button>

            <!-- Lau dọn -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.CHECKOUT)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
            >
              <svg class="w-5 h-5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
              </svg>
              <span>{{ t('roomMap.cleaning') }}</span>
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
              <span>{{ t('roomMap.maintenance') }}</span>
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
              <span>{{ t('roomMap.priorityRoom') }}</span>
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
              <span>{{ t('roomMap.dnd') }}</span>
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

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes cardEntrance {
  from {
    opacity: 0;
    transform: scale(0.92) translateY(12px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes pulseRing {
  0% {
    transform: scale(0.85);
    opacity: 0.6;
  }
  50% {
    opacity: 1;
  }
  100% {
    transform: scale(1.4);
    opacity: 0;
  }
}

.floor-row-animate {
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.room-card-animate {
  animation: cardEntrance 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.pulse-dot-ring {
  position: relative;
}

.pulse-dot-ring::after {
  content: '';
  position: absolute;
  top: -1px;
  left: -1px;
  right: -1px;
  bottom: -1px;
  border-radius: 50%;
  background-color: inherit;
  animation: pulseRing 1.8s cubic-bezier(0.16, 1, 0.3, 1) infinite;
  pointer-events: none;
}

.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #97d5ff;
  border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #7bc4ff;
}

.filter-panel-wrapper {
  transition: width 0.45s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.45s ease;
  will-change: width;
}
</style>






