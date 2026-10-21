<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Chọn dịch vụ in'
  },
  services: {
    type: Array,
    default: () => []
  },
  initialSelectedIds: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'confirm'])

const activeFilter = ref('all') // 'all' | 'room' | 'sale' | 'fo'
const selectedMap = ref({})

// Format currency
function formatCurrency(val) {
  const num = Number(val) || 0
  return num.toLocaleString('en-US')
}

// Khởi tạo trạng thái chọn
watch(() => props.isOpen, (open) => {
  if (open) {
    activeFilter.value = 'all'
    const newMap = {}
    if (props.initialSelectedIds && props.initialSelectedIds.length > 0) {
      props.services.forEach(svc => {
        newMap[svc.id] = props.initialSelectedIds.includes(svc.id)
      })
    } else {
      // Mặc định chọn tất cả
      props.services.forEach(svc => {
        newMap[svc.id] = true
      })
    }
    selectedMap.value = newMap
  }
}, { immediate: true })

// Danh sách dịch vụ theo bộ lọc
const filteredServices = computed(() => {
  if (activeFilter.value === 'room') {
    return props.services.filter(s => s.is_room)
  }
  if (activeFilter.value === 'sale') {
    return props.services.filter(s => s.source === 'sale' && !s.is_room)
  }
  if (activeFilter.value === 'fo') {
    return props.services.filter(s => s.source === 'fo')
  }
  return props.services
})

// Kiểm tra chọn tất cả trong danh sách hiển thị
const isAllFilteredSelected = computed(() => {
  if (filteredServices.value.length === 0) return false
  return filteredServices.value.every(s => selectedMap.value[s.id])
})

function toggleSelectAllFiltered(e) {
  const checked = e.target.checked
  filteredServices.value.forEach(s => {
    selectedMap.value[s.id] = checked
  })
}

function selectAll() {
  props.services.forEach(s => {
    selectedMap.value[s.id] = true
  })
}

function deselectAll() {
  props.services.forEach(s => {
    selectedMap.value[s.id] = false
  })
}

// Tổng số và tổng tiền đã chọn
const selectedCount = computed(() => {
  return props.services.filter(s => selectedMap.value[s.id]).length
})

const selectedTotalAmount = computed(() => {
  return props.services
    .filter(s => selectedMap.value[s.id])
    .reduce((sum, s) => sum + (Number(s.amount) || 0), 0)
})

function handleConfirm() {
  const selectedList = props.services.filter(s => selectedMap.value[s.id])
  emit('confirm', selectedList)
}

function handleKeyDown(e) {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close')
  }
}

onMounted(() => window.addEventListener('keydown', handleKeyDown))
onUnmounted(() => window.removeEventListener('keydown', handleKeyDown))
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 select-none animate-in fade-in duration-150"
      @click.self="emit('close')"
    >
      <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-4xl border border-slate-200 overflow-hidden flex flex-col max-h-[85vh] animate-in zoom-in-95 duration-150"
        style="font-family: Roboto, sans-serif;"
      >
        <!-- Modal Topbar Header -->
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between text-white" style="background: var(--pms-custom-theme, #006bdb);">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <h3 class="font-bold text-sm tracking-wide text-white">{{ title }}</h3>
          </div>
          <button
            type="button"
            @click="emit('close')"
            class="text-white/80 hover:text-white transition-colors text-lg leading-none p-1 cursor-pointer"
            title="Đóng (Esc)"
          >
            ✕
          </button>
        </div>

        <!-- Filter Bar & Quick Controls -->
        <div class="px-5 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
          <!-- Filter tabs -->
          <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5 shadow-2xs">
            <button
              type="button"
              @click="activeFilter = 'all'"
              class="px-3 py-1 rounded-md font-semibold transition-all cursor-pointer"
              :class="activeFilter === 'all' ? 'bg-[#006bdb] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Tất cả ({{ services.length }})
            </button>
            <button
              type="button"
              @click="activeFilter = 'room'"
              class="px-3 py-1 rounded-md font-semibold transition-all cursor-pointer"
              :class="activeFilter === 'room' ? 'bg-[#006bdb] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Tiền phòng ({{ services.filter(s => s.is_room).length }})
            </button>
            <button
              type="button"
              @click="activeFilter = 'sale'"
              class="px-3 py-1 rounded-md font-semibold transition-all cursor-pointer"
              :class="activeFilter === 'sale' ? 'bg-[#006bdb] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Dịch vụ Sales ({{ services.filter(s => s.source === 'sale' && !s.is_room).length }})
            </button>
            <button
              type="button"
              @click="activeFilter = 'fo'"
              class="px-3 py-1 rounded-md font-semibold transition-all cursor-pointer flex items-center gap-1"
              :class="activeFilter === 'fo' ? 'bg-[#006bdb] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              <span>DV Lễ tân post</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold" :class="activeFilter === 'fo' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">
                {{ services.filter(s => s.source === 'fo').length }}
              </span>
            </button>
          </div>

          <!-- Quick Select Buttons -->
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="selectAll"
              class="btn-pms-secondary h-7 px-2.5 text-xs font-semibold cursor-pointer"
            >
              Chọn tất cả
            </button>
            <button
              type="button"
              @click="deselectAll"
              class="btn-pms-secondary h-7 px-2.5 text-xs font-semibold cursor-pointer"
            >
              Bỏ chọn tất cả
            </button>
          </div>
        </div>

        <!-- Services Table List -->
        <div class="flex-1 overflow-y-auto p-4 bg-white">
          <table class="w-full border-collapse text-xs">
            <thead class="bg-slate-100 text-slate-700 sticky top-0 z-10 shadow-2xs font-semibold">
              <tr>
                <th class="border border-slate-200 px-2 py-2 text-center w-10">
                  <input
                    type="checkbox"
                    :checked="isAllFilteredSelected"
                    @change="toggleSelectAllFiltered"
                    class="rounded text-[#006bdb] focus:ring-[#006bdb] cursor-pointer"
                  />
                </th>
                <th class="border border-slate-200 px-2 py-2 text-center w-12">STT</th>
                <th class="border border-slate-200 px-2.5 py-2 text-center w-24">Ngày</th>
                <th class="border border-slate-200 px-2.5 py-2 text-center w-20">Phòng</th>
                <th class="border border-slate-200 px-3 py-2 text-left">Tên dịch vụ</th>
                <th class="border border-slate-200 px-2 py-2 text-center w-28">Nguồn phát sinh</th>
                <th class="border border-slate-200 px-2 py-2 text-center w-16">SL</th>
                <th class="border border-slate-200 px-2.5 py-2 text-right w-28">Đơn giá</th>
                <th class="border border-slate-200 px-3 py-2 text-right w-32">Thành tiền</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredServices.length === 0">
                <td colspan="9" class="border border-slate-200 p-8 text-center text-slate-400 italic">
                  Không có dịch vụ nào trong phân loại này
                </td>
              </tr>
              <tr
                v-for="(svc, index) in filteredServices"
                :key="svc.id"
                @click="selectedMap[svc.id] = !selectedMap[svc.id]"
                class="hover:bg-sky-50/50 cursor-pointer transition-colors border-b border-slate-200 text-slate-800"
                :class="{ 'bg-sky-50/70': selectedMap[svc.id] }"
              >
                <td class="border-r border-slate-200 px-2 py-2 text-center" @click.stop>
                  <input
                    type="checkbox"
                    v-model="selectedMap[svc.id]"
                    class="rounded text-[#006bdb] focus:ring-[#006bdb] cursor-pointer"
                  />
                </td>
                <td class="border-r border-slate-200 px-2 py-2 text-center text-slate-500 font-medium">{{ index + 1 }}</td>
                <td class="border-r border-slate-200 px-2.5 py-2 text-center font-semibold text-slate-700">{{ svc.date || '---' }}</td>
                <td class="border-r border-slate-200 px-2.5 py-2 text-center font-bold text-slate-800">{{ svc.room_number || '---' }}</td>
                <td class="border-r border-slate-200 px-3 py-2 font-medium">
                  <div class="text-slate-900 font-semibold">{{ svc.name }}</div>
                  <div v-if="svc.note" class="text-[11px] text-slate-400 italic">{{ svc.note }}</div>
                </td>
                <td class="border-r border-slate-200 px-2 py-2 text-center">
                  <span
                    v-if="svc.is_room"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800"
                  >
                    Tiền phòng
                  </span>
                  <span
                    v-else-if="svc.source === 'sale'"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                  >
                    DV Sales
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300"
                    title="Dịch vụ do Lễ tân hoặc các Outlet post qua hóa đơn"
                  >
                    Lễ tân post
                  </span>
                </td>
                <td class="border-r border-slate-200 px-2 py-2 text-center font-semibold">{{ svc.quantity }}</td>
                <td class="border-r border-slate-200 px-2.5 py-2 text-right font-medium text-slate-700">
                  {{ formatCurrency(svc.unit_price) }}
                </td>
                <td class="border-slate-200 px-3 py-2 text-right font-bold text-sky-700">
                  {{ formatCurrency(svc.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer Actions Bar -->
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
          <!-- Selection Counter & Total Amount -->
          <div class="flex items-center gap-4 text-slate-700">
            <div>
              Đã chọn: <b class="text-[#006bdb] text-sm">{{ selectedCount }}</b> / {{ services.length }} mục
            </div>
            <div class="border-l border-slate-300 pl-4">
              Tổng tiền chọn: <b class="text-sky-700 text-sm">{{ formatCurrency(selectedTotalAmount) }} VND</b>
            </div>
          </div>

          <!-- Buttons -->
          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="emit('close')"
              class="btn-pms-close h-8 px-4 font-semibold text-xs flex items-center gap-1.5"
            >
              <span>Đóng</span>
            </button>
            <button
              type="button"
              @click="handleConfirm"
              :disabled="selectedCount === 0"
              class="btn-pms-primary h-8 px-5 font-semibold text-xs flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              <span>Xem & In ({{ selectedCount }})</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
