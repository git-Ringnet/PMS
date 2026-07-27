<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { fetchOutlets, fetchFbLocations, fetchFbTables } from '@/services/outlet-service'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  currentTable: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close', 'transfer'])

// Data states
const outlets = ref([])
const zones = ref([])
const tables = ref([])

// Selection states
const selectedOutletCode = ref('')
const selectedZoneId = ref('')
const selectedTableId = ref(null)

const isLoading = ref(false)

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

const loadOutlets = async () => {
  try {
    const res = await fetchOutlets()
    outlets.value = res.data || []
    if (outlets.value.length > 0) {
      selectedOutletCode.value = outlets.value[0].code
    }
  } catch (error) {
    console.error('Lỗi khi tải danh sách nhà hàng:', error)
  }
}

const loadZones = async () => {
  if (!selectedOutletCode.value) return
  try {
    const res = await fetchFbLocations(selectedOutletCode.value)
    zones.value = res.data.data || res.data || []
    if (zones.value.length > 0) {
      // If the current table's zone is in this outlet, select it
      const currentZone = zones.value.find(z => z.id === props.currentTable?.location_id)
      selectedZoneId.value = currentZone ? currentZone.id : zones.value[0].id
    } else {
      selectedZoneId.value = ''
      tables.value = []
    }
  } catch (error) {
    console.error('Lỗi khi tải danh sách khu vực:', error)
  }
}

const loadTables = async () => {
  if (!selectedZoneId.value) return
  isLoading.value = true
  try {
    const res = await fetchFbTables({ location_id: selectedZoneId.value })
    const rawTables = res.data.data || res.data || []
    tables.value = rawTables.map(t => {
      const s = t.status ? t.status.toLowerCase() : 'empty'
      return { ...t, status: s === 'active' ? 'empty' : s }
    })
  } catch (error) {
    console.error('Lỗi khi tải danh sách bàn:', error)
  } finally {
    isLoading.value = false
  }
}

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    selectedTableId.value = null
    if (outlets.value.length === 0) {
      loadOutlets()
    } else {
      // Ensure data is loaded/refreshed
      loadZones()
    }
  }
})

watch(selectedOutletCode, () => {
  if (props.isOpen) {
    loadZones()
  }
})

watch(selectedZoneId, () => {
  if (props.isOpen) {
    loadTables()
  }
})

const handleClose = () => {
  emit('close')
}

const handleConfirm = () => {
  if (!selectedTableId.value) return
  const targetTable = tables.value.find(t => t.id === selectedTableId.value)
  if (targetTable) {
    emit('transfer', targetTable)
  }
}

const selectTargetTable = (table) => {
  if (table.id === props.currentTable?.id) return
  if (table.status !== 'empty') return // Only allow transfer to empty tables
  selectedTableId.value = table.id
}
</script>

<template>
  <Transition name="modal">
    <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="handleClose"></div>

      <!-- Modal Content -->
      <div class="bg-white rounded-lg shadow-xl w-[90vw] max-w-5xl h-[80vh] flex flex-col relative overflow-hidden animate-in zoom-in-95 duration-200">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-slate-200 flex justify-between items-center bg-slate-50 shrink-0">
          <h3 class="font-bold text-slate-800 text-sm uppercase tracking-wide flex items-center gap-2">
            <div class="w-7 h-7 rounded bg-sky-100 flex items-center justify-center text-sky-500">
               <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            </div>
            Chuyển bàn
          </h3>
          <button @click="handleClose" class="text-slate-400 hover:text-slate-600 transition-colors rounded p-1 hover:bg-slate-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 flex overflow-hidden">
          <!-- Left Sidebar (Outlet & Zones) -->
          <div class="w-56 border-r border-slate-200 flex flex-col shrink-0 bg-[#f8f9fa] shadow-[2px_0_8px_rgba(0,0,0,0.02)] z-10 relative">
            <div class="p-3 border-b border-slate-100 bg-white">
              <select v-model="selectedOutletCode" class="w-full border border-slate-200 rounded px-2 py-2 text-xs focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 bg-white font-bold text-slate-700 shadow-sm cursor-pointer">
                <option v-for="outlet in outlets" :key="outlet.code" :value="outlet.code">{{ outlet.name }}</option>
              </select>
            </div>
            
            <div class="flex-1 overflow-y-auto p-3 space-y-3 scrollbar-thin flex flex-col items-center">
              <div
                v-for="zone in zones"
                :key="zone.id"
                @click="selectedZoneId = zone.id"
                :class="[
                  'w-full rounded-[14px] border p-2.5 transition-all duration-200 cursor-pointer flex flex-col items-center gap-2 shadow-sm',
                  selectedZoneId === zone.id
                    ? 'border-sky-400 bg-sky-50/20 ring-1 ring-sky-300'
                    : 'border-slate-200/80 hover:border-slate-350 bg-white'
                ]"
              >
                <!-- Zone image -->
                <div class="w-full aspect-[4/3] rounded-[10px] overflow-hidden border border-slate-100 bg-slate-100 shrink-0 relative group">
                  <img :src="getImageUrl(zone.image)" :alt="zone.name" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                </div>
                <span class="text-[10px] font-extrabold text-slate-800 text-center uppercase tracking-wide truncate w-full px-0.5">{{ zone.name }}</span>
              </div>
              <div v-if="zones.length === 0" class="text-center text-slate-400 text-xs italic mt-4 w-full">Không có khu vực nào</div>
            </div>
          </div>

          <!-- Right Content (Tables Grid) -->
          <div class="flex-1 bg-[#fafafa] relative overflow-hidden flex flex-col">
            <div class="p-3 bg-white border-b border-slate-100 flex items-center gap-4 shrink-0 text-[10px] font-bold text-slate-500 uppercase tracking-wide">
              <span>Đang chọn: {{ props.currentTable?.name || '---' }}</span>
              <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
              <span class="text-sky-600">{{ tables.find(t => t.id === selectedTableId)?.name || 'Chưa chọn bàn đích' }}</span>
            </div>

            <div class="flex-1 relative p-4 overflow-hidden">
              <div v-if="isLoading" class="absolute inset-0 bg-white/50 backdrop-blur-[1px] flex items-center justify-center z-50 transition-all duration-200">
                <div class="loader">
                  <div class="inner one"></div>
                  <div class="inner two"></div>
                  <div class="inner three"></div>
                </div>
              </div>

              <div class="h-full overflow-auto pr-2 scrollbar-thin content-start">
                <div v-if="tables.length === 0 && !isLoading" class="flex flex-col items-center justify-center text-slate-400 py-16 h-full w-full">
                  <svg class="w-12 h-12 text-slate-300 mb-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9h18M9 21V9m6 12V9m3-6H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2z" />
                  </svg>
                  <span class="text-xs font-semibold text-slate-500">Khu vực này không có bàn.</span>
                </div>

                <!-- CSS Grid for tables -->
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                  <div
                    v-for="table in tables"
                    :key="table.id"
                    @click="selectTargetTable(table)"
                    :class="[
                      'h-[90px] rounded-[16px] border flex flex-col p-3 transition-all duration-200 relative overflow-hidden group shrink-0',
                      table.id === currentTable?.id 
                        ? 'bg-slate-100 border-slate-300 opacity-60 cursor-not-allowed'
                        : table.status !== 'empty'
                          ? 'opacity-60 cursor-not-allowed'
                          : 'cursor-pointer hover:shadow-md hover:scale-[1.02]',
                      table.status === 'empty' && table.id !== currentTable?.id && 'bg-slate-200/50 border-slate-300 hover:border-slate-400',
                      table.status === 'serving' && table.id !== currentTable?.id && 'bg-sky-500 border-sky-600',
                      table.status === 'reserved' && table.id !== currentTable?.id && 'bg-[#fef3c7] border-[#fde68a]',
                      table.status === 'waiting' && table.id !== currentTable?.id && 'bg-red-50 border-red-200',
                      selectedTableId === table.id && 'ring-2 ring-sky-500 border-sky-500 bg-sky-50 shadow-md transform scale-[1.02] z-10'
                    ]"
                  >
                    <!-- Selection overlay if selected -->
                    <div v-if="selectedTableId === table.id" class="absolute top-2 right-2 text-sky-500 bg-white rounded-full shadow-sm">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    </div>

                    <!-- Table name in upper left -->
                    <span class="font-extrabold text-xs tracking-wide" :class="table.status === 'serving' && table.id !== currentTable?.id ? 'text-white' : 'text-slate-800'">{{ table.name }}</span>

                    <!-- Status info -->
                    <div class="flex-1 flex flex-col justify-end mt-2">
                      <div v-if="table.id === currentTable?.id" class="text-[10px] text-sky-600 font-extrabold select-none">
                        Bàn hiện tại
                      </div>
                      <div v-else-if="table.status === 'serving'" class="text-xs text-white font-semibold space-y-0.5 select-none">
                        <div class="flex items-center gap-1 text-[10px]">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                          <span>{{ table.guest || 0 }}</span>
                        </div>
                      </div>
                      <div v-else-if="table.status === 'reserved'" class="text-xs text-amber-700 font-semibold select-none">
                        <div class="flex items-center gap-1 text-[10px]">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                          <span>{{ table.reservedTime || '--:--' }}</span>
                        </div>
                      </div>
                      <div v-else-if="table.status === 'waiting'" class="text-[10px] text-red-600 font-extrabold select-none">
                        Chờ thanh toán
                      </div>
                      <div v-else class="text-[10px] text-slate-400 font-bold select-none">Trống</div>
                    </div>
                    
                    <div class="absolute inset-0 bg-white/0 group-hover:bg-white/5 transition-all duration-200 rounded-[16px] pointer-events-none"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-slate-200 bg-white flex justify-end gap-3 shrink-0 shadow-[0_-4px_10px_rgba(0,0,0,0.02)]">
          <button @click="handleClose" class="px-5 py-1.5 rounded text-sm font-semibold text-slate-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 transition-colors">
            Hủy
          </button>
          <button 
            @click="handleConfirm" 
            :disabled="!selectedTableId"
            :class="selectedTableId ? 'bg-sky-500 hover:bg-sky-600 shadow-sm shadow-sky-500/20' : 'bg-slate-300 cursor-not-allowed'"
            class="px-5 py-1.5 rounded text-sm font-semibold text-white transition-all flex items-center gap-1.5"
          >
            Chuyển bàn
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .zoom-in-95 {
  transform: scale(0.95) translateY(10px);
}

.loader {
  position: relative;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  perspective: 800px;
}

.inner {
  position: absolute;
  box-sizing: border-box;
  width: 100%;
  height: 100%;
  border-radius: 50%;
}

.inner.one {
  left: 0%;
  top: 0%;
  animation: rotate-one 1s linear infinite;
  border-bottom: 3px solid #38bdf8;
}

.inner.two {
  right: 0%;
  top: 0%;
  animation: rotate-two 1s linear infinite;
  border-right: 3px solid #0284c7;
}

.inner.three {
  right: 0%;
  bottom: 0%;
  animation: rotate-three 1s linear infinite;
  border-top: 3px solid #7dd3fc;
}

@keyframes rotate-one {
  0% { transform: rotateX(35deg) rotateY(-45deg) rotateZ(0deg); }
  100% { transform: rotateX(35deg) rotateY(-45deg) rotateZ(360deg); }
}

@keyframes rotate-two {
  0% { transform: rotateX(50deg) rotateY(10deg) rotateZ(0deg); }
  100% { transform: rotateX(50deg) rotateY(10deg) rotateZ(360deg); }
}

@keyframes rotate-three {
  0% { transform: rotateX(35deg) rotateY(55deg) rotateZ(0deg); }
  100% { transform: rotateX(35deg) rotateY(55deg) rotateZ(360deg); }
}
</style>
