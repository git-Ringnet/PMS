<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Search, Plus, FileSpreadsheet, ClipboardList, ChevronLeft, ChevronRight, X, Trash2, Save, BarChart2, ChevronDown, ChevronUp, Image } from '@lucide/vue'

const route = useRoute()

const showAddModal = ref(false)
const showCheckModal = ref(false)
const showEditModal = ref(false)
const showProductSearch = ref(false)
const showAddProductCheckModal = ref(false)
const editTabName = ref('')

const mbExpanded = ref(true)
const minibarExpanded = ref(true)

const activeTab = ref('minibar')

// Mock Data
const currentMonth = ref('2026-06')
const minibarCategories = ref([
  {
    name: 'Minibar',
    isExpanded: true,
    subgroups: [
      {
        name: 'Minibar',
        isExpanded: true,
        items: [
          { id: 1, name: 'Nước suối Aqua 500ml', startStock: 1000, sln: 500, slx: 40, slc: 0, finalStock: 1460, days: {} },
          { id: 2, name: 'Nước suối Aqua 1,5l', startStock: 1200, sln: 0, slx: 0, slc: 0, finalStock: 1200, days: {} }
        ]
      }
    ]
  }
])

const filterState = ref({
  search: '',
  groups: [],
  warningOnly: false,
  sortBy: 'name_asc',
  activity: 'all'
})

onMounted(() => {
  if (route.query.warningOnly === 'true') {
    filterState.value.warningOnly = true
  }
})

watch(() => route.query.warningOnly, (newVal) => {
  filterState.value.warningOnly = (newVal === 'true')
})

const isLoading = ref(false)
const triggerSearchLoading = () => {
  isLoading.value = true
  setTimeout(() => {
    isLoading.value = false
  }, 400)
}

watch([filterState, activeTab], () => {
  triggerSearchLoading()
}, { deep: true })

const showGroupDropdown = ref(false)

const selectedGroupLabel = computed(() => {
  if (filterState.value.groups.length === 0) return 'Tất cả'
  if (filterState.value.groups.length === 1) return filterState.value.groups[0]
  return `${filterState.value.groups.length} nhóm`
})

const categories = computed(() => {
  if (activeTab.value !== 'minibar') return []
  
  return minibarCategories.value.map(cat => {
    const subgroups = cat.subgroups.map(sub => {
      let items = sub.items.filter(item => {
        if (filterState.value.search && !item.name.toLowerCase().includes(filterState.value.search.toLowerCase())) return false
        
        // Low stock warning (threshold 1300)
        if (filterState.value.warningOnly && item.finalStock >= 1300) return false

        if (filterState.value.groups.length > 0 && !filterState.value.groups.includes(sub.name)) return false

        const hasActivity = item.sln > 0 || item.slx > 0 || item.slc > 0
        if (filterState.value.activity === 'has_activity' && !hasActivity) return false
        if (filterState.value.activity === 'no_activity' && hasActivity) return false

        return true
      })

      items.sort((a, b) => {
        if (filterState.value.sortBy === 'name_asc') {
          return a.name.localeCompare(b.name)
        }
        if (filterState.value.sortBy === 'name_desc') {
          return b.name.localeCompare(a.name)
        }
        if (filterState.value.sortBy === 'stock_asc') {
          return a.finalStock - b.finalStock
        }
        if (filterState.value.sortBy === 'stock_desc') {
          return b.finalStock - a.finalStock
        }
        return 0
      })

      return {
        ...sub,
        items
      }
    }).filter(sub => sub.items.length > 0)

    return {
      ...cat,
      subgroups
    }
  }).filter(cat => cat.subgroups.length > 0)
})

const openEditModal = (name) => {
  editTabName.value = name;
  showEditModal.value = true;
}

const days = computed(() => {
  if (!currentMonth.value) return []
  const [year, month] = currentMonth.value.split('-')
  const daysInMonth = new Date(year, month, 0).getDate()
  return Array.from({ length: daysInMonth }, (_, i) => i + 1)
})

const handleOutsideClick = (e) => {
  if (!e.target.closest('.group-dropdown-wrapper')) {
    showGroupDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
})

</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative">
    
    <!-- Top Control Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-4 shrink-0">
      <!-- Tabs Section -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-white rounded-t-xl">
        <div class="flex items-center gap-8 pt-2">
          <div class="group flex items-center gap-1.5 cursor-pointer" @click="activeTab = 'minibar'">
            <h2 class="text-[13px] font-black tracking-wide uppercase pb-2 transition-colors relative" :class="activeTab === 'minibar' ? 'text-[var(--hk-primary-dark)]' : 'text-slate-500 hover:text-slate-700'">
              KHO MINIBAR
              <div v-if="activeTab === 'minibar'" class="absolute bottom-0 left-0 w-full h-0.5 bg-[var(--hk-primary-dark)] rounded-t-full"></div>
            </h2>
            <button @click.stop="openEditModal('KHO MINIBAR')" class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 text-slate-400 hover:text-[var(--hk-primary-dark)] hover:bg-slate-50 rounded-full -mt-2 bg-white cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
          </div>
          <div class="group flex items-center gap-1.5 cursor-pointer" @click="activeTab = 'bep'">
            <h2 class="text-[13px] font-black tracking-wide uppercase pb-2 transition-colors relative" :class="activeTab === 'bep' ? 'text-[var(--hk-primary-dark)]' : 'text-slate-500 hover:text-slate-700'">
              KHO BẾP
              <div v-if="activeTab === 'bep'" class="absolute bottom-0 left-0 w-full h-0.5 bg-[var(--hk-primary-dark)] rounded-t-full"></div>
            </h2>
            <button @click.stop="openEditModal('KHO BẾP')" class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 text-slate-400 hover:text-[var(--hk-primary-dark)] hover:bg-slate-50 rounded-full -mt-2 bg-white cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
          </div>
        </div>
        <button @click="showAddModal = true" class="btn-primary flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-lg shadow-sm cursor-pointer">
          <Plus class="w-4 h-4" stroke-width="2.5" />
          Thêm Kho
        </button>
      </div>

      <!-- Toolbar Section -->
      <div class="px-5 py-4 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-4">
          <!-- Month Picker -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-500 uppercase tracking-wide">Tháng:</span>
            <input type="month" v-model="currentMonth" class="w-36 text-[12px] font-bold text-slate-705 bg-white border border-slate-350 rounded-lg px-2.5 py-1.5 outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm cursor-pointer" />
          </div>

          <!-- Group Filter -->
          <div class="relative group-dropdown-wrapper">
            <button @click="showGroupDropdown = !showGroupDropdown" class="bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 flex items-center gap-1 hover:bg-slate-50 transition-colors cursor-pointer text-slate-650 font-medium shadow-sm">
              <span>Nhóm: {{ selectedGroupLabel }}</span>
              <ChevronDown class="w-3.5 h-3.5 text-slate-400" />
            </button>
            <Transition name="hk-dropdown">
              <div v-if="showGroupDropdown" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-lg p-2 z-30 min-w-[170px] flex flex-col gap-1">
                <label v-for="g in ['Minibar']" :key="g" class="flex items-center gap-2 hover:bg-slate-50 p-1.5 rounded cursor-pointer font-medium text-slate-700">
                  <input type="checkbox" :value="g" v-model="filterState.groups" class="rounded text-[var(--hk-primary-dark)] focus:ring-[var(--hk-primary)] w-3.5 h-3.5 cursor-pointer" />
                  <span>{{ g }}</span>
                </label>
                <div class="border-t border-slate-100 mt-1 pt-1.5 flex justify-between text-[10px]">
                  <button @click="filterState.groups = []" class="text-slate-500 hover:text-slate-700 font-semibold px-1 py-0.5 cursor-pointer">Reset</button>
                  <button @click="showGroupDropdown = false" class="bg-[var(--hk-primary-dark)] text-white font-semibold px-2 py-0.5 rounded shadow-sm hover:brightness-95 cursor-pointer">OK</button>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Warning Only Switch -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-500">Cảnh báo tồn:</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="filterState.warningOnly" class="sr-only peer" />
              <div class="w-8 h-4.5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-[var(--hk-primary-dark)]"></div>
            </label>
          </div>

          <!-- Sort dropdown -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-500">Sắp xếp:</span>
            <select v-model="filterState.sortBy" class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-705 bg-white focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] cursor-pointer shadow-sm font-medium">
              <option value="name_asc">Tên A-Z</option>
              <option value="name_desc">Tên Z-A</option>
              <option value="stock_asc">Tồn cuối tăng dần</option>
              <option value="stock_desc">Tồn cuối giảm dần</option>
            </select>
          </div>
        </div>
        
        <div class="flex items-center gap-2">
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-4 py-2 rounded-lg transition-all text-xs font-bold flex items-center gap-2 shadow-sm cursor-pointer">
            <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
            Xuất Excel
          </button>
          <button @click="showCheckModal = true" class="btn-primary px-5 py-2 rounded-lg transition-all text-xs font-bold shadow-sm flex items-center gap-2 cursor-pointer">
            <ClipboardList class="w-4 h-4" stroke-width="2.5" />
            Kiểm Kê Định Kỳ
          </button>
        </div>
      </div>

      <!-- Activity filters chips -->
      <div class="px-5 py-2 border-t border-slate-100 flex items-center gap-2 text-xs bg-slate-50/25 rounded-b-xl">
        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mr-1">Phát sinh:</span>
        <button 
          v-for="st in [
            { label: 'Tất cả sản phẩm', value: 'all' },
            { label: 'Có phát sinh', value: 'has_activity' },
            { label: 'Không phát sinh', value: 'no_activity' }
          ]"
          :key="st.value"
          @click="filterState.activity = st.value"
          class="px-2.5 py-1 rounded-full text-xs transition-all duration-200 cursor-pointer border"
          :class="filterState.activity === st.value
            ? 'bg-[var(--hk-primary-light)] text-sky-850 border-[var(--hk-primary)] font-bold shadow-sm'
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
        >
          {{ st.label }}
        </button>
      </div>
    </div>

    <!-- Table Section -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1 hk-scroll">
        <table class="w-full text-left border-collapse text-[13px] whitespace-nowrap min-w-max">
          <thead class="sticky top-0 z-20 backdrop-blur-md bg-white/95">
            <tr class="bg-slate-100 text-slate-750 font-bold border-b border-slate-300 uppercase tracking-wider text-[11px]">
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 w-64 min-w-[256px] text-center align-middle sticky left-0 z-30 shadow-[1px_0_0_0_#e2e8f0] bg-slate-100">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="flex items-center">
                    Sản Phẩm <Search @click="showProductSearch = !showProductSearch" class="w-3.5 h-3.5 inline-block ml-1.5 text-slate-400 cursor-pointer hover:text-[var(--hk-primary-dark)] transition-colors" />
                  </div>
                  <div v-if="showProductSearch" class="relative w-full mt-1 px-2">
                    <Search class="absolute left-4 top-1.5 w-3.5 h-3.5 text-slate-400" />
                    <input type="text" v-model="filterState.search" placeholder="Tìm kiếm sản phẩm..." data-hk-search class="w-full pl-8 pr-3 py-1.5 text-xs font-normal bg-white border border-slate-300 rounded-md focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] outline-none transition-all shadow-sm" />
                  </div>
                </div>
              </th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 w-20 min-w-[80px] text-center align-middle sticky left-[256px] z-30 shadow-[1px_0_0_0_#e2e8f0] bg-slate-100">SLĐK</th>
              <th v-for="day in days" :key="day" colspan="3" class="py-2 px-2 text-center border-r border-slate-200 border-b border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.08)]' : 'bg-slate-50/50'">
                {{ day }}
              </th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLN</th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLX</th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLC</th>
              <th rowspan="2" class="py-3 px-4 border-l border-slate-200 bg-slate-100 text-center align-middle sticky right-0 shadow-[-1px_0_0_0_#e2e8f0] text-[var(--hk-primary-dark)] font-black">Tồn Cuối</th>
            </tr>
            <tr class="bg-slate-100/80 text-slate-500 font-bold border-b border-slate-250 text-[10px] uppercase">
              <template v-for="day in days" :key="'sub'+day">
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.04)]' : 'bg-slate-50/20'">Nhập</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.04)]' : 'bg-slate-50/20'">Xuất</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.04)]' : 'bg-slate-50/20'">Chuyển</th>
              </template>
            </tr>
          </thead>
          
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Skeleton rows -->
            <template v-if="isLoading">
              <tr v-for="i in 3" :key="'skeleton-'+i" class="animate-pulse">
                <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-white shadow-[1px_0_0_0_#e2e8f0]"><div class="h-4 w-40 bg-slate-200 rounded"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200 sticky left-[256px] z-10 bg-white shadow-[1px_0_0_0_#e2e8f0]"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <template v-for="day in days" :key="'skeleton-day-'+day">
                  <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50/10"><div class="h-4 w-6 bg-slate-200/50 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50/10"><div class="h-4 w-6 bg-slate-200/50 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50/10"><div class="h-4 w-6 bg-slate-200/50 rounded mx-auto"></div></td>
                </template>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-white"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-white"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-white"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-4 border-l border-slate-200 sticky right-0 bg-white shadow-[-1px_0_0_0_#e2e8f0]"><div class="h-4 w-12 bg-slate-200 rounded mx-auto"></div></td>
              </tr>
            </template>

            <!-- Empty state check -->
            <tr v-else-if="categories.length === 0">
              <td :colspan="6 + days.length * 3" class="py-20 text-center bg-white text-slate-400">
                <div class="flex flex-col items-center justify-center gap-3">
                  <span class="text-4xl animate-bounce">📦</span>
                  <p class="font-bold text-slate-700 text-sm">Không có dữ liệu tồn kho trùng khớp</p>
                  <p class="text-xs text-slate-550">Thử tắt bộ lọc cảnh báo hoặc thay đổi từ khóa tìm kiếm.</p>
                </div>
              </td>
            </tr>

            <template v-for="(cat, idx) in categories" :key="'cat-'+idx">
              <!-- Parent Category Row -->
              <tr class="bg-slate-50/80 hover:bg-slate-100 font-bold border-b border-slate-200 transition-colors">
                <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-slate-50 flex items-center gap-2 cursor-pointer min-w-[256px] shadow-[1px_0_0_0_#e2e8f0]" @click="cat.isExpanded = !cat.isExpanded">
                  <div class="w-4 h-4 bg-[var(--hk-primary-dark)] flex items-center justify-center text-white text-[12px] leading-none pb-[1.5px] rounded-sm shadow-sm font-bold">{{ cat.isExpanded ? '-' : '+' }}</div>
                  <span class="text-[13px] text-slate-800 uppercase tracking-wide">{{ cat.name }}</span>
                </td>
                <td class="py-2.5 px-2 border-r border-slate-200 sticky left-[256px] z-10 bg-slate-50 min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]"></td>
                <template v-for="day in days" :key="'cat-sub-'+day">
                  <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                  <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                  <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                </template>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50"></td>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50"></td>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50"></td>
                <td class="py-2.5 px-4 border-l border-slate-200 sticky right-0 bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]"></td>
              </tr>
              <!-- Subgroups & Item Rows -->
              <template v-if="cat.isExpanded">
                <template v-for="(sub, subIdx) in cat.subgroups" :key="'sub-'+subIdx">
                  <!-- Subcategory Row -->
                  <tr class="bg-white hover:bg-slate-50 font-bold border-b border-slate-100 transition-colors">
                    <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-white flex items-center gap-2 cursor-pointer min-w-[256px] pl-8 shadow-[1px_0_0_0_#e2e8f0]" @click="sub.isExpanded = !sub.isExpanded">
                      <div class="w-4 h-4 bg-[var(--hk-primary)] flex items-center justify-center text-white text-[12px] leading-none pb-[1.5px] rounded-sm shadow-sm font-bold">{{ sub.isExpanded ? '-' : '+' }}</div>
                      <span class="text-[13px] text-slate-700">{{ sub.name }}</span>
                    </td>
                    <td class="py-2.5 px-2 border-r border-slate-200 sticky left-[256px] z-10 bg-white min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]"></td>
                    <template v-for="day in days" :key="'subcat-sub-'+day">
                      <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                      <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                      <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                    </template>
                    <td class="py-2.5 px-2 border-r border-slate-200 bg-white"></td>
                    <td class="py-2.5 px-2 border-r border-slate-200 bg-white"></td>
                    <td class="py-2.5 px-2 border-r border-slate-200 bg-white"></td>
                    <td class="py-2.5 px-4 border-l border-slate-200 sticky right-0 bg-white shadow-[-1px_0_0_0_#e2e8f0]"></td>
                  </tr>
                  
                  <!-- Items with fade transition -->
                  <TransitionGroup name="row-fade" v-if="sub.isExpanded">
                    <tr v-for="item in sub.items" :key="'item-'+item.id" class="hover:bg-slate-50 transition-colors border-b border-slate-100 group">
                      <td class="py-2.5 px-4 border-r border-slate-200 pl-14 sticky left-0 z-10 bg-white group-hover:bg-slate-50 font-semibold min-w-[256px] text-slate-600 shadow-[1px_0_0_0_#e2e8f0] flex items-center justify-between">
                        <span>{{ item.name }}</span>
                        <!-- Low Stock Indicator Badge -->
                        <span v-if="item.finalStock < 1300" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 border border-rose-200 shrink-0 ml-1">
                          🚨 LOW
                        </span>
                      </td>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 font-semibold sticky left-[256px] z-10 bg-white group-hover:bg-slate-50 min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]">{{ item.startStock || '' }}</td>
                      <template v-for="day in days" :key="'item-day-'+day">
                        <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-500 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-500 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-500 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''"></td>
                      </template>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.sln || '' }}</td>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.slx || '' }}</td>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.slc || '' }}</td>
                      <td class="py-2.5 px-4 border-l border-slate-200 text-right text-[var(--hk-primary-dark)] font-black sticky right-0 bg-white group-hover:bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0] text-sm">{{ item.finalStock ? item.finalStock.toLocaleString() : '' }}</td>
                    </tr>
                  </TransitionGroup>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination Footer -->
    <div class="mt-4 flex items-center justify-between shrink-0">
      <div class="text-[12px] text-slate-550 font-semibold uppercase tracking-wider">Hiển thị dữ liệu kho buồng phòng</div>
      <div class="flex items-center gap-1.5">
        <button class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors shadow-sm cursor-pointer" disabled>
          <ChevronLeft class="w-4 h-4" />
        </button>
        <button class="w-8 h-8 flex items-center justify-center border border-[var(--hk-primary)] bg-[var(--hk-primary)] text-white font-bold rounded-lg shadow-sm">
          1
        </button>
        <button class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors shadow-sm cursor-pointer" disabled>
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Edit Modal -->
    <Transition name="hk-modal">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[400px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <!-- Header -->
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Cập nhật tên kho</h3>
            <button @click="showEditModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
              <X class="w-5 h-5" />
            </button>
          </div>
          <!-- Body -->
          <div class="p-5 flex flex-col gap-2 bg-slate-50">
            <label class="text-[12px] font-bold text-slate-700">Tên kho</label>
            <input type="text" v-model="editTabName" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm" />
          </div>
          <!-- Footer -->
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3">
            <button @click="showEditModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">
              Đóng
            </button>
            <button class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Trash2 class="w-4 h-4" /> Xóa
            </button>
            <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> Lưu
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add Modal -->
    <Transition name="hk-modal">
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[400px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <!-- Header -->
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Thêm Kho Mới</h3>
            <button @click="showAddModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
              <X class="w-5 h-5" />
            </button>
          </div>
          <!-- Body -->
          <div class="p-5 flex flex-col gap-2 bg-slate-50">
            <label class="text-[12px] font-bold text-slate-700">Tên kho</label>
            <input type="text" placeholder="Nhập tên kho..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm" />
          </div>
          <!-- Footer -->
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3 font-semibold">
            <button @click="showAddModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">
              Đóng
            </button>
            <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> Lưu
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Periodic Inventory Check Modal -->
    <Transition name="hk-modal">
      <div v-if="showCheckModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[950px] flex flex-col overflow-hidden transform transition-all max-h-[90vh] border border-slate-200">
          <!-- Header -->
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[16px] uppercase tracking-wide">Kiểm Kê Tồn Kho Định Kỳ</h3>
            <button @click="showCheckModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
              <X class="w-5 h-5" />
            </button>
          </div>
          <!-- Body -->
          <div class="p-5 flex flex-col gap-5 overflow-y-auto bg-slate-50 flex-1 hk-scroll">
            <!-- Top Form -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-5 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Tháng / Năm</label>
                <input type="month" value="2026-06" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm cursor-pointer bg-white" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Mã Kiểm Kê</label>
                <input type="text" value="9" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Kho</label>
                <select disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none appearance-none">
                  <option>KHO MINIBAR</option>
                </select>
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Người Kiểm Kho</label>
                <select disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none appearance-none">
                  <option>Admin</option>
                </select>
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Ghi Chú</label>
                <input type="text" placeholder="Nhập ghi chú..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm bg-white" />
              </div>
            </div>

            <!-- Inventory Items Table -->
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
              <table class="w-full text-left border-collapse text-[12px] whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-600 font-bold">
                  <tr>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">Mã Kiểm Kê</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">Mã SP</th>
                    <th class="py-3 px-4 border-r border-slate-200">Tên SP</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">Đơn Vị</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">Tồn Đầu Kì</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">SL Thực Tế</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">Chênh Lệch</th>
                    <th class="py-3 px-4 text-center">Ghi Chú</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[13px] text-slate-700">
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500 font-mono">9</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500 font-mono">1</td>
                    <td class="py-2 px-4 border-r border-slate-200 font-semibold text-slate-800">Nước suối Aqua 500ml</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center">Chai</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="0" class="w-16 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white" /></td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="1000" class="w-20 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white" /></td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center font-bold text-[var(--hk-primary-dark)]">1000</td>
                    <td class="py-2 px-3"><input type="text" placeholder="..." class="w-full text-[13px] border border-slate-300 rounded-md px-2 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white" /></td>
                  </tr>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500 font-mono">9</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500 font-mono">2</td>
                    <td class="py-2 px-4 border-r border-slate-200 font-semibold text-slate-800">Nước suối Aqua 1,5l</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center">Chai</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="0" class="w-16 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white" /></td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="1200" class="w-20 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white" /></td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center font-bold text-[var(--hk-primary-dark)]">1200</td>
                    <td class="py-2 px-3"><input type="text" placeholder="..." class="w-full text-[13px] border border-slate-300 rounded-md px-2 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-end gap-3 shrink-0">
            <button class="px-5 py-2 flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">
              <BarChart2 class="w-4 h-4 text-slate-550" /> Thống kê
            </button>
            <button class="px-5 py-2 flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">
              <FileSpreadsheet class="w-4 h-4 text-slate-550" /> Xuất Excel
            </button>
            <button @click="showAddProductCheckModal = true" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm ml-auto cursor-pointer">
              <Plus class="w-4 h-4" stroke-width="2.5" /> Thêm SP
            </button>
            <button class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Trash2 class="w-4 h-4" /> Xóa
            </button>
            <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> Lưu
            </button>
          </div>
        </div>
      </div>
    </Transition>
    
    <!-- Thêm Sản Phẩm (Kiểm Kê) Modal -->
    <Transition name="hk-modal">
      <div v-if="showAddProductCheckModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[400px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <!-- Header -->
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Thêm sản phẩm</h3>
            <button @click="showAddProductCheckModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
              <X class="w-5 h-5" />
            </button>
          </div>
          <!-- Body -->
          <div class="p-0 flex flex-col max-h-[60vh] overflow-y-auto bg-slate-50 hk-scroll">
            <table class="w-full text-left border-collapse text-[13px]">
              <thead class="bg-slate-100 sticky top-0 z-10 shadow-sm border-b border-slate-200 font-bold uppercase">
                <tr>
                  <th class="py-2.5 px-3 border-r border-slate-200 w-12 text-center"><input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" /></th>
                  <th class="py-2.5 px-4 text-slate-700">Sản Phẩm</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-slate-700">
                <tr class="bg-slate-50 hover:bg-slate-100 transition-colors">
                  <td class="py-2.5 px-3 border-r border-slate-200 bg-transparent cursor-pointer" colspan="2" @click="mbExpanded = !mbExpanded">
                    <div class="flex items-center gap-2">
                      <div class="w-5 h-5 bg-[var(--hk-primary-dark)] flex items-center justify-center text-white text-[14px] leading-none pb-[2px] rounded-md shadow-sm font-bold">{{ mbExpanded ? '-' : '+' }}</div>
                      <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" @click.stop />
                      <span class="font-bold text-slate-800">MB</span>
                    </div>
                  </td>
                </tr>
                <template v-if="mbExpanded">
                  <tr class="bg-white hover:bg-slate-50 transition-colors">
                    <td class="py-2.5 px-3 border-r border-slate-200 bg-transparent pl-8 cursor-pointer" colspan="2" @click="minibarExpanded = !minibarExpanded">
                      <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-[var(--hk-primary)] flex items-center justify-center text-white text-[12px] leading-none pb-[1px] rounded-sm shadow-sm font-bold">{{ minibarExpanded ? '-' : '+' }}</div>
                        <span class="font-bold text-slate-700">Minibar</span>
                      </div>
                    </td>
                  </tr>
                  <template v-if="minibarExpanded">
                    <tr class="hover:bg-slate-50 transition-colors bg-white">
                      <td class="py-2.5 px-3 border-r border-slate-200 text-center"><input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" /></td>
                      <td class="py-2.5 px-4 pl-14 text-slate-600 font-semibold">Nước suối Aqua 500ml</td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-colors bg-white">
                      <td class="py-2.5 px-3 border-r border-slate-200 text-center"><input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" /></td>
                      <td class="py-2.5 px-4 pl-14 text-slate-600 font-semibold">Nước suối Aqua 1,5l</td>
                    </tr>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
          <!-- Footer -->
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3 shrink-0">
            <button @click="showAddProductCheckModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">
              Đóng
            </button>
            <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> Lưu
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Modern styling tokens and variables */
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

/* Modals slide-in transitions */
.hk-modal-enter-active {
  animation: hkModalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-modal-leave-active {
  animation: hkModalOut 0.2s ease-in forwards;
}
@keyframes hkModalIn {
  from { opacity: 0; transform: scale(0.97) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes hkModalOut {
  from { opacity: 1; transform: scale(1) translateY(0); }
  to { opacity: 0; transform: scale(0.97) translateY(8px); }
}

/* Dropdown transition */
.hk-dropdown-enter-active {
  animation: hkDropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-dropdown-leave-active {
  animation: hkDropIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) reverse;
}
@keyframes hkDropIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Row fade transition */
.row-fade-enter-active, .row-fade-leave-active {
  transition: all 0.2s ease;
}
.row-fade-enter-from, .row-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
