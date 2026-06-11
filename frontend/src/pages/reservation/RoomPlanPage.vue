<script setup>
import { ref, computed } from 'vue'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Filter states
const dateRangeText = ref('09 / 06 / 2026 ~ 29 / 06 / 2026')
const selectedRoomType = ref('0') // All
const showNights = ref(true)
const showNotes = ref(true)
const startDate = ref(new Date('2026-06-09'))

// Vietnamese weekdays mapping
const weekDays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']

// Legend items
const legends = [
  { name: 'OOO', class: 'bg-[repeating-linear-gradient(45deg,#3b82f6,#3b82f6_5px,#60a5fa_5px,#60a5fa_10px)] text-white border-blue-400' },
  { name: 'OOS', class: 'bg-[repeating-linear-gradient(45deg,#94a3b8,#94a3b8_5px,#cbd5e1_5px,#cbd5e1_10px)] text-white border-slate-400' },
  { name: 'InHouse', class: 'bg-[#a6dcfc] text-[#0369a1] border-[#7dd3fc]' },
  { name: 'Reservation', class: 'bg-[#fef3c7] text-[#b45309] border-[#fde68a]' },
  { name: 'Late Checkout', class: 'bg-[#fef9c3] text-[#854d0e] border-[#fef08a]' },
  { name: 'Guaranteed', class: 'bg-[#dcfce7] text-[#15803d] border-[#bbf7d0]' },
  { name: 'Allotment', class: 'bg-[#ffedd5] text-[#9a3412] border-[#fed7aa]' },
  { name: 'None Guaranteed', class: 'bg-[#1e293b] text-white border-slate-700' }
]

// Generate 21 columns for the timeline (09/06 to 29/06)
const days = computed(() => {
  const list = []
  const start = new Date(startDate.value)
  for (let i = 0; i < 21; i++) {
    const current = new Date(start)
    current.setDate(start.getDate() + i)
    const dayOfWeek = current.getDay()
    
    list.push({
      dateStr: `${String(current.getDate()).padStart(2, '0')}/${String(current.getMonth() + 1).padStart(2, '0')}`,
      dow: weekDays[dayOfWeek],
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isSunday: dayOfWeek === 0,
      fullDate: current
    })
  }
  return list
})

// Mock Rooms with floor 4 and 5 details
const roomsList = [
  { room: '401', type: 'DLXD Double', hasBroom: false },
  { room: '402', type: 'DLXTB Twin', hasBroom: true },
  { room: '403', type: 'DLXTB Twin', hasBroom: true },
  { room: '404', type: 'SUPT Twin', hasBroom: true },
  { room: '405', type: 'FAM Family', hasBroom: false },
  { room: '406', type: 'SUPT Twin', hasBroom: false },
  { room: '407', type: 'SUPTR Triple', hasBroom: true },
  { room: '408', type: 'SUPD Double', hasBroom: false },
  { room: '409', type: 'SUPTR Triple', hasBroom: true },
  { room: '410', type: 'SUPT Twin', hasBroom: false },
  { room: '411', type: 'DLXDB Double', hasBroom: false },
  { room: '412', type: 'DLXDB Double', hasBroom: false },
  { room: '501', type: 'DLXD Double', hasBroom: false },
  { room: '502', type: 'DLXTB Twin', hasBroom: false },
  { room: '503', type: 'DLXTB Twin', hasBroom: false },
  { room: '504', type: 'SUPT Twin', hasBroom: false },
  { room: '505', type: 'FAM Family', hasBroom: false },
  { room: '506', type: 'SUPT Twin', hasBroom: false }
]

// Mock booking timeline blocks for each room
const bookingsByRoom = {
  '401': [
    { start: 0, end: 4, type: 'InHouse', label: 'GAL 5333 - TRAVEL CONCIERGE - 2637449 (COMMITMENT)...' },
    { start: 9, end: 14, type: 'Guaranteed', label: 'GAL 5181 - ANEX TOUR - 112422912 - ANEX TOUR - 650,000đ' }
  ],
  '402': [
    { start: 0, end: 5, type: 'InHouse', label: '' },
    { start: 6, end: 14, type: 'Guaranteed', label: 'GAL 5436 - PEGAS - 10701399 - PEGAS - 650,000đ' },
    { start: 17, end: 20, type: 'Guaranteed', label: 'GAL 4737 - ODEON TOUR - 4472796 - ODEON TOURS - 890,000đ' }
  ],
  '403': [
    { start: 0, end: 2, type: 'InHouse', label: '0,000đ' },
    { start: 4, end: 14, type: 'Guaranteed', label: 'GAL 5407 - FUN & SUN TRAVEL - 11751612 - FUN & SUN TRAVEL - 890,000đ' }
  ],
  '404': [
    { start: 7, end: 9, type: 'Guaranteed', label: 'GAL 5424 - VNEXPR...' },
    { start: 9, end: 12, type: 'Guaranteed', label: 'GAL 4910 - DI RỒN...' },
    { start: 13, end: 15, type: 'Guaranteed', label: 'GAL 4532 - VIETRAV...' },
    { start: 16, end: 19, type: 'Guaranteed', label: 'GAL 4988 - THÁI ĐẠ...' }
  ],
  '405': [
    { start: 0, end: 1, type: 'Guaranteed', label: 'GAL 54' },
    { start: 4, end: 14, type: 'Guaranteed', label: 'GAL 5408 - FUN & SUN TRAVEL - 11751651 - FUN & SUN TRAVEL - 1,180,000đ' },
    { start: 13, end: 15, type: 'Guaranteed', label: 'GAL 4532 - VIETRAV...' }
  ],
  '406': [
    { start: 0, end: 8, type: 'Guaranteed', label: 'GAL 5330 - ANEX TOUR - 112459435 - ANEX TOUR - 540,000đ' },
    { start: 10, end: 11, type: 'Guaranteed', label: 'GAL 50' },
    { start: 13, end: 15, type: 'Guaranteed', label: 'GAL 4532 - VIETRAV...' },
    { start: 16, end: 19, type: 'Guaranteed', label: 'GAL 4988 - THÁI ĐẠ...' }
  ],
  '407': [
    { start: 1, end: 7, type: 'Guaranteed', label: 'GAL 5041 - GREEN TRAVEL GROUP - 404407 - GREEN TRAVEL GROUP - 540,000đ' },
    { start: 13, end: 15, type: 'Guaranteed', label: 'GAL 4532 - VIETRAV...' }
  ],
  '408': [
    { start: 6, end: 7, type: 'Guaranteed', label: 'GAL 48' },
    { start: 7, end: 9, type: 'Guaranteed', label: 'GAL 5424 - VNEXPR...' },
    { start: 9, end: 12, type: 'Guaranteed', label: 'GAL 4910 - DI RỒN...' },
    { start: 13, end: 15, type: 'Guaranteed', label: 'GAL 4532 - VIETRAV...' }
  ],
  '409': [
    { start: 0, end: 13, type: 'InHouse', label: 'TRAVEL CONCIERGE - 2635589 (COMMITMENT) - TRAVEL CONCIERGE - (COMMITMENT) - 490,000đ' },
    { start: 13, end: 14, type: 'Guaranteed', label: 'GAL 5335 - Ngọc Anh' }
  ],
  '410': [
    { start: 0, end: 3, type: 'Guaranteed', label: '1 - 540,000đ' },
    { start: 5, end: 6, type: 'Guaranteed', label: 'GAL 52' },
    { start: 7, end: 12, type: 'Guaranteed', label: 'GAL 5420 - ANEX TOUR - 112480010 - ANEX TOUR - 540,000đ' },
    { start: 13, end: 15, type: 'Guaranteed', label: 'GAL 4532 - VIETRAV...' },
    { start: 16, end: 19, type: 'Guaranteed', label: 'GAL 4988 - THÁI ĐẠ...' }
  ],
  '411': [
    { start: 0, end: 13, type: 'InHouse', label: 'TRAVEL CONCIERGE - 2640967 (COMMITMENT) - TRAVEL CONCIERGE - (COMMITMENT) - 890,000đ' }
  ],
  '412': [
    { start: 1, end: 6, type: 'Guaranteed', label: 'GAL 4982 - FUN & SUN TRAVEL - 11804879 - FUN & SUN TRAVEL - 830,000đ' },
    { start: 11, end: 20, type: 'Guaranteed', label: 'GAL 4801 - FUN & SUN TRAVEL - 11533727 - FUN & SUN TRAVEL - 890,000đ' }
  ],
  '501': [
    { start: 0, end: 5, type: 'InHouse', label: 'TRAVEL CONCIERGE - 2637449 (COMMITMENT) - TRAVEL CONCIERGE - (COMMITMENT) - 890,000đ' }
  ],
  '502': [
    { start: 0, end: 3, type: 'InHouse', label: '' }
  ],
  '503': [
    { start: 0, end: 10, type: 'InHouse', label: 'TRAVEL CONCIERGE - 2639638 (COMMITMENT) - TRAVEL CONCIERGE - (COMMITMENT) - 890,000đ' }
  ],
  '504': [
    { start: 0, end: 2, type: 'Guaranteed', label: 'GAL 5096 - Trip.com...' },
    { start: 2, end: 3, type: 'Guaranteed', label: 'GAL 54...' },
    { start: 3, end: 7, type: 'Guaranteed', label: 'GAL 5450 - PEGAS - 10804757 - PEGAS - 540,000đ' },
    { start: 16, end: 19, type: 'Guaranteed', label: 'GAL 4988 - THÁI ĐẠ...' }
  ],
  '505': [
    { start: 0, end: 2, type: 'InHouse', label: 'NGUYỄN THỊ HỒNG PHƯƠNG...' },
    { start: 3, end: 11, type: 'Guaranteed', label: 'GAL 5258 - PEGAS - 10670323 - PEGAS - 650,000đ' },
    { start: 16, end: 17, type: 'Guaranteed', label: 'GAL 53...' },
    { start: 18, end: 20, type: 'Guaranteed', label: 'GAL 5280 - OLGA NECHAEVA / Hotelbeds - 233467-358-3' }
  ],
  '506': [
    { start: 0, end: 2, type: 'InHouse', label: 'GAL 5096 - Trip.com...' },
    { start: 4, end: 13, type: 'Guaranteed', label: 'GAL 5424 - VNEXPR...' },
    { start: 16, end: 19, type: 'Guaranteed', label: 'GAL 4988 - THÁI ĐẠ...' }
  ]
}

// Map legend colors to Tailwind classes
function getBookingClass(type) {
  if (type === 'InHouse') return 'bg-[#a6dcfc] text-[#0369a1] border-[#7dd3fc]'
  if (type === 'Reservation') return 'bg-[#fef3c7] text-[#b45309] border-[#fde68a]'
  if (type === 'Late Checkout') return 'bg-[#fef9c3] text-[#854d0e] border-[#fef08a]'
  if (type === 'Guaranteed') return 'bg-[#dcfce7] text-[#15803d] border-[#bbf7d0]'
  if (type === 'Allotment') return 'bg-[#ffedd5] text-[#9a3412] border-[#fed7aa]'
  return 'bg-[#1e293b] text-white border-slate-700'
}

// Sum stats at bottom
const occStats = [108, 111, 90, 92, 105, 96, 87, 88, 99, 88, 84, 85, 66, 63, 83, 80, 48, 67, 61, 35, 47]
const avStats = [23, 20, 41, 39, 26, 35, 44, 43, 32, 43, 47, 46, 65, 68, 48, 51, 83, 64, 70, 96, 84]

function handleViewClick() {
  uiStore.showToast('Đã tải lại kế hoạch phòng!', 'success')
}
</script>

<template>
  <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4 overflow-hidden h-full">
    <!-- Top Filters & Controls -->
    <div class="flex items-center justify-between shrink-0">
      <!-- Left side controls -->
      <div class="flex items-center gap-3">
        <!-- Date Selector Input -->
        <div class="relative flex items-center border border-slate-200 rounded-lg bg-slate-50 px-2.5 py-1.5 shadow-sm text-xs font-semibold text-slate-700">
          <input 
            type="text" 
            v-model="dateRangeText" 
            class="bg-transparent border-none focus:outline-none w-[170px] text-slate-700"
          />
          <svg class="w-4 h-4 text-slate-400 ml-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>

        <!-- View Button -->
        <button 
          @click="handleViewClick"
          class="px-4 py-1.5 bg-[#a6dcfc] hover:bg-[#8ecefa] text-sky-800 rounded-lg text-xs font-bold border-none shadow-sm transition-colors cursor-pointer"
        >
          View
        </button>

        <!-- Gear Icon -->
        <button class="p-1.5 hover:bg-slate-100 rounded text-slate-500 bg-transparent border-none cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
          </svg>
        </button>

        <!-- Room Type Select -->
        <select 
          v-model="selectedRoomType" 
          class="border border-slate-200 rounded-lg bg-slate-50 px-2 py-1.5 text-xs font-semibold text-slate-700 outline-none"
        >
          <option value="0">Loại phòng: 0</option>
          <option value="DLXD">DLXD</option>
          <option value="DLXTB">DLXTB</option>
          <option value="SUPT">SUPT</option>
        </select>

        <!-- Switch Xem đêm -->
        <div class="flex items-center gap-1.5 select-none ml-1">
          <span class="text-[10px] text-slate-500 font-extrabold uppercase">Xem đêm</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="showNights" class="sr-only peer">
            <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
          </label>
        </div>

        <!-- Switch Ghi chú -->
        <div class="flex items-center gap-1.5 select-none">
          <span class="text-[10px] text-slate-500 font-extrabold uppercase">Ghi chú</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="showNotes" class="sr-only peer">
            <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
          </label>
        </div>
      </div>

      <!-- Right side Legend -->
      <div class="flex items-center gap-1.5 text-[9px] font-bold text-slate-600 select-none">
        <div 
          v-for="leg in legends" 
          :key="leg.name" 
          class="px-2 py-0.5 border rounded-sm leading-tight text-center whitespace-nowrap shadow-xs" 
          :class="leg.class"
        >
          {{ leg.name }}
        </div>
      </div>
    </div>

    <!-- Timeline Grid Matrix -->
    <div class="flex-1 overflow-auto border border-slate-200 rounded-lg relative">
      <!-- Col width: 62px, sticky room header: 160px -->
      <table class="w-full text-xs border-collapse table-fixed select-none">
        <colgroup>
          <col class="w-[160px] sticky left-0 z-30" />
          <col v-for="i in 21" :key="i" class="w-[62px]" />
        </colgroup>

        <!-- Header -->
        <thead>
          <!-- Day of week row -->
          <tr class="border-b border-slate-200 text-slate-600 font-bold select-none h-8">
            <th class="p-2 border-r border-slate-200 text-center sticky left-0 top-0 z-40 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center sticky top-0 z-30 shadow-[inset_0_-1px_0_#e2e8f0]"
              :class="[day.isWeekend ? 'bg-blue-500 text-white' : 'bg-slate-100 text-slate-600']"
            >
              {{ day.dow }}
            </th>
          </tr>

          <!-- Date row -->
          <tr class="border-b border-slate-200 text-slate-700 font-black h-8">
            <th class="p-2 border-r border-slate-200 text-center sticky left-0 top-[32px] z-40 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center text-[10px] sticky top-[32px] z-30 shadow-[inset_0_-1px_0_#e2e8f0]"
              :class="[day.isWeekend ? 'bg-blue-500 text-white border-blue-400' : 'bg-slate-50 text-slate-700']"
            >
              {{ day.dateStr }}
            </th>
          </tr>
        </thead>

        <!-- Body Grid -->
        <tbody>
          <!-- Timeline Rows -->
          <tr 
            v-for="item in roomsList" 
            :key="item.room"
            class="border-b border-slate-200 h-[38px] hover:bg-slate-50 relative"
          >
            <!-- Room Info (Sticky Left) -->
            <td class="p-2 border-r border-slate-200 bg-white sticky left-0 z-20 shadow-[inset_-1px_0_0_#e2e8f0] h-[37px] overflow-hidden">
              <div class="flex items-center justify-between gap-1 h-full w-full">
                <div class="flex flex-col text-[10px] leading-tight select-none">
                  <span class="font-black text-slate-800 text-[11px]">{{ item.room }}</span>
                  <span class="text-slate-500 font-bold text-[9px]">{{ item.type }}</span>
                </div>
                <div v-if="item.hasBroom" class="shrink-0 text-slate-500/80">
                  <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M19 19L5 5M12 12l2.5-2.5m1.5-1.5l1.5-1.5M7.5 7.5L5 5" />
                    <path d="M5.5 19.5c.6.6 1.4 1 2.3 1H10l9-9c1-1 1-2.6 0-3.5l-1.5-1.5c-1-1-2.6-1-3.5 0l-9 9v2.2c0 .9.4 1.7 1 2.3Z" />
                  </svg>
                </div>
              </div>
            </td>

            <!-- Empty Grid Cells behind bookings -->
            <td 
              v-for="i in 21" 
              :key="i" 
              class="border-r border-slate-100 h-full p-0 relative"
            >
              <!-- Absolute Booking Block Render -->
              <template v-if="bookingsByRoom[item.room]">
                <template v-for="(bk, bkIdx) in bookingsByRoom[item.room]" :key="bkIdx">
                  <!-- Render inside the matching cell for absolute positioning relative to row -->
                  <div
                    v-if="bk.start === (i - 1)"
                    :title="bk.label"
                    class="absolute top-[2px] left-[2px] h-[33px] border rounded flex items-center px-2 z-10 overflow-hidden text-[9px] font-bold leading-tight select-none shadow-xs truncate hover:brightness-95 hover:shadow-md transition-all cursor-pointer"
                    :class="getBookingClass(bk.type)"
                    :style="{
                      width: `calc(${(bk.end - bk.start + 1) * 62}px - 5px)`
                    }"
                  >
                    {{ bk.label }}
                  </div>
                </template>
              </template>
            </td>
          </tr>

          <!-- Summary OCC Footer Row -->
          <tr class="bg-[#e0f2fe]/20 border-b border-slate-200 h-[38px] font-black text-slate-800">
            <td class="p-1.5 border-r border-slate-200 text-left sticky left-0 bg-[#e0f2fe] shadow-[inset_-1px_0_0_#93c5fd] font-extrabold text-[10px] pl-2 select-none leading-tight">
              <div>OCC</div>
              <div class="text-[9px] text-sky-800 font-black">1683 (61.18%)</div>
            </td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center text-[9px] font-bold text-slate-800 bg-[#e0f2fe]/25"
            >
              {{ occStats[idx % occStats.length] }} ({{ ((occStats[idx % occStats.length] / 131) * 100).toFixed(2) }}%)
            </td>
          </tr>

          <!-- Summary AV Footer Row -->
          <tr class="bg-white border-b border-slate-200 h-[38px] font-black text-slate-800">
            <td class="p-1.5 border-r border-slate-200 text-left sticky left-0 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-extrabold text-[10px] pl-2 select-none leading-tight">
              <div>AV</div>
              <div class="text-[9px] text-slate-500 font-bold">1068</div>
            </td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center text-[10px] font-bold text-slate-700 bg-white"
            >
              {{ avStats[idx % avStats.length] }}
            </td>
          </tr>

          <!-- Summary OOO Footer Row -->
          <tr class="bg-white h-[38px] font-black text-slate-800">
            <td class="p-1.5 border-r border-slate-200 text-left sticky left-0 bg-slate-50 shadow-[inset_-1px_0_0_#e2e8f0] font-extrabold text-[10px] pl-2 select-none leading-tight">
              <div>OOO</div>
              <div class="text-[9px] text-slate-500 font-bold">0</div>
            </td>
            <td 
              v-for="i in 21" 
              :key="i" 
              class="p-1 border-r border-slate-200 text-center text-[10px] font-bold text-slate-500 bg-white"
            >
              0
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
