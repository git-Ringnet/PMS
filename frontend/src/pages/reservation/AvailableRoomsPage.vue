<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { fetchAvailabilityGrid, fetchRegistrationStatuses } from '@/services/availability-service'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const uiStore = useUiStore()

const isLoading = ref(true)
const startDateYMD = ref('')
const endDateYMD = ref('')
const dates = ref([])
const roomClasses = ref([])
const gridData = ref({})
const statistics = ref({})
const totals = ref({ grand_total: 0, grand_max_extra_beds: 0 })

// Dropdown statuses
const registrationStatuses = ref([])
const isDropdownOpen = ref(false)
const dropdownRef = ref(null)

// Default selected statuses from localStorage or initial defaults
const savedStatuses = localStorage.getItem('pms_availability_selected_statuses')
const defaultStatuses = savedStatuses 
  ? JSON.parse(savedStatuses) 
  : ['AV', 'OOO', 'OOS', 'EB', 'SOFAB']

const selectedStatuses = ref(defaultStatuses)

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
  const cols = []
  if (selectedStatuses.value.includes('AV')) cols.push('AV')
  
  // Show OCC sub-column if Guaranteed or None Guaranteed are checked
  const hasOcc = selectedStatuses.value.includes('Guaranteed') || 
                 selectedStatuses.value.includes('None Guaranteed')
  if (hasOcc) cols.push('OCC')
  
  if (selectedStatuses.value.includes('OOO')) cols.push('OOO')
  if (selectedStatuses.value.includes('OOS')) cols.push('OOS')
  if (selectedStatuses.value.includes('EB')) cols.push('EB')
  if (selectedStatuses.value.includes('SOFAB')) cols.push('SOFAB')
  if (selectedStatuses.value.includes('Allotment')) cols.push('ALM')
  
  // Default fallback to at least AV to keep table layout correct
  if (cols.length === 0) cols.push('AV')
  
  return cols
})

// Build dynamic statuses options (only showing active ones with is_availability=true)
const allStatusesList = computed(() => {
  const system = [
    { code: 'AV', label: 'AV', color: '#0284c7' },
    { code: 'OOO', label: 'OOO', color: '#059669' },
    { code: 'OOS', label: 'OOS', color: '#475569' },
    { code: 'EB', label: 'EB', color: '#f43f5e' },
    { code: 'SOFAB', label: 'SOFAB', color: '#a855f7' }
  ]
  const db = registrationStatuses.value.map(s => ({
    code: s.name,
    label: s.name,
    color: s.color || '#94a3b8'
  }))
  return [...system, ...db]
})

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
  let base = 'p-2 border-r border-slate-200 text-center font-semibold text-[12px] '
  if (isWeekend) {
    base += 'bg-[#cae8fc] '
  }
  if (val === 0) {
    base += 'text-slate-400 font-normal'
  } else {
    if (subCol === 'AV') {
      base += 'text-slate-800 font-bold'
    } else if (subCol === 'OOO' || subCol === 'OOS') {
      base += 'text-amber-700 font-bold'
    } else if (subCol === 'OCC') {
      base += 'text-blue-700 font-bold'
    } else {
      base += 'text-slate-800 font-bold'
    }
  }
  return base
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
    uiStore.showToast('Vui lòng chọn đầy đủ ngày bắt đầu và ngày kết thúc.', 'warning')
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
  
  // 1. Fetch statuses for dropdown filters first
  try {
    const res = await fetchRegistrationStatuses()
    if (res.data && res.data.success) {
      registrationStatuses.value = res.data.data
      
      // If there is NO saved choice in localStorage, we populate selectedStatuses with all of them
      if (!localStorage.getItem('pms_availability_selected_statuses')) {
        const dbNames = registrationStatuses.value.map(s => s.name)
        selectedStatuses.value = ['AV', 'OOO', 'OOS', 'EB', 'SOFAB', ...dbNames]
      }
    }
  } catch (error) {
    console.error('Error loading registration statuses:', error)
  }

  // 2. Load availability data
  const savedStart = localStorage.getItem('pms_availability_start_date')
  const savedEnd = localStorage.getItem('pms_availability_end_date')
  if (savedStart && savedEnd) {
    await loadAvailability(savedStart, savedEnd)
  } else {
    await loadAvailability()
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
        <!-- Date Selector Inputs -->
        <div class="flex items-center gap-2">
          <!-- Start Date -->
          <div class="relative flex items-center border border-slate-200 rounded-lg bg-slate-50 px-2.5 py-1 shadow-sm text-xs font-semibold text-slate-700">
            <span class="text-slate-400 mr-1 text-[11px]">Từ:</span>
            <input 
              type="date" 
              v-model="startDateYMD" 
              @change="handleFilterSubmit"
              class="bg-transparent border-none focus:outline-none w-[115px] text-slate-700 font-bold"
            />
          </div>

          <!-- End Date -->
          <div class="relative flex items-center border border-slate-200 rounded-lg bg-slate-50 px-2.5 py-1 shadow-sm text-xs font-semibold text-slate-700">
            <span class="text-slate-400 mr-1 text-[11px]">Đến:</span>
            <input 
              type="date" 
              v-model="endDateYMD" 
              @change="handleFilterSubmit"
              class="bg-transparent border-none focus:outline-none w-[115px] text-slate-700 font-bold"
            />
          </div>
        </div>

        <!-- Button Xem -->
        <button 
          @click="handleFilterSubmit"
          class="flex items-center gap-1.5 px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold cursor-pointer border-none shadow-sm transition-all"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          Xem
        </button>

        <!-- Button Xuất excel -->
        <button 
          @click="showExportToast"
          class="flex items-center gap-1.5 px-4 py-1.5 bg-sky-100 hover:bg-sky-200 text-sky-700 border border-sky-200 rounded-lg text-xs font-bold cursor-pointer transition-all shadow-xs"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Xuất excel
        </button>
      </div>

      <!-- Status Filter Dropdown on right -->
      <div class="relative" ref="dropdownRef">
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-700">Lọc Trạng Thái:</span>
          <button 
            @click="toggleDropdown"
            class="flex items-center justify-between gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold cursor-pointer transition-all shadow-xs text-slate-700 min-w-[120px]"
          >
            <span class="truncate max-w-[120px]">
              {{ selectedStatuses.length === allStatusesList.length ? 'Tất cả' : selectedStatuses.join(', ') || 'Trống' }}
            </span>
            <svg class="w-3 h-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
        
        <div 
          v-if="isDropdownOpen" 
          class="absolute right-0 mt-1.5 w-44 bg-white border border-slate-200 rounded-lg shadow-lg z-50 py-1 max-h-64 overflow-y-auto"
        >
          <label 
            v-for="status in allStatusesList" 
            :key="status.code"
            class="flex items-center px-3 py-1.5 hover:bg-slate-50 cursor-pointer text-xs font-semibold text-slate-700 select-none"
          >
            <input 
              type="checkbox" 
              :value="status.code" 
              v-model="selectedStatuses"
              class="hidden"
            />
            <!-- Custom checkbox with status background -->
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

    <!-- Main Grid Matrix Container -->
    <div class="flex-1 overflow-auto border border-slate-200 rounded-lg relative">
      <!-- Loading Overlay -->
      <LoadingOverlay :show="isLoading" />

      <table class="w-full text-xs text-left border-collapse table-fixed select-none">
        <!-- Main Column Width Definitions -->
        <colgroup>
          <col class="w-[75px] sticky left-0 z-20" />
          <col class="w-[175px] sticky left-[75px] z-20" />
          <col class="w-[50px] sticky left-[250px] z-20" />
          <col class="w-[70px] sticky left-[300px] z-20" />
          <template v-for="day in days" :key="day.fullDateStr">
            <col v-for="subCol in activeSubColumns" :key="subCol" class="w-[45px]" />
          </template>
        </colgroup>

        <!-- Table Headers -->
        <thead>
          <!-- First Row: Weekdays -->
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold select-none h-8">
            <th rowspan="2" class="p-2 border-r border-slate-200 text-center sticky left-0 z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]">Mã Loại</th>
            <th rowspan="2" class="p-2 border-r border-slate-200 sticky left-[75px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]">Loại phòng</th>
            <th rowspan="2" class="p-2 border-r border-slate-200 text-center sticky left-[250px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]">Tổng</th>
            <th rowspan="2" class="p-2 border-r border-slate-200 text-center sticky left-[300px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0] leading-tight text-[10px]">SL Phòng Tối Đa</th>
            
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              :colspan="activeSubColumns.length"
              class="p-1 border-r border-slate-200 text-center text-[10px]"
              :class="[day.isWeekend ? 'bg-[#8cc4fb] text-slate-800' : 'bg-slate-100 text-slate-600']"
            >
              {{ day.dow }}<br/>{{ day.dateStr }}
            </th>
          </tr>

          <!-- Second Row: Sub-Columns (AV, OOO, OOS...) -->
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black h-8">
            <template v-for="day in days" :key="day.fullDateStr">
              <th 
                v-for="subCol in activeSubColumns" 
                :key="subCol"
                class="p-1 border-r border-slate-200 text-center text-[10px] font-bold"
                :class="[day.isWeekend ? 'bg-[#8cc4fb] text-slate-800' : 'bg-slate-50 text-slate-500']"
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
            class="border-b border-slate-200 h-9 hover:bg-slate-50"
          >
            <!-- Room Type Identifiers (Sticky on Left) -->
            <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-800 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              <div class="flex items-center gap-1 justify-center">
                <span class="text-slate-400 font-extrabold mr-0.5">•</span>
                {{ rc.code }}
              </div>
            </td>
            <td class="p-2 border-r border-slate-200 font-bold text-slate-700 sticky left-[75px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] truncate">
              {{ rc.name }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center font-black text-slate-800 sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ rc.total }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ rc.max_extra_beds }}
            </td>

            <!-- Dynamic grid cells based on selected filters -->
            <template v-for="day in days" :key="day.fullDateStr">
              <td 
                v-for="subCol in activeSubColumns" 
                :key="subCol"
                :class="getCellClass(subCol, getSubColValue(rc.code, day.fullDateStr, subCol), day.isWeekend)"
              >
                {{ getSubColValue(rc.code, day.fullDateStr, subCol) }}
              </td>
            </template>
          </tr>

          <!-- TỔNG Row (Sum totals) -->
          <tr class="bg-slate-200 border-b border-slate-355 font-black h-9 text-slate-850">
            <td class="p-2 border-r border-slate-355 text-center sticky left-0 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]">TỔNG</td>
            <td class="p-2 border-r border-slate-355 sticky left-[75px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]"></td>
            <td class="p-2 border-r border-slate-355 text-center sticky left-[250px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]">
              {{ totals.grand_total }}
            </td>
            <td class="p-2 border-r border-slate-355 text-center sticky left-[300px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]">
              {{ totals.grand_max_extra_beds }}
            </td>

            <template v-for="day in days" :key="day.fullDateStr">
              <td 
                v-for="subCol in activeSubColumns"
                :key="subCol"
                class="p-2 border-r border-slate-300 text-center text-[13px]"
                :class="[
                  day.isWeekend ? 'bg-[#cae8fc] text-slate-805 font-black' : 'font-black',
                  getSumValue(subCol, day.fullDateStr) === 0 ? 'text-slate-400' : 'text-slate-800'
                ]"
              >
                {{ getSumValue(subCol, day.fullDateStr) }}
              </td>
            </template>
          </tr>

          <!-- THỐNG KÊ Title Header Row -->
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-black h-8 text-center uppercase tracking-wide">
            <td colspan="4" class="p-2 sticky left-0 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0] text-left pl-4">THỐNG KÊ</td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-1 border-r border-slate-200"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            ></td>
          </tr>

          <!-- 1. Tổng (Always Visible) -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Tổng</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ (totals.grand_total * dates.length) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-500 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.total_rooms ?? 0 }}
            </td>
          </tr>

          <!-- 2. OOO -->
          <tr v-if="selectedStatuses.includes('OOO')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">OOO</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.ooo ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.ooo ?? 0 }}
            </td>
          </tr>

          <!-- 3. OOS -->
          <tr v-if="selectedStatuses.includes('OOS')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">OOS</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.oos ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.oos ?? 0 }}
            </td>
          </tr>

          <!-- 4. Tổng số phòng có thể bán (Always Visible) -->
          <tr class="border-b border-slate-200 h-8 font-bold text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-blue-600 pl-4">Tổng số phòng có thể bán</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-blue-600">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.sellable ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-blue-600/90 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.sellable ?? 0 }}
            </td>
          </tr>

          <!-- 5. Series (Always Visible) -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Series</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.series ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.series ?? 0 }}
            </td>
          </tr>

          <!-- 6. Allotment (Always Visible) -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Allotment</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.allotment ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.allotment ?? 0 }}
            </td>
          </tr>

          <!-- 7. Đặt phòng đảm bảo -->
          <tr v-if="selectedStatuses.includes('Guaranteed')" class="border-b border-slate-200 h-8 font-bold text-red-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Đặt phòng đảm bảo</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-amber-700 font-black">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.bk_guaranteed ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-amber-700 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.bk_guaranteed ?? 0 }}
            </td>
          </tr>

          <!-- 8. Đặt phòng không đảm bảo -->
          <tr v-if="selectedStatuses.includes('None Guaranteed')" class="border-b border-slate-200 h-8 font-bold text-red-500 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Đặt phòng không đảm bảo</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-red-500 font-black">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.bk_nonguaranteed ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-red-500 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.bk_nonguaranteed ?? 0 }}
            </td>
          </tr>

          <!-- 9. Tổng số phòng chiếm dụng -->
          <tr v-if="selectedStatuses.includes('Guaranteed') || selectedStatuses.includes('None Guaranteed')" class="border-b border-slate-200 h-10 font-bold hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-red-655 pl-4">Tổng số phòng chiếm dụng</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-red-600 font-black">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.total_occupied ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-1 border-r border-slate-200 text-center leading-tight text-red-600 text-[11px]"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.total_occupied ?? 0 }}<br/>
              <span class="text-[9px] font-semibold text-amber-700">({{ statistics[day.fullDateStr]?.occupied_pct ?? 0 }}%)</span>
            </td>
          </tr>

          <!-- 10. Phòng trống -->
          <tr v-if="selectedStatuses.includes('AV')" class="border-b border-slate-200 h-8 font-bold text-red-500 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng trống</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-red-500 font-black">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.av ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-[13px] text-red-500 font-black"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.av ?? 0 }}
            </td>
          </tr>

          <!-- 11. Phòng nội bộ -->
          <tr v-if="selectedStatuses.includes('AV')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng nội bộ</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-700">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.internal_rooms ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-700 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.internal_rooms ?? 0 }}
            </td>
          </tr>

          <!-- 12. Phòng miễn phí -->
          <tr v-if="selectedStatuses.includes('AV')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng miễn phí</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-700">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.free_rooms ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.free_rooms ?? 0 }}
            </td>
          </tr>

          <!-- 13. Tổng khách (Always Visible) -->
          <tr class="border-b border-slate-200 h-8 font-bold text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Tổng khách</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-800 font-black">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.total_guests ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center font-bold text-slate-700"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.total_guests ?? 0 }}
            </td>
          </tr>

          <!-- 14. Phòng đến (Room/Pax) -->
          <tr v-if="selectedStatuses.includes('Guaranteed') || selectedStatuses.includes('None Guaranteed')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng đến (Room/Pax)</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-700">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.arrivals_rooms ?? 0), 0) }}/{{ dates.reduce((sum, d) => sum + (statistics[d]?.arrivals_pax ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.arrivals_rooms ?? 0 }}/{{ statistics[day.fullDateStr]?.arrivals_pax ?? 0 }}
            </td>
          </tr>

          <!-- 15. Phòng đang ở -->
          <tr v-if="selectedStatuses.includes('Guaranteed') || selectedStatuses.includes('None Guaranteed')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng đang ở</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-700">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.inhouse ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-700 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.inhouse ?? 0 }}
            </td>
          </tr>

          <!-- 16. Thêm giường -->
          <tr v-if="selectedStatuses.includes('EB')" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Thêm giường</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-700">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.extra_beds ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.extra_beds ?? 0 }}
            </td>
          </tr>

          <!-- 17. Phòng hủy -->
          <tr v-if="selectedStatuses.some(s => s.toLowerCase().includes('cancelled'))" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng hủy</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-700">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.cancellations ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.cancellations ?? 0 }}
            </td>
          </tr>

          <!-- 18. Phòng noshow -->
          <tr v-if="selectedStatuses.some(s => s.toLowerCase().includes('noshow'))" class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] pl-4">Phòng noshow</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[250px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-slate-750">
              {{ dates.reduce((sum, d) => sum + (statistics[d]?.noshow ?? 0), 0) }}
            </td>
            <td class="p-2 border-r border-slate-200 sticky left-[300px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="day in days" 
              :key="day.fullDateStr" 
              :colspan="activeSubColumns.length"
              class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold"
              :class="[day.isWeekend ? 'bg-[#cae8fc]' : '']"
            >
              {{ statistics[day.fullDateStr]?.noshow ?? 0 }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
