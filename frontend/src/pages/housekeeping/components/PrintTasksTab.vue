<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Printer, FileSpreadsheet } from '@lucide/vue'

const route = useRoute()

// Mock Data with extra fields for filters
const tasks = ref([
  { id: 1, ttDk: '', floor: 4, room: '401', type: 'DLXD', category: 'Double', guestName: '', regCode: '', regName: '', arrive: '', depart: '', company: '', isOccupied: false, selected: false, status: 'Phòng chưa dọn', vip: false, assignee: '', lateCheckIn: false, extraBed: false },
  { id: 2, ttDk: 'red', floor: 4, room: '402', type: 'DLXTB', category: 'Twin', guestName: 'Ms.IVANOVA DARIA', regCode: '5197', regName: 'PEGAS - 10660748', arrive: '04-06-2026', depart: '19-06-2026', company: 'PEGAS', isOccupied: true, selected: false, status: 'Chiếm dụng chưa dọn', vip: true, assignee: 'Nguyễn Văn A', lateCheckIn: true, extraBed: false },
  { id: 3, ttDk: '', floor: 4, room: '403', type: 'DLXTB', category: 'Twin', guestName: 'Mr.Guest 1', regCode: '5407', regName: 'FUN & SUN TRAVEL - 11751612', arrive: '14-06-2026', depart: '23-06-2026', company: 'FUN & SUN TRAVEL', isOccupied: true, selected: false, status: 'Chiếm dụng sạch', vip: false, assignee: 'Trần Thị B', lateCheckIn: false, extraBed: true },
  { id: 4, ttDk: '', floor: 4, room: '404', type: 'SUPT', category: 'Twin', guestName: '', regCode: '', regName: '', arrive: '', depart: '', company: '', isOccupied: false, selected: false, status: 'Phòng sẵn sàng', vip: false, assignee: '', lateCheckIn: false, extraBed: false },
  { id: 5, ttDk: '', floor: 4, room: '405', type: 'FAM', category: 'Family', guestName: 'Mr.Guest 1', regCode: '5408', regName: 'FUN & SUN TRAVEL - 11751651', arrive: '14-06-2026', depart: '23-06-2026', company: 'FUN & SUN TRAVEL', isOccupied: true, selected: false, status: 'Chiếm dụng chưa dọn', vip: true, assignee: 'Nguyễn Văn A', lateCheckIn: false, extraBed: false },
  { id: 6, ttDk: '', floor: 4, room: '406', type: 'SUPT', category: 'Twin', guestName: 'Mr.Guest 1', regCode: '5330', regName: 'ANEX TOUR - 112459435', arrive: '09-06-2026', depart: '18-06-2026', company: 'ANEX TOUR', isOccupied: true, selected: false, status: 'Chiếm dụng sẵn sàng', vip: false, assignee: 'Trần Thị B', lateCheckIn: false, extraBed: false },
  { id: 7, ttDk: '', floor: 4, room: '407', type: 'SUPTR', category: 'Triple', guestName: 'Mr.Guest 1', regCode: '5041', regName: 'GREEN TRAVEL GROUP - 404407', arrive: '10-06-2026', depart: '20-06-2026', company: 'GREEN TRAVEL GROUP', isOccupied: true, selected: false, status: 'Chiếm dụng sạch', vip: true, assignee: 'Nguyễn Văn A', lateCheckIn: true, extraBed: true },
  { id: 8, ttDk: '', floor: 4, room: '408', type: 'SUPD', category: 'Double', guestName: '', regCode: '', regName: '', arrive: '', depart: '', company: '', isOccupied: false, selected: false, status: 'Phòng chưa dọn', vip: false, assignee: '', lateCheckIn: false, extraBed: false },
  { id: 9, ttDk: '', floor: 4, room: '409', type: 'SUPTR', category: 'Triple', guestName: 'Mr.Guest 1', regCode: '5299', regName: 'TRAVEL CONCIERGE - 2635589 (COMMITMENT)', arrive: '08-06-2026', depart: '22-06-2026', company: 'TRAVEL CONCIERGE - (COMMITMENT)', isOccupied: true, selected: false, status: 'Chiếm dụng chưa dọn', vip: false, assignee: 'Trần Thị B', lateCheckIn: false, extraBed: false },
  { id: 10, ttDk: 'red-green', floor: 4, room: '410', type: 'SUPT', category: 'Twin', guestName: 'Mr.RYEOJIN JUNG', regCode: '5200', regName: 'RYEOJIN JUNG / Trip.Com - 1400826237533131', arrive: '14-06-2026', depart: '19-06-2026', company: 'TRIP.COM', isOccupied: true, selected: false, status: 'Chiếm dụng sạch', vip: true, assignee: 'Nguyễn Văn A', lateCheckIn: false, extraBed: false },
])

const filterState = ref({
  ttdk: [],
  ttphong: [],
  tang: [],
  loaiphong: [],
  dangphong: [],
  nhanphongtre: false,
  themgiuong: false,
  tenkhach: '',
  madk: [],
  congty: [],
  dateRange: { start: '', end: '' },
  assignee: '',
  quickFilter: ''
})

onMounted(() => {
  if (route.query.quickFilter) {
    filterState.value.quickFilter = route.query.quickFilter
  }
})

watch(() => route.query.quickFilter, (newVal) => {
  filterState.value.quickFilter = newVal || ''
})

const isLoading = ref(false)

const triggerSearchLoading = () => {
  isLoading.value = true
  setTimeout(() => {
    isLoading.value = false
  }, 400)
}

// Watch filters to trigger skeleton loader simulation
watch(filterState, () => {
  triggerSearchLoading()
}, { deep: true })

const selectAll = computed({
  get() {
    const active = filteredTasks.value
    return active.length > 0 && active.every(t => t.selected)
  },
  set(val) {
    filteredTasks.value.forEach(t => t.selected = val)
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

const parseDate = (dStr) => {
  if (!dStr) return null
  const parts = dStr.split('-')
  if (parts.length === 3) {
    return new Date(parts[2], parts[1] - 1, parts[0])
  }
  return null
}

const filteredTasks = computed(() => {
  return tasks.value.filter(task => {
    // 1. TTĐK
    if (filterState.value.ttdk.length > 0) {
      const match = filterState.value.ttdk.some(filter => {
        if (filter === 'Phòng ở') return task.isOccupied
        if (filter === 'Phòng đi') return task.ttDk === 'red' || task.ttDk === 'red-green'
        if (filter === 'Phòng đến') return task.ttDk === 'red-green'
        if (filter === 'Khách lẻ') return task.guestName !== '' && !task.company
        return false
      })
      if (!match) return false
    }

    // 2. TT Phòng
    if (filterState.value.ttphong.length > 0) {
      if (!filterState.value.ttphong.includes(task.status)) return false
    }

    // 3. Tầng
    if (filterState.value.tang.length > 0) {
      if (!filterState.value.tang.includes(task.floor)) return false
    }

    // 4. Loại phòng
    if (filterState.value.loaiphong.length > 0) {
      const typeMap = {
        'DLXD': 'Deluxe Double City view',
        'DLXTB': 'Deluxe Twin City View',
        'SUPT': 'Superior Twin',
        'FAM': 'Family City View',
        'SUPTR': 'Superior Triple',
        'SUPD': 'Superior Double'
      }
      const mappedType = typeMap[task.type] || ''
      if (!filterState.value.loaiphong.includes(mappedType)) return false
    }

    // 5. Dạng phòng
    if (filterState.value.dangphong.length > 0) {
      if (!filterState.value.dangphong.includes(task.category)) return false
    }

    // 6. Nhận phòng trễ
    if (filterState.value.nhanphongtre && !task.lateCheckIn) return false

    // 7. Thêm giường
    if (filterState.value.themgiuong && !task.extraBed) return false

    // 8. Tên khách
    if (filterState.value.tenkhach) {
      const kw = filterState.value.tenkhach.toLowerCase()
      if (!task.guestName.toLowerCase().includes(kw)) return false
    }

    // 9. Mã ĐK
    if (filterState.value.madk.length > 0) {
      if (!filterState.value.madk.includes(task.regCode)) return false
    }

    // 10. Công ty
    if (filterState.value.congty.length > 0) {
      if (!filterState.value.congty.includes(task.company)) return false
    }

    // 11. Nhân viên phụ trách
    if (filterState.value.assignee && task.assignee !== filterState.value.assignee) return false

    // 12. Khoảng ngày đến/đi
    if (filterState.value.dateRange.start || filterState.value.dateRange.end) {
      const start = filterState.value.dateRange.start ? new Date(filterState.value.dateRange.start) : null
      const end = filterState.value.dateRange.end ? new Date(filterState.value.dateRange.end) : null
      const arrDate = parseDate(task.arrive)
      const depDate = parseDate(task.depart)
      
      let matches = false
      if (arrDate) {
        if (start && arrDate < start) {}
        else if (end && arrDate > end) {}
        else { matches = true }
      }
      if (depDate) {
        if (start && depDate < start) {}
        else if (end && depDate > end) {}
        else { matches = true }
      }
      if (!matches) return false
    }

    // 13. Bộ lọc nhanh
    if (filterState.value.quickFilter) {
      if (filterState.value.quickFilter === 'need_clean' && !task.status.includes('chưa dọn')) return false
      if (filterState.value.quickFilter === 'checkout_today' && task.depart !== '19-06-2026') return false
      if (filterState.value.quickFilter === 'vip' && !task.vip) return false
    }

    return true
  })
})

const hasActiveFilters = computed(() => {
  return filterState.value.ttdk.length > 0 ||
         filterState.value.ttphong.length > 0 ||
         filterState.value.tang.length > 0 ||
         filterState.value.loaiphong.length > 0 ||
         filterState.value.dangphong.length > 0 ||
         filterState.value.nhanphongtre ||
         filterState.value.themgiuong ||
         filterState.value.tenkhach !== '' ||
         filterState.value.madk.length > 0 ||
         filterState.value.congty.length > 0 ||
         filterState.value.dateRange.start !== '' ||
         filterState.value.dateRange.end !== '' ||
         filterState.value.assignee !== '' ||
         filterState.value.quickFilter !== '';
})

const resetAllFilters = () => {
  filterState.value = {
    ttdk: [],
    ttphong: [],
    tang: [],
    loaiphong: [],
    dangphong: [],
    nhanphongtre: false,
    themgiuong: false,
    tenkhach: '',
    madk: [],
    congty: [],
    dateRange: { start: '', end: '' },
    assignee: '',
    quickFilter: ''
  }
}

const toggleQuickFilter = (val) => {
  if (filterState.value.quickFilter === val) {
    filterState.value.quickFilter = ''
  } else {
    filterState.value.quickFilter = val
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
          <Printer class="w-5 h-5 text-[var(--hk-primary-dark)]" />
          In Phân Công Phòng
        </h2>
        <div class="flex items-center gap-3">
          <button class="btn-secondary px-4 py-2 rounded-lg flex items-center gap-2 text-[13px] font-semibold shadow-sm cursor-pointer">
            <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
            Xuất Excel
          </button>
          <button class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 text-[13px] font-semibold shadow-sm cursor-pointer">
            <Printer class="w-4 h-4" stroke-width="2.5" />
            In Danh Sách
          </button>
        </div>
      </div>

      <!-- Filters Sub-toolbar -->
      <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 shrink-0 z-20">
        <!-- Left: Quick filter chips -->
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mr-1">Bộ lọc nhanh:</span>
          <button 
            v-for="chip in [
              { label: 'Phòng chưa dọn', value: 'need_clean', icon: '🧹' },
              { label: 'Check-out hôm nay (19/06)', value: 'checkout_today', icon: '🚪' },
              { label: 'Phòng VIP', value: 'vip', icon: '👑' }
            ]" 
            :key="chip.value"
            @click="toggleQuickFilter(chip.value)"
            class="px-3 py-1.5 rounded-full text-[12px] font-medium transition-all duration-200 flex items-center gap-1.5 border cursor-pointer"
            :class="filterState.quickFilter === chip.value 
              ? 'bg-[var(--hk-primary-light)] text-sky-850 border-[var(--hk-primary)] shadow-sm scale-[1.02] font-semibold' 
              : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:border-slate-300'"
          >
            <span>{{ chip.icon }}</span>
            <span>{{ chip.label }}</span>
          </button>
          <button 
            v-if="hasActiveFilters" 
            @click="resetAllFilters" 
            class="text-[12px] text-rose-500 hover:text-rose-600 font-semibold px-2 py-1 transition-colors flex items-center gap-1 cursor-pointer"
          >
            Clear all
          </button>
        </div>

        <!-- Right: Date range & Assignee -->
        <div class="flex items-center gap-3 flex-wrap">
          <!-- Date range picker -->
          <div class="flex items-center gap-1.5">
            <span class="text-[12px] font-medium text-slate-500">Thời gian:</span>
            <input 
              type="date" 
              v-model="filterState.dateRange.start" 
              class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all shadow-sm" 
            />
            <span class="text-[12px] text-slate-400">—</span>
            <input 
              type="date" 
              v-model="filterState.dateRange.end" 
              class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all shadow-sm" 
            />
          </div>

          <!-- Assignee -->
          <div class="flex items-center gap-1.5">
            <span class="text-[12px] font-medium text-slate-500">Nhân viên:</span>
            <select 
              v-model="filterState.assignee" 
              class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all shadow-sm cursor-pointer min-w-[130px]"
            >
              <option value="">Tất cả nhân viên</option>
              <option value="Nguyễn Văn A">Nguyễn Văn A</option>
              <option value="Trần Thị B">Trần Thị B</option>
              <option value="Lê Văn C">Lê Văn C</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Main Table -->
      <div class="flex-1 overflow-auto hk-scroll">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max text-[13px]">
          <thead class="sticky top-0 z-10 bg-slate-100/95 backdrop-blur-sm shadow-sm">
            <tr class="text-slate-600 font-bold uppercase tracking-wider text-[11px] border-b border-slate-300">
              <th class="px-3 py-2.5 border-r border-slate-200 text-center w-10 align-middle">
                <input type="checkbox" v-model="selectAll" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
              </th>
              <!-- Column Filters -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.ttdk.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('ttdk')">
                  <span>TTĐK</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'ttdk'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[150px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="opt in ['Khách lẻ', 'Phòng ở', 'Phòng đến', 'Phòng đi']" :key="opt" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="opt" v-model="filterState.ttdk" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">{{ opt }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.ttdk = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              <!-- TTPHONG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.ttphong.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('ttphong')">
                  <span class="leading-tight">TT<br/>Phòng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'ttphong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[200px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto hk-scroll">
                    <label v-for="st in ['Phòng sẵn sàng', 'Phòng sạch', 'Phòng chưa dọn', 'Phòng OOO', 'Phòng OOS', 'Ưu tiên', 'Ưu tiên tính phí', 'Chiếm dụng sẵn sàng', 'Chiếm dụng sạch', 'Chiếm dụng chưa dọn']" :key="st" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="st" v-model="filterState.ttphong" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium text-[12px]">{{ st }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.ttphong = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              <!-- TANG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.tang.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('tang')">
                  <span>Tầng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'tang'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[120px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="f in [4,5,6,7,8,9,10,11]" :key="f" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="f" v-model="filterState.tang" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                      <span class="font-medium">Tầng {{ f }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.tang = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle hover:bg-slate-200/50 transition-colors">Phòng</th>
              
              <!-- LOAI PHONG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-24 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.loaiphong.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('loaiphong')">
                  <span class="whitespace-normal leading-tight">Loại<br/>phòng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'loaiphong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[240px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="t in ['Superior Double', 'Superior Twin', 'Superior Triple', 'Deluxe Double City view', 'Deluxe Twin City View', 'Deluxe Double with Balcony', 'Deluxe Twin with Balcony', 'Family City View', 'Suite', 'DỰ PHÒNG']" :key="t" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="t" v-model="filterState.loaiphong" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium text-[12px]">{{ t }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.loaiphong = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              <!-- DANG PHONG -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-24 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.dangphong.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('dangphong')">
                  <span class="whitespace-normal leading-tight">Dạng<br/>phòng</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'dangphong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[120px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="t in ['Double', 'Twin', 'Triple', 'Family', 'King']" :key="t" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="t" v-model="filterState.dangphong" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium">{{ t }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.dangphong = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <!-- NHAN PHONG TRE -->
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-20 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.nhanphongtre ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('nhanphongtre')">
                  <span class="whitespace-normal leading-tight">Nhận<br/>phòng<br/>trễ</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'nhanphongtre'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[150px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" v-model="filterState.nhanphongtre" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium text-[12px]">Nhận phòng trễ</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.nhanphongtre = false">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-20 whitespace-normal leading-tight hover:bg-slate-200/50 transition-colors">Chuyển<br/>phòng<br/>kế hoạch</th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-16 relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-center gap-1.5 cursor-pointer" :class="filterState.themgiuong ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('themgiuong')">
                  <span class="whitespace-normal leading-tight">Thêm<br/>giường</span>
                  <svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'themgiuong'" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[140px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" v-model="filterState.themgiuong" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium text-[12px]">Thêm giường</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.themgiuong = false">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 text-center align-middle w-16 whitespace-normal leading-tight hover:bg-slate-200/50 transition-colors">Yêu<br/>cầu ĐB</th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors min-w-[150px]">
                <div class="flex items-center justify-between cursor-pointer" :class="filterState.tenkhach !== '' ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('tenkhach')">
                  <span>Tên khách</span>
                  <svg class="w-3.5 h-3.5 text-slate-400 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'tenkhach'" class="absolute top-full right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[200px] p-3 normal-case tracking-normal">
                  <input type="text" data-hk-search v-model="filterState.tenkhach" placeholder="Tìm tên khách hàng..." class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-450 mb-3 placeholder-slate-400 shadow-sm transition-all" />
                  <div class="flex items-center gap-2">
                    <button class="flex-1 flex items-center justify-center gap-1.5 bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-3 py-1.5 rounded-md cursor-pointer border-none font-medium transition-colors shadow-sm" @click.stop="activeFilterMenu = null">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                      Tìm kiếm
                    </button>
                    <button class="flex-1 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-3 py-1.5 rounded-md cursor-pointer font-medium transition-colors" @click.stop="filterState.tenkhach = ''">Reset</button>
                  </div>
                </div>
              </Transition>
              </th>
              
              <th class="px-3 py-2.5 border-r border-slate-200 align-middle relative filter-dropdown-wrapper hover:bg-slate-200/50 transition-colors">
                <div class="flex items-center justify-between cursor-pointer gap-2" :class="filterState.madk.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('madk')">
                  <span>Mã ĐK</span>
                  <svg class="w-3 h-3 text-slate-400 cursor-pointer shrink-0" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'madk'" class="absolute top-full right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[140px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto">
                    <label v-for="id in ['No BKK', '1989', '2154', '3170', '3171', '3289', '3593', '3824', '5197', '5407', '5408', '5330', '5041', '5299', '5200']" :key="id" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="id" v-model="filterState.madk" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium text-[12px]">{{ id }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.madk = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
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
                <div class="flex items-center justify-between cursor-pointer" :class="filterState.congty.length > 0 ? 'text-[var(--hk-primary-dark)] font-black' : ''" @click="toggleFilterMenu('congty')">
                  <span>Công ty</span>
                  <svg class="w-3 h-3 text-slate-400 cursor-pointer" fill="currentColor" viewBox="0 0 320 512"><path d="M137.4 41.4c12.5-12.5 32.8-12.5 45.3 0l128 128c9.2 9.2 11.9 22.9 6.9 34.9s-16.6 19.8-29.6 19.8H32c-12.9 0-24.6-7.8-29.6-19.8s-2.2-25.7 6.9-34.9l128-128zm0 429.3l-128-128c-9.2-9.2-11.9-22.9-6.9-34.9s16.6-19.8 29.6-19.8H288c12.9 0 24.6 7.8 29.6 19.8s2.2 25.7-6.9 34.9l-128 128c-12.5 12.5-32.8 12.5-45.3 0z"/></svg>
                </div>
                <Transition name="hk-dropdown">
                <div v-if="activeFilterMenu === 'congty'" class="absolute top-full right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg z-50 text-left font-normal text-[13px] text-slate-700 w-max min-w-[240px] overflow-hidden normal-case tracking-normal">
                  <div class="p-2 flex flex-col gap-1 max-h-60 overflow-y-auto hk-scroll">
                    <label v-for="c in ['PEGAS', 'FUN & SUN TRAVEL', 'ANEX TOUR', 'GREEN TRAVEL GROUP', 'TRAVEL CONCIERGE - (COMMITMENT)', 'TRIP.COM', 'SELFIE TRAVEL', 'ODEON TOURS']" :key="c" class="flex items-center gap-2.5 cursor-pointer hover:bg-slate-50 p-2 rounded-md transition-colors">
                      <input type="checkbox" :value="c" v-model="filterState.congty" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 shrink-0" />
                      <span class="font-medium text-[12px]">{{ c }}</span>
                    </label>
                  </div>
                  <div class="bg-slate-50 border-t border-slate-100 p-2 flex items-center justify-between">
                    <button class="text-slate-500 hover:text-slate-700 font-medium px-2 py-1 transition-colors cursor-pointer" @click.stop="filterState.congty = []">Reset</button>
                    <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-4 py-1.5 rounded-md font-medium transition-colors shadow-sm cursor-pointer" @click.stop="activeFilterMenu = null">OK</button>
                  </div>
                </div>
              </Transition>
              </th>
            </tr>
          </thead>
          
          <tbody class="divide-y divide-slate-100">
            <!-- Loading Skeleton rows -->
            <template v-if="isLoading">
              <tr v-for="i in 5" :key="'skeleton-'+i" class="animate-pulse">
                <td class="px-3 py-4 text-center"><div class="h-4 w-4 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-4 bg-slate-200 rounded-full mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-5 w-5 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-12 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4"></td>
                <td class="px-3 py-4"></td>
                <td class="px-3 py-4"></td>
                <td class="px-3 py-4"></td>
                <td class="px-3 py-4"><div class="h-4 w-32 bg-slate-200 rounded"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-12 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4"><div class="h-4 w-40 bg-slate-200 rounded"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
                <td class="px-3 py-4"><div class="h-4 w-28 bg-slate-200 rounded"></div></td>
              </tr>
            </template>

            <!-- No results row -->
            <template v-else-if="filteredTasks.length === 0">
              <tr>
                <td colspan="17" class="px-3 py-16 text-center text-slate-400 bg-white">
                  <div class="flex flex-col items-center justify-center gap-3">
                    <span class="text-4xl">🔍</span>
                    <p class="font-medium text-slate-500 text-[14px]">Không tìm thấy phòng phân công phù hợp</p>
                    <button @click="resetAllFilters" class="mt-1 text-[12px] bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-1.5 rounded-lg transition-colors border border-slate-350 cursor-pointer">
                      Xóa tất cả bộ lọc
                    </button>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Actual tasks list -->
            <template v-else>
              <tr 
                v-for="task in filteredTasks" 
                :key="task.id" 
                class="group transition-colors duration-150"
                :class="task.selected ? 'row-selected' : (task.isOccupied ? 'row-occupied' : 'row-default')"
              >
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                  <input type="checkbox" v-model="task.selected" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 bg-white cursor-pointer" />
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                  <div v-if="task.ttDk === 'red'" class="w-4 h-4 rounded-full bg-rose-500 mx-auto shadow-sm ring-2 ring-white hk-pulse-dot"></div>
                  <div v-if="task.ttDk === 'red-green'" class="w-4 h-4 rounded-full mx-auto shadow-sm ring-2 ring-white hk-pulse-dot animate-pulse-blend" style="background: linear-gradient(135deg, #f43f5e 50%, #10b981 50%);"></div>
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                  <div class="flex items-center justify-center p-1.5 bg-white rounded-md shadow-sm border border-slate-200 w-8 h-8 mx-auto text-slate-500 hover:text-[var(--hk-primary-dark)] transition-colors cursor-pointer group-hover:border-[var(--hk-primary)]">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M19.36,2.72L20.78,4.14L15.06,9.85C16.13,11.39 16.28,13.24 15.38,14.44L9.06,8.12C10.26,7.22 12.11,7.37 13.65,8.44L19.36,2.72M5.93,17.57C3.92,15.56 2.69,13.16 2.35,10.92L7.23,8.83L14.67,16.27L12.58,21.15C10.34,20.81 7.94,19.58 5.93,17.57Z" />
                    </svg>
                  </div>
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-semibold text-slate-700">{{ task.floor }}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-bold text-[var(--hk-primary-dark)] text-[14px]">{{ task.room }}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-medium text-slate-600">{{ task.type }}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle text-slate-600">{{ task.category }}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle text-center">
                  <span v-if="task.lateCheckIn" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-250 animate-pulse">LATE</span>
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle"></td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle">
                  <span v-if="task.extraBed" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-250">EXTRA</span>
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle"></td>
                <td class="px-3 py-2 border-r border-slate-100 align-middle font-semibold text-slate-800">
                  <div class="flex items-center gap-1.5">
                    <span>{{ task.guestName }}</span>
                    <span v-if="task.vip" class="text-[11px]" title="VVIP">👑</span>
                  </div>
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle font-mono font-medium text-slate-600">{{ task.regCode }}</td>
                <td class="px-3 py-2 border-r border-slate-100 align-middle text-slate-700">{{ task.regName }}</td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle whitespace-normal font-medium text-slate-700 leading-relaxed" v-html="formatMultilineDate(task.arrive)"></td>
                <td class="px-3 py-2 border-r border-slate-100 text-center align-middle whitespace-normal font-medium text-slate-700 leading-relaxed" v-html="formatMultilineDate(task.depart)"></td>
                <td class="px-3 py-2 align-middle font-medium text-slate-700">
                  <div class="flex items-center justify-between gap-2">
                    <span>{{ task.company }}</span>
                    <span v-if="task.assignee" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                      👤 {{ task.assignee }}
                    </span>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped component variables and button states */
.btn-primary {
  background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5));
  color: #0f172a;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
}
.btn-primary:hover {
  transform: translateY(-1px) scale(1.02);
  box-shadow: 0 4px 12px rgba(151, 213, 255, 0.4);
  filter: brightness(1.03);
}
.btn-primary:active {
  transform: translateY(0);
}

.btn-secondary {
  background: white;
  border: 1px solid #cbd5e1;
  color: #475569;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  transform: translateY(-1px) scale(1.02);
}

/* Selected & occupied rows using approved color scheme */
.row-occupied {
  background-color: rgba(151, 213, 255, 0.12) !important;
}
.row-occupied:hover {
  background-color: rgba(151, 213, 255, 0.20) !important;
}
.row-selected {
  background-color: rgba(151, 213, 255, 0.25) !important;
}
.row-selected:hover {
  background-color: rgba(151, 213, 255, 0.32) !important;
}
.row-default {
  background-color: white;
}
.row-default:hover {
  background-color: rgba(151, 213, 255, 0.04);
}

/* Subtle status dots pulse animation */
.hk-pulse-dot {
  position: relative;
}
.hk-pulse-dot::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 9999px;
  background: inherit;
  opacity: 0.65;
  animation: hkPulse 2s infinite;
}
@keyframes hkPulse {
  0% { transform: scale(1); opacity: 0.65; }
  100% { transform: scale(2.3); opacity: 0; }
}

.animate-pulse-blend {
  animation: pulseBlend 2s infinite;
}
@keyframes pulseBlend {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

/* Dropdown transition */
.hk-dropdown-enter-active {
  animation: hkDropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-dropdown-leave-active {
  animation: hkDropIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) reverse;
}
@keyframes hkDropIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

