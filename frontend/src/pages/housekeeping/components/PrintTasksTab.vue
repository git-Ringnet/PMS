<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Printer, FileSpreadsheet } from '@lucide/vue'

// Mock Data from Image
const tasks = ref([
  { id: 1, ttDk: '', floor: 4, room: '401', type: 'DLXD', category: 'Double', guestName: '', regCode: '', regName: '', arrive: '', depart: '', company: '', isOccupied: false, selected: false },
  { id: 2, ttDk: 'red', floor: 4, room: '402', type: 'DLXTB', category: 'Twin', guestName: 'Ms.IVANOVA DARIA', regCode: '5197', regName: 'PEGAS - 10660748', arrive: '04-06-2026', depart: '15-06-2026', company: 'PEGAS', isOccupied: true, selected: false },
  { id: 3, ttDk: '', floor: 4, room: '403', type: 'DLXTB', category: 'Twin', guestName: 'Mr.Guest 1', regCode: '5407', regName: 'FUN & SUN TRAVEL - 11751612', arrive: '14-06-2026', depart: '23-06-2026', company: 'FUN & SUN TRAVEL', isOccupied: true, selected: false },
  { id: 4, ttDk: '', floor: 4, room: '404', type: 'SUPT', category: 'Twin', guestName: '', regCode: '', regName: '', arrive: '', depart: '', company: '', isOccupied: false, selected: false },
  { id: 5, ttDk: '', floor: 4, room: '405', type: 'FAM', category: 'Family', guestName: 'Mr.Guest 1', regCode: '5408', regName: 'FUN & SUN TRAVEL - 11751651', arrive: '14-06-2026', depart: '23-06-2026', company: 'FUN & SUN TRAVEL', isOccupied: true, selected: false },
  { id: 6, ttDk: '', floor: 4, room: '406', type: 'SUPT', category: 'Twin', guestName: 'Mr.Guest 1', regCode: '5330', regName: 'ANEX TOUR - 112459435', arrive: '09-06-2026', depart: '18-06-2026', company: 'ANEX TOUR', isOccupied: true, selected: false },
  { id: 7, ttDk: '', floor: 4, room: '407', type: 'SUPTR', category: 'Triple', guestName: 'Mr.Guest 1', regCode: '5041', regName: 'GREEN TRAVEL GROUP - 404407', arrive: '10-06-2026', depart: '20-06-2026', company: 'GREEN TRAVEL GROUP', isOccupied: true, selected: false },
  { id: 8, ttDk: '', floor: 4, room: '408', type: 'SUPD', category: 'Double', guestName: '', regCode: '', regName: '', arrive: '', depart: '', company: '', isOccupied: false, selected: false },
  { id: 9, ttDk: '', floor: 4, room: '409', type: 'SUPTR', category: 'Triple', guestName: 'Mr.Guest 1', regCode: '5299', regName: 'TRAVEL CONCIERGE - 2635589 (COMMITMENT)', arrive: '08-06-2026', depart: '22-06-2026', company: 'TRAVEL CONCIERGE - (COMMITMENT)', isOccupied: true, selected: false },
  { id: 10, ttDk: 'red-green', floor: 4, room: '410', type: 'SUPT', category: 'Twin', guestName: 'Mr.RYEOJIN JUNG', regCode: '5200', regName: 'RYEOJIN JUNG / Trip.Com - 1400826237533131', arrive: '14-06-2026', depart: '15-06-2026', company: 'TRIP.COM', isOccupied: true, selected: false },
])

const selectAll = computed({
  get() {
    return tasks.value.length > 0 && tasks.value.every(t => t.selected)
  },
  set(val) {
    tasks.value.forEach(t => t.selected = val)
  }
})

const formatMultilineDate = (dateStr) => {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    return `${parts[0]}-${parts[1]}-<br/><span class="text-slate-500 font-normal">${parts[2]}</span>`;
  }
  return dateStr;
}

const activeFilterMenu = ref(null)

const toggleFilterMenu = (menu) => {
  if (activeFilterMenu.value === menu) {
    activeFilterMenu.value = null
  } else {
    activeFilterMenu.value = menu
  }
}

const closeMenuIfClickedOutside = (event) => {
  if (!event.target.closest('.filter-dropdown-wrapper')) {
    activeFilterMenu.value = null
  }
}

onMounted(() => {
  document.addEventListener('click', closeMenuIfClickedOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeMenuIfClickedOutside)
})
</script>

<template>
  <div class="h-full bg-slate-50 p-5 flex flex-col font-sans relative overflow-hidden">
    
    <!-- Table Card Container -->
    <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 flex flex-col overflow-hidden">
      
      <!-- Toolbar -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white z-30">
        <h2 class="text-[15px] font-bold text-slate-700 flex items-center gap-2 uppercase tracking-wide">
          <Printer class="w-5 h-5 text-sky-500" />
          In Phân Công Phòng
        </h2>
        <div class="flex items-center gap-3">
          <button class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg flex items-center gap-2 transition-colors text-[13px] font-semibold shadow-sm">
            <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
            Xuất Excel
          </button>
          <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors text-[13px] font-semibold shadow-sm">
            <Printer class="w-4 h-4" stroke-width="2.5" />
            In Danh Sách
          </button>
        </div>
      </div>

      <!-- Main Table -->
      <div class="flex-1 overflow-auto custom-scrollbar">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max text-[13px]">
          <thead class="sticky top-0 z-20 bg-slate-100/95 backdrop-blur-sm shadow-sm">
            <tr class="text-slate-600 font-bold uppercase tracking-wider text-[11px] border-b border-slate-300">
              <th class="px-3 py-2.5 border-r border-slate-200 text-center w-10 align-middle">
                <input type="checkbox" v-model="selectAll" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
              </th>
              <!-- Column Filters -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('ttdk')">
                  <span>TTĐK</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'ttdk'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[150px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">Khách lẻ</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">Phòng ở</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">Phòng đến</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">Phòng đi</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              <!-- TTPHONG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('ttphong')">
                  <span class="leading-tight">TT<br/>Phòng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'ttphong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[200px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto custom-scrollbar">
                    <label v-for="st in ['Phòng sẵn sàng', 'Phòng sạch', 'Phòng chưa dọn', 'Phòng OOO', 'Phòng OOS', 'Ưu tiên', 'Ưu tiên tính phí', 'Chiếm dụng sẵn sàng', 'Chiếm dụng sạch', 'Chiếm dụng chưa dọn']" :key="st" class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">{{ st }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              <!-- TANG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('tang')">
                  <span>Tầng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'tang'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[120px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="f in [4,5,6,7,8,9,10,11]" :key="f" class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">Tầng {{ f }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle hover:bg-slate-200/50 transition-colors">Phòng</th>
              
              <!-- LOAI PHONG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-24 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('loaiphong')">
                  <span class="whitespace-normal leading-tight">Loại<br/>phòng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'loaiphong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[240px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="t in ['Superior Double', 'Superior Twin', 'Superior Triple', 'Deluxe Double City view', 'Deluxe Twin City View', 'Deluxe Double with Balcony', 'Deluxe Twin with Balcony', 'Family City View', 'Suite', 'DỰ PHÒNG']" :key="t" class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">{{ t }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              <!-- DANG PHONG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-24 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('dangphong')">
                  <span class="whitespace-normal leading-tight">Dạng<br/>phòng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'dangphong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[120px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="t in ['Double', 'Twin', 'Triple', 'Family', 'King']" :key="t" class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">{{ t }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <!-- NHAN PHONG TRE -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-20 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('nhanphongtre')">
                  <span class="whitespace-normal leading-tight">Nhận<br/>phòng<br/>trễ</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'nhanphongtre'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[150px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">Nhận phòng trễ</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-20 whitespace-normal leading-tight hover:bg-slate-200/50 transition-colors">Chuyển<br/>phòng<br/>kế hoạch</th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-16 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" @click="toggleFilterMenu('themgiuong')">
                  <span class="whitespace-normal leading-tight">Thêm<br/>giường</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'themgiuong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[140px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">Thêm giường</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-16 whitespace-normal leading-tight hover:bg-slate-200/50 transition-colors">Yêu<br/>cầu ĐB</th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors min-w-[150px]">
                <div class="flex items-center justify-between cursor-pointer" @click="toggleFilterMenu('tenkhach')">
                  <span>Tên khách</span>
                  <svg class="w-3.5 h-3.5 text-slate-400 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'tenkhach'" class="absolute top-full right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[200px] p-3 normal-case tracking-normal">
                  <input type="text" placeholder="Tìm tên khách hàng..." class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 mb-3 placeholder-slate-400 shadow-sm transition-all" />
                  <div class="flex items-center gap-2">
                    <button class="flex-1 flex items-center justify-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white px-3 py-1.5 rounded-md cursor-pointer border-none font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                      Tìm kiếm
                    </button>
                    <button class="flex-1 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-3 py-1.5 rounded-md cursor-pointer font-medium transition-colors">Reset</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-between cursor-pointer gap-2" @click="toggleFilterMenu('madk')">
                  <span>Mã ĐK</span>
                  <svg class="w-3 h-3 text-slate-400 cursor-pointer shrink-0" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'madk'" class="absolute top-full right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[140px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="id in ['No BKK', '1989', '2154', '3170', '3171', '3289', '3593', '3824']" :key="id" class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">{{ id }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 align-middle min-w-[180px] hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-between">
                  <span>Tên đăng ký</span>
                </div>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle whitespace-normal w-20 leading-tight hover:bg-slate-200/50 transition-colors">Ngày<br/>đến</th>
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle whitespace-normal w-20 leading-tight hover:bg-slate-200/50 transition-colors">Ngày đi</th>
              
              <th class="px-3 py-2.5 align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors min-w-[180px]">
                <div class="flex items-center justify-between cursor-pointer" @click="toggleFilterMenu('congty')">
                  <span>Công ty</span>
                  <svg class="w-3 h-3 text-slate-400 cursor-pointer" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="dropdown-fade">
                <div v-if="activeFilterMenu === 'congty'" class="absolute top-full right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[240px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto custom-scrollbar">
                    <label v-for="c in ['PEGAS', 'FUN & SUN TRAVEL', 'ANEX TOUR', 'GREEN TRAVEL GROUP', 'TRAVEL CONCIERGE - (COMMITMENT)', 'TRIP.COM', 'SELFIE TRAVEL', 'ODEON TOURS']" :key="c" class="flex items-center gap-2.5 cursor-pointer hover:bg-sky-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">{{ c }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors">Reset</button>
                    <button class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
            </tr>
          </thead>
          
          <tbody class="divide-y divide-slate-100">
            <tr 
              v-for="task in tasks" 
              :key="task.id" 
              class="group transition-colors duration-150"
              :class="task.selected ? 'bg-[#f0f8ff] hover:bg-[#e0f0fe]' : (task.isOccupied ? 'bg-[#e0f2fe] hover:bg-[#bae6fd]' : 'bg-white hover:bg-slate-50/80')"
            >
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                <input type="checkbox" v-model="task.selected" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 bg-white cursor-pointer" />
              </td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                <div v-if="task.ttDk === 'red'" class="w-4 h-4 rounded-full bg-rose-500 mx-auto shadow-sm ring-2 ring-white"></div>
                <div v-if="task.ttDk === 'red-green'" class="w-4 h-4 rounded-full mx-auto shadow-sm ring-2 ring-white" style="background: linear-gradient(135deg, #f43f5e 50%, #10b981 50%);"></div>
              </td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                <div class="flex items-center justify-center p-1.5 bg-white rounded-md shadow-sm border border-slate-200 w-8 h-8 mx-auto text-slate-500 hover:text-sky-500 transition-colors cursor-pointer group-hover:border-sky-200">
                  <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.36,2.72L20.78,4.14L15.06,9.85C16.13,11.39 16.28,13.24 15.38,14.44L9.06,8.12C10.26,7.22 12.11,7.37 13.65,8.44L19.36,2.72M5.93,17.57C3.92,15.56 2.69,13.16 2.35,10.92L7.23,8.83L14.67,16.27L12.58,21.15C10.34,20.81 7.94,19.58 5.93,17.57Z" />
                  </svg>
                </div>
              </td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-semibold text-slate-700">{{ task.floor }}</td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-bold text-sky-700 text-[14px]">{{ task.room }}</td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-medium text-slate-600">{{ task.type }}</td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle text-slate-600">{{ task.category }}</td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle"></td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle"></td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle"></td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle"></td>
              <td class="px-3 py-2 border-r border-slate-100 align-middle font-semibold text-slate-800">{{ task.guestName }}</td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-mono font-medium text-slate-600">{{ task.regCode }}</td>
              <td class="px-3 py-2 border-r border-slate-100 align-middle text-slate-700">{{ task.regName }}</td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle whitespace-normal font-medium text-slate-700 leading-relaxed" v-html="formatMultilineDate(task.arrive)"></td>
              <td class="px-3 py-2 border-r border-slate-100 text-center align-middle whitespace-normal font-medium text-slate-700 leading-relaxed" v-html="formatMultilineDate(task.depart)"></td>
              <td class="px-3 py-2 align-middle font-medium text-slate-700">{{ task.company }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
