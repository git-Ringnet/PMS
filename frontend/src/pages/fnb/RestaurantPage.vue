<template>
  <div class="flex h-[calc(100vh-48px)] w-full overflow-hidden bg-white">
    <!-- Sidebar -->
    <div class="w-40 border-r border-slate-200 flex flex-col shrink-0 bg-white shadow-[2px_0_8px_rgba(0,0,0,0.02)] z-10 overflow-hidden">
      <!-- Restaurant Name Header -->
      <div class="px-4 py-3 shrink-0 border-b border-slate-100 bg-slate-50/50">
        <h2 class="text-slate-800 font-extrabold text-xs uppercase tracking-wider text-center truncate">{{ outletName }}</h2>
      </div>

      <!-- Zone tabs -->
      <div class="flex-1 overflow-y-auto p-3 space-y-3.5 scrollbar-thin flex flex-col items-center">
        <!-- Zone Item Card -->
        <div
          v-for="zone in zones"
          :key="zone.id"
          @click="selectedZone = zone.id"
          :class="[
            'w-full max-w-[120px] rounded-[24px] border p-3 transition-all duration-200 cursor-pointer flex flex-col items-center gap-2.5 shadow-sm',
            selectedZone === zone.id
              ? 'border-sky-400 bg-sky-50/20 ring-1 ring-sky-300'
              : 'border-slate-200/80 hover:border-slate-350 bg-white'
          ]"
        >
          <!-- Zone image -->
          <div class="w-full aspect-[4/3] rounded-[14px] overflow-hidden border border-slate-100 bg-slate-100 shrink-0">
            <img :src="zone.image" :alt="zone.name" class="w-full h-full object-cover" />
          </div>
          <span class="text-[11px] font-extrabold text-slate-800 text-center uppercase tracking-wide truncate w-full px-0.5">{{ zone.name }}</span>
        </div>
        
        <div v-if="zones.length === 0" class="text-slate-400 italic text-center py-6 text-[10px]">
          Chưa có khu vực
        </div>
      </div>

      <!-- Status legend -->
      <div class="p-3 border-t border-slate-100 space-y-1.5 bg-slate-50/30">
        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Trạng thái</p>
        <div class="flex items-center gap-1.5 text-[10px] text-slate-600 font-bold"><span class="w-2.5 h-2.5 rounded-sm bg-slate-200/60 border border-slate-300 flex-shrink-0"></span> Trống</div>
        <div class="flex items-center gap-1.5 text-[10px] text-slate-600 font-bold"><span class="w-2.5 h-2.5 rounded-sm bg-[#c9eeff] border border-[#7ec0f3] flex-shrink-0"></span> Đang phục vụ</div>
        <div class="flex items-center gap-1.5 text-[10px] text-slate-600 font-bold"><span class="w-2.5 h-2.5 rounded-sm bg-[#fef3c7] border border-[#fde68a] flex-shrink-0"></span> Đặt trước</div>
        <div class="flex items-center gap-1.5 text-[10px] text-slate-600 font-bold"><span class="w-2.5 h-2.5 rounded-sm bg-red-400 flex-shrink-0"></span> Chờ thanh toán</div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden bg-[#fafafa] relative">
      <!-- Loading Overlay -->
      <div v-if="isLoading" class="absolute inset-0 bg-white/80 backdrop-blur-[2px] flex items-center justify-center z-50 transition-all duration-200">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>

      <!-- Top toolbar -->
      <div v-if="!selectedTable" class="flex items-center justify-between p-4 shrink-0 bg-white border-b border-slate-100">
        <div class="flex-1"></div>
        <div class="flex-1 flex justify-center">
          <!-- Search box -->
          <div class="relative w-80">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input type="text" v-model="searchQuery" class="block w-full pl-10 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-slate-50 focus:bg-white transition-colors" placeholder="Tìm kiếm" />
          </div>
        </div>
        <div class="flex-1 flex justify-end gap-3">
          <!-- Settings Icon -->
          <div class="relative">
            <button @click="showSettings = !showSettings" class="p-1.5 text-slate-500 hover:text-slate-800 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
            <!-- Settings Popover -->
            <div v-if="showSettings" class="absolute top-full right-0 mt-1 w-64 bg-white border border-slate-200 shadow-xl rounded-lg p-4 z-50 animate-in fade-in slide-in-from-top-1 duration-150 font-sans text-xs">
              <div class="flex justify-between items-center mb-3 border-b border-slate-100 pb-2">
                <h3 class="font-bold text-slate-800 text-xs">Cài đặt hiển thị</h3>
                <button @click="resetToDefault" class="text-[10px] text-sky-500 hover:text-sky-600 font-extrabold transition-colors">Mặc định</button>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="flex justify-between text-slate-600 mb-1 font-semibold">
                    <span>Độ rộng bàn</span>
                    <span class="font-extrabold text-slate-800">{{ cellWidth }}px</span>
                  </div>
                  <input type="range" v-model="cellWidth" min="100" max="400" step="10" class="w-full custom-range mb-2" />
                </div>
                <div>
                  <div class="flex justify-between text-slate-600 mb-1 font-semibold">
                    <span>Chiều cao bàn</span>
                    <span class="font-extrabold text-slate-800">{{ cellHeight }}px</span>
                  </div>
                  <input type="range" v-model="cellHeight" min="50" max="300" step="10" class="w-full custom-range mb-2" />
                </div>
              </div>
            </div>
          </div>
          <!-- Help Icon -->
          <button class="w-7 h-7 rounded-full flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition shadow-sm" title="Trợ giúp">
            <span class="text-xs font-bold">?</span>
          </button>
        </div>
      </div>

      <!-- Grid Content -->
      <div v-if="!selectedTable" class="flex-1 overflow-auto p-6 pt-4 relative scrollbar-thin" :class="{'flex flex-col items-center justify-center': rows.length === 0}" @click="showSettings = false">
        <!-- Empty fallback -->
        <div v-if="rows.length === 0" class="flex flex-col items-center justify-center text-slate-400 py-16">
          <svg class="w-16 h-16 text-slate-300 mb-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9h18M9 21V9m6 12V9m3-6H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2z" />
          </svg>
          <span class="text-xs font-semibold text-slate-500">Không tìm thấy bàn nào trong khu vực này.</span>
        </div>

        <div v-else class="flex flex-col gap-6 w-max">
          <div v-for="row in rows" :key="row.id" class="flex flex-nowrap gap-4">
            <div
              v-for="table in row.tables"
              :key="table.id"
              @click="selectedTable = table.id"
              :style="{ width: cellWidth + 'px', height: cellHeight + 'px' }"
              :class="[
                'rounded-[20px] border flex flex-col p-4 cursor-pointer transition-all duration-200 hover:shadow-md hover:scale-[1.02] relative overflow-hidden group shrink-0',
                table.status === 'empty' && 'bg-slate-200/50 border-slate-300 hover:border-slate-400',
                table.status === 'serving' && 'bg-[#c9eeff] border-[#7ec0f3] hover:border-blue-400',
                table.status === 'reserved' && 'bg-[#fef3c7] border-[#fde68a] hover:border-amber-400',
                table.status === 'waiting' && 'bg-red-50 border-red-200 hover:border-red-400',
              ]"
            >
              <!-- Table name in upper left -->
              <span class="absolute left-3.5 top-3 font-extrabold text-slate-800 text-xs tracking-wide">{{ table.name }}</span>

              <!-- Status info -->
              <div class="flex-1 flex flex-col justify-end mt-4">
                <div v-if="table.status === 'serving'" class="text-xs text-[#0369a1] font-semibold space-y-0.5 select-none">
                  <div class="flex items-center gap-1 text-[10px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>{{ table.guest }}</span>
                  </div>
                  <div class="flex items-center gap-1 text-[10px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ table.time }}</span>
                  </div>
                </div>
                <div v-else-if="table.status === 'reserved'" class="text-xs text-amber-700 font-semibold select-none">
                  <div class="flex items-center gap-1 text-[10px]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ table.reservedTime }}</span>
                  </div>
                </div>
                <div v-else-if="table.status === 'waiting'" class="text-[10px] text-red-600 font-extrabold select-none">
                  Chờ thanh toán
                </div>
                <div v-else class="text-[10px] text-slate-400 font-bold select-none">Trống</div>
              </div>
              
              <!-- Hover overlay -->
              <div class="absolute inset-0 bg-white/0 group-hover:bg-white/5 transition-all duration-200 rounded-[20px]"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table Detail -->
      <TableDetail v-else :table-id="selectedTable" @back="selectedTable = null" @open-item="isQuickAddModalOpen = true" />
    </div>

    <!-- Modals -->
    <QuickAddMenuModal 
      :is-open="isQuickAddModalOpen" 
      @close="isQuickAddModalOpen = false" 
      @add="handleQuickAddOpenDetail" 
    />
    <AddMenuItemModal 
      :is-open="isAddMenuItemModalOpen" 
      @close="isAddMenuItemModalOpen = false" 
      @save="isAddMenuItemModalOpen = false" 
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import TableDetail from './components/restaurant/TableDetail.vue'
import QuickAddMenuModal from './components/restaurant/modals/QuickAddMenuModal.vue'
import AddMenuItemModal from './components/restaurant/modals/AddMenuItemModal.vue'
import { fetchFbLocations, fetchOutlets, fetchFbTables } from '@/services/outlet-service'

const route = useRoute()

const isLoading = ref(false)
const showSettings = ref(false)
const cellWidth = ref(200)
const cellHeight = ref(120)
const searchQuery = ref('')

const selectedTable = ref(null)
const selectedZone = ref('')
const outletName = ref('Nhà Hàng')

const zones = ref([])
const tables = ref([])

const getImageUrl = (path) => {
  if (!path) return 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
    return path
  }
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  const cleanPath = path.startsWith('/') ? path.substring(1) : path
  let finalPath = cleanPath
  if (!cleanPath.startsWith('storage/')) {
    finalPath = 'storage/' + cleanPath
  }
  return isDev ? `${backendUrl}/${finalPath}` : `/${finalPath}`
}

const loadOutletData = async () => {
  const outletCode = route.query.outlet_code
  if (!outletCode) {
    outletName.value = 'Nhà Hàng'
    zones.value = []
    tables.value = []
    return
  }

  isLoading.value = true
  try {
    // 1. Load Outlet Name
    const outletRes = await fetchOutlets()
    const currentOutlet = (outletRes.data || []).find(o => o.code === outletCode)
    if (currentOutlet) {
      outletName.value = currentOutlet.name
    }

    // 2. Load Real Locations
    const locRes = await fetchFbLocations(outletCode)
    const dbLocations = locRes.data.data || locRes.data || []

    // 3. Load Real Tables
    const tableRes = await fetchFbTables({ outlet_code: outletCode })
    const dbTables = tableRes.data.data || tableRes.data || []
    
    // Map to local structure
    tables.value = dbTables.map(t => ({
      id: t.id,
      name: t.name,
      location_id: t.location_id,
      row_index: t.row_index,
      col_index: t.col_index,
      max_seats: t.max_seats,
      status: t.status ? t.status.toLowerCase() : 'empty',
      guest: t.guest_name || '',
      time: t.checkin_time || '',
      reservedTime: t.reserved_time || ''
    }))

    // 4. Map locations to Zones with real photos
    zones.value = dbLocations.map(loc => ({
      id: loc.id,
      name: loc.name,
      image: getImageUrl(loc.image),
      letter: loc.letter || ''
    }))

    // Select the first zone as default if not selected
    if (zones.value.length > 0) {
      const exists = zones.value.some(z => z.id === selectedZone.value)
      if (!exists) {
        selectedZone.value = zones.value[0].id
      }
    } else {
      selectedZone.value = ''
    }
  } catch (err) {
    console.error('Lỗi khi tải thông tin outlet/location/table:', err)
  } finally {
    isLoading.value = false
  }
}

watch(() => route.query.outlet_code, () => {
  loadOutletData()
})

onMounted(() => {
  loadOutletData()
})

// Filter by zone and search query
const filteredTables = computed(() => {
  let result = tables.value
  
  if (selectedZone.value) {
    result = result.filter(t => t.location_id === selectedZone.value)
  }
  
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    result = result.filter(t => t.name.toLowerCase().includes(q))
  }
  
  return result
})

// Group filtered tables by row_index
const rows = computed(() => {
  const group = {}
  filteredTables.value.forEach(table => {
    const rIdx = table.row_index || 1
    if (!group[rIdx]) {
      group[rIdx] = {
        id: rIdx,
        tables: []
      }
    }
    group[rIdx].tables.push(table)
  })
  
  // Sort tables within each row by col_index ascending
  return Object.values(group)
    .map(row => {
      row.tables.sort((a, b) => (a.col_index || 0) - (b.col_index || 0))
      return row
    })
    .sort((a, b) => a.id - b.id)
})

const isQuickAddModalOpen = ref(false)
const isAddMenuItemModalOpen = ref(false)

const handleQuickAddOpenDetail = () => {
  isQuickAddModalOpen.value = false
  isAddMenuItemModalOpen.value = true
}

const resetToDefault = () => {
  cellWidth.value = 200
  cellHeight.value = 120
}
</script>

<script>
export default {
  name: 'RestaurantPage'
}
</script>

<style scoped>
.custom-range {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  width: 100%;
}
.custom-range:focus {
  outline: none;
}
.custom-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  height: 16px;
  width: 16px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #7dd3fc; /* sky-300 */
  cursor: pointer;
  margin-top: -6px; /* align center with the 4px track */
}
.custom-range::-moz-range-thumb {
  height: 14px;
  width: 14px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #7dd3fc; 
  cursor: pointer;
}
</style>
