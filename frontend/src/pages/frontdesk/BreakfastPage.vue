<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { fetchBreakfastList, fetchHotelSettings } from '@/services/breakfast-service'
import { fetchBusinessInfo } from '@/services/company-service'
import { fetchSystemDate } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import BreakfastPrintModal from './components/BreakfastPrintModal.vue'
import BreakfastCouponPreview from './components/BreakfastCouponPreview.vue'

const uiStore = useUiStore()
const route = useRoute()

// State
const fromDate = ref('')
const toDate = ref('')
const dateType = ref('breakfast') // 'breakfast' | 'arrival'
const showType = ref(1) // 1: only breakfast, 0: all, 2: no breakfast

const isLoading = ref(false)
const rawList = ref([])
const hotelSettings = ref({})

// Search filters per column
const searchFilters = ref({
  bookingCode: '',
  bookingName: '',
  roomNumber: '',
  arrivalDate: '',
  departureDate: '',
  guestName: ''
})

// Selected Rows (list of unique room IDs or room_targetDate strings)
const selectedRowKeys = ref([])

// Collapsed state for tree groups (keys are group identifiers)
const collapsedGroups = ref({})
const isAllExpanded = ref(true)

// Modals
const isPrintModalOpen = ref(false)
const isPreviewOpen = ref(false)
const couponsToPrint = ref([])

// Date Picker refs
const fromDatePickerRef = ref(null)
const toDatePickerRef = ref(null)

function syncRoomFilterFromRoute() {
  const roomNumber = route.query.roomNumber
  searchFilters.value.roomNumber = typeof roomNumber === 'string' ? roomNumber : ''
}

function getRouteDate(name) {
  const value = route.query[name]
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : ''
}

function openFromDatePicker() {
  if (fromDatePickerRef.value) {
    try {
      if (typeof fromDatePickerRef.value.showPicker === 'function') {
        fromDatePickerRef.value.showPicker()
      } else {
        fromDatePickerRef.value.click()
      }
    } catch (e) {
      fromDatePickerRef.value.click()
    }
  }
}

function openToDatePicker() {
  if (toDatePickerRef.value) {
    try {
      if (typeof toDatePickerRef.value.showPicker === 'function') {
        toDatePickerRef.value.showPicker()
      } else {
        toDatePickerRef.value.click()
      }
    } catch (e) {
      toDatePickerRef.value.click()
    }
  }
}

onMounted(async () => {
  syncRoomFilterFromRoute()
  await initDates()
  await loadSettings()
  await loadData()
  // Đi từ Room Map có mã phòng thì mở thẳng màn hình preview nếu đã có phiếu.
  if (route.query.data && selectedRooms.value.length > 0) {
    handleConfirmPrint({ mode: 'all', fromDate: fromDate.value, toDate: toDate.value })
  }
})

watch(() => route.query.roomNumber, async () => {
  syncRoomFilterFromRoute()
  if (fromDate.value) await loadData()
})

async function initDates() {
  const routeFromDate = getRouteDate('fromDate')
  const routeToDate = getRouteDate('toDate')
  if (routeFromDate || routeToDate) {
    const from = routeFromDate || routeToDate
    const to = routeToDate || routeFromDate
    fromDate.value = from <= to ? from : to
    toDate.value = from <= to ? to : from
    return
  }

  let today = new Date().toISOString().slice(0, 10)
  try {
    const res = await fetchSystemDate()
    const sysDate = res.data?.data?.system_date || res.data?.system_date
    if (sysDate) {
      today = sysDate
    }
  } catch (err) {
    console.warn('Cannot fetch system date, fallback to client date:', err)
  }
  fromDate.value = today
  toDate.value = today
}

async function loadSettings() {
  try {
    const [bizRes, hotelRes] = await Promise.allSettled([
      fetchBusinessInfo(),
      fetchHotelSettings()
    ])
    const bizData = bizRes.status === 'fulfilled' ? (bizRes.value.data?.data || bizRes.value.data || {}) : {}
    const hotelData = hotelRes.status === 'fulfilled' ? (hotelRes.value.data?.data || hotelRes.value.data || {}) : {}
    
    // Ưu tiên Logo và Tên công ty từ Thông tin công ty (VISTA SYSTEM - /info-business)
    hotelSettings.value = {
      ...hotelData,
      hotel_name: bizData.company_name || hotelData.hotel_name || '',
      logo_url: bizData.logo_url || hotelData.logo_url || '',
      logo_path: bizData.logo_path || hotelData.logo_path || '',
    }
  } catch (err) {
    console.warn('Cannot load company/hotel settings:', err)
  }
}

async function loadData() {
  if (!fromDate.value) return
  isLoading.value = true
  try {
    const roomContext = typeof route.query.roomNumber === 'string' ? route.query.roomNumber.trim() : ''
    const res = await fetchBreakfastList({
      from_date: fromDate.value,
      to_date: toDate.value || fromDate.value,
      date_type: dateType.value,
      show_type: showType.value,
      ...(roomContext ? { room_number: roomContext, include_reserved: 1 } : {})
    })
    if (res.data?.success) {
      rawList.value = res.data.data || []
      // Khi mở từ Room Map, chỉ chọn các dòng của phòng đang thao tác.
      selectedRowKeys.value = filteredList.value.map(r => r.id)
    }
  } catch (err) {
    console.error('Lỗi tải danh sách phòng ăn sáng:', err)
    if (uiStore.showToast) {
      uiStore.showToast('Không thể tải danh sách phòng ăn sáng', 'error')
    }
  } finally {
    isLoading.value = false
  }
}

function formatDateDisplay(dStr) {
  if (!dStr) return ''
  const clean = String(dStr).split('T')[0]
  if (clean.includes('-')) {
    const [y, m, d] = clean.split('-')
    return `${d}/${m}/${y}`
  }
  return clean
}

// Filtered list based on column search
const filteredList = computed(() => {
  let list = rawList.value

  const sBkCode = searchFilters.value.bookingCode.trim().toLowerCase()
  const sBkName = searchFilters.value.bookingName.trim().toLowerCase()
  const sRoom = searchFilters.value.roomNumber.trim().toLowerCase()
  const sArr = searchFilters.value.arrivalDate.trim()
  const sDep = searchFilters.value.departureDate.trim()
  const sGuest = searchFilters.value.guestName.trim().toLowerCase()

  if (sBkCode) {
    list = list.filter(r => (r.booking_code || '').toLowerCase().includes(sBkCode))
  }
  if (sBkName) {
    list = list.filter(r => (r.booking_name || '').toLowerCase().includes(sBkName))
  }
  if (sRoom) {
    list = list.filter(r => (r.room_number || '').toLowerCase().includes(sRoom))
  }
  if (sArr) {
    list = list.filter(r => formatDateDisplay(r.arrival_date).includes(sArr))
  }
  if (sDep) {
    list = list.filter(r => formatDateDisplay(r.departure_date).includes(sDep))
  }
  if (sGuest) {
    list = list.filter(r => (r.guest_name || '').toLowerCase().includes(sGuest))
  }

  return list
})

// Grouped Tree Data: Always 3-level hierarchy (Date -> Booking -> Rooms)
const groupedData = computed(() => {
  const list = filteredList.value
  const dateGroups = {}

  list.forEach(item => {
    const dKey = item.target_date || item.arrival_date || 'Unknown'
    if (!dateGroups[dKey]) {
      dateGroups[dKey] = {
        date: dKey,
        displayDate: formatDateDisplay(dKey),
        bookings: {},
        items: []
      }
    }
    dateGroups[dKey].items.push(item)

    const bkKey = item.information_bk || `${item.booking_code}-${item.booking_name}` || 'Khách lẻ'
    if (!dateGroups[dKey].bookings[bkKey]) {
      dateGroups[dKey].bookings[bkKey] = {
        key: bkKey,
        booking_code: item.booking_code,
        booking_name: item.booking_name,
        company: item.company,
        items: []
      }
    }
    dateGroups[dKey].bookings[bkKey].items.push(item)
  })

  return dateGroups
})

// Selection helpers
const isAllSelected = computed(() => {
  if (filteredList.value.length === 0) return false
  return filteredList.value.every(r => selectedRowKeys.value.includes(r.id))
})

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedRowKeys.value = []
  } else {
    selectedRowKeys.value = filteredList.value.map(r => r.id)
  }
}

function toggleGroupSelect(items) {
  const itemIds = items.map(i => i.id)
  const allSelected = itemIds.every(id => selectedRowKeys.value.includes(id))
  if (allSelected) {
    selectedRowKeys.value = selectedRowKeys.value.filter(id => !itemIds.includes(id))
  } else {
    const next = new Set([...selectedRowKeys.value, ...itemIds])
    selectedRowKeys.value = Array.from(next)
  }
}

function isGroupSelected(items) {
  if (!items || items.length === 0) return false
  return items.every(i => selectedRowKeys.value.includes(i.id))
}

function toggleRowSelect(id) {
  const idx = selectedRowKeys.value.indexOf(id)
  if (idx > -1) {
    selectedRowKeys.value.splice(idx, 1)
  } else {
    selectedRowKeys.value.push(id)
  }
}

function isRowSelected(id) {
  return selectedRowKeys.value.includes(id)
}

function toggleGroupCollapse(key) {
  collapsedGroups.value[key] = !collapsedGroups.value[key]
}

function isGroupCollapsed(key) {
  return !!collapsedGroups.value[key]
}

// Summary totals
const totalSummary = computed(() => {
  const list = filteredList.value
  const uniqueRooms = new Set(list.map(r => r.rental_room_id)).size
  const totalAdults = list.reduce((sum, r) => sum + (Number(r.adults) || 0), 0)
  const totalChildren = list.reduce((sum, r) => sum + (Number(r.children_breakfast) || 0), 0)
  return {
    rooms: uniqueRooms,
    adults: totalAdults,
    children: totalChildren
  }
})

// PRINT LOGIC
const selectedRooms = computed(() => {
  return rawList.value.filter(r => selectedRowKeys.value.includes(r.id))
})

function openPrintModal() {
  if (selectedRooms.value.length === 0) {
    if (uiStore.showToast) {
      uiStore.showToast('Vui lòng chọn ít nhất 1 phòng để in phiếu ăn sáng!', 'warning')
    } else {
      alert('Vui lòng chọn ít nhất 1 phòng để in phiếu ăn sáng!')
    }
    return
  }
  isPrintModalOpen.value = true
}

function handleConfirmPrint({ mode, fromDate: rFrom, toDate: rTo }) {
  isPrintModalOpen.value = false
  const coupons = []

  const roomMap = new Map()
  selectedRooms.value.forEach(r => {
    if (!roomMap.has(r.rental_room_id)) {
      roomMap.set(r.rental_room_id, r)
    }
  })

  if (mode === 'all') {
    roomMap.forEach(room => {
      const dates = room.all_breakfast_dates || [room.target_date]
      const guestCount = (Number(room.adults) || 1) + (Number(room.children_breakfast) || 0)

      dates.forEach(dateStr => {
        for (let i = 0; i < guestCount; i++) {
          coupons.push({
            booking_info: room.information_bk || `${room.booking_code}/${room.booking_name}`,
            room_number: room.room_number,
            date: dateStr,
            guest_name: room.guest_name,
          })
        }
      })
    })
  } else {
    const fromD = new Date(rFrom)
    const toD = new Date(rTo)
    fromD.setHours(0, 0, 0, 0)
    toD.setHours(23, 59, 59, 999)

    roomMap.forEach(room => {
      const allDates = room.all_breakfast_dates || [room.target_date]
      const validDates = allDates.filter(dStr => {
        const d = new Date(dStr)
        return d >= fromD && d <= toD
      })

      const guestCount = (Number(room.adults) || 1) + (Number(room.children_breakfast) || 0)

      validDates.forEach(dateStr => {
        for (let i = 0; i < guestCount; i++) {
          coupons.push({
            booking_info: room.information_bk || `${room.booking_code}/${room.booking_name}`,
            room_number: room.room_number,
            date: dateStr,
            guest_name: room.guest_name,
          })
        }
      })
    })
  }

  couponsToPrint.value = coupons
  isPreviewOpen.value = true
}
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 text-slate-800 text-xs overflow-hidden">
    <!-- Top Filter Bar -->
    <header class="bg-white border-b border-slate-200 px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 shrink-0 shadow-2xs">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Date Range Filter with Clickable Calendar Picker -->
        <div class="flex items-center gap-2 bg-slate-100/90 px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
          <!-- From Date -->
          <div 
            class="flex items-center gap-1.5 cursor-pointer hover:text-sky-600 transition-colors select-none" 
            @click="openFromDatePicker"
            title="Click để chọn ngày bắt đầu"
          >
            <span class="font-black text-slate-800 text-xs tracking-tight">{{ formatDateDisplay(fromDate) }}</span>
            <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
              <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <input 
              ref="fromDatePickerRef"
              type="date" 
              v-model="fromDate"
              @change="loadData"
              class="sr-only"
            />
          </div>

          <span class="text-slate-400 font-black select-none">~</span>

          <!-- To Date -->
          <div 
            class="flex items-center gap-1.5 cursor-pointer hover:text-sky-600 transition-colors select-none" 
            @click="openToDatePicker"
            title="Click để chọn ngày kết thúc"
          >
            <span class="font-black text-slate-800 text-xs tracking-tight">{{ formatDateDisplay(toDate) }}</span>
            <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
              <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <input 
              ref="toDatePickerRef"
              type="date" 
              v-model="toDate"
              @change="loadData"
              class="sr-only"
            />
          </div>
        </div>

        <!-- Date Search Type Segmented Button Toggle -->
        <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 shadow-2xs">
          <button
            type="button"
            @click="dateType = 'breakfast'; loadData()"
            :class="dateType === 'breakfast' ? 'bg-sky-500 text-white shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
            class="px-3.5 py-1.5 rounded-md text-xs transition-all cursor-pointer flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Ngày ăn sáng</span>
          </button>
          <button
            type="button"
            @click="dateType = 'arrival'; loadData()"
            :class="dateType === 'arrival' ? 'bg-sky-500 text-white shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
            class="px-3.5 py-1.5 rounded-md text-xs transition-all cursor-pointer flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Ngày đến</span>
          </button>
        </div>

        <!-- Button Xem -->
        <button 
          @click="loadData" 
          :disabled="isLoading"
          class="h-8 px-4 bg-sky-400 hover:bg-sky-500 text-white font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-xs disabled:opacity-50 cursor-pointer"
        >
          <svg v-if="isLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>Xem</span>
        </button>

        <!-- Button In Phiếu Ăn Sáng (Direct Modal Trigger) -->
        <button 
          @click="openPrintModal"
          class="h-8 px-4 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer border border-slate-300"
          title="In phiếu ăn sáng"
        >
          <svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <span>In phiếu ăn sáng</span>
        </button>
      </div>

      <!-- Right Selection count -->
      <div class="text-xs text-slate-500 flex items-center gap-2">
        <span>Đã chọn: <strong class="text-sky-600 font-bold">{{ selectedRooms.length }}</strong> dòng</span>
      </div>
    </header>

    <!-- Table Container (Scrollable Area) -->
    <div class="flex-1 overflow-auto bg-white select-none">
      <table class="w-full border-collapse text-left text-xs">
        <thead class="sticky top-0 z-20 bg-slate-100 border-b border-slate-200 shadow-2xs">
          <!-- Header Row with Column Titles and Search Inputs -->
          <tr class="text-slate-700 h-9 font-bold divide-x divide-slate-200">
            <!-- Checkbox Column -->
            <th class="w-10 px-2 text-center">
              <input 
                type="checkbox" 
                :checked="isAllSelected" 
                @change="toggleSelectAll"
                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer"
              />
            </th>

            <!-- Mã đăng ký -->
            <th class="p-1.5 min-w-[220px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                  type="text" 
                  v-model="searchFilters.bookingCode" 
                  placeholder="Mã đăng ký"
                  class="w-full bg-transparent border-none outline-none font-bold text-slate-700 placeholder-slate-700"
                />
              </div>
            </th>

            <!-- Tên đăng ký -->
            <th class="p-1.5 min-w-[180px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                  type="text" 
                  v-model="searchFilters.bookingName" 
                  placeholder="Tên đăng ký"
                  class="w-full bg-transparent border-none outline-none font-bold text-slate-700 placeholder-slate-700"
                />
              </div>
            </th>

            <!-- Phòng -->
            <th class="p-1.5 min-w-[90px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                  type="text" 
                  v-model="searchFilters.roomNumber" 
                  placeholder="Phòng"
                  class="w-full bg-transparent border-none outline-none font-bold text-slate-700 placeholder-slate-700"
                />
              </div>
            </th>

            <!-- Ngày đến -->
            <th class="p-1.5 min-w-[110px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                  type="text" 
                  v-model="searchFilters.arrivalDate" 
                  placeholder="Ngày đến"
                  class="w-full bg-transparent border-none outline-none font-bold text-slate-700 placeholder-slate-700"
                />
              </div>
            </th>

            <!-- Ngày Đi -->
            <th class="p-1.5 min-w-[110px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                  type="text" 
                  v-model="searchFilters.departureDate" 
                  placeholder="Ngày Đi"
                  class="w-full bg-transparent border-none outline-none font-bold text-slate-700 placeholder-slate-700"
                />
              </div>
            </th>

            <!-- Tên Khách -->
            <th class="p-1.5 min-w-[160px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                  type="text" 
                  v-model="searchFilters.guestName" 
                  placeholder="Tên Khách"
                  class="w-full bg-transparent border-none outline-none font-bold text-slate-700 placeholder-slate-700"
                />
              </div>
            </th>

            <!-- Q (search generic) -->
            <th class="w-10 px-2 text-center font-bold text-slate-500">
              Q
            </th>

            <!-- Người Lớn -->
            <th class="p-2 min-w-[90px] text-center font-bold text-slate-700">
              Người Lớn
            </th>

            <!-- Trẻ Em Ăn Sáng -->
            <th class="p-2 min-w-[110px] text-center font-bold text-slate-700">
              Trẻ Em Ăn Sáng
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100 text-slate-700">
          <!-- Loading State -->
          <tr v-if="isLoading">
            <td colspan="10" class="text-center py-16 text-slate-400">
              <div class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5 animate-spin text-sky-500" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Đang tải dữ liệu phòng ăn sáng...</span>
              </div>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-else-if="filteredList.length === 0">
            <td colspan="10" class="text-center py-16 text-slate-400">
              Không có dữ liệu phòng ăn sáng trong khoảng thời gian đã chọn.
            </td>
          </tr>

          <!-- ==================== UNIFIED 3-LEVEL TREE HIERARCHY ==================== -->
          <template v-else>
            <template v-for="(dGroup, dKey) in groupedData" :key="dKey">
              <!-- LEVEL 1: DATE ROW -->
              <tr class="bg-white border-b border-slate-200/80 hover:bg-slate-50 font-bold text-slate-800 transition-colors">
                <td class="p-1.5 pl-2" colspan="10">
                  <div class="flex items-center gap-2">
                    <!-- Collapse / Expand Button (Cyan square) -->
                    <button
                      type="button"
                      @click="toggleGroupCollapse('date_' + dKey)"
                      class="w-4 h-4 bg-[#7dc0e7] hover:bg-[#68b2dc] text-white flex items-center justify-center text-xs font-black rounded-xs shadow-2xs cursor-pointer select-none transition-transform"
                    >
                      <span class="leading-none -mt-[1px]">{{ isGroupCollapsed('date_' + dKey) ? '+' : '−' }}</span>
                    </button>

                    <!-- Date Checkbox -->
                    <input 
                      type="checkbox" 
                      :checked="isGroupSelected(dGroup.items)"
                      @change="toggleGroupSelect(dGroup.items)"
                      class="w-3.5 h-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer"
                    />

                    <!-- Date Label -->
                    <span 
                      class="font-black text-slate-900 cursor-pointer tracking-wide text-xs"
                      @click="toggleGroupCollapse('date_' + dKey)"
                    >
                      {{ dGroup.displayDate }}
                    </span>
                  </div>
                </td>
              </tr>

              <!-- LEVEL 2: BOOKING ROWS UNDER THIS DATE -->
              <template v-if="!isGroupCollapsed('date_' + dKey)">
                <template v-for="(bkGroup, bkKey) in dGroup.bookings" :key="bkKey">
                  <!-- Booking Header Row -->
                  <tr class="bg-white border-b border-slate-200/60 hover:bg-slate-50/80 font-bold text-slate-800 transition-colors">
                    <td class="p-1.5 pl-6" colspan="10">
                      <div class="flex items-center gap-2">
                        <!-- Collapse / Expand Button for Booking -->
                        <button
                          type="button"
                          @click="toggleGroupCollapse('bk_' + dKey + '_' + bkKey)"
                          class="w-4 h-4 bg-[#7dc0e7] hover:bg-[#68b2dc] text-white flex items-center justify-center text-xs font-black rounded-xs shadow-2xs cursor-pointer select-none transition-transform"
                        >
                          <span class="leading-none -mt-[1px]">{{ isGroupCollapsed('bk_' + dKey + '_' + bkKey) ? '+' : '−' }}</span>
                        </button>

                        <!-- Booking Checkbox -->
                        <input 
                          type="checkbox" 
                          :checked="isGroupSelected(bkGroup.items)"
                          @change="toggleGroupSelect(bkGroup.items)"
                          class="w-3.5 h-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer"
                        />

                        <!-- Booking Key Label -->
                        <span 
                          class="font-bold text-slate-800 cursor-pointer text-xs uppercase"
                          @click="toggleGroupCollapse('bk_' + dKey + '_' + bkKey)"
                        >
                          {{ bkGroup.key }}
                        </span>
                      </div>
                    </td>
                  </tr>

                  <!-- LEVEL 3: ROOM / COUPON ROWS UNDER BOOKING -->
                  <template v-if="!isGroupCollapsed('bk_' + dKey + '_' + bkKey)">
                    <tr 
                      v-for="room in bkGroup.items" 
                      :key="room.id"
                      :class="{'bg-sky-50/50': isRowSelected(room.id)}"
                      class="hover:bg-sky-50/40 transition-colors border-b border-slate-100 divide-x divide-slate-100/60"
                    >
                      <!-- Checkbox Column with Indentation -->
                      <td class="p-1.5 text-center">
                        <div class="flex items-center justify-center pl-6">
                          <input 
                            type="checkbox" 
                            :checked="isRowSelected(room.id)"
                            @change="toggleRowSelect(room.id)"
                            class="w-3.5 h-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer"
                          />
                        </div>
                      </td>

                      <!-- Mã đăng ký -->
                      <td class="px-2.5 py-1.5 font-medium text-slate-800">
                        {{ room.booking_code }}
                      </td>

                      <!-- Tên đăng ký -->
                      <td class="px-2.5 py-1.5 text-slate-700">
                        {{ room.booking_name }}
                      </td>

                      <!-- Phòng -->
                      <td class="px-2.5 py-1.5 font-black text-slate-900">
                        {{ room.room_number }}
                      </td>

                      <!-- Ngày đến -->
                      <td class="px-2.5 py-1.5 text-slate-600">
                        {{ formatDateDisplay(room.arrival_date) }}
                      </td>

                      <!-- Ngày Đi -->
                      <td class="px-2.5 py-1.5 text-slate-600">
                        {{ formatDateDisplay(room.departure_date) }}
                      </td>

                      <!-- Tên Khách -->
                      <td class="px-2.5 py-1.5 text-slate-700">
                        {{ room.guest_name }}
                      </td>

                      <!-- Q (Generic spacer) -->
                      <td class="px-1 py-1.5 text-center text-slate-300">
                      </td>

                      <!-- Người Lớn -->
                      <td class="px-2.5 py-1.5 text-center font-bold text-slate-800">
                        {{ room.adults }}
                      </td>

                      <!-- Trẻ Em Ăn Sáng -->
                      <td class="px-2.5 py-1.5 text-center font-bold text-slate-800">
                        {{ room.children_breakfast }}
                      </td>
                    </tr>
                  </template>
                </template>
              </template>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pinned Bottom Summary Bar (Always docked at bottom of the page) -->
    <footer class="bg-slate-100 border-t-2 border-slate-300 px-6 py-2.5 flex flex-wrap items-center justify-between gap-4 shrink-0 shadow-lg text-slate-800 select-none">
      <div class="flex items-center gap-6">
        <div class="flex items-center gap-2">
          <span class="text-xs uppercase font-bold text-slate-500">Tổng phòng:</span>
          <span class="px-3 py-0.5 bg-white border border-slate-300 rounded-md font-black text-xs text-slate-900 shadow-2xs">
            {{ totalSummary.rooms }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs uppercase font-bold text-slate-500">Tổng Người lớn:</span>
          <span class="px-3 py-0.5 bg-white border border-slate-300 rounded-md font-black text-xs text-sky-700 shadow-2xs">
            {{ totalSummary.adults }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs uppercase font-bold text-slate-500">Tổng Trẻ em ăn sáng:</span>
          <span class="px-3 py-0.5 bg-white border border-slate-300 rounded-md font-black text-xs text-emerald-700 shadow-2xs">
            {{ totalSummary.children }}
          </span>
        </div>
      </div>

      <div class="text-xs text-slate-500">
        Đang hiển thị <strong class="text-slate-800 font-bold">{{ filteredList.length }}</strong> dòng kết quả
      </div>
    </footer>

    <!-- Breakfast Print Option Modal (Choose In All or In Range) -->
    <BreakfastPrintModal
      :is-open="isPrintModalOpen"
      :selected-rooms="selectedRooms"
      :default-from-date="fromDate"
      :default-to-date="toDate"
      @close="isPrintModalOpen = false"
      @confirm="handleConfirmPrint"
    />

    <!-- Breakfast Coupon Preview / Print Dialog -->
    <BreakfastCouponPreview
      :is-open="isPreviewOpen"
      :coupons="couponsToPrint"
      :hotel-settings="hotelSettings"
      @close="isPreviewOpen = false"
    />
  </div>
</template>
