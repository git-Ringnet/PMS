<template>

  <div class="flex h-[calc(100vh-48px)] w-full overflow-hidden bg-white">

    <!-- Sidebar -->
    <div v-if="!selectedTable" class="w-44 border-r border-slate-200 flex flex-col shrink-0 bg-white z-10 overflow-hidden">

      <!-- Restaurant Name Header -->
      <div class="px-3 py-3 shrink-0 border-b border-slate-200 bg-gradient-to-b from-sky-600 to-sky-700">
        <h2 class="text-white font-bold text-xs uppercase tracking-wider text-center truncate">{{ outletName }}</h2>
      </div>

      <!-- Zone tabs -->
      <div class="flex-1 overflow-y-auto py-2 px-2 flex flex-col gap-1.5">

        <!-- Zone Item Card -->
        <div
          v-for="zone in zones"
          :key="zone.id"
          @click="selectedZone = zone.id"
          :class="[
            'w-full rounded-xl border px-3 py-2.5 transition-all duration-150 cursor-pointer flex items-center gap-2.5',
            selectedZone === zone.id
              ? 'border-sky-400 bg-sky-50 text-sky-700 shadow-sm'
              : 'border-slate-100 hover:border-sky-200 bg-white hover:bg-sky-50/50 text-slate-700'
          ]"
        >
          <div class="w-8 h-8 rounded-lg overflow-hidden border border-slate-100 bg-slate-100 shrink-0">
            <img :src="zone.image" :alt="zone.name" class="w-full h-full object-cover" />
          </div>
          <span class="text-[11px] font-bold uppercase tracking-wide truncate">{{ zone.name }}</span>
        </div>

        <div v-if="zones.length === 0" class="text-slate-400 italic text-center py-6 text-[10px]">
          Chưa có khu vực
        </div>

      </div>

      <!-- Status legend -->
      <div class="p-3 border-t border-slate-100 space-y-1.5 bg-slate-50">
        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-2">Chú thích</p>
        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-semibold">
          <span class="w-3 h-3 rounded-sm bg-slate-200 border border-slate-300 shrink-0"></span>Trống
        </div>
        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-semibold">
          <span class="w-3 h-3 rounded-sm bg-sky-500 shrink-0"></span>Đang phục vụ
        </div>
        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-semibold">
          <span class="w-3 h-3 rounded-sm bg-amber-200 border border-amber-400 shrink-0"></span>Đặt trước
        </div>
        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-semibold">
          <span class="w-3 h-3 rounded-sm bg-red-400 shrink-0"></span>Chờ thanh toán
        </div>
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
      <div v-if="!selectedTable" class="flex items-center justify-between px-4 py-2.5 shrink-0 bg-white border-b border-slate-100 shadow-sm">
        <!-- Status summary chips -->
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-500">
            <span class="w-2 h-2 rounded-full bg-slate-300"></span>
            {{ tables.filter(t => t.status === 'empty').length }} trống
          </span>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-50 text-sky-600">
            <span class="w-2 h-2 rounded-full bg-sky-500"></span>
            {{ tables.filter(t => t.status === 'serving').length }} phục vụ
          </span>
          <span v-if="tables.filter(t => t.status === 'reserved').length > 0" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-600">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            {{ tables.filter(t => t.status === 'reserved').length }} đặt trước
          </span>
        </div>
        <!-- Search box -->
        <div class="relative w-64">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input type="text" v-model="searchQuery" class="block w-full pl-9 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 bg-slate-50 focus:bg-white transition-all" placeholder="Tìm bàn..." />
        </div>
        <!-- Settings -->
        <div class="flex items-center gap-2">
          <div class="relative">
            <button @click="showSettings = !showSettings" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-all">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
            <!-- Settings Popover -->
            <div v-if="showSettings" class="absolute top-full right-0 mt-1 w-64 bg-white border border-slate-200 shadow-xl rounded-xl p-4 z-50 font-sans text-xs">
              <div class="flex justify-between items-center mb-3 border-b border-slate-100 pb-2">
                <h3 class="font-bold text-slate-800 text-xs">Cài đặt hiển thị</h3>
                <button @click="resetToDefault" class="text-[10px] text-sky-500 hover:text-sky-600 font-bold transition-colors">Mặc định</button>
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
        </div>
      </div>

      <!-- Grid Content -->
      <div v-if="!selectedTable" class="flex-1 overflow-auto p-5 pt-4 relative" :class="{'flex flex-col items-center justify-center': rows.length === 0}" @click="showSettings = false">
        <!-- Empty fallback -->
        <div v-if="rows.length === 0" class="flex flex-col items-center justify-center text-slate-400 py-16">
          <svg class="w-16 h-16 text-slate-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9h18M9 21V9m6 12V9m3-6H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2z" />
          </svg>
          <span class="text-sm font-semibold text-slate-400">Không tìm thấy bàn nào trong khu vực này.</span>
        </div>

        <div v-else class="flex flex-col gap-5 w-max">
          <div v-for="row in rows" :key="row.id" class="flex flex-nowrap gap-4">
            <div
              v-for="table in row.tables"
              :key="table.id"
              @click="handleTableClick(table)"
              :style="{ width: cellWidth + 'px', height: cellHeight + 'px' }"
              :class="[
                'rounded-2xl border-2 flex flex-col p-3 cursor-pointer transition-all duration-200 hover:shadow-lg hover:scale-[1.03] relative overflow-hidden shrink-0',
                table.status === 'empty' && 'bg-white border-slate-200 hover:border-slate-300 hover:shadow-slate-100',
                table.status === 'serving' && 'bg-gradient-to-br from-sky-500 to-sky-600 border-sky-400 hover:border-sky-300 hover:shadow-sky-200',
                table.status === 'reserved' && 'bg-amber-50 border-amber-300 hover:border-amber-400 hover:shadow-amber-100',
                table.status === 'waiting' && 'bg-red-50 border-red-300 hover:border-red-400 hover:shadow-red-100',
              ]"
            >
              <!-- Table name -->
              <div class="flex items-start justify-between">
                <span class="font-extrabold text-sm tracking-wide" :class="table.status === 'serving' ? 'text-white' : 'text-slate-800'">{{ table.name }}</span>
                <!-- Status dot -->
                <span v-if="table.status === 'serving'" class="w-2 h-2 rounded-full bg-white/70 mt-1 shrink-0"></span>
                <span v-else-if="table.status === 'reserved'" class="w-2 h-2 rounded-full bg-amber-400 mt-1 shrink-0"></span>
                <span v-else-if="table.status === 'waiting'" class="w-2 h-2 rounded-full bg-red-500 animate-pulse mt-1 shrink-0"></span>
              </div>

              <!-- Status info - always visible -->
              <div class="flex-1 flex flex-col justify-end">
                <!-- Serving -->
                <div v-if="table.status === 'serving'" class="space-y-0.5 select-none">
                  <div class="flex items-center gap-1 text-[10px] text-white/90 font-semibold">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>{{ table.guest || 1 }} khách</span>
                  </div>
                  <div class="flex items-center gap-1 text-[10px] text-white/90 font-semibold">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ table.time || '--:--' }}</span>
                  </div>
                  <div v-if="table.totalAmount" class="mt-1 text-[11px] text-white font-extrabold tracking-tight">
                    {{ Number(table.totalAmount).toLocaleString('vi-VN') }} đ
                  </div>
                </div>
                <!-- Reserved -->
                <div v-else-if="table.status === 'reserved'" class="select-none">
                  <div class="flex items-center gap-1 text-[10px] text-amber-700 font-semibold">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ table.reservedTime }}</span>
                  </div>
                  <div class="text-[10px] text-amber-600 font-bold mt-0.5">Đặt trước</div>
                </div>
                <!-- Waiting -->
                <div v-else-if="table.status === 'waiting'" class="text-[10px] text-red-600 font-extrabold select-none">
                  ⏳ Chờ thanh toán
                </div>
                <!-- Empty -->
                <div v-else class="text-[10px] text-slate-400 font-semibold select-none">Trống</div>
              </div>
              
              <!-- Hover Overlay -->
              <div class="absolute inset-0 bg-slate-900/95 text-white p-2.5 flex flex-col opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 overflow-hidden backdrop-blur-sm pointer-events-none">
                <div class="font-extrabold text-[11px] mb-1.5 text-sky-400 border-b border-slate-700/50 pb-1 flex justify-between items-center">
                  <span>{{ table.name }}</span>
                  <span class="text-slate-400 font-medium text-[9px]">{{ table.max_seats }} chỗ</span>
                </div>
                
                <div class="text-[10px] space-y-1.5 flex-1 overflow-y-auto custom-scrollbar pr-1">
                  <template v-if="table.status === 'serving'">
                    <div class="flex justify-between items-center bg-slate-800/50 p-1.5 rounded">
                      <span class="text-slate-400">Khách</span>
                      <span class="font-medium truncate max-w-[80px] text-right">{{ table.guest || 'Khách lẻ' }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-slate-800/50 p-1.5 rounded">
                      <span class="text-slate-400">Giờ vào</span>
                      <span class="font-medium">{{ table.time || '--:--' }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-sky-900/30 p-1.5 rounded border border-sky-800/30 mt-2">
                      <span class="text-sky-200/70">Tổng tiền</span>
                      <span class="font-bold text-sky-300">{{ table.totalAmount ? Number(table.totalAmount).toLocaleString('vi-VN') + ' đ' : '0 đ' }}</span>
                    </div>
                  </template>
                  
                  <template v-else-if="table.status === 'reserved'">
                     <div class="flex flex-col gap-1.5 justify-center h-full items-center text-amber-400/80 mt-2">
                        <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-center">Đã đặt trước<br/>lúc {{ table.reservedTime }}</span>
                     </div>
                  </template>
                  
                  <template v-else-if="table.status === 'waiting'">
                     <div class="flex flex-col justify-center h-full items-center text-red-400/90 font-medium mt-3 gap-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Đang chờ thanh toán</span>
                     </div>
                  </template>

                  <template v-else>
                     <div class="flex justify-center items-center h-full text-slate-500 font-medium mt-5">
                        Bàn trống
                     </div>
                  </template>
                </div>
              </div>
            </div>

          </div>

        </div>

      </div>



      <!-- Table Detail -->

      <TableDetail v-else :table="selectedTable" @back="selectedTable = null" @save="handleTableSave" @open-item="isQuickAddModalOpen = true" @transfer-success="handleTransferSuccess" @transfer-items-success="handleTransferItemsSuccess" />

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

import { fetchFbLocations, fetchOutlets, fetchFbTables, fetchActiveOrders, syncOrders } from '@/services/outlet-service'



const route = useRoute()



const isLoading = ref(false)

const showSettings = ref(false)

const cellWidth = ref(200)



const handleTransferSuccess = (payload) => {

  selectedTable.value = null

  loadOutletData()

}



const handleTransferItemsSuccess = () => {

  selectedTable.value = null

  loadOutletData()

}



const handleTableSave = async (saveData) => {

  if (!selectedTable.value) return

  

  try {

    // Sync with backend

    const res = await syncOrders(selectedTable.value.id, {

      bills: saveData.bills

    })

    

    // Update local state based on response or saveData

    const t = tables.value.find(x => x.id === selectedTable.value.id)

    if (t) {

      t.status = res.data?.table_status || saveData.status || 'serving'

      t.guest = saveData.guest || t.guest

      t.time = saveData.time || t.time

      t.totalAmount = saveData.totalAmount || t.totalAmount

      t.bills = saveData.bills || t.bills

    }

  } catch (e) {

    console.error('Lỗi khi lưu đơn:', e)

    // Fallback local update if API fails? We should probably show an error using uiStore here.

  }

}



const cellHeight = ref(120)

const searchQuery = ref('')



const selectedTable = ref(null)

const selectedZone = ref('')

const outletName = ref('Nhà Hàng')



const zones = ref([])

const tables = ref([])



const handleTableClick = async (table) => {

  if (table.status !== 'empty') {

    try {

      const res = await fetchActiveOrders(table.id)

      if (res.data && res.data.length > 0) {

        table.bills = res.data

      }

    } catch (e) {

      console.error('Lỗi khi tải thông tin bill của bàn:', e)

    }

  }

  selectedTable.value = table

}



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

    

    // Map to local structure

    tables.value = (tableRes.data?.data || tableRes.data || []).map(t => {

      const rawStatus = t.status ? t.status.toLowerCase() : 'empty';

      return {

        id: t.id,

        name: t.name,

        location_id: t.location_id,

        row_index: t.row_index,

        col_index: t.col_index,

        max_seats: t.max_seats,

        status: rawStatus === 'active' ? 'empty' : rawStatus,

        guest: t.guest_name || '',

        time: t.checkin_time || '',

        totalAmount: t.total_amount || 0,

        reservedTime: t.reserved_time || ''

      }

    })



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

  selectedTable.value = null

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

