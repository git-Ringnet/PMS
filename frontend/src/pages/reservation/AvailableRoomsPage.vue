<script setup>
import { ref, computed } from 'vue'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Mock date range starting 09/06/2026 - 08/07/2026
const dateRangeText = ref('09/06/2026 - 08/07/2026')
const startDate = ref(new Date('2026-06-09'))

const capacities = {
  'SUPD': { name: 'Superior Double', total: 6, max: 0 },
  'SUPT': { name: 'Superior Twin', total: 18, max: 0 },
  'SUPTR': { name: 'Superior Triple', total: 30, max: 2 },
  'DLXD': { name: 'Deluxe Double City view', total: 10, max: 0 },
  'DLXT': { name: 'Deluxe Twin City View', total: 12, max: 0 },
  'DLXDB': { name: 'Deluxe Double with Balcony', total: 22, max: 3 },
  'DLXTB': { name: 'Deluxe Twin with Balcony', total: 21, max: 2 },
  'FAM': { name: 'Family City View', total: 11, max: 0 },
  'JST': { name: 'Suite', total: 1, max: 0 }
}

const typeKeys = Object.keys(capacities)

// Generate 30 days of columns
const days = computed(() => {
  const list = []
  const start = new Date(startDate.value)
  for (let i = 0; i < 30; i++) {
    const current = new Date(start)
    current.setDate(start.getDate() + i)
    
    const dayOfWeek = current.getDay()
    let dow = ''
    if (dayOfWeek === 0) dow = 'CN'
    else dow = `T${dayOfWeek + 1}`
    
    list.push({
      dateStr: `${String(current.getDate()).padStart(2, '0')}/${String(current.getMonth() + 1).padStart(2, '0')}`,
      dow,
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isSunday: dayOfWeek === 0,
      fullDate: current
    })
  }
  return list
})

// Deterministic mock available count for each type and day to look realistic
function getAvailableCount(typeCode, dayIndex) {
  const total = capacities[typeCode].total
  const max = capacities[typeCode].max
  
  // Specific override values to closely match screenshot row 1 (SUPD) and row 2 (SUPT)
  if (typeCode === 'SUPD') {
    const supdValues = [1, 1, 1, 2, 1, 1, 1, 0, 1, 1, 1, 3, 4, 0, 0, 5, 4, 3, 3, 2, 3, 3, 3, 3, 2, 2]
    return supdValues[dayIndex % supdValues.length]
  }
  if (typeCode === 'SUPT') {
    const suptValues = [2, 3, 9, 9, 6, 8, 8, 11, 0, 1, 5, 1, 12, 12, 5, 5, 17, 2, 2, 16, 2, 17, 12, 10, 10, 10]
    return suptValues[dayIndex % suptValues.length]
  }
  if (typeCode === 'SUPTR') {
    const suptrValues = [8, 5, 7, 3, 4, 5, 2, 3, 4, 9, 8, 9, 9, 11, 9, 11, 17, 15, 16, 20, 20, 23, 21, 24, 23, 22]
    return suptrValues[dayIndex % suptrValues.length]
  }
  
  // Fallback formula
  const seed = (typeCode.charCodeAt(0) + typeCode.charCodeAt(1) + dayIndex * 11) % total
  let val = total - Math.floor(total * 0.6) - (seed % 4)
  if (val < 0) val = 0
  return val
}

// Column Sums for the matrix
const columnSums = computed(() => {
  const sums = []
  for (let i = 0; i < 30; i++) {
    let sum = 0
    for (const key of typeKeys) {
      sum += getAvailableCount(key, i)
    }
    sums.push(sum)
  }
  return sums
})

function showExportToast() {
  uiStore.showToast('Xuất báo cáo Excel thành công!', 'success')
}

function showFilterToast() {
  uiStore.showToast('Đã cập nhật bộ lọc hiển thị phòng trống!', 'info')
}
</script>

<template>
  <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4 overflow-hidden h-full">
    <!-- Top Filter Controls -->
    <div class="flex items-center justify-between shrink-0">
      <!-- Date pickers & Action buttons -->
      <div class="flex items-center gap-3">
        <!-- Date Selector Input -->
        <div class="relative flex items-center border border-slate-200 rounded-lg bg-slate-50 px-3 py-1.5 shadow-sm text-xs font-semibold text-slate-700">
          <svg class="w-4 h-4 text-slate-400 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <input 
            type="text" 
            v-model="dateRangeText" 
            class="bg-transparent border-none focus:outline-none w-[160px] text-slate-700"
          />
        </div>

        <!-- Button Xem -->
        <button 
          @click="showFilterToast"
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

      <!-- Legend badges on right -->
      <div class="flex items-center gap-2 text-[10px] font-bold text-slate-600 uppercase">
        <span class="px-2 py-0.5 border border-slate-200 bg-slate-50 rounded select-none">AV</span>
        <span class="px-2 py-0.5 bg-blue-500 text-white rounded select-none">OCC</span>
        <span class="px-2 py-0.5 border border-slate-200 bg-slate-50 rounded select-none">OOO</span>
        <span class="px-2 py-0.5 border border-slate-200 bg-slate-50 rounded select-none">OOS</span>
        <span class="px-2 py-0.5 bg-rose-400 text-white rounded select-none">EB</span>
        <span class="px-2 py-0.5 bg-purple-500 text-white rounded select-none font-medium">SOFAB</span>
        <span class="px-2 py-0.5 border border-slate-200 bg-slate-50 rounded select-none">occ</span>
      </div>
    </div>

    <!-- Main Grid Matrix Container -->
    <div class="flex-1 overflow-auto border border-slate-200 rounded-lg">
      <table class="w-full text-xs text-left border-collapse table-fixed select-none">
        <!-- Main Column Width Definitions -->
        <colgroup>
          <col class="w-[70px] sticky left-0 z-20" />
          <col class="w-[170px] sticky left-[70px] z-20" />
          <col class="w-[50px] sticky left-[240px] z-20" />
          <col class="w-[70px] sticky left-[290px] z-20" />
          <col v-for="i in 30" :key="i" class="w-[55px]" />
        </colgroup>

        <!-- Table Headers -->
        <thead>
          <!-- First Row: Weekdays -->
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold select-none h-8">
            <th class="p-2 border-r border-slate-200 text-center sticky left-0 z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]">Mã Loại</th>
            <th class="p-2 border-r border-slate-200 sticky left-[70px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]">Loại phòng</th>
            <th class="p-2 border-r border-slate-200 text-center sticky left-[240px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]">Tổng</th>
            <th class="p-2 border-r border-slate-200 text-center sticky left-[290px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0] leading-tight text-[10px]">SL Phòng Tối Đa</th>
            
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center text-[10px]"
              :class="[day.isSunday ? 'bg-rose-100 text-rose-700' : day.isWeekend ? 'bg-yellow-100 text-amber-700' : '']"
            >
              {{ day.dow }}
            </th>
          </tr>

          <!-- Second Row: Dates -->
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-black h-8">
            <th class="p-2 border-r border-slate-200 text-center sticky left-0 z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th class="p-2 border-r border-slate-200 sticky left-[70px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th class="p-2 border-r border-slate-200 text-center sticky left-[240px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th class="p-2 border-r border-slate-200 text-center sticky left-[290px] z-30 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center text-[11px]"
              :class="[day.isSunday ? 'bg-rose-100 text-rose-700' : day.isWeekend ? 'bg-yellow-100 text-amber-700' : '']"
            >
              {{ day.dateStr }}
            </th>
          </tr>

          <!-- Third Row: Status Type (AV) -->
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold h-7">
            <th class="p-1 border-r border-slate-200 text-center sticky left-0 z-30 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th class="p-1 border-r border-slate-200 sticky left-[70px] z-30 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th class="p-1 border-r border-slate-200 text-center sticky left-[240px] z-30 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th class="p-1 border-r border-slate-200 text-center sticky left-[290px] z-30 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center text-[10px]"
              :class="[day.isSunday ? 'bg-rose-50 text-rose-500' : day.isWeekend ? 'bg-yellow-50 text-amber-500' : '']"
            >
              AV
            </th>
          </tr>
        </thead>

        <!-- Matrix Body -->
        <tbody>
          <!-- Room Types Rows -->
          <tr 
            v-for="key in typeKeys" 
            :key="key" 
            class="border-b border-slate-200 h-9 hover:bg-slate-50"
          >
            <!-- Room Type Identifiers (Sticky on Left) -->
            <td class="p-2 border-r border-slate-200 text-center font-bold text-[#0284c7] sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              <div class="flex items-center gap-1 justify-center">
                <span class="text-blue-500 font-black">+</span>
                {{ key }}
              </div>
            </td>
            <td class="p-2 border-r border-slate-200 font-bold text-slate-700 sticky left-[70px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] truncate">
              {{ capacities[key].name }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center font-black text-slate-800 sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ capacities[key].total }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-700 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">
              {{ capacities[key].max }}
            </td>

            <!-- Grid cells with available count -->
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center font-bold text-[13px]"
              :class="[
                day.isSunday ? 'bg-rose-50/60' : day.isWeekend ? 'bg-yellow-50/60' : '',
                getAvailableCount(key, idx) === 0 ? 'text-red-500' : 'text-slate-700'
              ]"
            >
              {{ getAvailableCount(key, idx) }}
            </td>
          </tr>

          <!-- TỔNG Row (Sum totals) -->
          <tr class="bg-slate-200 border-b border-slate-300 font-black h-9 text-slate-800">
            <td class="p-2 border-r border-slate-350 text-center sticky left-0 bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]">TỔNG</td>
            <td class="p-2 border-r border-slate-350 sticky left-[70px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]"></td>
            <td class="p-2 border-r border-slate-350 text-center sticky left-[240px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]">131</td>
            <td class="p-2 border-r border-slate-350 text-center sticky left-[290px] bg-slate-200 shadow-[inset_-1px_0_0_#cbd5e1]">7</td>

            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-300 text-center text-[13px]"
              :class="[day.isSunday ? 'bg-rose-100/80 text-rose-800' : day.isWeekend ? 'bg-yellow-100/80 text-amber-800' : '']"
            >
              {{ columnSums[idx] }}
            </td>
          </tr>

          <!-- THỐNG KÊ Title Header Row -->
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-black h-8 text-center uppercase tracking-wide">
            <td colspan="4" class="p-2 sticky left-0 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0] text-left pl-4">THỐNG KÊ</td>
            <td v-for="i in 30" :key="i" class="p-1 border-r border-slate-200"></td>
          </tr>

          <!-- Thống kê rows -->
          <!-- 1. Tổng -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Tổng</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">3930</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-500 font-bold"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              131
            </td>
          </tr>

          <!-- 2. OOO -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">OOO</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">0</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              0
            </td>
          </tr>

          <!-- 3. OOS -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">OOS</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">0</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              0
            </td>
          </tr>

          <!-- 4. Tổng số phòng có thể bán -->
          <tr class="border-b border-slate-200 h-8 font-bold text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-blue-600">Tổng số phòng có thể bán</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-blue-600">3930</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-blue-600/90"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              131
            </td>
          </tr>

          <!-- 5. Series -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Series</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">0</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-400 font-bold"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              0
            </td>
          </tr>

          <!-- 6. Allotment -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Allotment</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">4</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-600"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              {{ idx === 3 || idx === 4 ? 2 : 0 }}
            </td>
          </tr>

          <!-- 7. Đặt phòng đảm bảo -->
          <tr class="border-b border-slate-200 h-8 font-bold text-red-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Đặt phòng đảm bảo</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">2158</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-amber-700"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              {{ 131 - columnSums[idx] - 1 }}
            </td>
          </tr>

          <!-- 8. Đặt phòng không đảm bảo -->
          <tr class="border-b border-slate-200 h-8 font-bold text-red-500 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Đặt phòng không đảm bảo</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">1</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-red-500"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              {{ idx === 0 ? 1 : 0 }}
            </td>
          </tr>

          <!-- 9. Tổng số phòng chiếm dụng -->
          <tr class="border-b border-slate-200 h-10 font-bold hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-red-600">Tổng số phòng chiếm dụng</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0] text-red-600">2163 (55.04%)</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center leading-tight text-red-600 text-[11px]"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              {{ 131 - columnSums[idx] }}<br/>
              <span class="text-[9px] font-semibold text-amber-700">({{ Math.round(((131 - columnSums[idx]) / 131) * 100) }}%)</span>
            </td>
          </tr>

          <!-- 10. Phòng trống -->
          <tr class="border-b border-slate-200 h-8 font-bold text-red-500 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Phòng trống</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">1767</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-[13px]"
              :class="[day.isSunday ? 'bg-rose-100/40 text-red-500' : day.isWeekend ? 'bg-yellow-100/40 text-red-500' : 'text-red-500']"
            >
              {{ columnSums[idx] }}
            </td>
          </tr>

          <!-- 11. Phòng nội bộ -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Phòng nội bộ</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">0</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-400"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              0
            </td>
          </tr>

          <!-- 12. Phòng miễn phí -->
          <tr class="border-b border-slate-200 h-8 font-medium text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Phòng miễn phí</td>
            <td class="p-2 border-r border-slate-200 text-center font-bold sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">0</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center text-slate-400"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              0
            </td>
          </tr>

          <!-- 13. Tổng khách -->
          <tr class="border-b border-slate-200 h-8 font-bold text-slate-700 hover:bg-slate-50">
            <td colspan="2" class="p-2 border-r border-slate-200 sticky left-0 bg-white shadow-[inset_-1px_0_0_#e2e8f0]">Tổng khách</td>
            <td class="p-2 border-r border-slate-200 text-center sticky left-[240px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]">4508</td>
            <td class="p-2 border-r border-slate-200 sticky left-[290px] bg-white shadow-[inset_-1px_0_0_#e2e8f0]"></td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-2 border-r border-slate-200 text-center font-bold text-slate-700"
              :class="[day.isSunday ? 'bg-rose-50/40' : day.isWeekend ? 'bg-yellow-50/40' : '']"
            >
              {{ Math.round((131 - columnSums[idx]) * 2.1) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
