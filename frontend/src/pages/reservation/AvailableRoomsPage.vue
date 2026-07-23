<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { fetchAvailabilityGrid, fetchRegistrationStatuses } from '@/services/availability-service'
import { fetchSystemDate } from '@/services/booking-service'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import DateRangePicker from '@/components/DateRangePicker.vue'

const uiStore = useUiStore()

const isLoading = ref(true)
const systemDate = ref('')
const startDateYMD = ref('')
const endDateYMD = ref('')
const dates = ref([])
const roomClasses = ref([])
const gridData = ref({})
const statistics = ref({})
const totals = ref({ grand_total: 0, grand_max_rooms: 0, grand_max_extra_beds: 0 })

// Dropdown statuses
const registrationStatuses = ref([])
const isDropdownOpen = ref(false)
const dropdownRef = ref(null)

// Default selected statuses from localStorage or initial defaults
const savedStatuses = localStorage.getItem('pms_availability_selected_statuses')
let initialStatuses = ['AV', 'OCC', 'ALM', 'OOO', 'OOS', 'EB', 'SOFAB']
if (savedStatuses) {
  try {
    const parsed = JSON.parse(savedStatuses)
    if (parsed.includes('Guaranteed') || parsed.includes('None Guaranteed') || parsed.includes('Allotment')) {
      localStorage.removeItem('pms_availability_selected_statuses')
    } else {
      initialStatuses = parsed
    }
  } catch (e) {
    localStorage.removeItem('pms_availability_selected_statuses')
  }
}

const selectedStatuses = ref(initialStatuses)

// Watch changes to selectedStatuses and save to localStorage
watch(selectedStatuses, (newVal) => {
  localStorage.setItem('pms_availability_selected_statuses', JSON.stringify(newVal))
}, { deep: true })

// Watch changes to date selections and save to localStorage
watch([startDateYMD, endDateYMD], ([newStart, newEnd]) => {
  if (newStart && newEnd) {
    localStorage.setItem('pms_availability_start_date', newStart)
    localStorage.setItem('pms_availability_end_date', newEnd)
  }
})

// Generate columns representation from backend dates
const days = computed(() => {
  return dates.value.map(dStr => {
    const current = new Date(dStr)
    const dayOfWeek = current.getDay()
    let dow = ''
    if (dayOfWeek === 0) dow = 'CN'
    else dow = `T${dayOfWeek + 1}`

    const dateParts = dStr.split('-')
    const formattedDate = `${dateParts[2]}/${dateParts[1]}`

    return {
      dateStr: formattedDate,
      dow,
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isSunday: dayOfWeek === 0,
      fullDateStr: dStr
    }
  })
})

// Columns displayed inside the room class grid for each day
const activeSubColumns = computed(() => {
  const order = ['AV', 'OCC', 'ALM', 'OOO', 'OOS', 'EB', 'SOFAB']
  const cols = order.filter(code => selectedStatuses.value.includes(code))
  if (cols.length === 0) cols.push('AV')
  return cols
})

// Build dynamic statuses options (only showing active ones with is_availability=true)
const allStatusesList = computed(() => {
  return [
    { code: 'AV', label: 'AV', color: '#0284c7', activeClasses: 'bg-sky-50 border-sky-300 text-sky-700' },
    { code: 'OCC', label: 'OCC', color: '#3b82f6', activeClasses: 'bg-indigo-50 border-indigo-300 text-indigo-700' },
    { code: 'ALM', label: 'ALM', color: '#f59e0b', activeClasses: 'bg-amber-50 border-amber-300 text-amber-700' },
    { code: 'OOO', label: 'OOO', color: '#10b981', activeClasses: 'bg-emerald-50 border-emerald-300 text-emerald-700' },
    { code: 'OOS', label: 'OOS', color: '#6b7280', activeClasses: 'bg-slate-100 border-slate-300 text-slate-700' },
    { code: 'EB', label: 'EB', color: '#ec4899', activeClasses: 'bg-pink-50 border-pink-300 text-pink-700' },
    { code: 'SOFAB', label: 'SOFAB', color: '#8b5cf6', activeClasses: 'bg-violet-50 border-violet-300 text-violet-700' }
  ]
})

function toggleStatus(code) {
  const index = selectedStatuses.value.indexOf(code)
  if (index > -1) {
    if (selectedStatuses.value.length > 1) {
      selectedStatuses.value.splice(index, 1)
    }
  } else {
    const order = ['AV', 'OCC', 'ALM', 'OOO', 'OOS', 'EB', 'SOFAB']
    const newList = [...selectedStatuses.value, code]
    newList.sort((a, b) => order.indexOf(a) - order.indexOf(b))
    selectedStatuses.value = newList
  }
}

function getAvailableCount(classCode, dateStr) {
  return gridData.value[classCode]?.[dateStr]?.av ?? 0
}

function getSubColValue(rcCode, dateStr, subCol) {
  const data = gridData.value[rcCode]?.[dateStr]
  if (!data) return 0
  
  if (subCol === 'AV') return data.av
  if (subCol === 'OOO') return data.ooo
  if (subCol === 'OOS') return data.oos
  if (subCol === 'OCC') return data.occ
  if (subCol === 'EB') return data.eb
  if (subCol === 'ALM') return data.alm
  if (subCol === 'SOFAB') return data.sofab
  return 0
}

function getSumValue(subCol, dateStr) {
  if (subCol === 'AV') return totalAvailableRow.value[dateStr] ?? 0
  if (subCol === 'OOO') return statistics.value[dateStr]?.ooo ?? 0
  if (subCol === 'OOS') return statistics.value[dateStr]?.oos ?? 0
  if (subCol === 'OCC') return statistics.value[dateStr]?.total_occupied ?? 0
  if (subCol === 'EB') return statistics.value[dateStr]?.extra_beds ?? 0
  if (subCol === 'ALM') return statistics.value[dateStr]?.allotment ?? 0
  if (subCol === 'SOFAB') return 0
  return 0
}

function getCellClass(subCol, val, isWeekend) {
  let base = 'p-1.5 border-r border-slate-200 text-center text-[12px] hover:bg-slate-200 transition-colors cursor-pointer '
  if (isWeekend) {
    base += 'bg-[#8cc4fb] hover:bg-[#b5defc] '
  }
  if (val <= 0) {
    base += 'text-red-500 font-light'
  } else {
    base += 'text-gray-900 font-light'
  }
  return base
}

function getCellTooltip(rcCode, dateStr, subCol) {
  const data = gridData.value[rcCode]?.[dateStr]
  if (!data) return ''
  
  let rooms = []
  let statusName = ''
  
  if (subCol === 'AV') {
    rooms = data.av_rooms || []
    statusName = 'Trống (AV)'
  } else if (subCol === 'OOO') {
    rooms = data.ooo_rooms || []
    statusName = 'Khóa OOO'
  } else if (subCol === 'OOS') {
    rooms = data.oos_rooms || []
    statusName = 'Khóa OOS'
  } else if (subCol === 'OCC') {
    rooms = data.occ_rooms || []
    statusName = 'Có khách (OCC)'
  } else {
    return ''
  }
  
  if (rooms.length === 0) return `Không có phòng nào`
  
  return `Danh sách phòng ${statusName} (${rooms.length} phòng):\n${rooms.join(', ')}`
}

function getStatTooltip(subCol, dateStr) {
  const stat = statistics.value[dateStr]
  if (!stat) return ''
  
  let rooms = []
  let statusName = ''
  
  if (subCol === 'AV') {
    rooms = stat.av_rooms || []
    statusName = 'Trống (AV)'
  } else if (subCol === 'OOO') {
    rooms = stat.ooo_rooms || []
    statusName = 'Khóa OOO'
  } else if (subCol === 'OOS') {
    rooms = stat.oos_rooms || []
    statusName = 'Khóa OOS'
  } else if (subCol === 'OCC') {
    rooms = stat.occ_rooms || []
    statusName = 'Có khách (OCC)'
  } else {
    return ''
  }
  
  if (rooms.length === 0) return `Không có phòng nào`
  
  return `Thống kê phòng ${statusName} (${rooms.length} phòng):\n${rooms.join(', ')}`
}

// Total of available rooms row sum
const totalAvailableRow = computed(() => {
  const rowSums = {}
  dates.value.forEach(dStr => {
    let sum = 0
    roomClasses.value.forEach(rc => {
      sum += getAvailableCount(rc.code, dStr)
    })
    rowSums[dStr] = sum
  })
  return rowSums
})

// Fetch grid and stats data
async function loadAvailability(start = null, end = null) {
  isLoading.value = true
  try {
    const response = await fetchAvailabilityGrid(start, end)
    if (response.data && response.data.success) {
      const data = response.data
      dates.value = data.dates
      roomClasses.value = data.room_classes
      gridData.value = data.grid
      statistics.value = data.statistics
      totals.value = data.totals

      startDateYMD.value = data.start_date
      endDateYMD.value = data.end_date
    }
  } catch (error) {
    console.error('Error fetching availability:', error)
    uiStore.showToast('Không thể tải dữ liệu phòng trống từ server.', 'error')
  } finally {
    isLoading.value = false
  }
}

// Filter button / manual xem click handler
const handleFilterSubmit = () => {
  if (startDateYMD.value && endDateYMD.value) {
    loadAvailability(startDateYMD.value, endDateYMD.value)
  } else {
    uiStore.showToast('Vui lòng chọn đầy đủ ngày bắt đầu và Ngày mở khóa.', 'warning')
  }
}

const toggleDropdown = (event) => {
  event.stopPropagation()
  isDropdownOpen.value = !isDropdownOpen.value
}

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isDropdownOpen.value = false
  }
}

onMounted(async () => {
  window.addEventListener('click', handleClickOutside)
  
  // 1. Fetch system date first
  try {
    const sysRes = await fetchSystemDate()
    if (sysRes.data && sysRes.data.success && sysRes.data.data?.system_date) {
      systemDate.value = sysRes.data.data.system_date
    }
  } catch (error) {
    console.error('Error loading system date:', error)
  }

  // 2. Fetch statuses for dropdown filters
  try {
    const res = await fetchRegistrationStatuses()
    if (res.data && res.data.success) {
      registrationStatuses.value = res.data.data
      
      // If there is NO saved choice in localStorage, we populate selectedStatuses with all of them
      if (!localStorage.getItem('pms_availability_selected_statuses')) {
        selectedStatuses.value = ['AV', 'OCC', 'ALM', 'OOO', 'OOS', 'EB', 'SOFAB']
      }
    }
  } catch (error) {
    console.error('Error loading registration statuses:', error)
  }

  // 3. Load availability data
  const savedStart = localStorage.getItem('pms_availability_start_date')
  const savedEnd = localStorage.getItem('pms_availability_end_date')
  if (savedStart && savedEnd) {
    await loadAvailability(savedStart, savedEnd)
  } else {
    await loadAvailability(systemDate.value || null)
  }
})

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside)
})

function showExportToast() {
  uiStore.showToast('Xuất báo cáo Excel thành công!', 'success')
}


</script>

<template>
  <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4 overflow-hidden h-full">
    <!-- Top Filter Controls -->
    <div class="flex items-center justify-between shrink-0">
      <!-- Date pickers & Action buttons -->
      <div class="flex items-center gap-3">
        <!-- Date Range Picker -->
        <DateRangePicker 
          v-model:startDate="startDateYMD" 
          v-model:endDate="endDateYMD" 
          :systemDate="systemDate"
          @change="handleFilterSubmit"
        />

        <!-- Button Xuất excel -->
        <button 
          @click="showExportToast"
          class="flex items-center gap-1.5 px-4 py-1.5 bg-sky-100 hover:bg-sky-200 text-sky-700 border border-sky-200 rounded-lg text-xs font-semibold cursor-pointer transition-all shadow-xs"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Xuất excel
        </button>
      </div>

      <!-- Status Filter Dropdown & Horizontal Badges -->
      <div class="flex items-center gap-2" ref="dropdownRef">
        <!-- Horizontal Badges (Click to toggle) -->
        <div class="flex items-center gap-1">
          <button 
            v-for="status in allStatusesList" 
            :key="status.code"
            type="button"
            @click="toggleStatus(status.code)"
            class="px-2.5 py-1 text-[11px] font-semibold rounded border cursor-pointer transition-all shadow-sm flex items-center h-[28px]"
            :class="[
              selectedStatuses.includes(status.code) 
                ? status.activeClasses
                : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'
            ]"
          >
            {{ status.label }}
          </button>
        </div>

        <!-- Filter Settings Button -->
        <div class="relative">
          <button 
            @click="toggleDropdown"
            class="flex items-center justify-center p-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg cursor-pointer transition-all shadow-xs text-gray-900 h-[28px] w-[28px]"
          >
            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
            </svg>
          </button>

          <!-- Dropdown Popup -->
          <div 
            v-if="isDropdownOpen" 
            class="absolute right-0 mt-1.5 w-44 bg-white border border-slate-200 rounded-lg shadow-lg z-50 py-1 max-h-64 overflow-y-auto"
          >
            <label 
              v-for="status in allStatusesList" 
              :key="status.code"
              class="flex items-center px-3 py-1.5 hover:bg-slate-50 cursor-pointer text-xs font-semibold text-gray-900 select-none"
            >
              <input 
                type="checkbox" 
                :value="status.code" 
                v-model="selectedStatuses"
                class="hidden"
              />
              <!-- Custom checkbox with status color -->
              <span 
                class="w-4.5 h-4.5 rounded border mr-2 flex items-center justify-center transition-all shrink-0"
                :style="{
                  backgroundColor: selectedStatuses.includes(status.code) ? status.color : '#ffffff',
                  borderColor: selectedStatuses.includes(status.code) ? 'transparent' : '#cbd5e1'
                }"
                :class="[
                  selectedStatuses.includes(status.code) 
                    ? 'text-white' 
                    : 'bg-white'
                ]"
              >
                <svg v-if="selectedStatuses.includes(status.code)" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </span>
              {{ status.label }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Grid Matrix Container -->
    <div class="flex-1 overflow-auto border border-slate-200 rounded-lg relative">
      <!-- Loading Overlay -->
      <LoadingOverlay :show="isLoading" />

      <table class="text-slate-900 text-left border-collapse table-fixed w-max min-w-max">
        <!-- Main Column Width Definitions (Narrowed to fit more days at once) -->
        <colgroup>
          <col class="w-[80px] sticky left-0 z-20" />
          <col class="w-[170px] sticky left-[80px] z-20" />
          <col class="w-[50px] sticky left-[250px] z-20" />
          <col class="w-[65px] sticky left-[300px] z-20" />
          <template v-for="day in days" :key="day.fullDateStr">
            <col v-for="subCol in activeSubColumns" :key="subCol" class="w-[45px]" />
          </template>
        </colgroup>

        <!-- Table Headers -->
        <thead>
          <!-- First Row: Weekdays -->
          <tr class="bg-slate-200 border-b border-slate-300 text-gray-900 font-semibold h-8 text-[10px]">
            <th rowspan="2" class="p-2 border-r border-slate-300 text-left pl-3 sticky left-0 z-30 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] text-[12px] font-semibold">Mã Loại</th>
            <th rowspan="2" class="p-2 border-r border-slate-300 sticky left-[80px] z-30 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] text-[12px] font-semibold">Loại phòng</th>
            <th rowspan="2" class="p-2 border-r border-slate-300 text-center sticky left-[250px] z-30 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] text-[12px] font-semibold">Tổng</th>
            <th rowspan="2" class="p-2 border-r border-slate-300 text-center sticky left-[300px] z-30 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] leading-tight text-[12px] font-semibold">SL Phòng Tối Đa</th>
            
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              :colspan="activeSubColumns.length"
              class="p-1 border-r border-slate-200 text-center text-[10px] font-semibold"
              :class="[day.isWeekend ? 'bg-[#8cc4fb] text-gray-900' : 'bg-slate-200 text-gray-900']"
            >
              {{ day.dow }}<br/>{{ day.dateStr }}
            </th>
          </tr>

          <!-- Second Row: Sub-Columns (AV, OOO, OOS...) -->
          <tr class="bg-slate-200 border-b border-slate-200 text-gray-900 font-semibold h-8 text-[10px]">
            <template v-for="day in days" :key="day.fullDateStr">
              <th 
                v-for="subCol in activeSubColumns" 
                :key="subCol"
                class="p-1 border-r border-slate-200 text-center text-[10px] font-semibold"
                :class="[day.isWeekend ? 'bg-[#8cc4fb] text-gray-900' : 'bg-slate-200 text-gray-900']"
              >
                {{ subCol }}
              </th>
            </template>
          </tr>
        </thead>

        <!-- Matrix Body -->
        <tbody>
          <!-- Room Classes Rows -->
          <tr 
            v-for="rc in roomClasses" 
            :key="rc.code" 
            class="border-b border-slate-200 h-9"
          >
            <!-- Room Type Identifiers (Sticky on Left) -->
            <td class="p-2 border-r border-slate-200 text-left pl-3 font-semibold text-gray-900 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-[12px]">
              <div class="flex items-center gap-1 justify-start">
                <span class="text-gray-450 font-extrabold mr-0.5">•</span>
                {{ rc.code }}
              </div>
            </td>
            <td class="p-2 border-r border-slate-200 font-semibold text-gray-900 sticky left-[80px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] truncate text-[12px]">
              {{ rc.name }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center font-light text-gray-900 sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-[12px]">
              {{ rc.total }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center font-light text-gray-900 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-[12px]">
              {{ rc.max_rooms ?? 0 }}
            </td>

            <!-- Dynamic grid cells based on selected filters -->
            <template v-for="day in days" :key="day.fullDateStr">
              <td 
                v-for="subCol in activeSubColumns" 
                :key="subCol"
                :class="getCellClass(subCol, getSubColValue(rc.code, day.fullDateStr, subCol), day.isWeekend)"
                :title="getCellTooltip(rc.code, day.fullDateStr, subCol)"
              >
                {{ getSubColValue(rc.code, day.fullDateStr, subCol) }}
              </td>
            </template>
          </tr>

          <!-- TỔNG Row (Sum totals) -->
          <tr class="bg-slate-200 border-b border-slate-300 h-9 text-gray-900 text-[12px]">
            <td class="p-2 border-r border-slate-300 text-center sticky left-0 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] font-semibold">TỔNG</td>
            <td class="p-2 border-r border-slate-300 sticky left-[80px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]"></td>
            <td class="p-2 border-r border-slate-300 text-center sticky left-[250px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] font-light">
              {{ totals.grand_total }}
            </td>
            <td class="p-2 border-r border-slate-300 text-center sticky left-[300px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1] font-light">
              {{ totals.grand_max_rooms ?? 0 }}
            </td>

            <template v-for="day in days" :key="day.fullDateStr">
              <td 
                v-for="subCol in activeSubColumns"
                :key="subCol"
                class="p-2 border-r border-slate-300 text-center text-[12px] font-light text-gray-900"
                :class="[
                  day.isWeekend ? 'bg-[#8cc4fb]' : '',
                  getSumValue(subCol, day.fullDateStr) === 0 ? 'text-gray-400 font-light' : ''
                ]"
                :title="getStatTooltip(subCol, day.fullDateStr)"
              >
                {{ getSumValue(subCol, day.fullDateStr) }}
              </td>
            </template>
          </tr>

          <!-- THỐNG KÊ Title Header Row -->
          <tr class="bg-slate-200 border-b border-slate-200 text-gray-900 font-semibold h-8 text-center uppercase tracking-wide text-[12px]">
            <td colspan="4" class="p-2 sticky left-0 bg-slate-200 shadow-[inset_-1px_0_0_#e2e8f0] text-left pl-4 font-semibold">THỐNG KÊ</td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-1 border-r border-slate-200 bg-slate-200"
            ></td>
          </tr>

          <!-- 1. Tổng (Always Visible) -->
          <tr class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Tổng</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-light">
              {{ (totals.grand_total * dates.length) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50']"
            >
              {{ statistics[day.fullDateStr]?.total_rooms ?? 0 }}
            </td>
          </tr>

          <!-- 2. OOO -->
          <tr v-if="selectedStatuses.includes('OOO')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">OOO</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.ooo ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.ooo ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
              :title="getStatTooltip('OOO', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.ooo ?? 0 }}
            </td>
          </tr>

          <!-- 3. OOS -->
          <tr v-if="selectedStatuses.includes('OOS')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">OOS</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.oos ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.oos ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
              :title="getStatTooltip('OOS', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.oos ?? 0 }}
            </td>
          </tr>

          <!-- 4. Tổng số phòng có thể bán (Always Visible) -->
          <tr class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 pl-4 font-semibold">Tổng số phòng có thể bán</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.sellable ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-gray-900 font-light"
              :class="[day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50']"
            >
              {{ statistics[day.fullDateStr]?.sellable ?? 0 }}
            </td>
          </tr>

          <!-- 5. Series (Always Visible) -->
          <tr class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Series</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.series ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.series ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.series ?? 0 }}
            </td>
          </tr>

          <!-- 6. Allotment (Always Visible) -->
          <tr class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Allotment</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.allotment ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.allotment ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.allotment ?? 0 }}
            </td>
          </tr>

          <!-- 7. Đặt phòng đảm bảo -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-8 text-amber-700 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Đặt phòng đảm bảo</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-amber-700 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.bk_guaranteed ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-amber-700 font-light"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.bk_guaranteed ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
              :title="getStatTooltip('OCC', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.bk_guaranteed ?? 0 }}
            </td>
          </tr>

          <!-- 8. Đặt phòng không đảm bảo -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-8 text-red-500 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Đặt phòng không đảm bảo</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-red-500 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.bk_nonguaranteed ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-red-500 font-light"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.bk_nonguaranteed ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
              :title="getStatTooltip('OCC', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.bk_nonguaranteed ?? 0 }}
            </td>
          </tr>

          <!-- 9. Tổng số phòng chiếm dụng -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-10 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-red-600 pl-4 font-semibold">Tổng số phòng chiếm dụng</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-red-600 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.total_occupied ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-1 border-r border-slate-200 text-center leading-tight text-red-600 text-[12px] font-light"
              :class="[day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50']"
              :title="getStatTooltip('OCC', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.total_occupied ?? 0 }}<br/>
              <span class="text-[9px] font-medium text-amber-700">({{ statistics[day.fullDateStr]?.occupied_pct ?? 0 }}%)</span>
            </td>
          </tr>

          <!-- 10. Phòng trống -->
          <tr v-if="selectedStatuses.includes('AV')" class="group border-b border-slate-200 h-8 text-red-500 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng trống</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-red-500 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.av ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-red-500 font-light"
              :class="[day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50']"
              :title="getStatTooltip('AV', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.av ?? 0 }}
            </td>
          </tr>

          <!-- 11. Phòng nội bộ -->
          <tr v-if="selectedStatuses.includes('AV')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng nội bộ</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.internal_rooms ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.internal_rooms ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.internal_rooms ?? 0 }}
            </td>
          </tr>

          <!-- 12. Phòng miễn phí -->
          <tr v-if="selectedStatuses.includes('AV')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng miễn phí</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.free_rooms ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.free_rooms ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.free_rooms ?? 0 }}
            </td>
          </tr>

          <!-- 13. Tổng khách (Always Visible) -->
          <tr class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Tổng khách</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.total_guests ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.total_guests ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.total_guests ?? 0 }}
            </td>
          </tr>

          <!-- 14. Phòng đến (Room/Pax) -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng đến (Room/Pax)</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.arrivals_rooms ?? 0), 0) }}/{{ dates.reduce((sum, d) => sum + (statistics[d]?.arrivals_pax ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.arrivals_rooms ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.arrivals_rooms ?? 0 }}/{{ statistics[day.fullDateStr]?.arrivals_pax ?? 0 }}
            </td>
          </tr>

          <!-- 15. Phòng đang ở -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng đang ở</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.inhouse ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.inhouse ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
              :title="getStatTooltip('OCC', day.fullDateStr)"
            >
              {{ statistics[day.fullDateStr]?.inhouse ?? 0 }}
            </td>
          </tr>

          <!-- 16. Thêm giường -->
          <tr v-if="selectedStatuses.includes('EB')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Thêm giường</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.extra_beds ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.extra_beds ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.extra_beds ?? 0 }}
            </td>
          </tr>

          <!-- 17. Phòng hủy -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng hủy</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.cancellations ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.cancellations ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.cancellations ?? 0 }}
            </td>
          </tr>

          <!-- 18. Phòng noshow -->
          <tr v-if="selectedStatuses.includes('OCC')" class="group border-b border-slate-200 h-8 text-gray-900 hover:bg-slate-50 text-[12px] font-normal">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] pl-4 font-semibold">Phòng noshow</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] text-gray-900 font-light">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.noshow ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white group-hover:bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-light text-gray-900"
              :class="[
                day.isWeekend ? 'bg-[#8cc4fb] group-hover:bg-[#72b5f7]' : 'group-hover:bg-slate-50',
                (statistics[day.fullDateStr]?.noshow ?? 0) === 0 ? 'text-gray-400 font-light' : ''
              ]"
            >
              {{ statistics[day.fullDateStr]?.noshow ?? 0 }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
