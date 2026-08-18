<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { fetchSystemDate } from '@/services/booking-service'
import {
  fetchArrivals,
  fetchDepartures,
  fetchPending,
  updatePendingNote,
  fetchShuttle,
  fetchNoshow,
  fetchBirthdays
} from '@/services/shift-work-service'

const uiStore = useUiStore()

// Sub-tabs list
const tabs = [
  { key: 'arrivals', name: 'Phòng đến' },
  { key: 'departures', name: 'Phòng đi' },
  { key: 'pending', name: 'Đăng ký chờ xác nhận' },
  { key: 'shuttle', name: 'Đón tiễn khách' },
  { key: 'noshow', name: 'Phòng không đến (Noshow)' },
  { key: 'birthdays', name: 'Sinh nhật khách' }
]

const activeTab = ref('arrivals')
const isLoading = ref(false)

// Dates state
const searchDate = ref('') // Single date for arrivals, departures, shuttle
const searchDateFrom = ref('') // Range from for pending, noshow, birthdays
const searchDateTo = ref('') // Range to

const dateInputRef = ref(null)
const dateFromInputRef = ref(null)
const dateToInputRef = ref(null)

// Filters
const arrivalsStatus = ref('not_checked_in') // 'not_checked_in' (mặc định), 'checked_in', 'all'
const departuresStatus = ref('not_checked_out') // 'not_checked_out' (mặc định), 'checked_out', 'all'
const noshowFilter = ref('all') // 'all', 'charged', 'foc'
const shuttleType = ref('all') // 'all', 'arrival', 'departure'
const searchTerm = ref('')

// Data lists
const arrivalsData = ref([])
const departuresData = ref([])
const pendingConfirmationData = ref([])
const shuttleData = ref([])
const noshowData = ref([])
const birthdaysData = ref([])

// Expand/Collapse state: map of booking ID or group key -> boolean (true if collapsed)
const collapsedBookings = ref({})
const collapsedGroups = ref({})

function toggleCollapse(bookingId) {
  collapsedBookings.value[bookingId] = !collapsedBookings.value[bookingId]
}

function toggleGroupCollapse(groupKey) {
  collapsedGroups.value[groupKey] = !collapsedGroups.value[groupKey]
}

function isGroupCollapsed(groupKey) {
  return !!collapsedGroups.value[groupKey]
}

const isRangeTab = computed(() => {
  return ['pending', 'noshow', 'birthdays'].includes(activeTab.value)
})

// Date format helpers
function formatDateInput(dateStr) {
  if (!dateStr) return ''
  const clean = String(dateStr).split('T')[0].split(' ')[0]
  const parts = clean.split('-')
  if (parts.length !== 3) return dateStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

function triggerDatePicker() {
  if (dateInputRef.value) {
    if (typeof dateInputRef.value.showPicker === 'function') {
      dateInputRef.value.showPicker()
    } else {
      dateInputRef.value.click()
    }
  }
}

function triggerDateFromPicker() {
  if (dateFromInputRef.value) {
    if (typeof dateFromInputRef.value.showPicker === 'function') {
      dateFromInputRef.value.showPicker()
    } else {
      dateFromInputRef.value.click()
    }
  }
}

function triggerDateToPicker() {
  if (dateToInputRef.value) {
    if (typeof dateToInputRef.value.showPicker === 'function') {
      dateToInputRef.value.showPicker()
    } else {
      dateToInputRef.value.click()
    }
  }
}

const currentSystemDate = ref('')

function setDateToday() {
  const today = currentSystemDate.value || new Date().toISOString().slice(0, 10)
  if (isRangeTab.value) {
    searchDateFrom.value = today
    searchDateTo.value = today
  } else {
    searchDate.value = today
  }
  loadTabData()
}

function setDateTomorrow() {
  const base = currentSystemDate.value ? new Date(currentSystemDate.value) : new Date()
  base.setDate(base.getDate() + 1)
  const tomorrow = base.toISOString().slice(0, 10)
  if (isRangeTab.value) {
    searchDateFrom.value = tomorrow
    searchDateTo.value = tomorrow
  } else {
    searchDate.value = tomorrow
  }
  loadTabData()
}

function handleCopyDate() {
  const formatted = formatDateInput(searchDate.value)
  navigator.clipboard.writeText(formatted)
    .then(() => uiStore.showToast(`Đã sao chép ngày "${formatted}"!`, 'success'))
    .catch(() => uiStore.showToast('Không thể sao chép ngày!', 'error'))
}

function handleCopyDateFrom() {
  const formatted = formatDateInput(searchDateFrom.value)
  navigator.clipboard.writeText(formatted)
    .then(() => uiStore.showToast(`Đã sao chép ngày bắt đầu "${formatted}"!`, 'success'))
    .catch(() => uiStore.showToast('Không thể sao chép ngày!', 'error'))
}

function handleCopyDateTo() {
  const formatted = formatDateInput(searchDateTo.value)
  navigator.clipboard.writeText(formatted)
    .then(() => uiStore.showToast(`Đã sao chép ngày kết thúc "${formatted}"!`, 'success'))
    .catch(() => uiStore.showToast('Không thể sao chép ngày!', 'error'))
}

// Initialization & Data Loading
onMounted(async () => {
  await initDates()
  await loadTabData()
})

watch(activeTab, () => {
  searchTerm.value = ''
  loadTabData()
})

async function initDates() {
  let today = new Date().toISOString().slice(0, 10)
  try {
    const res = await fetchSystemDate()
    const rawDate = res.data?.data?.system_date || res.data?.system_date
    if (rawDate) {
      today = String(rawDate).split('T')[0].split(' ')[0]
    }
  } catch (err) {
    console.warn('Cannot fetch system date:', err)
  }
  currentSystemDate.value = today
  searchDate.value = today
  searchDateFrom.value = today

  // Plus 3 days for range
  const dTo = new Date(today)
  dTo.setDate(dTo.getDate() + 3)
  searchDateTo.value = dTo.toISOString().slice(0, 10)
}

async function loadTabData() {
  isLoading.value = true
  try {
    if (activeTab.value === 'arrivals') {
      const res = await fetchArrivals({
        date: searchDate.value,
        status: arrivalsStatus.value,
        search: searchTerm.value
      })
      arrivalsData.value = res.data?.data || []
    } else if (activeTab.value === 'departures') {
      const res = await fetchDepartures({
        date: searchDate.value,
        status: departuresStatus.value,
        search: searchTerm.value
      })
      departuresData.value = res.data?.data || []
    } else if (activeTab.value === 'pending') {
      const res = await fetchPending({
        from_date: searchDateFrom.value,
        to_date: searchDateTo.value,
        search: searchTerm.value
      })
      pendingConfirmationData.value = res.data?.data || []
    } else if (activeTab.value === 'shuttle') {
      const res = await fetchShuttle({
        date: searchDate.value,
        type: shuttleType.value,
        search: searchTerm.value
      })
      shuttleData.value = res.data?.data || []
    } else if (activeTab.value === 'noshow') {
      const res = await fetchNoshow({
        from_date: searchDateFrom.value,
        to_date: searchDateTo.value,
        search: searchTerm.value
      })
      noshowData.value = res.data?.data || []
    } else if (activeTab.value === 'birthdays') {
      const res = await fetchBirthdays({
        from_date: searchDateFrom.value,
        to_date: searchDateTo.value,
        search: searchTerm.value
      })
      birthdaysData.value = res.data?.data || []
    }
  } catch (err) {
    console.error('Lỗi tải dữ liệu ca làm việc:', err)
    uiStore.showToast('Không thể tải dữ liệu ca làm việc: ' + (err.message || ''), 'error')
  } finally {
    isLoading.value = false
  }
}

let searchDebounceTimer = null

function handleSearchInput() {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    loadTabData()
  }, 250)
}

function clearSearch() {
  searchTerm.value = ''
  clearTimeout(searchDebounceTimer)
  loadTabData()
}

function handleSearch() {
  clearTimeout(searchDebounceTimer)
  loadTabData()
}

// Arrivals Calculations
const arrivalsTotalRegistrations = computed(() => arrivalsData.value.length)
const arrivalsTotalRooms = computed(() => {
  return arrivalsData.value.reduce((acc, curr) => acc + (curr.roomsCount || curr.rooms?.length || 0), 0)
})

// Departures Calculations
const departuresTotalRegistrations = computed(() => departuresData.value.length)
const departuresTotalRooms = computed(() => {
  return departuresData.value.reduce((acc, curr) => acc + (curr.roomsCount || curr.rooms?.length || 0), 0)
})

// Noshow Filter
const filteredNoshowData = computed(() => {
  if (noshowFilter.value === 'all') return noshowData.value
  if (noshowFilter.value === 'charged') {
    return noshowData.value.filter(item => (item.totalAmount || 0) > 0)
  }
  if (noshowFilter.value === 'foc') {
    return noshowData.value.filter(item => (item.totalAmount || 0) === 0)
  }
  return noshowData.value
})

// Shuttle Groups
const shuttleGroups = computed(() => {
  const groups = {}
  shuttleData.value.forEach(item => {
    const d = item.date || 'Khác'
    if (!groups[d]) groups[d] = []
    groups[d].push(item)
  })
  return groups
})

const sortedShuttleDates = computed(() => {
  return Object.keys(shuttleGroups.value)
})

// Birthday Groups
const birthdayGroups = computed(() => {
  const groups = {}
  birthdaysData.value.forEach(item => {
    const d = item.birthday || 'Khác'
    if (!groups[d]) groups[d] = []
    groups[d].push(item)
  })
  return groups
})

const sortedBirthdayDates = computed(() => {
  return Object.keys(birthdayGroups.value)
})

// Pending Groups
const pendingGroups = computed(() => {
  const groups = {}
  pendingConfirmationData.value.forEach(item => {
    const d = item.confirmDate || item.arrivalDate || 'Chờ xác nhận'
    if (!groups[d]) groups[d] = []
    groups[d].push(item)
  })
  return groups
})

const sortedPendingDates = computed(() => {
  return Object.keys(pendingGroups.value)
})

// Footer statistics
const footerStats = computed(() => {
  if (activeTab.value === 'arrivals') {
    return {
      label1: 'Tổng đăng ký:',
      val1: arrivalsTotalRegistrations.value,
      label2: 'Tổng phòng:',
      val2: arrivalsTotalRooms.value
    }
  } else if (activeTab.value === 'departures') {
    return {
      label1: 'Tổng đăng ký:',
      val1: departuresTotalRegistrations.value,
      label2: 'Tổng phòng:',
      val2: departuresTotalRooms.value
    }
  } else if (activeTab.value === 'pending') {
    const totalNights = pendingConfirmationData.value.reduce((sum, item) => sum + (item.nights || 0), 0)
    return {
      label1: 'Tổng đăng ký:',
      val1: pendingConfirmationData.value.length,
      label2: 'Đêm phòng:',
      val2: totalNights
    }
  } else if (activeTab.value === 'shuttle') {
    return {
      label1: 'Tổng lượt:',
      val1: shuttleData.value.length,
      label2: 'Tổng lượt:',
      val2: shuttleData.value.length
    }
  } else if (activeTab.value === 'noshow') {
    const totalNights = filteredNoshowData.value.reduce((sum, item) => sum + (item.nights || 0), 0)
    return {
      label1: 'Tổng số phòng:',
      val1: filteredNoshowData.value.length,
      label2: 'Tổng đêm vắng:',
      val2: totalNights
    }
  } else if (activeTab.value === 'birthdays') {
    return {
      label1: 'Tổng khách:',
      val1: birthdaysData.value.length,
      label2: 'Phòng:',
      val2: birthdaysData.value.filter(item => item.roomNumber).length
    }
  }
  return { label1: 'Tổng:', val1: 0, label2: 'Phòng:', val2: 0 }
})

const editingNotes = ref({})

function toggleEditNote(itemId) {
  editingNotes.value[itemId] = !editingNotes.value[itemId]
}

async function saveNote(item) {
  editingNotes.value[item.id] = false
  try {
    await updatePendingNote(item.bookingId || item.id, item.notes)
    uiStore.showToast(`Đã lưu ghi chú cho đăng ký ${item.id} thành công!`, 'success')
  } catch (err) {
    uiStore.showToast('Lỗi lưu ghi chú: ' + (err.message || ''), 'error')
  }
}

function formatMoney(num) {
  if (num === undefined || num === null) return '0'
  return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-full">
    <!-- Sub tabs header (3rd Level Menu) -->
    <div class="flex items-center border-b border-slate-200 px-4 bg-slate-50/50 shrink-0 overflow-x-auto scrollbar-none">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="py-3 px-4 text-xs font-bold transition-all relative border-b-2 whitespace-nowrap cursor-pointer"
        :class="activeTab === tab.key
          ? 'border-sky-500 text-sky-600'
          : 'border-transparent text-slate-700 hover:text-slate-900 hover:border-slate-300'"
      >
        {{ tab.name }}
      </button>
    </div>

    <!-- Custom Date Selector & Filters Toolbar -->
    <div class="p-3 border-b border-slate-100 flex items-center gap-2 bg-white shrink-0 flex-wrap">
      <!-- Case 1: Single Date Picker for arrivals / departures / shuttle -->
      <div v-if="!isRangeTab" class="flex items-center gap-2 flex-wrap">
        <span class="text-xs font-bold text-slate-600">
          {{ activeTab === 'arrivals' ? 'Ngày đến:' : (activeTab === 'departures' ? 'Ngày đi:' : 'Ngày:') }}
        </span>
        <div class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-sm hover:border-slate-300 transition-colors">
          <span 
            @click="triggerDatePicker"
            class="text-xs font-bold text-slate-700 px-3 py-1 cursor-pointer select-none"
          >
            {{ formatDateInput(searchDate) }}
          </span>
          
          <input
            ref="dateInputRef"
            type="date"
            v-model="searchDate"
            @change="loadTabData"
            class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
          />

          <button
            @click="triggerDatePicker"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-[#10b981] bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors"
            title="Chọn ngày"
          >
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </button>

          <button
            @click="handleCopyDate"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors ml-0.5 border-l border-slate-100"
            title="Sao chép ngày"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
          </button>
        </div>

        <!-- Quick date buttons -->
        <div class="flex items-center gap-1">
          <button
            @click="setDateToday"
            type="button"
            class="px-2.5 py-1 text-xs font-semibold rounded border border-slate-200 hover:bg-slate-50 text-slate-700 cursor-pointer transition-colors"
          >
            Hôm nay
          </button>
          <button
            @click="setDateTomorrow"
            type="button"
            class="px-2.5 py-1 text-xs font-semibold rounded border border-slate-200 hover:bg-slate-50 text-slate-700 cursor-pointer transition-colors"
          >
            Ngày mai
          </button>
        </div>

        <!-- Status Filter for Arrivals -->
        <div v-if="activeTab === 'arrivals'" class="flex items-center gap-1.5 ml-2">
          <span class="text-xs font-bold text-slate-600">Trạng thái:</span>
          <select
            v-model="arrivalsStatus"
            @change="loadTabData"
            class="text-xs font-medium border border-slate-200 rounded-lg px-2.5 py-1 bg-white text-slate-700 focus:outline-none focus:border-sky-500 shadow-sm"
          >
            <option value="not_checked_in">Chưa nhận phòng</option>
            <option value="checked_in">Đã nhận phòng</option>
            <option value="all">Tất cả</option>
          </select>
        </div>

        <!-- Status Filter for Departures -->
        <div v-if="activeTab === 'departures'" class="flex items-center gap-1.5 ml-2">
          <span class="text-xs font-bold text-slate-600">Trạng thái:</span>
          <select
            v-model="departuresStatus"
            @change="loadTabData"
            class="text-xs font-medium border border-slate-200 rounded-lg px-2.5 py-1 bg-white text-slate-700 focus:outline-none focus:border-sky-500 shadow-sm"
          >
            <option value="not_checked_out">Chưa trả</option>
            <option value="checked_out">Đã trả phòng</option>
            <option value="all">Tất cả</option>
          </select>
        </div>

        <!-- Type Filter for Shuttle -->
        <div v-if="activeTab === 'shuttle'" class="flex items-center gap-1.5 ml-2">
          <span class="text-xs font-bold text-slate-600">Loại:</span>
          <select
            v-model="shuttleType"
            @change="loadTabData"
            class="text-xs font-medium border border-slate-200 rounded-lg px-2.5 py-1 bg-white text-slate-700 focus:outline-none focus:border-sky-500 shadow-sm"
          >
            <option value="all">Tất cả</option>
            <option value="arrival">Đón sân bay</option>
            <option value="departure">Tiễn sân bay</option>
          </select>
        </div>
      </div>

      <!-- Case 2: Date Range Picker for range-based tabs (pending, noshow, birthdays) -->
      <div v-else class="flex items-center gap-1.5 flex-wrap">
        <span class="text-xs font-bold text-slate-600">Từ ngày:</span>
        <div class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-sm hover:border-slate-300 transition-colors">
          <span 
            @click="triggerDateFromPicker"
            class="text-xs font-bold text-slate-700 px-3 py-1 cursor-pointer select-none"
          >
            {{ formatDateInput(searchDateFrom) }}
          </span>
          <input
            ref="dateFromInputRef"
            type="date"
            v-model="searchDateFrom"
            @change="loadTabData"
            class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
          />
          <button
            @click="triggerDateFromPicker"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-[#10b981] bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors"
            title="Chọn ngày bắt đầu"
          >
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </button>
          <button
            @click="handleCopyDateFrom"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors ml-0.5 border-l border-slate-100"
            title="Sao chép ngày bắt đầu"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
          </button>
        </div>

        <span class="text-xs font-bold text-slate-400 mx-1">~ Đến ngày:</span>

        <!-- Date To Picker -->
        <div class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-sm hover:border-slate-300 transition-colors">
          <span 
            @click="triggerDateToPicker"
            class="text-xs font-bold text-slate-700 px-3 py-1 cursor-pointer select-none"
          >
            {{ formatDateInput(searchDateTo) }}
          </span>
          <input
            ref="dateToInputRef"
            type="date"
            v-model="searchDateTo"
            @change="loadTabData"
            class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
          />
          <button
            @click="triggerDateToPicker"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-[#10b981] bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors"
            title="Chọn ngày kết thúc"
          >
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </button>
          <button
            @click="handleCopyDateTo"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors ml-0.5 border-l border-slate-100"
            title="Sao chép ngày kết thúc"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
          </button>
        </div>

        <!-- Noshow Radio Group Filter -->
        <div v-if="activeTab === 'noshow'" class="flex items-center gap-3 ml-3 border-l border-slate-200 pl-4 text-xs font-bold text-slate-700">
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value="all" v-model="noshowFilter" class="accent-sky-500" />
            Tất cả
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value="charged" v-model="noshowFilter" class="accent-sky-500" />
            Tính phí
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value="foc" v-model="noshowFilter" class="accent-sky-500" />
            Không tính phí
          </label>
        </div>
      </div>

      <!-- Quick Search Box (Live Search with Clear X Button) -->
      <div class="flex items-center ml-auto">
        <div class="relative flex items-center">
          <input
            v-model="searchTerm"
            @input="handleSearchInput"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="Tìm mã BK, tên, phòng, cty..."
            class="pl-8 pr-8 py-1.5 text-xs border border-slate-200 rounded-lg w-60 focus:w-72 transition-all focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-200 shadow-sm bg-white text-slate-800"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>

          <!-- Clear Icon 'X' -->
          <button
            v-if="searchTerm"
            @click="clearSearch"
            type="button"
            class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 rounded-full hover:bg-slate-100 transition-colors cursor-pointer border-none flex items-center justify-center"
            title="Xóa tìm kiếm"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Main List/Table Content -->
    <div class="flex-1 overflow-hidden bg-slate-50/40 flex flex-col relative">
      <!-- Loading Overlay -->
      <div v-if="isLoading" class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-30 flex items-center justify-center">
        <div class="flex flex-col items-center gap-2">
          <div class="w-8 h-8 border-3 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
          <span class="text-xs font-bold text-slate-600">Đang tải dữ liệu thực tế...</span>
        </div>
      </div>

      <!-- Tab 1: Arrivals Page ("Phòng đến") -->
      <div v-if="activeTab === 'arrivals'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1600px] table-fixed">
            <thead>
              <tr class="bg-[#f1f5f9] border-b border-slate-200 text-slate-800 font-bold select-none h-9 text-[11.5px]">
                <th class="py-2 px-2.5 w-[110px] sticky top-0 bg-[#f1f5f9] z-10">Mã đăng ký <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2.5 w-[220px] sticky top-0 bg-[#f1f5f9] z-10">Tên đăng ký <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-left w-[130px] sticky top-0 bg-[#f1f5f9] z-10">Tình trạng đăng ký</th>
                <th class="py-2 px-2 text-left w-[130px] sticky top-0 bg-[#f1f5f9] z-10">Loại phòng</th>
                <th class="py-2 px-2 text-center w-[75px] sticky top-0 bg-[#f1f5f9] z-10">Phòng <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-center w-[95px] sticky top-0 bg-[#f1f5f9] z-10">Ngày đến <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-center w-[95px] sticky top-0 bg-[#f1f5f9] z-10">Ngày đi <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-center w-[75px] sticky top-0 bg-[#f1f5f9] z-10">Đêm phòng</th>
                <th class="py-2 px-2 text-center w-[65px] sticky top-0 bg-[#f1f5f9] z-10">Người lớn</th>
                <th class="py-2 px-2 text-center w-[65px] sticky top-0 bg-[#f1f5f9] z-10">Trẻ em</th>
                <th class="py-2 px-2 text-right w-[100px] sticky top-0 bg-[#f1f5f9] z-10">Giá phòng</th>
                <th class="py-2 px-2 text-center w-[90px] sticky top-0 bg-[#f1f5f9] z-10">Mã giá phòng</th>
                <th class="py-2 px-2 text-right w-[110px] sticky top-0 bg-[#f1f5f9] z-10">Tổng phòng</th>
                <th class="py-2 px-2.5 text-left w-[180px] sticky top-0 bg-[#f1f5f9] z-10">Yêu cầu ĐB</th>
                <th class="py-2 px-2.5 text-left w-[170px] sticky top-0 bg-[#f1f5f9] z-10">Công ty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="arrivalsData.length === 0 && !isLoading">
                <td colspan="15" class="p-8 text-center text-slate-400 font-medium">
                  Không có danh sách phòng đến trong ngày này.
                </td>
              </tr>
              <template v-for="booking in arrivalsData" :key="booking.id">
                <!-- Group Header Banner Row (Exact layout from user screenshot) -->
                <tr class="border-b border-t border-slate-200 bg-[#edf5fc]">
                  <td colspan="15" class="py-2 px-3">
                    <div class="flex items-center justify-between gap-4 text-xs">
                      <!-- Left Info String -->
                      <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap">
                        <button
                          @click="toggleCollapse(booking.id)"
                          type="button"
                          class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px]"
                        >
                          {{ collapsedBookings[booking.id] ? '+' : '-' }}
                        </button>
                        
                        <div class="flex items-center gap-1.5 flex-wrap text-[11.5px] leading-tight">
                          <span class="font-normal text-slate-800">Booking <span class="font-bold">{{ booking.id }}</span></span>
                          <span class="font-black text-slate-900 uppercase">{{ booking.bookingName }}</span>
                          <span class="text-slate-700">{{ booking.arrivalDate }}~{{ booking.departureDate }} _ Room Night: {{ booking.roomNight }} _ Phòng: {{ booking.roomsCount }}</span>
                          <span v-if="booking.notes" class="text-slate-600 font-normal ml-1 border-l border-slate-300 pl-2">
                            {{ booking.notes }}
                          </span>
                        </div>
                      </div>

                      <!-- Right Financials -->
                      <div class="flex items-center gap-5 shrink-0 text-[11.5px] font-bold text-slate-800 whitespace-nowrap">
                        <span>Đặt cọc : {{ formatMoney(booking.deposit) }}</span>
                        <span>Tổng tiền : {{ formatMoney(booking.totalAmount) }}</span>
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- Child Room Rows -->
                <tr
                  v-if="!collapsedBookings[booking.id]"
                  v-for="(room, rIdx) in booking.rooms"
                  :key="`${booking.id}-room-${rIdx}`"
                  class="border-b border-slate-100 h-8 hover:bg-slate-50 transition-colors text-[11.5px]"
                >
                  <td class="py-1.5 px-2.5 font-bold text-slate-900">
                    {{ booking.id }}
                  </td>
                  <td class="py-1.5 px-2.5 text-slate-800 font-medium break-words whitespace-normal leading-tight">
                    {{ booking.bookingName }}
                  </td>
                  <td class="py-1.5 px-2 text-slate-600">
                    {{ room.status }}
                  </td>
                  <td class="py-1.5 px-2 text-slate-700 truncate font-normal">
                    {{ room.roomType }}
                  </td>
                  <td class="py-1.5 px-2 text-center font-bold text-slate-900">
                    {{ room.roomNumber }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.arrivalDate }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.departureDate }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.nights }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.adults }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600" :class="{ 'text-sky-600 font-bold': room.children > 0 }">
                    {{ room.children }}
                  </td>
                  <td class="py-1.5 px-2 text-right text-slate-800">
                    {{ formatMoney(room.price) }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.rateCode }}
                  </td>
                  <td class="py-1.5 px-2 text-right text-slate-900 font-medium">
                    {{ formatMoney(room.roomTotal) }}
                  </td>
                  <td class="py-1.5 px-2.5 text-slate-600 truncate" :title="room.specialRequest">
                    {{ room.specialRequest }}
                  </td>
                  <td class="py-1.5 px-2.5 text-slate-700 break-words whitespace-normal leading-tight">
                    {{ room.company }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 2: Departures Page ("Phòng đi") -->
      <div v-else-if="activeTab === 'departures'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1600px] table-fixed">
            <thead>
              <tr class="bg-[#f1f5f9] border-b border-slate-200 text-slate-800 font-bold select-none h-9 text-[11.5px]">
                <th class="py-2 px-2.5 w-[110px] sticky top-0 bg-[#f1f5f9] z-10">Mã đăng ký <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2.5 w-[220px] sticky top-0 bg-[#f1f5f9] z-10">Tên đăng ký <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-left w-[130px] sticky top-0 bg-[#f1f5f9] z-10">Tình trạng đăng ký</th>
                <th class="py-2 px-2 text-left w-[130px] sticky top-0 bg-[#f1f5f9] z-10">Loại phòng</th>
                <th class="py-2 px-2 text-center w-[75px] sticky top-0 bg-[#f1f5f9] z-10">Phòng <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-center w-[95px] sticky top-0 bg-[#f1f5f9] z-10">Ngày đến <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-center w-[95px] sticky top-0 bg-[#f1f5f9] z-10">Ngày đi <span class="text-[10px] text-slate-400">⇅</span></th>
                <th class="py-2 px-2 text-center w-[75px] sticky top-0 bg-[#f1f5f9] z-10">Đêm phòng</th>
                <th class="py-2 px-2 text-center w-[65px] sticky top-0 bg-[#f1f5f9] z-10">Người lớn</th>
                <th class="py-2 px-2 text-center w-[65px] sticky top-0 bg-[#f1f5f9] z-10">Trẻ em</th>
                <th class="py-2 px-2 text-right w-[100px] sticky top-0 bg-[#f1f5f9] z-10">Giá phòng</th>
                <th class="py-2 px-2 text-center w-[90px] sticky top-0 bg-[#f1f5f9] z-10">Mã giá phòng</th>
                <th class="py-2 px-2 text-right w-[105px] sticky top-0 bg-[#f1f5f9] z-10">Tổng DV</th>
                <th class="py-2 px-2 text-right w-[105px] sticky top-0 bg-[#f1f5f9] z-10">Tổng TT</th>
                <th class="py-2 px-2.5 text-left w-[180px] sticky top-0 bg-[#f1f5f9] z-10">Yêu cầu ĐB</th>
                <th class="py-2 px-2.5 text-left w-[170px] sticky top-0 bg-[#f1f5f9] z-10">Công ty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="departuresData.length === 0 && !isLoading">
                <td colspan="16" class="p-8 text-center text-slate-400 font-medium">
                  Không có danh sách phòng đi trong ngày này.
                </td>
              </tr>
              <template v-for="booking in departuresData" :key="booking.id">
                <!-- Group Header Banner Row (Exact layout from user screenshot) -->
                <tr class="border-b border-t border-slate-200 bg-[#edf5fc]">
                  <td colspan="16" class="py-2 px-3">
                    <div class="flex items-center justify-between gap-4 text-xs">
                      <!-- Left Info String -->
                      <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap">
                        <button
                          @click="toggleCollapse(booking.id)"
                          type="button"
                          class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px]"
                        >
                          {{ collapsedBookings[booking.id] ? '+' : '-' }}
                        </button>
                        
                        <div class="flex items-center gap-1.5 flex-wrap text-[11.5px] leading-tight">
                          <span class="font-normal text-slate-800">Booking <span class="font-bold">{{ booking.id }}</span></span>
                          <span class="font-black text-slate-900 uppercase">{{ booking.bookingName }}</span>
                          <span class="text-slate-700">{{ booking.arrivalDate }}~{{ booking.departureDate }} _ Room Night: {{ booking.roomNight }} _ Phòng: {{ booking.roomsCount }}</span>
                          <span v-if="booking.notes" class="text-slate-600 font-normal ml-1 border-l border-slate-300 pl-2">
                            {{ booking.notes }}
                          </span>
                        </div>
                      </div>

                      <!-- Right Financials -->
                      <div class="flex items-center gap-5 shrink-0 text-[11.5px] font-bold text-slate-800 whitespace-nowrap">
                        <span>Tiền dịch vụ : {{ formatMoney(booking.totalServices) }}</span>
                        <span>Tiền đã thanh toán : {{ formatMoney(booking.totalPayment) }}</span>
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- Child Room Rows -->
                <tr
                  v-if="!collapsedBookings[booking.id]"
                  v-for="(room, rIdx) in booking.rooms"
                  :key="`${booking.id}-room-${rIdx}`"
                  class="border-b border-slate-100 h-8 hover:bg-slate-50 transition-colors text-[11.5px]"
                >
                  <td class="py-1.5 px-2.5 font-bold text-slate-900">
                    {{ booking.id }}
                  </td>
                  <td class="py-1.5 px-2.5 text-slate-800 font-medium break-words whitespace-normal leading-tight">
                    {{ booking.bookingName }}
                  </td>
                  <td class="py-1.5 px-2 text-slate-600">
                    {{ room.status }}
                  </td>
                  <td class="py-1.5 px-2 text-slate-700 truncate font-normal">
                    {{ room.roomType }}
                  </td>
                  <td class="py-1.5 px-2 text-center font-bold text-slate-900">
                    {{ room.roomNumber }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.arrivalDate }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.departureDate }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.nights }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.adults }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600" :class="{ 'text-sky-600 font-bold': room.children > 0 }">
                    {{ room.children }}
                  </td>
                  <td class="py-1.5 px-2 text-right text-slate-800">
                    {{ formatMoney(room.price) }}
                  </td>
                  <td class="py-1.5 px-2 text-center text-slate-600">
                    {{ room.rateCode }}
                  </td>
                  <td class="py-1.5 px-2 text-right text-slate-900 font-medium">
                    {{ formatMoney(room.totalServices) }}
                  </td>
                  <td class="py-1.5 px-2 text-right text-emerald-600 font-bold">
                    {{ formatMoney(room.totalPayment) }}
                  </td>
                  <td class="py-1.5 px-2.5 text-slate-600 truncate" :title="room.specialRequest">
                    {{ room.specialRequest }}
                  </td>
                  <td class="py-1.5 px-2.5 text-slate-700 break-words whitespace-normal leading-tight">
                    {{ room.company }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 3: Pending Registration / Confirmation Page -->
      <div v-else-if="activeTab === 'pending'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1600px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">STT</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã ĐK</th>
                <th class="p-2 border-r border-slate-200 w-[200px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tình trạng đăng ký</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công ty</th>
                <th class="p-2 border-r border-slate-200 text-center w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày xác nhận</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[70px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đêm</th>
                <th class="p-2 border-r border-slate-200 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Loại phòng</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đặt cọc</th>
                <th class="p-2 border-r border-slate-200 w-[150px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Liên hệ</th>
                <th class="p-2 w-[250px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ghi chú (Sale)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="pendingConfirmationData.length === 0 && !isLoading">
                <td colspan="13" class="p-8 text-center text-slate-400 font-medium">
                  Không có đăng ký nào chờ xác nhận trong giai đoạn này.
                </td>
              </tr>
              <template v-for="dateGroup in sortedPendingDates" :key="dateGroup">
                <!-- Group Header Row (Date group) -->
                <tr class="group border-b border-slate-200 h-9 bg-slate-50/80">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/40 transition-colors">
                    <button
                      @click="toggleGroupCollapse('pending-' + dateGroup)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('pending-' + dateGroup) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="12" class="p-2.5 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/40 transition-colors">
                    {{ dateGroup }}
                  </td>
                </tr>

                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('pending-' + dateGroup)"
                  v-for="(item, idx) in pendingGroups[dateGroup]"
                  :key="item.id"
                  class="group border-b border-slate-200 h-8 hover:bg-[#bdecfe]/30 transition-colors"
                >
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600">
                    {{ idx + 1 }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                    {{ item.id }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-800 font-semibold break-words whitespace-normal leading-tight">
                    {{ item.bookingName }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-medium">
                    {{ item.status }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 break-words whitespace-normal leading-tight font-semibold">
                    {{ item.company }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-medium">
                    {{ item.confirmDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                    {{ item.arrivalDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                    {{ item.departureDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                    {{ item.nights }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-700 font-bold truncate" :title="item.roomTypes">
                    {{ item.roomTypes }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-right font-semibold text-slate-700">
                    {{ formatMoney(item.deposit) }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 truncate" :title="item.contact">
                    {{ item.contact }}
                  </td>
                  <td class="p-1">
                    <div class="flex items-center gap-1 w-full">
                      <input
                        type="text"
                        v-model="item.notes"
                        :disabled="!editingNotes[item.id]"
                        placeholder="Ghi chú xác nhận"
                        class="flex-1 min-w-0 border rounded px-1.5 py-0.5 text-[11px] transition-all h-7 shadow-sm"
                        :class="[editingNotes[item.id] ? 'bg-white border-sky-400 text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500' : 'bg-slate-50 border-slate-200 text-slate-600 cursor-not-allowed select-none']"
                      />
                      
                      <!-- Save Button -->
                      <button
                        @click="saveNote(item)"
                        :disabled="!editingNotes[item.id]"
                        type="button"
                        class="w-7 h-7 rounded flex items-center justify-center border-none transition-all shadow-sm shrink-0"
                        :class="[editingNotes[item.id] ? 'bg-[#7aa0b5] hover:bg-[#5b859e] text-white cursor-pointer' : 'bg-[#94a3b8] text-slate-100 cursor-not-allowed opacity-60']"
                        title="Lưu ghi chú"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v5h8" />
                        </svg>
                      </button>
                      
                      <!-- Edit Button -->
                      <button
                        @click="toggleEditNote(item.id)"
                        type="button"
                        class="w-7 h-7 rounded flex items-center justify-center border-none transition-all shadow-sm shrink-0 bg-[#7dd3fc] hover:bg-[#38bdf8] text-[#0369a1] cursor-pointer"
                        title="Chỉnh sửa ghi chú"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 4: Shuttle Service Page (Đón tiễn khách) -->
      <div v-else-if="activeTab === 'shuttle'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1300px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">STT</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã ĐK</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[90px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Khách hàng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Loại đưa đón</th>
                <th class="p-2 border-r border-slate-200 text-center w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Chuyến bay</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giờ</th>
                <th class="p-2 border-r border-slate-200 text-center w-[60px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Pax</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phương tiện</th>
                <th class="p-2 border-r border-slate-200 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ghi chú</th>
                <th class="p-2 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="shuttleData.length === 0 && !isLoading">
                <td colspan="12" class="p-8 text-center text-slate-400 font-medium">
                  Không có thông tin đón tiễn khách trong ngày này.
                </td>
              </tr>
              <template v-for="dateGroup in sortedShuttleDates" :key="dateGroup">
                <!-- Group Header Row -->
                <tr class="group border-b border-slate-200 h-9 bg-slate-50/80">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/40 transition-colors">
                    <button
                      @click="toggleGroupCollapse('shuttle-' + dateGroup)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('shuttle-' + dateGroup) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="11" class="p-2 border-r border-slate-200 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/40 transition-colors">
                    {{ dateGroup }}
                  </td>
                </tr>

                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('shuttle-' + dateGroup)"
                  v-for="(item, sIdx) in shuttleGroups[dateGroup]"
                  :key="item.id + '-' + sIdx"
                  class="group border-b border-slate-200 h-8 hover:bg-[#bdecfe]/30 transition-colors"
                >
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600">
                    {{ sIdx + 1 }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                    {{ item.id }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-800 font-semibold break-words whitespace-normal leading-tight">
                    {{ item.bookingName }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-black text-slate-700">
                    {{ item.roomNumber }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-700 font-medium">
                    {{ item.guestName }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-sky-600">
                    {{ item.shuttleType }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-semibold text-slate-700">
                    {{ item.flightCode }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold">
                    {{ item.flightTime }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-700 font-medium">
                    {{ item.pax }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 truncate">
                    {{ item.vehicle }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 truncate" :title="item.notes">
                    {{ item.notes }}
                  </td>
                  <td class="p-2 text-center text-slate-600 font-medium">
                    {{ item.status }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 5: Noshow Rooms Page (Phòng không đến) -->
      <div v-else-if="activeTab === 'noshow'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1500px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">STT</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã BK</th>
                <th class="p-2 border-r border-slate-200 w-[200px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên nhóm</th>
                <th class="p-2 border-r border-slate-200 w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Loại phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[70px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Số Đêm</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày Vắng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[70px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giờ</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tổng tiền</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Người Dùng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[60px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ca</th>
                <th class="p-2 border-r border-slate-200 w-[200px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Lý Do</th>
                <th class="p-2 w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công ty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredNoshowData.length === 0 && !isLoading">
                <td colspan="14" class="p-8 text-center text-slate-400 font-medium">
                  Không có dữ liệu phòng không đến.
                </td>
              </tr>
              <tr
                v-for="(item, nIdx) in filteredNoshowData"
                :key="item.id + '-' + nIdx"
                class="group border-b border-slate-200 h-8 hover:bg-[#bdecfe]/30 transition-colors"
              >
                <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600">
                  {{ nIdx + 1 }}
                </td>
                <td
                  class="p-2 border-r border-slate-200 text-center font-black text-slate-800"
                  :class="[item.roomNumber ? 'bg-[#c9eeff]/60' : 'bg-white']"
                >
                  {{ item.roomNumber }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                  {{ item.id }}
                </td>
                <td class="p-2 border-r border-slate-200 text-slate-800 font-semibold break-words whitespace-normal leading-tight">
                  {{ item.bookingName }}
                </td>
                <td class="p-2 border-r border-slate-200 text-slate-700 truncate">
                  {{ item.roomType }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                  {{ item.arrivalDate }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold">
                  {{ item.nights }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-medium">
                  {{ item.noshowDate }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                  {{ item.noshowTime }}
                </td>
                <td class="p-2 border-r border-slate-200 text-right text-slate-800 font-bold">
                  {{ formatMoney(item.totalAmount) }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                  {{ item.username }}
                </td>
                <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                  {{ item.shift }}
                </td>
                <td class="p-2 border-r border-slate-200 text-slate-600 truncate" :title="item.reason">
                  {{ item.reason }}
                </td>
                <td class="p-2 text-slate-600 truncate font-semibold">
                  {{ item.company }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 6: Guest Birthdays Page (Sinh nhật khách) -->
      <div v-else-if="activeTab === 'birthdays'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1700px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">STT</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã ĐK</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên khách</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Sinh nhật</th>
                <th class="p-2 border-r border-slate-200 text-center w-[60px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tuổi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Loại giấy tờ</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Số giấy tờ</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Quốc tịch</th>
                <th class="p-2 border-r border-slate-200 w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Điện thoại</th>
                <th class="p-2 border-r border-slate-200 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Email</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đi</th>
                <th class="p-2 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công ty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="birthdaysData.length === 0 && !isLoading">
                <td colspan="14" class="p-8 text-center text-slate-400 font-medium">
                  Không có khách sinh nhật trong giai đoạn này.
                </td>
              </tr>
              <template v-for="dateGroup in sortedBirthdayDates" :key="dateGroup">
                <!-- Group Header Row -->
                <tr class="group border-b border-slate-200 h-9 bg-slate-50/80">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/40 transition-colors">
                    <button
                      @click="toggleGroupCollapse('birthdays-' + dateGroup)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('birthdays-' + dateGroup) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="13" class="p-2 border-r border-slate-200 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/40 transition-colors">
                    {{ dateGroup }}
                  </td>
                </tr>

                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('birthdays-' + dateGroup)"
                  v-for="(item, bIdx) in birthdayGroups[dateGroup]"
                  :key="item.bookingCode + '-' + bIdx"
                  class="group border-b border-slate-200 h-8 hover:bg-[#bdecfe]/30 transition-colors"
                >
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600">
                    {{ bIdx + 1 }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700">
                    {{ item.bookingCode }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-black text-slate-800"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]/60' : 'bg-white']"
                  >
                    {{ item.roomNumber }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-800 font-semibold break-words whitespace-normal leading-tight">
                    {{ item.guestName }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-semibold">
                    {{ item.birthday }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-700 font-bold">
                    {{ item.age }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                    {{ item.idType }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-mono">
                    {{ item.idNumber }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                    {{ item.nationality }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 truncate">
                    {{ item.phone }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 truncate">
                    {{ item.email }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                    {{ item.arrivalDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600">
                    {{ item.departureDate }}
                  </td>
                  <td class="p-2 text-slate-600 break-words whitespace-normal leading-tight font-semibold">
                    {{ item.company }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Sticky footer info bar -->
    <div class="px-4 py-2 border-t border-slate-200 bg-slate-50/50 flex items-center gap-6 shrink-0 text-xs font-bold text-slate-700 select-none">
      <div class="flex items-center gap-1.5">
        <span class="text-slate-400 uppercase tracking-wide text-[10px]">{{ footerStats.label1 }}</span>
        <span class="text-slate-800 text-sm font-black">{{ footerStats.val1 }}</span>
      </div>
      <div class="flex items-center gap-1.5 border-l border-slate-200 pl-6">
        <span class="text-slate-400 uppercase tracking-wide text-[10px]">{{ footerStats.label2 }}</span>
        <span class="text-slate-800 text-sm font-black">{{ footerStats.val2 }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
