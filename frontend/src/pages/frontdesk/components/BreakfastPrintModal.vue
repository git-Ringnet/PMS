<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  selectedRooms: {
    type: Array,
    default: () => []
  },
  defaultFromDate: {
    type: String,
    default: ''
  },
  defaultToDate: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'confirm'])

const printMode = ref('all') // 'all' | 'range'
const fromDate = ref('')
const toDate = ref('')

const modalFromDateRef = ref(null)
const modalToDateRef = ref(null)

function openModalFromDatePicker() {
  if (modalFromDateRef.value) {
    try {
      if (typeof modalFromDateRef.value.showPicker === 'function') {
        modalFromDateRef.value.showPicker()
      } else {
        modalFromDateRef.value.click()
      }
    } catch (e) {
      modalFromDateRef.value.click()
    }
  }
}

function openModalToDatePicker() {
  if (modalToDateRef.value) {
    try {
      if (typeof modalToDateRef.value.showPicker === 'function') {
        modalToDateRef.value.showPicker()
      } else {
        modalToDateRef.value.click()
      }
    } catch (e) {
      modalToDateRef.value.click()
    }
  }
}

function formatDateDisplay(dStr) {
  if (!dStr) return ''
  const clean = String(dStr).split('T')[0]
  if (clean.includes('-')) {
    const [y, m, d] = clean.split('-')
    return `${d}/${m}/${y}`
  }
  return clean
}

watch(() => props.isOpen, (val) => {
  if (val) {
    printMode.value = 'all'
    fromDate.value = props.defaultFromDate || new Date().toISOString().slice(0, 10)
    toDate.value = props.defaultToDate || fromDate.value
  }
})

function handleConfirm() {
  if (printMode.value === 'range') {
    if (!fromDate.value || !toDate.value) {
      alert('Vui lòng chọn đầy đủ giai đoạn ngày cần in!')
      return
    }
  }
  emit('confirm', {
    mode: printMode.value,
    fromDate: fromDate.value,
    toDate: toDate.value
  })
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150 text-slate-800">
      <!-- Header -->
      <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center font-bold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
          </span>
          <div>
            <h3 class="text-sm font-bold text-slate-800">Tùy Chọn In Phiếu Ăn Sáng</h3>
            <p class="text-xs text-slate-500">Đã chọn: <span class="font-bold text-sky-600">{{ selectedRooms.length }}</span> phòng</p>
          </div>
        </div>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-md hover:bg-slate-100 transition-colors cursor-pointer">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-5 space-y-3.5 text-xs">
        <p class="text-slate-600 font-semibold">Vui lòng chọn 1 trong 2 hình thức in phiếu ăn sáng:</p>

        <!-- Option 1: In All Card -->
        <label 
          @click="printMode = 'all'"
          :class="printMode === 'all' ? 'border-sky-500 bg-sky-50/40 ring-2 ring-sky-500/20' : 'border-slate-200 hover:bg-slate-50'"
          class="flex items-start gap-3 p-3.5 rounded-xl border transition-all cursor-pointer block select-none"
        >
          <input 
            type="radio" 
            name="printMode" 
            value="all" 
            v-model="printMode" 
            class="mt-0.5 text-sky-600 focus:ring-sky-500"
          />
          <div class="space-y-1">
            <div class="font-bold text-slate-900 flex items-center gap-2 text-xs">
              <span>1. In tất cả (In All)</span>
              <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">Khuyên dùng</span>
            </div>
            <p class="text-slate-500 text-[11px] leading-relaxed">
              Tự động tính toán và in phiếu ăn sáng cho <strong>toàn bộ giai đoạn lưu trú</strong> của các phòng đã chọn (từ sáng ngày sau Check-in đến sáng ngày Check-out).
            </p>
          </div>
        </label>

        <!-- Option 2: In Giai Đoạn Card -->
        <label 
          @click="printMode = 'range'"
          :class="printMode === 'range' ? 'border-sky-500 bg-sky-50/40 ring-2 ring-sky-500/20' : 'border-slate-200 hover:bg-slate-50'"
          class="flex flex-col gap-2.5 p-3.5 rounded-xl border transition-all cursor-pointer block select-none"
        >
          <div class="flex items-start gap-3">
            <input 
              type="radio" 
              name="printMode" 
              value="range" 
              v-model="printMode" 
              class="mt-0.5 text-sky-600 focus:ring-sky-500"
            />
            <div class="space-y-0.5">
              <div class="font-bold text-slate-900 text-xs">2. In theo giai đoạn ngày</div>
              <p class="text-slate-500 text-[11px] leading-relaxed">
                Chỉ in các phiếu ăn sáng rơi vào khoảng ngày được chỉ định bên dưới.
              </p>
            </div>
          </div>

          <!-- Date inputs inside Range Option -->
          <div v-if="printMode === 'range'" class="pt-2 pl-7 grid grid-cols-2 gap-3 border-t border-sky-100 animate-in fade-in duration-150">
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Từ ngày ăn sáng:</label>
              <div 
                class="flex items-center justify-between h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-800 hover:border-sky-500 cursor-pointer shadow-2xs select-none"
                @click="openModalFromDatePicker"
              >
                <span>{{ formatDateDisplay(fromDate) }}</span>
                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
                  <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input 
                  ref="modalFromDateRef"
                  v-model="fromDate" 
                  type="date" 
                  class="sr-only"
                />
              </div>
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Đến ngày ăn sáng:</label>
              <div 
                class="flex items-center justify-between h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-800 hover:border-sky-500 cursor-pointer shadow-2xs select-none"
                @click="openModalToDatePicker"
              >
                <span>{{ formatDateDisplay(toDate) }}</span>
                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
                  <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input 
                  ref="modalToDateRef"
                  v-model="toDate" 
                  type="date" 
                  class="sr-only"
                />
              </div>
            </div>
          </div>
        </label>
      </div>

      <!-- Footer -->
      <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 text-xs font-semibold">
        <button 
          @click="emit('close')"
          class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-lg transition-colors cursor-pointer"
        >
          Đóng
        </button>
        <button 
          @click="handleConfirm"
          class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors flex items-center gap-1.5 shadow-sm cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          Tiếp tục xem & in phiếu
        </button>
      </div>
    </div>
  </div>
</template>
